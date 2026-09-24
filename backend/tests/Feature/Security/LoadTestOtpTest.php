<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\OtpService;
use App\Jobs\SendOtpSms;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class LoadTestOtpTest extends TestCase
{
    use RefreshDatabase;

    private function codeFor(string $phone): string
    {
        Bus::fake([SendOtpSms::class]);
        app(OtpService::class)->request($phone, Device::factory()->create(), '127.0.0.1');
        $code = null;
        Bus::assertDispatched(SendOtpSms::class, function ($job) use (&$code) {
            $code = $job->code;

            return true;
        });

        return $code;
    }

    public function test_reserved_numbers_get_the_fixed_code_outside_production(): void
    {
        config(['walk.loadtest.otp_code' => '11111']);
        $this->assertSame('11111', $this->codeFor('+989991234567'));
        $this->assertNotSame('11111', $this->codeFor('+989121234567'), 'real numbers never');
    }

    public function test_never_in_production(): void
    {
        config(['walk.loadtest.otp_code' => '11111']);
        $this->app['env'] = 'production';
        $this->assertNotSame('11111', $this->codeFor('+989991234568'));
    }
}
