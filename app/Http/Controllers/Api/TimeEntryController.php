<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = TimeEntry::with(['user', 'branch']);
        if ($request->filled('user_id'))   $q->where('user_id', $request->user_id);
        if ($request->filled('branch_id')) $q->where('branch_id', $request->branch_id);
        if ($request->filled('date_from')) $q->whereDate('clock_in', '>=', $request->date_from);
        if ($request->filled('date_to'))   $q->whereDate('clock_in', '<=', $request->date_to);
        $perPage = $request->get('per_page', 25);
        return response()->json(['success'=>true,'message'=>'Time entries','data'=>$q->orderByDesc('clock_in')->paginate($perPage)]);
    }

    /** Currently open shift for the logged-in user. */
    public function current(Request $request): JsonResponse
    {
        $open = TimeEntry::where('user_id', $request->user()->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();
        return response()->json(['success'=>true,'message'=>$open?'Open':'None','data'=>$open]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $existing = TimeEntry::where('user_id', $request->user()->id)->whereNull('clock_out')->first();
        if ($existing) {
            return response()->json(['success'=>false,'message'=>'You already have an open shift.','data'=>$existing], 422);
        }
        $entry = TimeEntry::create([
            'user_id'   => $request->user()->id,
            'branch_id' => $request->user()->branch_id,
            'clock_in'  => now(),
            'notes'     => $request->input('notes'),
        ]);
        return response()->json(['success'=>true,'message'=>'Clocked in','data'=>$entry], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $entry = TimeEntry::where('user_id', $request->user()->id)->whereNull('clock_out')->first();
        if (!$entry) {
            return response()->json(['success'=>false,'message'=>'No open shift to clock out from.','data'=>null], 422);
        }
        $out = now();
        $entry->update([
            'clock_out'      => $out,
            'minutes_worked' => $entry->clock_in->diffInMinutes($out),
            'notes'          => trim(($entry->notes ? $entry->notes."\n" : '') . ($request->input('notes') ?? '')),
        ]);
        return response()->json(['success'=>true,'message'=>'Clocked out','data'=>$entry->fresh()]);
    }
}
