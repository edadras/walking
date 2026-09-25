<?php

namespace Tests\Unit;

use App\Domain\Cashout\IranianId;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\CreatesCashoutData;

class IranianIdTest extends TestCase
{
    use CreatesCashoutData;

    public function test_national_code_checksum(): void
    {
        $this->assertSame('0499370899', IranianId::nationalCode('0499370899'));
        $this->assertSame('0499370899', IranianId::nationalCode('۰۴۹۹۳۷۰۸۹۹'));
        $this->assertSame('0499370899', IranianId::nationalCode('049-937089-9'));
        $this->assertNull(IranianId::nationalCode('0499370898'));
        $this->assertNull(IranianId::nationalCode('1111111111'));
        $this->assertNull(IranianId::nationalCode('049937089'));
        $this->assertNotNull(IranianId::nationalCode($this->nationalCode('123456789')));
    }

    public function test_sheba_mod97_and_bank(): void
    {
        // A widely published sample Sheba (Bank Melli format).
        $this->assertSame('IR062960000000100324200001', IranianId::sheba('IR06 2960 0000 0010 0324 2000 01'));
        $this->assertSame('IR062960000000100324200001', IranianId::sheba('062960000000100324200001'));
        $this->assertNull(IranianId::sheba('IR072960000000100324200001'));
        $this->assertNull(IranianId::sheba('IR0629600000001003242000'));

        $s = $this->sheba('012');
        $this->assertSame($s, IranianId::sheba(strtolower($s)));
        $this->assertSame('ملت', IranianId::bankName($s));
        $this->assertSame('بانک نامشخص', IranianId::bankName('IR062960000000100324200001'));
        $this->assertSame('••••••7890', IranianId::mask('1234567890'));
    }
}
