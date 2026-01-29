<?php

namespace App\Services\Payments\Gateways;

use App\Model\Transaction;
use App\Model\User;
use App\Providers\AsaasAPIServiceProvider;

class AsaasGateway
{
    /**
     * Ensure the user exists as a customer in Asaas.
     *
     * @param User $user
     * @return string
     */
    public function ensureCustomer(User $user): string
    {
        if ($user->asaas_customer_id) {
            return $user->asaas_customer_id;
        }

        $customerData = [
            'name' => $user->name,
            'email' => $user->email,
            'externalReference' => (string) $user->id,
        ];

        $asaasCustomer = AsaasAPIServiceProvider::createCustomer($customerData);
        $user->asaas_customer_id = $asaasCustomer['id'];
        $user->save();

        return $asaasCustomer['id'];
    }

    /**
     * Create a PIX charge.
     *
     * @param Transaction $transaction
     * @return array
     */
    public function createPixCharge(Transaction $transaction): array
    {
        $user = $transaction->sender;
        $customerId = $this->ensureCustomer($user);

        $paymentData = [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $transaction->amount,
            'dueDate' => now()->addDays(1)->format('Y-m-d'),
            'description' => 'Pagamento #' . $transaction->id,
            'externalReference' => (string) $transaction->id,
        ];

        $asaasPayment = AsaasAPIServiceProvider::createPayment($paymentData);
        $pixInfo = AsaasAPIServiceProvider::getPixIdentification($asaasPayment['id']);

        $transaction->asaas_customer_id = $customerId;
        $transaction->asaas_payment_id = $asaasPayment['id'];
        $transaction->asaas_status = $asaasPayment['status'];
        $transaction->asaas_pix_qr_code = $pixInfo['encodedImage'];
        $transaction->asaas_pix_payload = $pixInfo['payload'];
        $transaction->asaas_raw = $asaasPayment;
        $transaction->save();

        return [
            'id' => $asaasPayment['id'],
            'status' => $asaasPayment['status'],
            'qrCode' => $pixInfo['encodedImage'],
            'payload' => $pixInfo['payload'],
        ];
    }

    /**
     * Create a Boleto charge.
     *
     * @param Transaction $transaction
     * @return array
     */
    public function createBoletoCharge(Transaction $transaction): array
    {
        $user = $transaction->sender;
        $customerId = $this->ensureCustomer($user);

        $paymentData = [
            'customer' => $customerId,
            'billingType' => 'BOLETO',
            'value' => $transaction->amount,
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
            'description' => 'Pagamento #' . $transaction->id,
            'externalReference' => (string) $transaction->id,
        ];

        $asaasPayment = AsaasAPIServiceProvider::createPayment($paymentData);

        $transaction->asaas_customer_id = $customerId;
        $transaction->asaas_payment_id = $asaasPayment['id'];
        $transaction->asaas_status = $asaasPayment['status'];
        $transaction->asaas_invoice_url = $asaasPayment['bankSlipUrl'] ?? $asaasPayment['invoiceUrl'];
        $transaction->asaas_raw = $asaasPayment;
        $transaction->save();

        return [
            'id' => $asaasPayment['id'],
            'status' => $asaasPayment['status'],
            'invoiceUrl' => $transaction->asaas_invoice_url,
        ];
    }

    /**
     * Map Asaas status to internal status.
     *
     * @param string $asaasStatus
     * @return string
     */
    public static function mapStatus(string $asaasStatus): string
    {
        switch ($asaasStatus) {
            case 'RECEIVED':
            case 'CONFIRMED':
            case 'RECEIVED_IN_CASH':
                return Transaction::APPROVED_STATUS;
            case 'OVERDUE':
                return Transaction::DECLINED_STATUS;
            case 'REFUND_REQUESTED':
            case 'REFUNDED':
            case 'CHARGEBACK_REQUESTED':
            case 'CHARGEBACK_DISPUTE':
                return Transaction::REFUNDED_STATUS;
            case 'CANCELED':
                return Transaction::CANCELED_STATUS;
            case 'PENDING':
            default:
                return Transaction::PENDING_STATUS;
        }
    }
}
