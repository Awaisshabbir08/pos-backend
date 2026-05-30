<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $products  = Product::where('status', 'active')->get();

        if ($products->isEmpty()) {
            return;
        }

        $statuses        = ['completed', 'completed', 'completed', 'pending', 'cancelled'];
        $paymentMethods  = ['cash', 'cash', 'card', 'card', 'other'];

        for ($i = 0; $i < 50; $i++) {
            // Random date in the last 30 days
            $daysAgo   = rand(0, 30);
            $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            // 20% chance of walk-in customer (null customer_id)
            $customerId = (rand(1, 100) <= 20) ? null : $customers->random()->id;

            $status        = $statuses[array_rand($statuses)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Create 1-5 order items
            $numItems      = rand(1, 5);
            $selectedItems = $products->random(min($numItems, $products->count()));
            $subtotal      = 0.0;

            $orderNumber = 'ORD-' . strtoupper(Str::random(8));

            $order = Order::create([
                'customer_id'     => $customerId,
                'order_number'    => $orderNumber,
                'total_amount'    => 0,
                'tax_amount'      => 0,
                'discount_amount' => 0,
                'paid_amount'     => 0,
                'change_amount'   => 0,
                'payment_method'  => $paymentMethod,
                'status'          => $status,
                'notes'           => null,
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            foreach ($selectedItems as $product) {
                $quantity  = rand(1, 5);
                $unitPrice = (float) $product->price;
                $itemTotal = $quantity * $unitPrice;
                $subtotal  += $itemTotal;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $itemTotal,
                ]);
            }

            // Calculate tax (8%) and totals
            $taxAmount      = round($subtotal * 0.08, 2);
            $totalAmount    = round($subtotal + $taxAmount, 2);
            $paidAmount     = ($status === 'completed') ? $totalAmount : 0.0;
            $changeAmount   = 0.0;

            $order->update([
                'total_amount'    => $totalAmount,
                'tax_amount'      => $taxAmount,
                'discount_amount' => 0,
                'paid_amount'     => $paidAmount,
                'change_amount'   => $changeAmount,
            ]);
        }
    }
}
