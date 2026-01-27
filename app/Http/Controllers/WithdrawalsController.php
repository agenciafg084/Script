<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWithdrawalRequest;
use App\Model\Withdrawal;
use App\Providers\EmailsServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\StripeServiceProvider;
use App\Providers\WithdrawalsServiceProvider;
use App\Model\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Stripe\Payout;

class WithdrawalsController extends Controller
{
    /**
     * Method used for requesting an withdrawal request from the admin.
     *
     * @param CreateWithdrawalRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(CreateWithdrawalRequest $request)
    {
        try {
            $amount = $request->request->get('amount');
            $message = $request->request->get('message');
            $identifier = $request->request->get('identifier');
            $method = $request->request->get('method');

            $user = Auth::user();
            if ($amount != null && $user != null) {
                if ($user->wallet == null) {
                    $user->wallet = GenericHelperServiceProvider::createUserWallet($user);
                }

                if(floatval($amount) === floatval(PaymentsServiceProvider::getWithdrawalMinimumAmount()) && floatval($amount) > $user->wallet->total){
                    return response()->json(
                        [
                            'success' => false,
                            'message' => __("You don't have enough credit to withdraw. Minimum amount is: ", ['minAmount' => PaymentsServiceProvider::getWithdrawalMinimumAmount()]),
                        ]
                    );
                }

                if (floatval($amount) > $user->wallet->total) {
                    return response()->json(['success' => false, 'message' => __('You cannot withdraw this amount, try with a lower one')]);
                }

                $fee = Withdrawal::calculateFee($amount);

                Withdrawal::create([
                    'user_id' => Auth::user()->id,
                    'amount' => floatval($amount),
                    'status' => Withdrawal::REQUESTED_STATUS,
                    'message' => $message,
                    'payment_method' => $method,
                    'payment_identifier' => $identifier,
                    'fee' => $fee,
                ]);

                $user->wallet->update([
                    'total' => $user->wallet->total - floatval($amount),
                ]);

                $totalAmount = number_format($user->wallet->total, 2, '.', '');
                $pendingBalance = number_format($user->wallet->pendingBalance, 2, '.', '');

                // Sending out admin email
                WithdrawalsServiceProvider::processNewWithdrawalEmailNotification();

                return response()->json([
                    'success' => true,
                    'message' => __('Successfully requested withdrawal'),
                    'totalAmount' => SettingsServiceProvider::getWebsiteFormattedAmount($totalAmount),
                    'pendingBalance' => SettingsServiceProvider::getWebsiteFormattedAmount($pendingBalance),
                ]);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['success' => false, 'message' => __('Something went wrong, please try again')], 500);
    }

    public function onboarding() {
        $user = Auth::user();

        try {
            // redirect user to the form where he must add his details for the first time
            $onboardingType = "account_onboarding";
            // check if user have a stripe account created
            if(!$user->stripe_account_id) {
                WithdrawalsServiceProvider::createStripeAccountForUser($user);
            }

            // check if user done onboarding and if so just redirect him to only update his details
            if(WithdrawalsServiceProvider::userDoneStripeOnboarding($user)) {
                $onboardingType = "account_update";
            }

            // create account link (Stripe hosted UI to complete verification / onboarding process)
            $accountLink = StripeServiceProvider::createStripeAccountLink($user->stripe_account_id, $onboardingType);
        } catch (\Exception $exception) {
            Log::channel('withdrawals')->error(
                'StripeConnect onboarding failed being initiated',
                ['error' => $exception->getMessage(), 'userId' => $user->id]
            );
            return back()->with('error', __('Onboarding initiation failed, please retry or contact support'));
        }

        // redirect on Stripe hosted UI
        return Redirect::away($accountLink->url);
    }

    public function approveWithdrawal($withdrawalId) {
        $approvalResponse = WithdrawalsServiceProvider::approve($withdrawalId);
        $statusCode = $approvalResponse['success'] ? 200 : ($approvalResponse['error'] === __('Withdrawal not found') ? 404 : 400);

        return response()->json($approvalResponse, $statusCode);
    }

    public function rejectWithdrawal($withdrawalId) {
        $rejectionResponse = WithdrawalsServiceProvider::reject($withdrawalId);
        $statusCode = $rejectionResponse['success'] ? 200 : ($rejectionResponse['error'] === __('Withdrawal not found') ? 404 : 400);

        return response()->json($rejectionResponse, $statusCode);
    }
}
