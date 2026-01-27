<?php

/**
 * Created by PhpStorm.
 * User: Lab #2
 * Date: 6/6/2021
 * Time: 4:10 PM.
 */

namespace App\Helpers;

use App\Model\Country;
use App\Model\Post;
use App\Model\Stream;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\UserMessage;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\InvoiceServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\PaypalAPIServiceProvider;
use App\Providers\RazorPayServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\VerotelServiceProvider;
use App\Model\User;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Ramsey\Uuid\Uuid;
use Stripe\StripeClient;
use Yabacon\Paystack;
use Yabacon\Paystack\Exception\ApiException;

class PaymentHelper
{
    public function generatePaypalSubscriptionByTransaction(Transaction $transaction): ?string
    {
        //initiate the recurring payment, send back the link for the user to approve it.
        if ($transaction['payment_provider'] === Transaction::PAYPAL_PROVIDER) {
            $paypalPlan = PaypalAPIServiceProvider::createPlan($transaction);
            $paypalSubscription = PaypalAPIServiceProvider::createSubscriptionByPlanAndTransaction(
                $paypalPlan['id'],
                $transaction
            );

            $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
                $transaction['sender_user_id'],
                $transaction['recipient_user_id'],
                Transaction::PAYPAL_PROVIDER
            );

            if ($existingSubscription != null) {
                $subscription = $existingSubscription;
            } else {
                $subscription = $this->createSubscriptionFromTransaction($transaction);
            }

            $subscription['paypal_agreement_id'] = $paypalSubscription['id'];
            $subscription['paypal_plan_id'] = $paypalPlan['id'];
            $subscription->save();

            $approvalUrl = PaypalAPIServiceProvider::getApprovalUrlByResource($paypalSubscription, 'approve');
            $paypalTransactionToken = PaypalAPIServiceProvider::getPayPalTransactionTokenFromApprovalLink($approvalUrl);
            $transaction['paypal_transaction_token'] = $paypalTransactionToken;
            $transaction['subscription_id'] = $subscription['id'];

            return $approvalUrl;
        }

