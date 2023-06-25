<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Helpers\Cart;
use App\Mail\NewOrderEmail;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xendit\Xendit;

class CheckoutController extends Controller {
    public function checkout(Request $request) {
        /** @var \App\Models\User $user */
        $user = $request->user();

        Xendit::setApiKey(env('XENDIT_SECRET_KEY'));

        [$products, $cartItems] = Cart::getProductsAndCartItems();

        $orderItems = [];
        $lineItems = [];
        $totalPrice = 0;
        foreach ($products as $product) {
            $quantity = $cartItems[$product->id]['quantity'];
            $totalPrice += $product->price * $quantity;
            $lineItems[] = [
                'name' => $product->title,
                'price' => $product->price * 100,
                'quantity' => $quantity,
            ];
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ];
        }

        $invoice = \Xendit\Invoice::create([
            'external_id' => uniqid(), // Generate a unique ID for each transaction
            'payer_email' => $user->email,
            'description' => 'Order Payment',
            'amount' => $totalPrice,
            'success_redirect_url' => route('checkout.success', [], true),
            'failure_redirect_url' => route('checkout.failure', [], true),
            'line_items' => $lineItems,
        ]);

        // Create Order
        $orderData = [
            'total_price' => $totalPrice,
            'status' => OrderStatus::Unpaid,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
        $order = Order::create($orderData);

        // Create Order Items
        foreach ($orderItems as $orderItem) {
            $orderItem['order_id'] = $order->id;
            OrderItem::create($orderItem);
        }

        // Create Payment
        $paymentData = [
            'order_id' => $order->id,
            'amount' => $totalPrice,
            'status' => PaymentStatus::Pending,
            'type' => 'ewallet', // Indicate the payment type as e-wallet
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'invoice_id' => $invoice['id'],
        ];
        Payment::create($paymentData);

        CartItem::where(['user_id' => $user->id])->delete();

        return redirect($invoice['invoice_url']);
    }

    public function success(Request $request) {
        /** @var \App\Models\User $user */
        $user = $request->user();
        Xendit::setApiKey(env('XENDIT_SECRET_KEY'));

        try {
            $invoiceId = $request->query('invoice_id');
            // $invoiceId = $request->get('invoice_id');

            $payment = Payment::query()
                ->where(['invoice_id' => $invoiceId])
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
                ->first();

            if (!$payment) {
                throw new NotFoundHttpException();
            }

            if ($payment->status === PaymentStatus::Pending->value) {
                $this->updateOrderAndPayment($payment);
            }

            $customer = $user;
            return view('checkout.success', compact('customer'));
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return view('checkout.failure', ['message' => $e->getMessage()]);
        }
    }

    public function failure(Request $request) {
        return view('checkout.failure', ['message' => 'Payment failed.']);
    }

    public function checkoutOrder(Order $order, Request $request) {
        $user = $request->user();

        Xendit::setApiKey(env('XENDIT_SECRET_KEY'));

        $lineItems = [];
        $totalPrice = 0;

        foreach ($order->items as $item) {
            $lineItems[] = [
                'name' => $item->product->title,
                'price' => $item->unit_price * 100,
                'quantity' => $item->quantity,
            ];

            $totalPrice += $item->unit_price * $item->quantity;
        }

        $invoice = \Xendit\Invoice::create([
            'external_id' => uniqid(),
            'payer_email' => $user->email,
            'description' => 'Order Payment',
            'amount' => $totalPrice,
            'success_redirect_url' => route('checkout.success', [], true),
            'failure_redirect_url' => route('checkout.failure', [], true),
            'line_items' => $lineItems,
        ]);

        $order->payment->invoice_id = $invoice['id'];
        $order->payment->save();

        return redirect($invoice['invoice_url']);
    }



    // public function webhook(Request $request) {
    //     $payload = $request->getContent();
    //     $signature = $request->header('X-Xendit-Signature');
    //     $xendit = new \Xendit\Xendit(\Xendit\XenditClient(env('XENDIT_SECRET_KEY')));


    //     $isValidSignature = $xendit->validatesSignature($payload, $signature);

    //     if (!$isValidSignature) {
    //         return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
    //     }

    //     $event = json_decode($payload, true);

    //     switch ($event['type']) {
    //         case 'INVOICE_PAID':
    //             $invoiceId = $event['data']['id'];
    //             $payment = Payment::where('invoice_id', $invoiceId)->first();

    //             if ($payment) {
    //                 $this->updateOrderAndPayment($payment);
    //             }
    //             break;
    //             // Add other event types handling if necessary

    //         default:
    //             return response()->json(['success' => false, 'message' => 'Unknown event type.'], 400);
    //     }

    //     return response()->json(['success' => true], 200);
    // }

    private function updateOrderAndPayment(Payment $payment) {
        $payment->status = PaymentStatus::Paid->value;
        $payment->update();

        $order = $payment->order;

        $order->status = OrderStatus::Paid->value;
        $order->update();

        $adminUsers = User::where('is_admin', 1)->get();

        foreach ([...$adminUsers, $order->user] as $user) {
            Mail::to($user)->send(new NewOrderEmail($order, (bool)$user->is_admin));
        }
    }
}
