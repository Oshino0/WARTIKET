<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const VA = '0000001234';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ipaymu.va' => self::VA,
            'ipaymu.api_key' => 'test-key',
            'ipaymu.base_url' => 'https://sandbox.ipaymu.com',
        ]);
    }

    /** @return array{0: Order, 1: Payment, 2: TicketType} a pending order of 2 x Rp 100.000 with an open payment */
    private function pendingOrder(int $quota = 10): array
    {
        $user = User::create(['name' => 'Budi', 'email' => 'budi@example.test', 'password' => 'password12', 'role' => 'customer', 'is_active' => true]);
        $category = Category::create(['name' => 'Konser', 'slug' => 'konser', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Fest', 'slug' => 'fest', 'status' => Product::PUBLISHED]);
        $type = TicketType::create([
            'product_id' => $product->id, 'name' => 'Reguler', 'price' => 100000, 'quota' => $quota,
            'min_per_order' => 1, 'max_per_order' => 10, 'status' => 'active',
        ]);

        $order = app(OrderService::class)->create($user, [$type->id => 2]);
        $payment = Payment::create([
            'order_id' => $order->id, 'reference_id' => $order->order_number . '-1',
            'amount' => $order->total, 'status' => Payment::PENDING, 'provider' => 'ipaymu',
        ]);

        return [$order, $payment, $type];
    }

    private function fakeCheck(int $status = 1, int $amount = 200000): void
    {
        Http::fake(['*/api/v2/transaction' => Http::response([
            'Status' => 200, 'Success' => true, 'Message' => 'success',
            'Data' => ['TransactionId' => 12345, 'Status' => $status, 'Amount' => $amount, 'ReferenceId' => null],
        ])]);
    }

    private function sendCallback(Payment $payment, bool $validSignature = true, array $override = [])
    {
        $payload = array_merge([
            'trx_id' => '12345', 'sid' => 'SESSION1', 'reference_id' => $payment->reference_id,
            'status' => 'berhasil', 'status_code' => '1', 'transaction_status_code' => '1',
            'amount' => (string) (int) $payment->amount, 'via' => 'va', 'channel' => 'bca',
        ], $override);

        $sorted = $payload;
        ksort($sorted);
        $signature = hash_hmac('sha256', json_encode($sorted), $validSignature ? self::VA : 'wrong-secret');

        return $this->call('POST', '/payment/ipaymu/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload));
    }

    public function test_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        [$order, $payment] = $this->pendingOrder();
        $this->fakeCheck();

        $this->sendCallback($payment, validSignature: false)->assertStatus(400);

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);
    }

    public function test_valid_callback_pays_the_order_and_turns_the_reservation_into_a_sale(): void
    {
        [$order, $payment, $type] = $this->pendingOrder();
        $this->fakeCheck();

        $this->sendCallback($payment)->assertOk();

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(Payment::PAID, $payment->fresh()->status);
        $this->assertSame(2, $type->fresh()->sold);
        $this->assertSame(0, $type->fresh()->reserved);
    }

    public function test_repeated_callbacks_do_not_double_count_sales(): void
    {
        [, $payment, $type] = $this->pendingOrder();
        $this->fakeCheck();

        $this->sendCallback($payment)->assertOk();
        $this->sendCallback($payment)->assertOk()->assertJson(['result' => 'duplicate']);

        $this->assertSame(2, $type->fresh()->sold);
    }

    public function test_amount_mismatch_with_i_paymu_does_not_pay_the_order(): void
    {
        [$order, $payment, $type] = $this->pendingOrder();
        $this->fakeCheck(amount: 1000);

        $this->sendCallback($payment)->assertOk()->assertJson(['result' => 'mismatch']);

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertTrue($payment->fresh()->needs_review);
        $this->assertSame(0, $type->fresh()->sold);
    }

    public function test_unreachable_i_paymu_makes_the_callback_retryable(): void
    {
        [$order, $payment] = $this->pendingOrder();
        Http::fake(['*/api/v2/transaction' => Http::response([], 500)]);

        $this->sendCallback($payment)->assertStatus(503);

        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_late_payment_is_honoured_when_stock_is_still_free(): void
    {
        [$order, $payment, $type] = $this->pendingOrder();
        app(OrderService::class)->release($order, Order::EXPIRED);
        $this->fakeCheck();

        $this->sendCallback($payment)->assertOk()->assertJson(['result' => 'paid_late']);

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(2, $type->fresh()->sold);
    }

    public function test_late_payment_without_stock_is_kept_for_manual_refund(): void
    {
        [$order, $payment, $type] = $this->pendingOrder();
        app(OrderService::class)->release($order, Order::EXPIRED);
        $type->update(['quota' => 1]); // someone else took the seats in the meantime
        $this->fakeCheck();

        $this->sendCallback($payment)->assertOk()->assertJson(['result' => 'paid_unfulfillable']);

        $this->assertSame(Order::EXPIRED, $order->fresh()->status);
        $this->assertSame(Payment::PAID, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->needs_review);
        $this->assertSame(0, $type->fresh()->sold);
    }
}