        return null;
    }

    private function createSubscriptionFromTransaction(Transaction $transaction): Subscription
    {
        $subscription = new Subscription();

        if ($transaction['recipient_user_id'] != null && $transaction['sender_user_id'] != null) {
            $subscription['recipient_user_id'] = $transaction['recipient_user_id'];
            $subscription['sender_user_id'] = $transaction['sender_user_id'];
            $subscription['provider'] = $transaction['payment_provider'];
            $subscription['type'] = $transaction['type'];
            $subscription['status'] = Transaction::PENDING_STATUS;
        }

        return $subscription;
    }

    public function initiateOneTimePaypalTransaction(Transaction $transaction): string
    {
        $paypalOrder = PaypalAPIServiceProvider::createOrderByTransaction($transaction);

        $transaction['paypal_transaction_token'] = $paypalOrder['id'];

        // fetch and return redirect url from the creation order response
        return PaypalAPIServiceProvider::getApprovalUrlByResource($paypalOrder, 'payer-action');
    }

    public function executePaypalSubscriptionPayment($transaction): ?Transaction {
        try {
            $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();
            if($subscription && $subscription->paypal_agreement_id) {
                $transaction = $this->verifyPaypalSubscriptionPayment($subscription->paypal_agreement_id, null, $transaction);
            }
        } catch (\Exception $exception) {
            Log::channel('payments')
                ->error('Failed executing PayPal subscription payment: '.$exception->getMessage());
        }

        return $transaction;
    }

    public function verifyPaypalSubscriptionPayment(
        string $subscriptionId,
        string $paypalPaymentId = null,
        $transaction = null
    ): ?Transaction {
        $subscription = Subscription::query()->where(['paypal_agreement_id' => $subscriptionId])->first();

        if($subscription) {
            $paypalSubscription = PaypalAPIServiceProvider::getSubscription($subscriptionId);
            Log::channel('payments')
                ->debug("PayPal Sub Data: ", [$paypalSubscription]);
            // only fetch the last subscription transaction if this call does come from hooks, and it's the first sub payment
            if(!$transaction && $this->isFirstPaymentForPaypalSubscription($paypalSubscription)) {
                // handles PayPal initial payment for recurring subscription
                // find last initiated transaction by subscription and update its status
                $existingTransaction = Transaction::query()->where([
                    'subscription_id' => $subscription->id,
                    'payment_provider' => Transaction::PAYPAL_PROVIDER,
                ])->orderBy('id', 'DESC')->first();

                if ($existingTransaction instanceof Transaction) {
                    // if transaction was already approved by the callback call
                    // we'll only update the paypal_transaction_id for the transaction entry
                    if($existingTransaction->status === Transaction::APPROVED_STATUS) {
                        $existingTransaction->paypal_transaction_id = $paypalPaymentId;
                        $existingTransaction->save();

                        return $existingTransaction;
                    }

                    if($existingTransaction->status === Transaction::INITIATED_STATUS) {
                        $transaction = $existingTransaction;
                    }

                    Log::channel('payments')
                        ->debug("Found existing transaction for subscription: ".$transaction->id);
                }
            }

            // if we have a transaction at this point it means this call is triggered by the initial subscription payment,
            // so we want to validate if user paid the initial setup fee
            if ($transaction && !$this->validatePaypalSubscriptionInitialPayment($paypalSubscription, $transaction)) {
                return null;
            }

            // fetch subscription next billing date
            $subNextBillingDate = $paypalSubscription['billing_info']['next_billing_time'] ?? null;
            if (!$subNextBillingDate) {
                return null;
            }

            // handles PayPal subscription renewal payments
            if ($subscription->status == Subscription::ACTIVE_STATUS
                || $subscription->status == Subscription::SUSPENDED_STATUS
                || $subscription->status == Subscription::EXPIRED_STATUS) {
                $this->createSubscriptionRenewalTransaction($subscription, $paymentSucceeded = true, $paypalPaymentId);
            }

            $nextBillingDate = new DateTime($subNextBillingDate, new DateTimeZone('UTC'));

            $subscription->expires_at = $nextBillingDate;
            $subscription->status = Subscription::ACTIVE_STATUS;

            // handles initial recurring payment transaction update
            if ($transaction) {
                $transaction->status = Transaction::APPROVED_STATUS;
                $subscription->amount = $transaction->amount;

                if(isset($paypalSubscription['subscriber']) && isset($paypalSubscription['subscriber']['payer_id'])) {
                    $transaction->paypal_payer_id = $paypalSubscription['subscriber']['payer_id'];
                }

                // handle scenario where the callback call was missed so the transaction isn't approved,
                // and we need to set the paypal_transaction_id
                $startTime = new DateTime('-1 hour', new DateTimeZone('UTC'));
                $endTime = new DateTime('now', new DateTimeZone('UTC'));
                $paypalSubPayments = PaypalAPIServiceProvider::getTransactionsBySubscription(
                    $subscriptionId,
                    $startTime->format('Y-m-d\TH:i:s.v\Z'),
                    $endTime->format('Y-m-d\TH:i:s.v\Z')
                );

                if(isset($paypalSubPayments['transactions'])) {
                    $subPaypalTransaction = $paypalSubPayments['transactions'][0];
                    $transaction->paypal_transaction_id = $subPaypalTransaction['id'];
                }

                $transaction->save();

                // credit receiver for transaction
                $this->creditReceiverForTransaction($transaction);
            }

            $subscription->save();

            NotificationServiceProvider::createNewSubscriptionNotification($subscription);
        }

        return $transaction;
    }

    private function isFirstPaymentForPaypalSubscription(array $paypalSubscription): bool {
        if ($paypalSubscription
            && isset($paypalSubscription['billing_info'])
            && isset($paypalSubscription['billing_info']['cycle_executions']))
        {
            $cycleExecution = $paypalSubscription['billing_info']['cycle_executions'][0];
            if (isset($cycleExecution['sequence']) && isset($cycleExecution['cycles_completed'])) {
                return $cycleExecution['sequence'] === 1 && $cycleExecution['cycles_completed'] === 0;
            }
        }

        return false;
    }

    private function validatePaypalSubscriptionInitialPayment(array $subscriptionData, $transaction): bool {
        $paypalSubLastPaymentAmount = null;
        if(isset($subscriptionData['billing_info'])
            && isset($subscriptionData['billing_info']['last_payment'])
            && isset($subscriptionData['billing_info']['last_payment']['amount'])
        ) {
            $paypalSubLastPaymentAmount = $subscriptionData['billing_info']['last_payment']['amount']['value'] ?? null;
        }

        // if the amount is null stop here
        if(!$paypalSubLastPaymentAmount) {
            return false;
        }

        // if the amount doesn't match stop here
        if($paypalSubLastPaymentAmount != $transaction['amount']) {
            return false;
        }

        return true;
    }

    public function capturePaymentForOrder($transaction): Transaction {
        try {
            $paypalOrderCapture = PaypalAPIServiceProvider::capturePaymentForOrder($transaction);
            $paypalTransactionId = null;
            $paypalPayerId = null;

            if(isset($paypalOrderCapture['purchase_units'])
                && isset($paypalOrderCapture['purchase_units'][0])
                && isset($paypalOrderCapture['purchase_units'][0]['payments'])
                && isset($paypalOrderCapture['purchase_units'][0]['payments']['captures'])
                && isset($paypalOrderCapture['purchase_units'][0]['payments']['captures'][0])
            ) {
                $paypalTransactionId = $paypalOrderCapture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
            }

            if(isset($paypalOrderCapture['payer'])) {
                $paypalPayerId = $paypalOrderCapture['payer']['payer_id'] ?? null;
            }

            // Stop processing here if we cannot find transaction / payer id in capture order response
            if(!$paypalTransactionId || !$paypalPayerId) {
                Log::channel('payments')->error(
                    "Missing PayPal transaction / payer id",
                    [
                        'internalTransactionId' => $transaction['id'],
                        'paypalTransactionId' => $paypalTransactionId,
                        'paypalPayerId' => $paypalPayerId,
                    ]
                );

                // return here
                return $transaction;
            }

            if ($paypalOrderCapture['status'] === 'COMPLETED') {
                $saleStatus = Transaction::APPROVED_STATUS;
            } elseif (in_array($paypalOrderCapture['status'], ['DECLINED', 'FAILED'])) {
                $saleStatus = Transaction::DECLINED_STATUS;
            } else {
                $saleStatus = Transaction::PENDING_STATUS;
            }

            $transaction->status = $saleStatus;
            $transaction->paypal_transaction_id = $paypalTransactionId;
            $transaction->paypal_payer_id = $paypalPayerId;

            $transaction->save();

            if ($transaction->status == Transaction::APPROVED_STATUS) {
                // credit receiver for transaction
                $this->creditReceiverForTransaction($transaction);
            }

            if ($transaction->status === Transaction::APPROVED_STATUS
                && ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE)) {
                NotificationServiceProvider::createNewTipNotification($transaction);
            }
        } catch (\Exception $ex) {
            Log::channel('payments')->error('Failed capturing one time paypal payment: '.$ex->getMessage());
        }

        return $transaction;
    }

    public function creditReceiverForTransaction($transaction): void
    {
        if ($transaction->type != null && $transaction->status == Transaction::APPROVED_STATUS) {
            $user = User::query()->where('id', $transaction->recipient_user_id)->first();

            if ($user != null) {
                $userWallet = $user->wallet;

                // Adding available balance
                $amountWithTaxesDeducted = PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($transaction);

                $walletData = ['total' => $userWallet->total + $amountWithTaxesDeducted];

                $userWallet->update($walletData);
            }
        }
    }

    public function updateTransactionByStripeSessionId($sessionId)
    {
        $transaction = Transaction::query()->where(['stripe_session_id' => $sessionId])->first();
        if ($transaction != null) {
            try {
                $stripeClient = new StripeClient(getSetting('payments.stripe_secret_key'));
                $stripeSession = $stripeClient->checkout->sessions->retrieve($sessionId);
                if ($stripeSession != null) {
                    if (isset($stripeSession->payment_status)) {
                        $transaction->stripe_transaction_id = $stripeSession->payment_intent;
                        if ($stripeSession->payment_status == 'paid') {
                            if ($transaction->status != Transaction::APPROVED_STATUS) {
                                $transaction->status = Transaction::APPROVED_STATUS;
                                $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();
                                if ($subscription != null && $this->isSubscriptionPayment($transaction->type)) {
                                    if ($stripeSession->subscription != null) {
                                        $subscription->stripe_subscription_id = $stripeSession->subscription;
                                        $stripeSubscription = $stripeClient->subscriptions->retrieve($stripeSession->subscription);
                                        if($stripeSubscription != null){
                                            $latestInvoiceForSubscription = $stripeClient->invoices->retrieve($stripeSubscription->latest_invoice);
                                            if($latestInvoiceForSubscription != null){
                                                $transaction->stripe_transaction_id = $latestInvoiceForSubscription->payment_intent;
                                            }
                                        }
                                    }

                                    $expiresDate = new DateTime('+'.PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type).' month', new DateTimeZone('UTC'));
                                    if ($subscription->status != Subscription::ACTIVE_STATUS) {
                                        $subscription->status = Subscription::ACTIVE_STATUS;
                                        $subscription->expires_at = $expiresDate;

                                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                                    } else {
                                        $subscription->expires_at = $expiresDate;
                                    }

                                    $subscription->update();

                                    $this->creditReceiverForTransaction($transaction);
                                } else {
                                    $this->creditReceiverForTransaction($transaction);
                                }
                            }
                        } else {
                            $transaction->status = Transaction::CANCELED_STATUS;

                            $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();

                            if ($subscription != null && $subscription->status == Subscription::ACTIVE_STATUS && $subscription->expires_at <= new DateTime()) {
                                $subscription->status = Subscription::CANCELED_STATUS;

                                $subscription->update();
                            }
                        }
                    }

                    $transaction->update();
                }
            } catch (\Exception $exception) {
                Log::channel('payments')->error($exception->getMessage());
            }
        }

        return $transaction;
    }

    public function generateStripeSubscriptionByTransaction($transaction)
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::STRIPE_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function createSubscriptionRenewalTransaction($subscription, $paymentSucceeded, $paymentId = null)
    {
        $transaction = new Transaction();
        $transaction['sender_user_id'] = $subscription->sender_user_id;
        $transaction['recipient_user_id'] = $subscription->recipient_user_id;
        $transaction['type'] = Transaction::SUBSCRIPTION_RENEWAL;
        $transaction['status'] = $paymentSucceeded ? Transaction::APPROVED_STATUS : Transaction::DECLINED_STATUS;
        $transaction['amount'] = $subscription->amount;
        $transaction['currency'] = config('app.site.currency_code');
        $transaction['payment_provider'] = $subscription->provider;
        $transaction['subscription_id'] = $subscription->id;

        // find latest transaction for subscription to get taxes
        $lastTransactionForSubscription = Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($lastTransactionForSubscription != null) {
            $transaction['taxes'] = $lastTransactionForSubscription->taxes;
        }

        if ($paymentId != null) {
            if ($transaction['payment_provider'] === Transaction::PAYPAL_PROVIDER) {
                $transaction['paypal_transaction_id'] = $paymentId;
            } elseif ($transaction['payment_provider'] === Transaction::STRIPE_PROVIDER) {
                $transaction['stripe_transaction_id'] = $paymentId;
            } elseif ($transaction['payment_provider'] === Transaction::CCBILL_PROVIDER) {
                $transaction['ccbill_subscription_id'] = $paymentId;
            } elseif ($transaction['payment_provider'] === Transaction::VEROTEL_PROVIDER) {
                $transaction['verotel_sale_id'] = $paymentId;
            }
        }

        $transaction->save();

        $this->creditReceiverForTransaction($transaction);

        if ($transaction['status'] === Transaction::APPROVED_STATUS && $transaction['type'] === Transaction::CREDIT_PROVIDER) {
            $this->deductMoneyFromUserWalletForCreditTransaction($transaction, $subscription->subscriber->wallet);
        }

        try {
            $invoice = InvoiceServiceProvider::createInvoiceByTransaction($transaction);
            if ($invoice != null) {
                $transaction->invoice_id = $invoice->id;
                $transaction->save();
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed generating invoice for transaction: ".$transaction->id." error: ".$exception->getMessage());
        }

        return $transaction;
    }

    public function cancelPaypalSubscription(string $subscriptionId) {
        PaypalAPIServiceProvider::cancelSubscription($subscriptionId);
    }

    public function cancelStripeSubscription($stripeSubscriptionId)
    {
        $stripe = new StripeClient(getSetting('payments.stripe_secret_key'));

        $stripe->subscriptions->cancel($stripeSubscriptionId);
    }

    public function deductMoneyFromUserForRefundedTransaction($transaction)
    {
        if ($transaction->type != null && $transaction->status == Transaction::REFUNDED_STATUS) {
            switch ($transaction->type) {
                case Transaction::DEPOSIT_TYPE:
                case Transaction::TIP_TYPE:
                case Transaction::CHAT_TIP_TYPE:
                case Transaction::ONE_MONTH_SUBSCRIPTION:
                case Transaction::THREE_MONTHS_SUBSCRIPTION:
                case Transaction::SIX_MONTHS_SUBSCRIPTION:
                case Transaction::YEARLY_SUBSCRIPTION:
                    $user = User::query()->where('id', $transaction->recipient_user_id)->first();
                    $amountWithTaxesDeducted = PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($transaction);
                    if ($user != null) {
                        $user->wallet->update(['total' => $user->wallet->total - $amountWithTaxesDeducted]);
                    }
                    break;
            }
        }
    }

    public function getLoggedUserAvailableAmount()
    {
        $amount = 0.00;
        if (Auth::user() != null && Auth::user()->wallet != null) {
            $amount = Auth::user()->wallet->total;
        }

        return $amount;
    }

    public function generateOneTimeCreditTransaction($transaction)
    {
        $userAvailableAmount = $this->getLoggedUserAvailableAmount();
        if ($transaction['amount'] <= $userAvailableAmount) {
            $transaction['status'] = Transaction::APPROVED_STATUS;
        }
    }

    public function deductMoneyFromUserWalletForCreditTransaction($transaction, $userWallet)
    {
        if ($userWallet != null) {
            $userWallet->update([
                'total' => $userWallet->total - floatval($transaction['amount']),
            ]);
        }
    }

    private function getSubscriptionBySenderAndReceiverAndProvider($senderId, $receiverId, $provider)
    {
        $queryCriteria = [
            'recipient_user_id' => $receiverId,
            'sender_user_id' => $senderId,
            'provider' => $provider,
        ];

        return Subscription::query()->where($queryCriteria)->first();
    }

    public function generateCreditSubscriptionByTransaction($transaction)
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::CREDIT_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
        }
        $subscription['amount'] = $transaction['amount'];
        $subscription['expires_at'] = new DateTime('+'.PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type).' '.'month', new DateTimeZone('UTC'));
        $subscription['status'] = Subscription::ACTIVE_STATUS;
        $transaction['status'] = Transaction::APPROVED_STATUS;

        $subscription->save();

        // only send the notification for new subs
        if($existingSubscription === null){
            NotificationServiceProvider::createNewSubscriptionNotification($subscription);
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function createNewTipNotificationForCreditTransaction($transaction)
    {
        if ($transaction != null
            && $transaction->payment_provider === Transaction::CREDIT_PROVIDER
            && $transaction->status === Transaction::APPROVED_STATUS
            && ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE)) {
            NotificationServiceProvider::createNewTipNotification($transaction);
        }
    }

    public function generateStripeSessionByTransaction(Transaction $transaction)
    {
        $redirectLink = null;
        $transactionType = $transaction->type;
        if ($transactionType == null || empty($transactionType)) {
            return null;
        }

        try {
            \Stripe\Stripe::setApiKey(getSetting('payments.stripe_secret_key'));
            $isSubscriptionPayment = $this->isSubscriptionPayment($transactionType);
            if ($isSubscriptionPayment) {
                // generate stripe product
                $product = \Stripe\Product::create([
                    'name' => $this->getPaymentDescriptionByTransaction($transaction),
                ]);

                // generate stripe price
                $price = \Stripe\Price::create([
                    'product' => $product->id,
                    'unit_amount' => $transaction->amount * 100,
                    'currency' => config('app.site.currency_code'),
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transactionType),
                    ],
                ]);

                $stripeLineItems = [
                    'price' => $price->id,
                    'quantity' => 1,
                ];
            } else {
                $stripeLineItems = [
                    'price_data' => [
                        // To accept `oxxo`, all line items must have currency: mxn
                        'currency' => config('app.site.currency_code'),
                        'product_data' => [
                            'name' => $this->getPaymentDescriptionByTransaction($transaction),
                            'description' => $this->getPaymentDescriptionByTransaction($transaction),
                        ],
                        'unit_amount' => $transaction->amount * 100,
                    ],
                    'quantity' => 1,
                ];
            }

            $data = [
                'payment_method_types' => ['card'],
                'line_items' => [$stripeLineItems],
                'locale' => 'auto',
                'customer_email' => Auth::user()->email,
                'metadata' => [
                    'transactionType' => $transaction->type,
                    'user_id' => Auth::user()->id,
                ],
                'mode' => $transactionType == $this->isSubscriptionPayment($transaction->type) ? 'subscription' : 'payment',
                'success_url' => route('payment.checkStripePaymentStatus').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.checkStripePaymentStatus').'?session_id={CHECKOUT_SESSION_ID}',
            ];

            if($transaction->payment_provider === Transaction::OXXO_PROVIDER) {
                $data['payment_method_types'] = ['oxxo'];
            }

            // Enable some one time payment providers through Stripe checkout
            if(!$isSubscriptionPayment) {
                $currencyCode = strtolower(getSetting('payments.currency_code'));
                // only enable some payment providers if currency is eur
                if($currencyCode === 'eur') {
                    // iDEAL
                    if(getSetting('payments.stripe_ideal_provider_enabled')) {
                        $data['payment_method_types'][] = 'ideal';
                    }

                    // Bancontact
                    if(getSetting('payments.stripe_bancontact_provider_enabled')) {
                        $data['payment_method_types'][] = 'bancontact';
                    }

                    // EPS
                    if(getSetting('payments.stripe_eps_provider_enabled')) {
                        $data['payment_method_types'][] = 'eps';
                    }

                    // Giropay
                    if(getSetting('payments.stripe_giropay_provider_enabled')) {
                        $data['payment_method_types'][] = 'giropay';
                    }
                }

                // only enable Blik if currency is pln
                if(getSetting('payments.stripe_blik_provider_enabled') && $currencyCode === 'pln') {
                    $data['payment_method_types'][] = 'blik';
                }

                // only enable Przelewy24 if currency is eur / pln
                if(getSetting('payments.stripe_przelewy_provider_enabled') && in_array($currencyCode, ['eur', 'pln'])) {
                    $data['payment_method_types'][] = 'p24';
                }
            }

            $session = \Stripe\Checkout\Session::create($data);

            $transaction['stripe_session_id'] = $session->id;
            $redirectLink = $session->url;
        } catch (\Exception $e) {
            Log::channel('payments')->error('Failed generating stripe session for transaction: '.$transaction->id.' error: '.$e->getMessage());
        }

        return $redirectLink;
    }

    /**
     * Verify if payment is made for a subscription.
     *
     * @param $transactionType
     * @return bool
     */
    public function isSubscriptionPayment($transactionType)
    {
        return $transactionType != null
            && ($transactionType === Transaction::SIX_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::THREE_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::ONE_MONTH_SUBSCRIPTION
                || $transactionType === Transaction::YEARLY_SUBSCRIPTION);
    }

    /**
     * Get payment description by transaction type.
     *
     * @param $transaction
     * @return string
     */
    public function getPaymentDescriptionByTransaction($transaction)
    {
        $description = 'Default payment description';
        if ($transaction != null) {
            $recipientUsername = null;
            if ($transaction->recipient_user_id != null) {
                $recipientUser = User::query()->where(['id' => $transaction->recipient_user_id])->first();
                if ($recipientUser != null) {
                    $recipientUsername = $recipientUser->name;
                }
            }

            if ($this->isSubscriptionPayment($transaction->type)) {
                if ($recipientUsername == null) {
                    $recipientUsername = 'creator';
                }

                $description = $recipientUsername.' for '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
            } else {
                if ($transaction->type === Transaction::DEPOSIT_TYPE) {
                    $description = SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount).' '.__('wallet top-up');
                } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
                    $tipPaymentDescription = SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount).' tip';
                    if ($transaction->recipient_user_id != null) {
                        $recipientUser = User::query()->where(['id' => $transaction->recipient_user_id])->first();
                        if ($recipientUser != null) {
                            $tipPaymentDescription = $tipPaymentDescription.' for '.$recipientUser->name;
                        }
                    }

                    $description = $tipPaymentDescription;
                } elseif ($transaction->type === Transaction::POST_UNLOCK) {
                    $description = __('Unlock post for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
                    $description = __('Join streaming for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
                    $description = __('Unlock message for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                }
            }
        }

        return $description;
    }

    /**
     * Redirect user to proper page after payment process.
     *
     * @param $transaction
     * @param null $message
     * @return RedirectResponse
     */
    public function redirectByTransaction($transaction, $message = null)
    {

        // Not sure why translation locale is not being applied here, re-appliying it
        App::setLocale(GenericHelperServiceProvider::getPreferredLanguage());

        $errorMessage = __('Payment failed.');
        if ($message != null) {
            $errorMessage = $message;
        }
        if ($transaction != null) {
            // handles approved status
            $recipient = User::query()->where(['id' => $transaction->recipient_user_id])->first();
            if ($transaction->status === Transaction::APPROVED_STATUS) {
                $successMessage = __('Payment succeeded');
                if ($this->isSubscriptionPayment($transaction->type)) {
                    $successMessage = __('You can now access this user profile.');
                } elseif ($transaction->type === Transaction::DEPOSIT_TYPE) {
                    $key = SettingsServiceProvider::leftAlignedCurrencyPosition()
                        ? 'You have been credited :currencySymbol:amount Happy spending!'
                        : 'You have been credited :amount:currencySymbol Happy spending!';
                    $successMessage = __($key, ['amount' => $transaction->amount, 'currencySymbol' => SettingsServiceProvider::getWebsiteCurrencySymbol()]);
                } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
                    $key = SettingsServiceProvider::leftAlignedCurrencyPosition()
                        ? 'You successfully sent a tip of :currencySymbol:amount.'
                        : 'You successfully sent a tip of :amount:currencySymbol.';
                    $successMessage = __($key, ['amount' => $transaction->amount, 'currencySymbol' => SettingsServiceProvider::getWebsiteCurrencySymbol()]);
                } elseif ($transaction->type === Transaction::POST_UNLOCK) {
                    $successMessage = __('You successfully unlocked this post.');
                } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
                    $successMessage = __('You successfully paid for this streaming.');
                } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
                    $successMessage = __('You successfully unlocked this message.');
                }

                return $this->handleRedirectByTransaction($transaction, $recipient, $successMessage, $success = true);
                // handles any other status
            } else {
                return $this->handleRedirectByTransaction($transaction, $recipient, $errorMessage, $success = false);
            }
        } else {
            return Redirect::route('feed')
                ->with('error', $errorMessage);
        }
    }

    /**
     * Handles redirect by transaction type.
     *
     * @param $transaction
     * @param $recipient
     * @param $message
     * @param bool $success
     * @return RedirectResponse
     */
    private function handleRedirectByTransaction($transaction, $recipient, $message, $success = false)
    {
        $labelType = $success ? 'success' : 'error';
        if ($this->isSubscriptionPayment($transaction->type)) {
            if(in_array($transaction->payment_provider, [Transaction::CCBILL_PROVIDER, Transaction::VEROTEL_PROVIDER])
                && $transaction->status === Transaction::INITIATED_STATUS) {
                $labelType = 'warning';
                $message = __('Your payment have been successfully initiated but needs to await for approval');
            }

            if($transaction->stream_id){
                return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                    ->with($labelType, $message);
            }
            return Redirect::route('profile', ['username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::DEPOSIT_TYPE) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)){
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            } elseif($transaction->payment_provider === Transaction::MANUAL_PROVIDER) {
                $labelType = 'warning';
                $message = __('Your payment have been successfully initiated but needs to await for processing');
            }

            return Redirect::route('my.settings', ['type' => 'wallet'])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)){
                if($transaction->status === Transaction::INITIATED_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }

            if ($transaction->post_id != null) {
                return Redirect::route('posts.get', ['post_id' => $transaction->post_id, 'username' => $recipient->username])
                    ->with($labelType, $message);
            }
            if($transaction->stream_id){
                return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                    ->with($labelType, $message);
            }
            if($transaction->type === Transaction::CHAT_TIP_TYPE) {
                return Redirect::route('my.messenger.get', ['tip'=>1])->with($labelType, $message);
            }
            return Redirect::route('profile', ['username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::POST_UNLOCK) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('posts.get', ['post_id' => $transaction->post_id, 'username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('my.messenger.get', ['messageUnlock' => 1, 'token' => $transaction->user_message_id])->with($labelType, $message);
        }
    }

    /**
     * Generate CoinBase transaction by an api call.
     * @param $transaction
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateCoinBaseTransaction($transaction)
    {
        $redirectUrl = null;
        $httpClient = new Client();
        self::generateCoinbaseTransactionToken($transaction);
        $coinBaseCheckoutRequest = $httpClient->request(
            'POST',
            Transaction::COINBASE_API_BASE_PATH.'/charges',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-CC-Api-Key' => getSetting('payments.coinbase_api_key'),
                    'X-CC-Version' => '2018-03-22',
                ],
                'body' => json_encode(array_merge_recursive([
                    'name' => self::getPaymentDescriptionByTransaction($transaction),
                    'description' => self::getPaymentDescriptionByTransaction($transaction),
                    'local_price' => [
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                    ],
                    'pricing_type' => 'fixed_price',
                    'metadata' => [],
                    'redirect_url' => route('payment.checkCoinBasePaymentStatus').'?token='.$transaction->coinbase_transaction_token,
                    'cancel_url' => route('payment.checkCoinBasePaymentStatus').'?token='.$transaction->coinbase_transaction_token,
                ])),
            ]
        );

        $response = json_decode($coinBaseCheckoutRequest->getBody(), true);
        if (isset($response['data'])) {
            if (isset($response['data']['id'])) {
                $transaction->coinbase_charge_id = $response['data']['id'];
            }

            if (isset($response['data']['hosted_url'])) {
                $redirectUrl = $response['data']['hosted_url'];
            }
        }

        return $redirectUrl;
    }

    /**
     * Generate unique coinbase transaction token used later as identifier.
     * @param $transaction
     * @throws \Exception
     */
    private function generateCoinbaseTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('coinbase_transaction_token', $id)->first() != null);
        $transaction->coinbase_transaction_token = $id;
    }

    /**
     * Update transaction by coinbase charge details.
     * @param $transaction
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateCoinbaseTransaction($transaction)
    {
        if ($transaction != null && $transaction->status != Transaction::APPROVED_STATUS
            && $transaction->payment_provider === Transaction::COINBASE_PROVIDER && $transaction->coinbase_charge_id != null) {
            $coinbaseChargeStatus = self::getCoinbaseChargeStatus($transaction);
            if($coinbaseChargeStatus === 'CANCELED'){
                $transaction->status = Transaction::CANCELED_STATUS;
            } elseif ($coinbaseChargeStatus === 'COMPLETED') {
                $transaction->status = Transaction::APPROVED_STATUS;
                self::creditReceiverForTransaction($transaction);
            }
        }
    }

    /**
     * Get coinbase charge latest status.
     * @param $transaction
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getCoinbaseChargeStatus($transaction)
    {
        $httpClient = new Client();
        $coinBaseCheckoutRequest = $httpClient->request(
            'GET',
            Transaction::COINBASE_API_BASE_PATH.'/charges/'.$transaction->coinbase_charge_id,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-CC-Api-Key' => getSetting('payments.coinbase_api_key'),
                    'X-CC-Version' => '2018-03-22',
                ],
            ]
        );
        $coinbaseChargeLastStatus = 'NEW';
        $response = json_decode($coinBaseCheckoutRequest->getBody(), true);
        if (isset($response['data']) && isset($response['data']['timeline'])) {
            $coinbaseChargeLastStatus = $response['data']['timeline'][count($response['data']['timeline']) - 1]['status'];
        }

        return $coinbaseChargeLastStatus;
    }

    /**
     * Generate now payments transaction.
     * @param $transaction
     * @return |null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateNowPaymentsTransaction($transaction)
    {
        $redirectUrl = null;
        $httpClient = new Client();
        $orderId = self::generateNowPaymentsOrderId($transaction);
        $coinBaseCheckoutRequest = $httpClient->request(
            'POST',
            Transaction::NOWPAYMENTS_API_BASE_PATH.'invoice',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => getSetting('payments.nowpayments_api_key'),
                ],
                'body' => json_encode(array_merge_recursive([
                    'price_amount' => $transaction->amount,
                    'price_currency' => $transaction->currency,
                    'ipn_callback_url' => route('nowPayments.payment.update'),
                    'order_id' => $orderId,
                    'success_url' => route('payment.checkNowPaymentStatus').'?orderId='.$orderId,
                    'cancel_url' => route('payment.checkNowPaymentStatus').'?orderId='.$orderId,
                ])),
            ]
        );

        $response = json_decode($coinBaseCheckoutRequest->getBody(), true);
        if (isset($response['payment_id'])) {
            $transaction->nowpayments_payment_id = $response['payment_id'];
        }
        if(isset($response['order_id'])) {
            $transaction->nowpayments_order_id = $response['order_id'];
        }
        if(isset($response['invoice_url'])) {
            $redirectUrl = $response['invoice_url'];
        }

        return $redirectUrl;
    }

    /**
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generateNowPaymentsOrderId($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('nowpayments_order_id', $id)->first() != null);
        $transaction->nowpayments_order_id = $id;

        return $id;
    }

    /**
     * Generates a unique identifier for ccbill transaction.
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generateCCBillUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('ccbill_payment_token', $id)->first() != null);
        $transaction->ccbill_payment_token = $id;

        return $id;
    }

    /**
     * @param $transaction
     * @return int|null
     * @throws \Exception
     */
    public function generateCCBillOneTimePaymentTransaction($transaction) {
        $redirectUrl = null;
        if(PaymentsServiceProvider::ccbillCredentialsProvided()) {
            // generate a unique token for transaction and prepare dynamic pricing for the flex form
            $this->generateCCBillUniqueTransactionToken($transaction);

            $redirectUrl = $this->generateCCBillRedirectUrlByTransaction($transaction);
        }

        return $redirectUrl;
    }

    /**
     * Generates redirect url for ccbill payment.
     * @param $transaction
     * @return int|string
     */
    private function generateCCBillRedirectUrlByTransaction($transaction) {
        $user = Auth::user();
        $country = Country::query()->where('name', $user->country)->first();
        $amount = $transaction->amount;
        $ccBillInitialPeriod = $this->getCCBillRecurringPeriodInDaysByTransaction($transaction);
        $ccBillNumRebills = 99;
        $isSubscriptionPayment = $this->isSubscriptionPayment($transaction->type);
        $ccBillClientAcc = getSetting('payments.ccbill_account_number');
        $ccBillClientSubAccRecurring = getSetting('payments.ccbill_subaccount_number_recurring');
        $ccBillClientSubAccOneTime = getSetting('payments.ccbill_subaccount_number_one_time');
        $ccBillSalt = getSetting('payments.ccbill_salt_key');
        $ccBillFlexFormId = getSetting('payments.ccbill_flex_form_id');
        $ccBillCurrencyCode = $this->getCCBillCurrencyCodeByCurrency(SettingsServiceProvider::getAppCurrencyCode());
        $ccBillRecurringPeriod = $this->getCCBillRecurringPeriodInDaysByTransaction($transaction);
        $billingAddress = urlencode($user->billing_address);
        $billingFirstName = $user->first_name;
        $billingLastName = $user->last_name;
        $billingEmail = $user->email;
        $billingCity = $user->city;
        $billingState = $user->state;
        $billingPostcode = $user->postcode;
        $billingCountry = $country != null ? $country->country_code : $user->country;
        $ccBillFormDigest = $isSubscriptionPayment
            ? md5(number_format(floatval($amount), 2).$ccBillInitialPeriod.$amount.$ccBillRecurringPeriod.$ccBillNumRebills.$ccBillCurrencyCode.$ccBillSalt)
            : md5(number_format(floatval($amount), 2).$ccBillInitialPeriod.$ccBillCurrencyCode.$ccBillSalt);

        // common form metadata for both one time & recurring payments
        $redirectUrl = Transaction::CCBILL_FLEX_FORM_BASE_PATH.$ccBillFlexFormId.
            '?clientAccnum='.$ccBillClientAcc.'&initialPrice='.$amount.
            '&initialPeriod='.$ccBillInitialPeriod.'&currencyCode='.$ccBillCurrencyCode.'&formDigest='.$ccBillFormDigest.
            '&customer_fname='.$billingFirstName.'&customer_lname='.$billingLastName.'&address1='.$billingAddress.
            '&email='.$billingEmail.'&city='.$billingCity.'&state='.$billingState.'&zipcode='.$billingPostcode.
            '&country='.$billingCountry.'&token='.$transaction->ccbill_payment_token;

        // set client sub account for recurring payments & add extra params
        if($isSubscriptionPayment){
            $redirectUrl .= '&clientSubacc='.$ccBillClientSubAccRecurring.'&recurringPrice='.$amount.'&recurringPeriod='.$ccBillRecurringPeriod.'&numRebills='.$ccBillNumRebills;
        // set client sub account for one time payments & add extra params
        } else {
            $redirectUrl .= '&clientSubacc='.$ccBillClientSubAccOneTime;
        }

        return $redirectUrl;
    }

    /**
     * Get ccbill subscription recurring billing period in days.
     * @param $transaction
     * @return float|int
     */
    public function getCCBillRecurringPeriodInDaysByTransaction($transaction) {
        return PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type) * 30;
    }

    /**
     * @param $currency
     * @return mixed
     */
    public function getCCBillCurrencyCodeByCurrency($currency) {
        $availableCurrencies = [
            'EUR' => '978',
            'AUD' => '036',
            'CAD' => '124',
            'GBP' => '826',
            'JPY' => '392',
            'USD' => '840',
        ];

        return $availableCurrencies[$currency];
    }

    /**
     * @param $transaction
     * @return int|string|null
     * @throws \Exception
     */
    public function generateCCBillSubscriptionPayment($transaction) {
        $redirectUrl = null;
        if(PaymentsServiceProvider::ccbillCredentialsProvided()) {
            // generate a unique token for transaction and prepare dynamic pricing for the flex form
            $this->generateCCBillUniqueTransactionToken($transaction);
            $this->generateCCBillSubscriptionByTransaction($transaction);
            $redirectUrl = $this->generateCCBillRedirectUrlByTransaction($transaction);
        }

        return $redirectUrl;
    }

    /**
     * @param $transaction
     * @return Subscription|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     * @throws \Exception
     */
    public function generateCCBillSubscriptionByTransaction($transaction)
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::CCBILL_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];
            $subscription['ccbill_subscription_id'] = $transaction['ccbill_subscription_id'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    /**
     * Makes the call to CCBill API to cancel a subscription.
     * @param $stripeSubscriptionId
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelCCBillSubscription($stripeSubscriptionId)
    {
        $client = new Client(['debug' => fopen('php://stderr', 'w')]);
        $cancellationData = [
            'clientAccnum' => getSetting('payments.ccbill_account_number'),
            'clientSubacc' => getSetting('payments.ccbill_subaccount_number_recurring'),
            'username' => getSetting('payments.ccbill_datalink_username'),
            'password' => getSetting('payments.ccbill_datalink_password'),
            'subscriptionId' => $stripeSubscriptionId,
            'action' => 'cancelSubscription',
        ];
        if(getSetting('payments.ccbill_skip_subaccount_from_cancellations')){
            unset($cancellationData['clientSubacc']);
        }
        $res = $client->request('GET', 'https://datalink.ccbill.com/utils/subscriptionManagement.cgi', [
            'query' => $cancellationData,
        ]);
        $response = $res->getBody()->getContents();
        if($response) {
            $responseAsArray = str_getcsv($response, "\n");
            if($responseAsArray && isset($responseAsArray[0]) && isset($responseAsArray[1])) {
                if($responseAsArray[0] === 'results' && $responseAsArray[1] === '1') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generatePaystackUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('paystack_payment_token', $id)->first() != null);
        $transaction->paystack_payment_token = $id;

        return $id;
    }

    /**
     * @param $transaction
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function generatePaystackTransaction($transaction, $email) {
        $paystack = new Paystack(getSetting('payments.paystack_secret_key'));
        $reference = self::generatePaystackUniqueTransactionToken($transaction);
        $paystackTransaction = $paystack->transaction->initialize([
            'amount'=>$transaction->amount * 100,
            'email'=>$email,
            'reference'=>$reference,
        ]);

        return $paystackTransaction->data->authorization_url;
    }

    /**
     * Calls PayStack API to verify payment status and updates transaction in our side accordingly.
     * @param $reference
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function verifyPaystackTransaction($reference) {
        $transaction = null;
        if($reference){
            $transaction = Transaction::query()->where('paystack_payment_token', $reference)->first();
            if($transaction && $transaction->status !== Transaction::APPROVED_STATUS) {
                $paystack = new Paystack(getSetting('payments.paystack_secret_key'));
                try
                {
                    $paystackTransaction = $paystack->transaction->verify([
                        'reference'=>$reference,
                    ]);

                    if ('success' === $paystackTransaction->data->status) {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }
                } catch(ApiException $e){
                    Log::channel('payments')->error("Failed verifying paystack transaction: ".$e->getMessage());
                }
            }
        }

        return $transaction;
    }

    public function validateTransaction($transaction, $recipientUser) {
        $valid = false;
        if($transaction) {
            $exclusiveTaxesAmount = 0;
            $fixedTaxesAmount = 0;
            $taxes = PaymentsServiceProvider::calculateTaxesForTransaction($transaction);
            if(isset($taxes['exclusiveTaxesAmount'])) {
                $exclusiveTaxesAmount = $taxes['exclusiveTaxesAmount'];
            }
            if(isset($taxes['fixedTaxesAmount'])) {
                $fixedTaxesAmount = $taxes['fixedTaxesAmount'];
            }
            $transactionAmountWithoutTaxes = (string)($transaction['amount'] - $exclusiveTaxesAmount - $fixedTaxesAmount);

            // Note*: Doing (string) comparison due to PHP float inaccuracy
            // Note* Doing (string)($number + 0) comparison because some mysql drivers doesn't truncate .00 decimals for floats

            switch ($transaction->type) {
                case Transaction::ONE_MONTH_SUBSCRIPTION:
                    if($transactionAmountWithoutTaxes === (string)($recipientUser->profile_access_price + 0)) {
                        $valid = true;
                    }
                    break;
                case Transaction::THREE_MONTHS_SUBSCRIPTION:
                    if($transactionAmountWithoutTaxes === (string)($recipientUser->profile_access_price_3_months * 3 + 0)) {
                        $valid = true;
                    }
                    break;
                case Transaction::SIX_MONTHS_SUBSCRIPTION:
                    if($transactionAmountWithoutTaxes === (string)($recipientUser->profile_access_price_6_months * 6 + 0)) {
                        $valid = true;
                    }
                    break;
                case Transaction::YEARLY_SUBSCRIPTION:
                    if($transactionAmountWithoutTaxes === (string)($recipientUser->profile_access_price_12_months * 12 + 0)) {
                        $valid = true;
                    }
                    break;
                case Transaction::POST_UNLOCK:
                    $post = Post::query()->where('id', $transaction->post_id)->first();
                    if((string)($post->price + 0) === $transactionAmountWithoutTaxes) {
                        $valid = true;
                    }
                    break;
                case Transaction::STREAM_ACCESS:
                    $stream = Stream::query()->where('id', $transaction->stream_id)->first();
                    if($stream && (string)($stream->price + 0) === $transactionAmountWithoutTaxes) {
                        $valid = true;
                    }
                    break;
                case Transaction::MESSAGE_UNLOCK:
                    $message = UserMessage::query()->where('id', $transaction->user_message_id)->first();
                    if((string)($message->price + 0) === $transactionAmountWithoutTaxes) {
                        $valid = true;
                    }
                    break;
                case Transaction::TIP_TYPE:
                case Transaction::CHAT_TIP_TYPE:
                case Transaction::DEPOSIT_TYPE:
                    $valid = true;
                    break;
            }
        }
        return $valid;
    }

    /**
     * Cancels a subscription.
     * @param $subscription
     * @return bool|RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelSubscription($subscription) {
        $cancelSubscription = false;

        if ($subscription->provider != null) {
            if ($subscription->provider === Transaction::PAYPAL_PROVIDER && $subscription->paypal_agreement_id != null) {
                $this->cancelPaypalSubscription($subscription->paypal_agreement_id);
                $cancelSubscription = true;
            } elseif ($subscription->provider === Transaction::STRIPE_PROVIDER && $subscription->stripe_subscription_id != null) {
                $this->cancelStripeSubscription($subscription->stripe_subscription_id);
                $cancelSubscription = true;
            } elseif ($subscription->provider === Transaction::VEROTEL_PROVIDER && $subscription->verotel_sale_id != null) {
                $cancelSubscription = VerotelServiceProvider::cancelSubscriptionSale($subscription->verotel_sale_id);
            } elseif ($subscription->provider === Transaction::CCBILL_PROVIDER && $subscription->ccbill_subscription_id != null) {
                if($this->cancelCCBillSubscription($subscription->ccbill_subscription_id)){
                    $cancelSubscription = true;
                }
            } elseif($subscription->provider === Transaction::CREDIT_PROVIDER) {
                $cancelSubscription = true;
            }

            // handle cancel subscription
            if($cancelSubscription) {
                $subscription->status = Subscription::CANCELED_STATUS;
                $subscription->canceled_at = new DateTime();

                $subscription->save();
            }
        }

        return $cancelSubscription;
    }

    /**
     * Generate Mercado transaction.
     * @param $transaction
     * @return string|void
     */
    public function generateMercadoTransaction($transaction) {
        try {
            $this->initiateMercadoPagoSdk();
            $reference = self::generateMercadoUniqueTransactionToken($transaction);

            $preference = new Preference();
            $preference->external_reference = $reference;
            $preference->notification_url = route('mercado.payment.update');

            $item = new \MercadoPago\Item();
            $item->title = self::getPaymentDescriptionByTransaction($transaction);
            $item->quantity = 1;
            $item->unit_price = $transaction->amount;

            $preference->items = [$item];

            $preference->back_urls = [
                "success" => route('payment.checkMercadoPaymentStatus'),
            ];
            $preference->auto_return = "approved";

            $preference->save();

            return $preference->init_point;
        } catch (\Exception $exception) {
            $this->redirectByTransaction($transaction);
        }
    }

    /**
     * Verify Mercado transaction and update transaction accordingly.
     * @param $reference
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function verifyMercadoTransaction($paymentId) {
        $transaction = null;
        try {
            $this->initiateMercadoPagoSdk();
            $mercadoPayment = \MercadoPago\Payment::get($paymentId);
            if($mercadoPayment) {
                $transaction = Transaction::query()->where('mercado_payment_token', $mercadoPayment->external_reference)->first();
                if($transaction && $transaction->status !== Transaction::APPROVED_STATUS) {
                    $success = $mercadoPayment->status === 'approved';
                    if($success) {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->mercado_payment_id = $paymentId;
                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Mercado transaction: ".$exception->getMessage());
        }

        return $transaction;
    }

    /**
     * Generates MercadoPago unique transaction token.
     * @param $transaction
     * @return \Ramsey\Uuid\Type\Hexadecimal
     */
    private function generateMercadoUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('paystack_payment_token', $id)->first() != null);
        $transaction->mercado_payment_token = $id;

        return $id;
    }

    /**
     * Initiates MercadoPago SDK.
     * @return void
     */
    private function initiateMercadoPagoSdk() {
        SDK::setAccessToken(getSetting('payments.mercado_access_token'));
    }

    public function getOriginalPaymentIdFromResourceForRefundedTransaction(array $resource): ?string {
        // Check if the resource contains the "links" array
        if (isset($resource['links'])) {
            foreach ($resource['links'] as $link) {
                if (isset($link['rel']) && $link['rel'] === 'up') {
                    // Extract the original payment ID from the "href" URL
                    $urlParts = explode('/', rtrim($link['href'], '/'));
                    return end($urlParts); // Return the last part of the URL (the ID)
                }
            }
        }

        // Return null if no payment ID is found
        return null;
    }

    public function handlePaypalTransactionRefund(string $transactionId): void {
        $transaction = Transaction::query()->where('paypal_transaction_id', $transactionId)->with('subscription')->first();
        if ($transaction) {
            if($transaction->status === Transaction::APPROVED_STATUS){
                $transaction->status = Transaction::REFUNDED_STATUS;
                $transaction->save();

                if($transaction->subscription != null){
                    $transaction->subscription->status = Subscription::SUSPENDED_STATUS;
                    $transaction->subscription->expires_at = new DateTime('now', new DateTimeZone('UTC'));
                    $transaction->subscription->save();
                }

                $this->deductMoneyFromUserForRefundedTransaction($transaction);
            }
        }
    }

    private function generateVerotelSubscriptionByTransaction($transaction): Subscription
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::VEROTEL_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];
            $subscription['verotel_sale_id'] = $transaction['verotel_sale_id'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function generateVerotelSubscriptionPayment($transaction): string {
        $token = self::generateVerotelUniqueTransactionToken($transaction);
        $this->generateVerotelSubscriptionByTransaction($transaction);

        return VerotelServiceProvider::generateSubscriptionPaymentUrl($transaction, $token);
    }

    public function generateVerotelTransaction($transaction): string {
        $token = self::generateVerotelUniqueTransactionToken($transaction);

        return VerotelServiceProvider::generateOneTimePaymentUrl($transaction, $token);
    }

    private function generateVerotelUniqueTransactionToken($transaction): string
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('verotel_payment_token', $id)->first() != null);
        $transaction->verotel_payment_token = $id;

        return $id;
    }

    public function verifyVerotelOneTimePayment(string $paymentToken, string $saleId): void {
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('verotel_payment_token', $paymentToken)->first();
            if($transaction) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if($transaction->status !== Transaction::APPROVED_STATUS
                    && isset($verotelPaymentData['saleResult'])
                    && $verotelPaymentData['saleResult'] === 'APPROVED'
                ) {
                    $transaction->status = Transaction::APPROVED_STATUS;
                    $transaction->verotel_sale_id = $saleId;
                    $transaction->save();

                    $this->creditReceiverForTransaction($transaction);
                    NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                    NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel transaction: ".$exception->getMessage());
        }
    }

    public function verifyVerotelInitialRecurringPayment(string $paymentToken, string $saleId, ?string $nextChargeOn = null): void {
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('verotel_payment_token', $paymentToken)->first();
            if($transaction) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if($transaction->status !== Transaction::APPROVED_STATUS
                    && isset($verotelPaymentData['saleResult'])
                    && $verotelPaymentData['saleResult'] === 'APPROVED'
                ) {
                    // handle initial recurring payment
                    $transaction->verotel_sale_id = $saleId;
                    $transaction->status = Transaction::APPROVED_STATUS;
                    $transaction->save();

                    $subscription = $transaction->subscription;
                    if($subscription) {
                        if($nextChargeOn) {
                            $expiryDate = new DateTime($nextChargeOn, new DateTimeZone('UTC'));
                        } else {
                            $expiryDate = new DateTime('+'.self::getCCBillRecurringPeriodInDaysByTransaction($transaction).' days', new DateTimeZone('UTC'));
                        }
                        $subscription->status = Subscription::ACTIVE_STATUS;
                        $subscription->expires_at = $expiryDate;
                        $subscription->verotel_sale_id = $saleId;
                        $subscription->save();

                        self::creditReceiverForTransaction($transaction);
                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel subscription transaction: ".$exception->getMessage());
        }
    }

    public function verifyVerotelRenewalRecurringPayment(string $saleId, string $nextChargeOn): void {
        try {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->where('verotel_sale_id', $saleId)->first();
            if($subscription) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if(isset($verotelPaymentData['saleResult']) && $verotelPaymentData['saleResult'] === 'APPROVED') {
                    self::createSubscriptionRenewalTransaction($subscription, $paymentSucceeded = true, $saleId);
                    $expiryDate = new DateTime($nextChargeOn, new DateTimeZone('UTC'));
                    $subscription->expires_at = $expiryDate;
                    if ($subscription->status != Subscription::ACTIVE_STATUS) {
                        $subscription->status = Subscription::ACTIVE_STATUS;

                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                    }
                    $subscription->save();
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel subscription renewal transaction: ".$exception->getMessage());
        }
    }

    public function handleVerotelTransactionRefund(string $saleId): void {
        $transaction = Transaction::where('verotel_sale_id', $saleId)->with('subscription')->first();
        $transaction->status = Transaction::REFUNDED_STATUS;
        $transaction->save();

        if ($transaction->status === Transaction::APPROVED_STATUS) {
            self::deductMoneyFromUserForRefundedTransaction($transaction);
        }

        if ($transaction->subscription != null) {
            $transaction->subscription->status = Subscription::SUSPENDED_STATUS;
            $transaction->subscription->expires_at = new DateTime('now', new DateTimeZone('UTC'));
            $transaction->subscription->save();
        }
    }

    public function handleVerotelSubscriptionCancelation(string $saleId): void {
        $subscription = Subscription::where('verotel_sale_id', $saleId)->first();

        if($subscription) {
            $subscription->status = Subscription::CANCELED_STATUS;
            $subscription->canceled_at = new DateTime();

            $subscription->save();
        }
    }

    public function generateRazorPayTransaction($transaction): string {
        $token = self::generateRazorPayUniqueTransactionToken($transaction);

        return RazorPayServiceProvider::createPaymentLinkByTransaction($transaction, $token);
    }

    private function generateRazorPayUniqueTransactionToken($transaction): string
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('razorpay_payment_token', $id)->first() != null);
        $transaction->razorpay_payment_token = $id;

        return $id;
    }

    public function verifyRazorpayPayment(string $paymentToken, string $paymentId): Transaction {
        $transaction = null;
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('razorpay_payment_token', $paymentToken)->first();
            if($transaction && $transaction->status === Transaction::INITIATED_STATUS) {
                $razorpayPaymentData = RazorPayServiceProvider::getPaymentData($paymentId);
                Log::channel('payments')->info("Razorpay payment data", [$razorpayPaymentData->toArray()]);
                if(isset($razorpayPaymentData['status'])) {
                    if($razorpayPaymentData['status'] === 'captured') {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->razorpay_payment_id = $paymentId;

                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }

                    if($razorpayPaymentData['status'] === 'failed') {
                        $transaction->status = Transaction::DECLINED_STATUS;
                        $transaction->razorpay_payment_id = $paymentId;

                        $transaction->save();
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying RazorPay transaction: ".$exception->getMessage());
        }

        return $transaction;
    }

    public function handleRazorpayTransactionRefund(string $paymentId): void
    {
        $transaction = Transaction::where('razorpay_payment_id', $paymentId)->first();
        if ($transaction && $transaction->status === Transaction::APPROVED_STATUS) {
            $razorpayPaymentData = RazorPayServiceProvider::getPaymentData($paymentId);
            Log::channel('payments')->debug("Razorpay payment data", [$razorpayPaymentData->toArray()]);

            if (isset($razorpayPaymentData['status']) && $razorpayPaymentData['status'] === 'refunded') {
                $transaction->status = Transaction::REFUNDED_STATUS;
                $transaction->save();

                self::deductMoneyFromUserForRefundedTransaction($transaction);
            }
        }
    }
}
