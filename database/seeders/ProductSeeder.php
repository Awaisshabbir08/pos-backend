<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryMap = Category::pluck('id', 'name');

        $products = [
            // Electronics
            [
                'category_id'    => $categoryMap['Electronics'],
                'name'           => 'USB-C Charging Cable 2m',
                'sku'            => 'ELEC-001',
                'description'    => 'Durable braided USB-C charging cable, 2 meter length',
                'price'          => 12.99,
                'stock_quantity' => 85,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Electronics'],
                'name'           => 'Wireless Earbuds',
                'sku'            => 'ELEC-002',
                'description'    => 'Bluetooth 5.0 wireless earbuds with charging case',
                'price'          => 49.99,
                'stock_quantity' => 32,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Electronics'],
                'name'           => 'Power Bank 10000mAh',
                'sku'            => 'ELEC-003',
                'description'    => 'Portable power bank with dual USB output',
                'price'          => 34.99,
                'stock_quantity' => 7,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Electronics'],
                'name'           => 'Laptop Stand Adjustable',
                'sku'            => 'ELEC-004',
                'description'    => 'Ergonomic aluminum laptop stand, adjustable height',
                'price'          => 28.99,
                'stock_quantity' => 18,
                'status'         => 'active',
            ],

            // Beverages
            [
                'category_id'    => $categoryMap['Beverages'],
                'name'           => 'Mineral Water 500ml',
                'sku'            => 'BEV-001',
                'description'    => 'Natural mineral water, 500ml bottle',
                'price'          => 0.99,
                'stock_quantity' => 200,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Beverages'],
                'name'           => 'Orange Juice 1L',
                'sku'            => 'BEV-002',
                'description'    => '100% pure squeezed orange juice, 1 liter carton',
                'price'          => 3.49,
                'stock_quantity' => 60,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Beverages'],
                'name'           => 'Cola Can 330ml',
                'sku'            => 'BEV-003',
                'description'    => 'Classic cola flavored carbonated drink, 330ml can',
                'price'          => 1.29,
                'stock_quantity' => 150,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Beverages'],
                'name'           => 'Green Tea 250ml',
                'sku'            => 'BEV-004',
                'description'    => 'Lightly sweetened green tea, 250ml bottle',
                'price'          => 1.79,
                'stock_quantity' => 4,
                'status'         => 'active',
            ],

            // Snacks
            [
                'category_id'    => $categoryMap['Snacks'],
                'name'           => 'Potato Chips Original 150g',
                'sku'            => 'SNK-001',
                'description'    => 'Crispy salted potato chips, 150g bag',
                'price'          => 2.49,
                'stock_quantity' => 95,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Snacks'],
                'name'           => 'Mixed Nuts 200g',
                'sku'            => 'SNK-002',
                'description'    => 'Roasted mixed nuts including almonds, cashews, and peanuts',
                'price'          => 5.99,
                'stock_quantity' => 40,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Snacks'],
                'name'           => 'Chocolate Bar 100g',
                'sku'            => 'SNK-003',
                'description'    => 'Milk chocolate bar, 100g',
                'price'          => 1.99,
                'stock_quantity' => 8,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Snacks'],
                'name'           => 'Crackers Whole Wheat 250g',
                'sku'            => 'SNK-004',
                'description'    => 'Whole wheat crackers, lightly salted',
                'price'          => 3.29,
                'stock_quantity' => 55,
                'status'         => 'active',
            ],

            // Dairy
            [
                'category_id'    => $categoryMap['Dairy'],
                'name'           => 'Full Cream Milk 1L',
                'sku'            => 'DAI-001',
                'description'    => 'Fresh full cream pasteurized milk, 1 liter',
                'price'          => 1.89,
                'stock_quantity' => 80,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Dairy'],
                'name'           => 'Cheddar Cheese 250g',
                'sku'            => 'DAI-002',
                'description'    => 'Mature cheddar cheese block, 250g',
                'price'          => 4.49,
                'stock_quantity' => 6,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Dairy'],
                'name'           => 'Natural Yogurt 500g',
                'sku'            => 'DAI-003',
                'description'    => 'Plain natural yogurt, no added sugar, 500g',
                'price'          => 2.29,
                'stock_quantity' => 35,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Dairy'],
                'name'           => 'Unsalted Butter 250g',
                'sku'            => 'DAI-004',
                'description'    => 'Pure unsalted butter, 250g block',
                'price'          => 3.19,
                'stock_quantity' => 28,
                'status'         => 'active',
            ],

            // Bakery
            [
                'category_id'    => $categoryMap['Bakery'],
                'name'           => 'White Sandwich Bread 600g',
                'sku'            => 'BAK-001',
                'description'    => 'Soft white sandwich bread, 600g loaf',
                'price'          => 2.19,
                'stock_quantity' => 50,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Bakery'],
                'name'           => 'Croissant Butter 6-pack',
                'sku'            => 'BAK-002',
                'description'    => 'Freshly baked butter croissants, pack of 6',
                'price'          => 4.99,
                'stock_quantity' => 15,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Bakery'],
                'name'           => 'Whole Wheat Bread 700g',
                'sku'            => 'BAK-003',
                'description'    => 'Nutritious whole wheat bread, 700g loaf',
                'price'          => 2.79,
                'stock_quantity' => 3,
                'status'         => 'active',
            ],

            // Meat & Poultry
            [
                'category_id'    => $categoryMap['Meat & Poultry'],
                'name'           => 'Chicken Breast 500g',
                'sku'            => 'MEAT-001',
                'description'    => 'Fresh boneless skinless chicken breast, 500g',
                'price'          => 6.99,
                'stock_quantity' => 25,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Meat & Poultry'],
                'name'           => 'Beef Mince 500g',
                'sku'            => 'MEAT-002',
                'description'    => 'Extra lean ground beef, 500g pack',
                'price'          => 8.49,
                'stock_quantity' => 20,
                'status'         => 'active',
            ],

            // Fruits & Vegetables
            [
                'category_id'    => $categoryMap['Fruits & Vegetables'],
                'name'           => 'Red Apple 1kg',
                'sku'            => 'FV-001',
                'description'    => 'Fresh red apples, 1kg bag',
                'price'          => 2.99,
                'stock_quantity' => 70,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Fruits & Vegetables'],
                'name'           => 'Banana 1kg',
                'sku'            => 'FV-002',
                'description'    => 'Fresh ripe bananas, approximately 1kg bunch',
                'price'          => 1.49,
                'stock_quantity' => 90,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Fruits & Vegetables'],
                'name'           => 'Baby Spinach 200g',
                'sku'            => 'FV-003',
                'description'    => 'Fresh baby spinach leaves, 200g bag',
                'price'          => 2.49,
                'stock_quantity' => 5,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Fruits & Vegetables'],
                'name'           => 'Tomatoes 500g',
                'sku'            => 'FV-004',
                'description'    => 'Fresh vine-ripened tomatoes, 500g',
                'price'          => 1.99,
                'stock_quantity' => 45,
                'status'         => 'active',
            ],

            // Personal Care
            [
                'category_id'    => $categoryMap['Personal Care'],
                'name'           => 'Shampoo 400ml',
                'sku'            => 'PC-001',
                'description'    => 'Moisturizing shampoo for all hair types, 400ml',
                'price'          => 5.49,
                'stock_quantity' => 30,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Personal Care'],
                'name'           => 'Toothpaste 150g',
                'sku'            => 'PC-002',
                'description'    => 'Whitening fluoride toothpaste, 150g tube',
                'price'          => 3.29,
                'stock_quantity' => 55,
                'status'         => 'active',
            ],

            // Cleaning Supplies
            [
                'category_id'    => $categoryMap['Cleaning Supplies'],
                'name'           => 'Dish Washing Liquid 750ml',
                'sku'            => 'CLN-001',
                'description'    => 'Anti-grease dish washing liquid, 750ml bottle',
                'price'          => 2.99,
                'stock_quantity' => 42,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Cleaning Supplies'],
                'name'           => 'Multi-Surface Spray 500ml',
                'sku'            => 'CLN-002',
                'description'    => 'All-purpose antibacterial spray cleaner, 500ml',
                'price'          => 3.99,
                'stock_quantity' => 2,
                'status'         => 'active',
            ],

            // Stationery
            [
                'category_id'    => $categoryMap['Stationery'],
                'name'           => 'Ball-point Pens 10-pack',
                'sku'            => 'STA-001',
                'description'    => 'Blue ink ball-point pens, pack of 10',
                'price'          => 3.49,
                'stock_quantity' => 60,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Stationery'],
                'name'           => 'A4 Notebook 200 Pages',
                'sku'            => 'STA-002',
                'description'    => 'Ruled A4 notebook with 200 pages, hardcover',
                'price'          => 5.99,
                'stock_quantity' => 38,
                'status'         => 'active',
            ],
            [
                'category_id'    => $categoryMap['Stationery'],
                'name'           => 'Highlighters 5-pack',
                'sku'            => 'STA-003',
                'description'    => 'Assorted color highlighter markers, pack of 5',
                'price'          => 4.49,
                'stock_quantity' => 22,
                'status'         => 'active',
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['sku' => $product['sku']], $product);
        }
    }
}
