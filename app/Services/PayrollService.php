<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

/**
 * Aggregates time_entries into payslips for a pay period.
 *
 * Pay types:
 *   - hourly: pays minutes_worked * hourly_rate / 60
 *   - salary: pro-rates monthly_salary by (period days / month days)
 *   - none:   skipped entirely
 */
class PayrollService
{
    /**
     * Generate payslips for every payable user in the active tenant for the
     * given period. Idempotent per (user, period_start, period_end) — re-runs
     * update the draft in place instead of duplicating.
     *
     * @return array{generated:int, skipped:int, slips: array<int, Payslip>}
     */
    public function generateForPeriod(Carbon $periodStart, Carbon $periodEnd, ?int $generatedByUserId = null): array
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd   = $periodEnd->copy()->endOfDay();

        $generated = 0;
        $skipped   = 0;
        $slips     = [];

        $users = User::whereIn('pay_type', ['hourly', 'salary'])->get();
        foreach ($users as $user) {
            if (!$this->isPayable($user)) { $skipped++; continue; }

            $minutes = (int) TimeEntry::where('user_id', $user->id)
                ->whereNotNull('clock_out')
                ->where('clock_in', '>=', $periodStart)
                ->where('clock_in', '<=', $periodEnd)
                ->sum('minutes_worked');

            $hours = round($minutes / 60, 2);
            $gross = $this->computeGross($user, $minutes, $periodStart, $periodEnd);

            $payslip = Payslip::updateOrCreate(
                [
                    'user_id'      => $user->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end'   => $periodEnd->toDateString(),
                ],
                [
                    'pay_type'             => $user->pay_type,
                    'hourly_rate'          => $user->hourly_rate,
                    'monthly_salary'       => $user->monthly_salary,
                    'minutes_worked'       => $minutes,
                    'hours_worked'         => $hours,
                    'gross_amount'         => $gross,
                    'deductions'           => 0,
                    'net_amount'           => $gross,
                    'status'               => 'draft',
                    'generated_by_user_id' => $generatedByUserId,
                ]
            );
            $slips[] = $payslip;
            $generated++;
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'slips' => $slips];
    }

    public function markPaid(Payslip $slip): Payslip
    {
        $slip->update(['status' => 'paid', 'paid_at' => now()]);
        return $slip;
    }

    public function finalize(Payslip $slip): Payslip
    {
        $slip->update(['status' => 'finalized']);
        return $slip;
    }

    public function applyDeductions(Payslip $slip, float $deductions, ?string $notes = null): Payslip
    {
        $slip->update([
            'deductions' => $deductions,
            'net_amount' => max(0, (float)$slip->gross_amount - $deductions),
            'notes'      => $notes ?? $slip->notes,
        ]);
        return $slip;
    }

    private function isPayable(User $user): bool
    {
        if ($user->pay_type === 'hourly' && $user->hourly_rate > 0)   return true;
        if ($user->pay_type === 'salary' && $user->monthly_salary > 0) return true;
        return false;
    }

    private function computeGross(User $user, int $minutes, Carbon $start, Carbon $end): float
    {
        if ($user->pay_type === 'hourly') {
            return round($minutes * ((float) $user->hourly_rate) / 60, 2);
        }
        if ($user->pay_type === 'salary') {
            // Pro-rate the monthly salary by the period's share of the month.
            $daysInPeriod = $start->diffInDays($end) + 1;
            $daysInMonth  = (int) $start->copy()->daysInMonth;
            $share        = min(1, $daysInPeriod / max(1, $daysInMonth));
            return round((float) $user->monthly_salary * $share, 2);
        }
        return 0;
    }
}
