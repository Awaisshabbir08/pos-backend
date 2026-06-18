<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbrSubmission;
use App\Services\FbrService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FbrSubmissionController extends Controller
{
    public function __construct(private FbrService $fbr) {}

    public function index(Request $request): JsonResponse
    {
        $q = FbrSubmission::with(['order:id,order_number,total_amount,status,created_at'])
            ->orderByDesc('created_at');
        if ($request->filled('status'))   $q->where('status', $request->status);
        if ($request->filled('order_id')) $q->where('order_id', $request->order_id);
        return response()->json([
            'success' => true,
            'message' => 'FBR submissions',
            'data'    => $q->paginate($request->get('per_page', 25)),
        ]);
    }

    public function show(FbrSubmission $fbrSubmission): JsonResponse
    {
        $fbrSubmission->load('order');
        return response()->json(['success'=>true,'message'=>'FBR submission','data'=>$fbrSubmission]);
    }

    public function retry(FbrSubmission $fbrSubmission): JsonResponse
    {
        $new = $this->fbr->retry($fbrSubmission);
        Audit::log('fbr.retry', $new, ['from_submission' => $fbrSubmission->id]);
        return response()->json(['success'=>true,'message'=>'Submission retried','data'=>$new]);
    }
}
