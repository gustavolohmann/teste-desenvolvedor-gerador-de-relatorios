<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Customer;
use Database\Seeders\BillingVolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingVolumeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_volume_seeder_inserts_without_faker(): void
    {
        config([
            'billing.volume' => 8,
            'billing.volume_customers' => 2,
        ]);

        $this->seed(BillingVolumeSeeder::class);

        $this->assertSame(2, Customer::query()->count());
        $this->assertSame(8, Billing::query()->count());
        $this->assertTrue(Billing::query()->where('status', 'paid')->where('interest_amount', '>', 0)->exists());
        $this->assertTrue(Billing::query()->where('status', 'pending')->exists());
    }
}
