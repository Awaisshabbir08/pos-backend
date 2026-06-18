<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Coupon::query();
        if ($request->filled('search'))  $q->where('code', 'like', '%'.$request->search.'%');
        if ($request->filled('status'))  $q->where('status', $request->status);
        $perPage = $request->get('per_page', 15);
        return response()->json(['success'=>true,'message'=>'Coupons','data'=>$q->orderByDesc('created_at')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request);
        $data['code'] = strtoupper($data['code']);
        $coupon = Coupon::create($data);
        return response()->json(['success'=>true,'message'=>'Coupon created','data'=>$coupon], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json(['success'=>true,'message'=>'Coupon','data'=>$coupon]);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $this->validate($request, $coupon->id);
        if (isset($data['code'])) $data['code'] = strtoupper($data['code']);
        $coupon->update($data);
        return response()->json(['success'=>true,'message'=>'Coupon updated','data'=>$coupon->fresh()]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json(['success'=>true,'message'=>'Coupon deleted','data'=>null]);
    }

    /** Validate a coupon for a given subtotal (used by POS before checkout). */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();
        if (!$coupon) {
            return response()->json(['success'=>false,'message'=>'Coupon code not found.','data'=>null], 404);
        }

        $reason = $coupon->reasonInvalidFor((float) $request->subtotal);
        if ($reason) {
            return response()->json(['success'=>false,'message'=>$reason,'data'=>null], 422);
        }

        $discount = $coupon->computeDiscount((float) $request->subtotal);

        return response()->json([
            'success' => true,
            'message' => 'Coupon valid',
            'data'    => [
                'coupon'   => $coupon,
                'discount' => $discount,
            ],
        ]);
    }

    private function validate(Request $request, ?int $id = null): array
    {
        // Use the active tenant context (correct for super-admin viewing-as too)
        // rather than the logged-in user's own tenant_id, which is null for super-admin.
        $tenantId = TenantContext::id();
        return $request->validate([
            'code'             => ['required', 'string', 'max:64', Rule::unique('coupons', 'code')->where('tenant_id', $tenantId)->ignore($id)],
            'description'      => 'nullable|string|max:255',
            'discount_type'    => 'required|in:percent,fixed',
            'discount_value'   => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'valid_from'       => 'nullable|date',
            'valid_until'      => 'nullable|date|after_or_equal:valid_from',
            'status'           => 'nullable|in:active,inactive',
        ]);
    }
}
