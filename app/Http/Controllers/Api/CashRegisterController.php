<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Order;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CashRegister::with(['branch', 'openedBy', 'closedBy']);

        $userBranch = $request->user()?->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('opened_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('opened_at', '<=', $request->date_to);

        $perPage = $request->get('per_page', 15);
        return response()->json([
            'success' => true,
            'message' => 'Cash registers retrieved successfully',
            'data'    => $query->orderByDesc('opened_at')->paginate($perPage),
        ]);
    }

    /** Currently open shift for the calling user's branch (or 404). */
    public function current(Request $request): JsonResponse
    {
        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $open = CashRegister::with(['branch', 'openedBy'])
            ->where('status', 'open')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('opened_at')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $open ? 'Open shift found' : 'No open shift',
            'data'    => $open,
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id'    => 'nullable|exists:branches,id',
            'opening_cash' => 'required|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');

        // Only one open shift per branch at a time.
        $existing = CashRegister::where('status', 'open')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A shift is already open for this branch. Close it before opening a new one.',
                'data'    => $existing,
            ], 422);
        }

        $reg = CashRegister::create([
            'branch_id'         => $branchId,
            'opened_by_user_id' => $request->user()->id,
            'opened_at'         => now(),
            'opening_cash'      => $request->input('opening_cash'),
            'notes'             => $request->input('notes'),
            'status'            => 'open',
        ]);

        Audit::log('cash_register.open', $reg, ['opening_cash' => $reg->opening_cash]);

        return response()->json([
            'success' => true,
            'message' => 'Shift opened',
            'data'    => $reg->load(['branch', 'openedBy']),
        ], 201);
    }

    public function close(Request $request, CashRegister $cashRegister): JsonResponse
    {
        if ($cashRegister->status !== 'open') {
            return response()->json(['success'=>false,'message'=>'Shift already closed.','data'=>null], 422);
        }

        $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes'       => 'nullable|string|max:500',
        ]);

        $cashSales = (float) Order::where('branch_id', $cashRegister->branch_id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->where('created_at', '>=', $cashRegister->opened_at)
            ->sum('paid_amount');

        $expected = (float) $cashRegister->opening_cash + $cashSales;
        $actual   = (float) $request->input('actual_cash');

        $cashRegister->update([
            'closed_by_user_id' => $request->user()->id,
            'closed_at'         => now(),
            'actual_cash'       => $actual,
            'expected_cash'     => $expected,
            'cash_difference'   => $actual - $expected,
            'notes'             => trim(($cashRegister->notes ? $cashRegister->notes . "\n" : '') . ($request->input('notes') ?? '')),
            'status'            => 'closed',
        ]);

        Audit::log('cash_register.close', $cashRegister, [
            'expected_cash' => $expected,
            'actual_cash'   => $actual,
            'difference'    => $actual - $expected,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift closed (Z-Report ready)',
            'data'    => $this->zReportPayload($cashRegister->fresh(['branch', 'openedBy', 'closedBy'])),
        ]);
    }

    /** Full Z-Report for a closed shift. */
    public function zReport(CashRegister $cashRegister): JsonResponse
    {
        $cashRegister->load(['branch', 'openedBy', 'closedBy']);
        return response()->json([
            'success' => true,
            'message' => 'Z-Report',
            'data'    => $this->zReportPayload($cashRegister),
        ]);
    }

    private function zReportPayload(CashRegister $r): array
    {
        $orderQ = Order::where('branch_id', $r->branch_id)
            ->where('created_at', '>=', $r->opened_at);
        if ($r->closed_at) $orderQ->where('created_at', '<=', $r->closed_at);

        $byMethod = (clone $orderQ)
            ->where('status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(paid_amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn($r) => [
                'method' => $r->payment_method,
                'orders' => (int) $r->orders,
                'total'  => (float) $r->total,
            ]);

        return [
            'register'        => $r,
            'orders_count'    => (clone $orderQ)->where('status', 'completed')->count(),
            'voided_count'    => (clone $orderQ)->where('status', 'voided')->count(),
            'refunded_count'  => (clone $orderQ)->where('status', 'refunded')->count(),
            'gross_sales'     => (float) (clone $orderQ)->where('status', 'completed')->sum('total_amount'),
            'total_tax'       => (float) (clone $orderQ)->where('status', 'completed')->sum('tax_amount'),
            'total_service'   => (float) (clone $orderQ)->where('status', 'completed')->sum('service_charge_amount'),
            'total_discount'  => (float) (clone $orderQ)->where('status', 'completed')->sum('discount_amount'),
            'refunded_amount' => (float) (clone $orderQ)->where('status', 'refunded')->sum('refunded_amount'),
            'by_payment'      => $byMethod,
        ];
    }
}
