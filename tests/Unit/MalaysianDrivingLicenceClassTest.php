<?php

namespace Tests\Unit;

use App\Support\MalaysianDrivingLicenceClass;
use PHPUnit\Framework\TestCase;

class MalaysianDrivingLicenceClassTest extends TestCase
{
    public function test_compact_multiple_classes_are_split_and_normalized(): void
    {
        $this->assertSame(['B2', 'D'], MalaysianDrivingLicenceClass::parse('B2D'));
        $this->assertSame('B2, D', MalaysianDrivingLicenceClass::normalize('B2 D'));
    }

    public function test_only_d_or_da_permits_carpool_motorcars(): void
    {
        $this->assertTrue(MalaysianDrivingLicenceClass::permitsMotorcar('B2D'));
        $this->assertTrue(MalaysianDrivingLicenceClass::permitsMotorcar('DA'));
        $this->assertFalse(MalaysianDrivingLicenceClass::permitsMotorcar('B2'));
    }
}
