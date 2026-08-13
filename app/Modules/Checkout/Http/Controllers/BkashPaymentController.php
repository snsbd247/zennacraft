<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Modules\Checkout\Exceptions\PaymentGatewayException;
use App\Modules\Checkout\Services\Payment\BkashPaymentClient;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;

/**
 * bKash redirects the customer's browser here after they pay (or cancel)
 * on bKash's own hosted page. This route is intentionally NOT behind
 * Laravel's `signed` middleware — bKash appends its own `paymentID` and
 * `status` query params, which would break a URL signature computed
 * before those existed. The `status` query param is treated as a hint
 * only; the real result always comes from executePayment() below (see
 * docs/courier-payment-providers.md).
 */
class BkashPaymentController extends Controller
{
    public function __construct(private BkashPaymentClient $bkash) {}

    public function callback(Request $request): RedirectResponse
    {
        $paymentId = (string) $request->query('paymentID', '');
        $order = $paymentId !== '' ? Order::where('payment_gateway_reference', $paymentId)->first() : null;

        if (! $order) {
            abort(404);
        }

        // Already settled (e.g. bKash redirected twice, or the customer
        // refreshed this page) — don't call executePayment a second time,
        // it would legitimately fail on an already-executed paymentID.
        if ($order->payment_status === 'paid') {
            return $this->toSuccess($order);
        }

        if ((string) $request->query('status', '') !== 'success') {
            $order->forceFill(['payment_status' => 'failed'])->save();

            return $this->toSuccess($order, 'Payment was not completed. You can retry from your invoice or contact us to arrange payment.');
        }

        try {
            $result = $this->bkash->executePayment($paymentId);
        } catch (PaymentGatewayException $exception) {
            logger()->warning('bKash execute payment failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);

            $order->forceFill(['payment_status' => 'failed'])->save();

            return $this->toSuccess($order, 'We could not confirm your bKash payment. Please contact us with your order number.');
        }

        if (! $result['success']) {
            $order->forceFill(['payment_status' => 'failed'])->save();

            return $this->toSuccess($order, 'bKash did not confirm this payment. You can retry from your invoice or contact us.');
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'payment_transaction_id' => $result['trx_id'],
            'paid_amount' => $result['amount'] ?? $order->total,
            'paid_by' => 'bkash',
        ])->save();

        return $this->toSuccess($order);
    }

    protected function toSuccess(Order $order, ?string $paymentError = null): RedirectResponse
    {
        $redirect = redirect()->to(URL::signedRoute('checkout.success', ['order' => $order->order_number]));

        return $paymentError ? $redirect->with('payment_error', $paymentError) : $redirect;
    }
}
