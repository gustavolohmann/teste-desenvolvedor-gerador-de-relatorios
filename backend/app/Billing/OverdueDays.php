<?php

namespace App\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class OverdueDays
{
    public function between(CarbonInterface|string $dueOn, CarbonInterface|string $reference): int
    {
        $due = Carbon::parse($dueOn)->startOfDay();
        $reference = Carbon::parse($reference)->startOfDay();

        if ($reference->lessThanOrEqualTo($due)) {
            return 0;
        }

        return (int) round($due->diffInDays($reference));
    }
}
