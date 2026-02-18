<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IfthenpayController extends Controller
{
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'productId'        => ['required', 'string', 'in:dogs,cats'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'method'           => ['required', 'string', 'in:credit_card,multibanco'],
            'customer.name'    => ['nullable', 'string', 'max:255'],
            'customer.email'   => ['nullable', 'email', 'max:255'],
        ]);

        $payment = Payment::create([
            'id'         => (string) Str::uuid(),
            'product_id' => $data['productId'],
            'amount'     => $data['amount'],
            'method'     => $data['method'],
            'status'     => 'pending',
        ]);

        if ($data['method'] === 'multibanco') {
            $response = Http::post(config('services.ifthenpay.multibanco_url'), [
                'mb_key'      => config('services.ifthenpay.mb_key'),
                'orderId'     => $payment->id,
                'amount'      => $payment->amount,
                'clientName'  => data_get($data, 'customer.name'),
                'clientEmail' => data_get($data, 'customer.email'),
            ]);

            $payload = $response->json();

            $payment->update([
                'provider_reference' => $payload['reference'] ?? null,
                'meta' => [
                    'entity'    => $payload['entity'] ?? null,
                    'reference' => $payload['reference'] ?? null,
                    'deadline'  => $payload['deadline'] ?? null,
                ],
            ]);

            return response()->json([
                'paymentId'  => $payment->id,
                'method'     => 'multibanco',
                'status'     => $payment->status,
                'amount'     => $payment->amount,
                'currency'   => 'EUR',
                'multibanco' => [
                    'entity'    => $payload['entity'] ?? null,
                    'reference' => $payload['reference'] ?? null,
                    'deadline'  => $payload['deadline'] ?? null,
                ],
            ]);
        }

        if ($data['method'] === 'credit_card') {
            $response = Http::post(config('services.ifthenpay.credit_card_url'), [
                'ccard_key'   => config('services.ifthenpay.ccard_key'),
                'orderId'     => $payment->id,
                'amount'      => $payment->amount,
                'clientName'  => data_get($data, 'customer.name'),
                'clientEmail' => data_get($data, 'customer.email'),
                'successUrl'  => config('app.url') . '/payment/success',
                'cancelUrl'   => config('app.url') . '/payment/cancel',
            ]);

            $payload = $response->json();

            $payment->update([
                'provider_reference' => $payload['transactionId'] ?? null,
                'meta' => [
                    'redirectUrl' => $payload['redirectUrl'] ?? null,
                ],
            ]);

            return response()->json([
                'paymentId'   => $payment->id,
                'method'      => 'credit_card',
                'status'      => $payment->status,
                'amount'      => $payment->amount,
                'currency'    => 'EUR',
                'redirectUrl' => $payload['redirectUrl'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Método de pagamento inválido'], 422);
    }

    public function callback(Request $request)
    {
        $paymentId = $request->input('payment_id');
        $status    = $request->input('status');

        $payment = Payment::find($paymentId);

        if (! $payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        if ($status === 'paid') {
            $meta = $payment->meta ?? [];
            $meta['ifthenpay_raw'] = $request->all();

            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
                'meta'    => $meta,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}