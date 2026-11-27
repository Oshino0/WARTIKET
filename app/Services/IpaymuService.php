<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for iPaymu API v2 (redirect payment, check transaction) and
 * callback signature validation. Written against https://docs.ipaymu.com
 */
class IpaymuService
{
    /** @return array{session_id: string, url: string} */
    public function createRedirectPayment(Order $order, Payment $payment, User $buyer, int $expiredHours): array
    {
        $products = $qty = $price = $description = [];

        foreach ($order->items as $item) {
            $products[] = $item->product_name_snapshot . ' - ' . $item->ticket_type_name_snapshot;
            $qty[] = (string) $item->quantity;
            $price[] = (string) (int) round((float) $item->unit_price);
            $description[] = $item->ticket_type_name_snapshot;
        }

        if ((float) $order->admin_fee > 0) {
            $products[] = 'Biaya layanan';
            $qty[] = '1';
            $price[] = (string) (int) round((float) $order->admin_fee);
            $description[] = 'Biaya layanan';
        }

        // Safety net: never send iPaymu a basket that does not add up to the stored order total.
        $basket = 0;
        foreach ($qty as $i => $q) {
            $basket += (int) $q * (int) $price[$i];
        }
        if ($basket !== (int) round((float) $order->total)) {
            throw new RuntimeException("Basket total {$basket} does not match order total {$order->total}. Ticket prices must be whole rupiah.");
        }

        $body = [
            'product' => $products,
            'qty' => $qty,
            'price' => $price,
            'description' => $description,
            'returnUrl' => $this->withOrder(config('ipaymu.return_url') ?: route('payment.return'), $order),
            'notifyUrl' => config('ipaymu.callback_url') ?: route('payment.callback'),
            'cancelUrl' => $this->withOrder(config('ipaymu.cancel_url') ?: route('payment.cancel'), $order),
            'referenceId' => $payment->reference_id,
            'buyerName' => $buyer->name,
            'buyerEmail' => $buyer->email,
            'expired' => $expiredHours,
            'feeDirection' => config('ipaymu.fee_direction', 'MERCHANT'),
        ];

        if ($buyer->phone) {
            $body['buyerPhone'] = $buyer->phone;
        }

        $response = $this->post('/api/v2/payment', $body);
        $url = data_get($response, 'Data.Url');

        if ((int) data_get($response, 'Status') !== 200 || ! $url) {
            throw new RuntimeException('iPaymu rejected the payment request: ' . json_encode(Arr::only($response, ['Status', 'Message'])));
        }

        return ['session_id' => (string) data_get($response, 'Data.SessionID'), 'url' => $url];
    }

    /**
     * Ask iPaymu directly for a transaction. Used to confirm a callback before granting tickets.
     * Throws on any API failure so the caller can answer 5xx and let iPaymu retry.
     */
    public function checkTransaction(string|int $transactionId): array
    {
        $response = $this->post('/api/v2/transaction', ['transactionId' => (string) $transactionId]);

        if ((int) data_get($response, 'Status') !== 200 || ! is_array(data_get($response, 'Data'))) {
            throw new RuntimeException('iPaymu transaction check failed: ' . json_encode(Arr::only($response, ['Status', 'Message'])));
        }

        return $response['Data'];
    }

    /**
     * Callback signature = HMAC-SHA256(json(ksort(payload without signature)), VA).
     * The docs describe type normalisation for form-encoded bodies, so both the payload as
     * received and its normalised form are tried. Either one matching proves the sender knows the VA.
     */
    public function validCallbackSignature(array $payload, ?string $signature): bool
    {
        $signature = strtolower(trim((string) $signature));
        $secret = (string) config('ipaymu.va');

        if ($signature === '' || $secret === '') {
            return false;
        }

        unset($payload['signature']);

        foreach ([$payload, $this->normalise($payload)] as $candidate) {
            ksort($candidate);
            $expected = hash_hmac('sha256', json_encode($candidate), $secret); // default flags escape "/" as "\/" like the docs require

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------

    private function normalise(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if ($key === 'is_escrow') {
                $out[$key] = in_array($value, [true, 'true', '1', 1], true);
            } elseif (in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'], true)) {
                $out[$key] = (int) $value;
            } elseif ($key === 'additional_info') {
                $out[$key] = $value === '[]' ? [] : $value;
            } elseif (is_array($value) || $value === null) {
                $out[$key] = $value;
            } else {
                $out[$key] = (string) $value;
            }
        }

        if (! array_key_exists('additional_info', $out)) {
            $out['additional_info'] = [];
        }

        return $out;
    }

    private function withOrder(string $url, Order $order): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'order=' . urlencode($order->order_number);
    }

    /** Signed POST. The signature is computed over the exact JSON string that is sent. */
    private function post(string $path, array $body): array
    {
        $va = (string) config('ipaymu.va');
        $apiKey = (string) config('ipaymu.api_key');

        if ($va === '' || $apiKey === '') {
            throw new RuntimeException('iPaymu credentials are not configured (IPAYMU_VA / IPAYMU_API_KEY).');
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stringToSign = 'POST:' . $va . ':' . strtolower(hash('sha256', $json)) . ':' . $apiKey;

        $response = Http::withHeaders([
            'va' => $va,
            'signature' => hash_hmac('sha256', $stringToSign, $apiKey),
            'timestamp' => now()->format('YmdHis'),
        ])
            ->acceptJson()
            ->timeout((int) config('ipaymu.timeout', 20))
            ->withBody($json, 'application/json')
            ->post(config('ipaymu.base_url') . $path);

        return $response->json() ?? [];
    }
}
