<?php

namespace App\Http\Controllers;

use App\Services\IpaymuService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /** Customer clicks "Bayar sekarang": send them to the iPaymu hosted checkout. */
    public function pay(Request $request, string $orderNumber, PaymentService $payments)
    {
        $order = $request->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        return redirect()->away($payments->start($order, $request->user()));
    }

    /**
     * iPaymu sends the customer back here. This is only navigation: it never changes any status
     * (PRD 6.4). The order page shows whatever the verified callback has recorded.
     */
    public function returned(Request $request)
    {
        $order = $this->ownOrder($request);

        return redirect()->route('orders.show', ['orderNumber' => $order->order_number, 'checking' => 1]);
    }

    public function cancelled(Request $request)
    {
        $order = $this->ownOrder($request);

        return redirect()
            ->route('orders.show', $order->order_number)
            ->with('status', 'Pembayaran belum selesai. Kamu bisa mencoba lagi sebelum batas waktu pesanan.');
    }

    /** Server-to-server notification from iPaymu. */
    public function callback(Request $request, IpaymuService $ipaymu, PaymentService $payments)
    {
        // Read the raw body: framework middleware (trim, empty-string-to-null) would change the signed data.
        $raw = $request->getContent();

        if (str_contains((string) $request->header('Content-Type'), 'json')) {
            $payload = json_decode($raw, true);
        } else {
            parse_str($raw, $payload);
        }

        if (! is_array($payload) || $payload === []) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $signature = $request->header('X-Signature') ?: ($payload['signature'] ?? null);

        if (! $ipaymu->validCallbackSignature($payload, $signature)) {
            Log::warning('iPaymu callback rejected: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'message' => 'Invalid Signature'], 400);
        }

        try {
            $result = $payments->handleCallback($payload);
        } catch (\Throwable $e) {
            report($e);

            // 5xx makes iPaymu retry later, which is what we want when we could not verify the payment.
            return response()->json(['status' => 'error', 'message' => 'Temporary error, please retry'], 503);
        }

        return response()->json(['status' => 'success', 'result' => $result]);
    }

    private function ownOrder(Request $request)
    {
        return $request->user()->orders()
            ->where('order_number', (string) $request->query('order'))
            ->firstOrFail();
    }
}
