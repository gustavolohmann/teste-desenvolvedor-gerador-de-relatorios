<?php

namespace App\Enums;

enum BillingStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
