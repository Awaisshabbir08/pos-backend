<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Requests\Api\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'waiter', 'table', 'rider', 'orderItems.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $query->where('order_number', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data'    => $orders,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $items = $request->input('items');
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$product->name}. Available: {$product->stock_quantity}",
                        'data'    => null,
                    ], 422);
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal'   => $subtotal,
                ];
            }

            $taxAmount      = $request->input('tax_amount', 0);
            $discountAmount = $request->input('discount_amount', 0);
            $finalTotal     = $totalAmount + $taxAmount - $discountAmount;
            $paidAmount     = $request->input('paid_amount');
            $changeAmount   = max(0, $paidAmount - $finalTotal);

            $serviceType = $request->input('service_type', 'dine_in');

            $order = Order::create([
                'customer_id'     => $request->input('customer_id'),
                'waiter_id'       => $serviceType !== 'delivery' ? $request->input('waiter_id') : null,
                'table_id'        => $serviceType === 'dine_in' ? $request->input('table_id') : null,
                'rider_id'        => $serviceType === 'delivery' ? $request->input('rider_id') : null,
                'service_type'    => $serviceType,
                'order_number'    => $this->generateOrderNumber(),
                'total_amount'    => $finalTotal,
                'tax_amount'      => $taxAmount,
                'discount_amount' => $discountAmount,
                'paid_amount'     => $paidAmount,
                'change_amount'   => $changeAmount,
                'payment_method'  => $request->input('payment_method'),
                'status'          => 'completed',
                'notes'           => $request->input('notes'),
            ]);

            foreach ($orderItemsData as &$itemData) {
                $itemData['order_id'] = $order->id;
            }

            $order->orderItems()->createMany($orderItemsData);

            // Deduct stock
            foreach ($items as $item) {
                Product::where('id', $item['product_id'])
                    ->decrement('stock_quantity', $item['quantity']);
            }

            DB::commit();

            $order->load(['customer', 'waiter', 'table', 'rider', 'orderItems.product']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data'    => $order,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['customer', 'waiter', 'table', 'rider', 'orderItems.product']);

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data'    => $order,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status;
        $order->update($request->validated());

        // Restore stock if order is cancelled
        if ($request->input('status') === 'cancelled' && $previousStatus !== 'cancelled') {
            foreach ($order->orderItems as $item) {
                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $item->quantity);
            }
        }

        $order->load(['customer', 'waiter', 'table', 'rider', 'orderItems.product']);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data'    => $order,
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a completed order',
                'data'    => null,
            ], 422);
        }

        $order->orderItems()->delete();
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
            'data'    => null,
        ]);
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $last = Order::where('order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_number');

        $nextNum = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
