<?php

namespace App\Http\Controllers;

use App\Model\Transaction;
use App\Services\Payments\Gateways\AsaasGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    /**
     * Handle Asaas Webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $token = $request->header('asaas-access-token');

        if ($token !== config('asaas.webhook_token')) {
            Log::channel('payments')->error('Asaas Webhook: Invalid token', ['token' => $token]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        Log::channel('payments')->info('Asaas Webhook received', $payload);

        $event = $payload['event'] ?? null;
        $payment = $payload['payment'] ?? null;

        if (!$event || !$payment) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $transaction = Transaction::where('asaas_payment_id', $payment['id'])->first();

        if (!$transaction) {
            Log::channel('payments')->warning('Asaas Webhook: Transaction not found', ['asaas_payment_id' => $payment['id']]);
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Update Asaas specific fields
        $transaction->asaas_status = $payment['status'];
        $transaction->asaas_raw = $payment;

        // Update internal status
        $newStatus = AsaasGateway::mapStatus($payment['status']);

        if ($transaction->status !== $newStatus) {
            $transaction->status = $newStatus;
            $transaction->save();

            Log::channel('payments')->info('Asaas Webhook: Transaction status updated', [
                'transaction_id' => $transaction->id,
                'status' => $newStatus
            ]);

            // If approved, trigger necessary actions (like crediting the receiver)
            if ($newStatus === Transaction::APPROVED_STATUS) {
                // Here you would typically call a method to credit the receiver
                // In this project, PaymentHelper usually handles this.
                // I'll need to check if I can trigger it from here.
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
