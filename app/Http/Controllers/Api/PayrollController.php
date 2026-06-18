<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request): JsonResponse
    {
        $q = Payslip::with(['user:id,name,email', 'generatedBy:id,name'])
            ->orderByDesc('period_end')
            ->orderBy('user_id');
        if ($request->filled('user_id'))      $q->where('user_id', $request->user_id);
        if ($request->filled('status'))       $q->where('status', $request->status);
        if ($request->filled('period_start')) $q->whereDate('period_start', '>=', $request->period_start);
        if ($request->filled('period_end'))   $q->whereDate('period_end',   '<=', $request->period_end);
        return response()->json([
            'success' => true,
            'message' => 'Payslips',
            'data'    => $q->paginate($request->get('per_page', 25)),
        ]);
    }

    public function show(Payslip $payslip): JsonResponse
    {
        $payslip->load(['user:id,name,email,pay_type,hourly_rate,monthly_salary', 'generatedBy:id,name']);
        return response()->json(['success'=>true,'message'=>'Payslip','data'=>$payslip]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);
        $result = $this->payroll->generateForPeriod(
            Carbon::parse($data['period_start']),
            Carbon::parse($data['period_end']),
            $request->user()->id
        );
        Audit::log('payroll.generate', null, [
            'period_start' => $data['period_start'],
            'period_end'   => $data['period_end'],
            'generated'    => $result['generated'],
            'skipped'      => $result['skipped'],
        ]);
        return response()->json([
            'success' => true,
            'message' => "Generated {$result['generated']} payslip(s); skipped {$result['skipped']} non-payable user(s).",
            'data'    => $result,
        ], 201);
    }

    public function applyDeductions(Request $request, Payslip $payslip): JsonResponse
    {
        $data = $request->validate([
            'deductions' => 'required|numeric|min:0',
            'notes'      => 'nullable|string|max:1000',
        ]);
        $this->payroll->applyDeductions($payslip, (float) $data['deductions'], $data['notes'] ?? null);
        return response()->json(['success'=>true,'message'=>'Deductions applied','data'=>$payslip->fresh()]);
    }

    public function finalize(Payslip $payslip): JsonResponse
    {
        if ($payslip->status === 'paid') {
            return response()->json(['success'=>false,'message'=>'Already paid — cannot finalize.','data'=>null], 422);
        }
        $this->payroll->finalize($payslip);
        Audit::log('payroll.finalize', $payslip);
        return response()->json(['success'=>true,'message'=>'Payslip finalized','data'=>$payslip->fresh()]);
    }

    public function markPaid(Payslip $payslip): JsonResponse
    {
        $this->payroll->markPaid($payslip);
        Audit::log('payroll.paid', $payslip);
        return response()->json(['success'=>true,'message'=>'Payslip marked paid','data'=>$payslip->fresh()]);
    }

    public function destroy(Payslip $payslip): JsonResponse
    {
        if ($payslip->status === 'paid') {
            return response()->json(['success'=>false,'message'=>'Cannot delete a paid payslip.','data'=>null], 422);
        }
        $payslip->delete();
        return response()->json(['success'=>true,'message'=>'Payslip deleted','data'=>null]);
    }
}
