<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Electronics',
                'description' => 'Electronic devices, gadgets, and accessories',
                'status'      => 'active',
            ],
            [
                'name'        => 'Beverages',
                'description' => 'Drinks including water, juice, soda, and coffee',
                'status'      => 'active',
            ],
            [
                'name'        => 'Snacks',
                'description' => 'Chips, crackers, nuts, and other snack foods',
                'status'      => 'active',
            ],
            [
                'name'        => 'Dairy',
                'description' => 'Milk, cheese, butter, yogurt, and dairy products',
                'status'      => 'active',
            ],
            [
                'name'        => 'Bakery',
                'description' => 'Bread, pastries, cakes, and baked goods',
                'status'      => 'active',
            ],
            [
                'name'        => 'Meat & Poultry',
                'description' => 'Fresh and frozen meats, chicken, and poultry',
                'status'      => 'active',
            ],
            [
                'name'        => 'Fruits & Vegetables',
                'description' => 'Fresh fruits and vegetables',
                'status'      => 'active',
            ],
            [
                'name'        => 'Personal Care',
                'description' => 'Hygiene, grooming, and personal care products',
                'status'      => 'active',
            ],
            [
                'name'        => 'Cleaning Supplies',
                'description' => 'Household cleaning products and supplies',
                'status'      => 'active',
            ],
            [
                'name'        => 'Stationery',
                'description' => 'Pens, notebooks, paper, and office supplies',
                'status'      => 'active',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
