<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data'    => $customers,
        ]);
    }

    /**
     * Fuzzy lookup for the POS phone search.
     * Returns up to 10 customers whose phone or name contains the query,
     * sorted with exact phone match first.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('phone', $request->get('q', '')));

        if ($term === '') {
            return response()->json([
                'success' => true,
                'message' => 'Provide a phone or q parameter',
                'data'    => [],
            ]);
        }

        $customers = Customer::query()
            ->where(function ($q) use ($term) {
                $q->where('phone', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%");
            })
            ->orderByRaw('CASE WHEN phone = ? THEN 0 ELSE 1 END', [$term])
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Customer matches',
            'data'    => $customers,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data'    => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load('orders');

        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data'    => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data'    => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
            'data'    => null,
        ]);
    }
}
