<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentTypeDetector;
use App\Services\Ocr\DrivingLicenceParser;
use App\Services\Ocr\VehicleGeranParser;
use PHPUnit\Framework\TestCase;

class DocumentOcrParsingTest extends TestCase
{
    public function test_document_types_are_detected_without_guessing(): void
    {
        $detector = new DocumentTypeDetector;
        $this->assertSame('DRIVING_LICENCE', $detector->detect("LESEN MEMANDU\nDRIVING LICENCE\nNO. PENGENALAN"));
        $this->assertSame('VEHICLE_GERAN', $detector->detect("SIJIL PEMILIKAN KENDERAAN\nNO. PENDAFTARAN\nNO. CHASIS"));
        $this->assertNull($detector->detect('UNKNOWN DOCUMENT'));
        $this->assertNull($detector->detect(''));
    }

    public function test_driving_licence_fields_preserve_sensitive_ocr_text(): void
    {
        $fields = (new DrivingLicenceParser)->parse($this->lines([
            'NAMA / NAME: TEST USER',
            'NO. PENGENALAN: 991109O40290',
        ]));
        $this->assertSame('TEST USER', $fields['name']['value']);
        $this->assertSame('991109O40290', $fields['identity_no']['value']);
    }

    public function test_vehicle_geran_fields_are_label_driven(): void
    {
        $fields = (new VehicleGeranParser)->parse($this->lines([
            'NAMA PEMUNYA BERDAFTAR: TEST USER',
            'NO. CHASIS: PL1BT3SRRSB407045',
        ]));
        $this->assertSame('TEST USER', $fields['registered_owner_name']['value']);
        $this->assertSame('PL1BT3SRRSB407045', $fields['chassis_no']['value']);
    }

    public function test_driving_licence_uses_card_layout_for_unlabelled_and_multiline_fields(): void
    {
        $lines = $this->lines([
            'LESEN MEMANDU', 'DRIVING LICENCE', 'TEO LE YANG',
            'No. Pengenalan / Identity No. Tarikh Lahir / Date Of Birth',
            '050317010879', '17/03/2005',
            'Warganegara / Nationality', 'Kelas / Class', 'MALAYSIA', 'D',
            'Tempoh / Validity', '11/03/2026 - 17/03/2027',
            'Alamat / Address', 'NO 11 JALAN BUKIT FLORA 2/16', 'TAMAN BUKIT FLORA 2', '83000 BATU PAHAT', 'JOHOR',
        ]);

        $fields = (new DrivingLicenceParser)->parse($lines);
        $this->assertSame('TEO LE YANG', $fields['name']['value']);
        $this->assertSame('050317010879', $fields['identity_no']['value']);
        $this->assertSame('17/03/2005', $fields['date_of_birth']['value']);
        $this->assertSame('MALAYSIA', $fields['nationality']['value']);
        $this->assertSame('D', $fields['licence_class']['value']);
        $this->assertSame('11/03/2026', $fields['valid_from']['value']);
        $this->assertSame('17/03/2027', $fields['valid_until']['value']);
        $this->assertSame("NO 11 JALAN BUKIT FLORA 2/16\nTAMAN BUKIT FLORA 2\n83000 BATU PAHAT\nJOHOR", $fields['address']['value']);
    }

    public function test_vehicle_geran_uses_layout_for_voc_reference_and_multiline_address(): void
    {
        $lines = [
            ['text' => 'SIJIL PEMILIKAN KENDERAAN', 'confidence' => 0.99, 'bounding_box' => [[200, 20], [800, 20], [800, 50], [200, 50]]],
            ['text' => 'VDZ8Y31G', 'confidence' => 0.94, 'bounding_box' => [[900, 90], [1000, 90], [1000, 120], [900, 120]]],
            ['text' => 'Alamat', 'confidence' => 0.99, 'bounding_box' => [[100, 200], [200, 200], [200, 220], [100, 220]]],
            ['text' => ': NO 11, JALAN BUKIT FLORA 2/16', 'confidence' => 0.98, 'bounding_box' => [[400, 200], [800, 200], [800, 220], [400, 220]]],
            ['text' => 'TAMAN BUKIT FLORA 2', 'confidence' => 0.99, 'bounding_box' => [[400, 230], [700, 230], [700, 250], [400, 250]]],
            ['text' => '83000 BATU PAHAT JOHOR', 'confidence' => 0.99, 'bounding_box' => [[400, 260], [700, 260], [700, 280], [400, 280]]],
            ['text' => 'No. Chasis / No. Enjin', 'confidence' => 0.99, 'bounding_box' => [[100, 300], [300, 300], [300, 320], [100, 320]]],
        ];

        $fields = (new VehicleGeranParser)->parse($lines);
        $this->assertSame('VDZ8Y31G', $fields['voc_reference_no']['value']);
        $this->assertSame("NO 11, JALAN BUKIT FLORA 2/16\nTAMAN BUKIT FLORA 2\n83000 BATU PAHAT JOHOR", $fields['owner_address']['value']);
    }

    private function lines(array $values): array
    {
        return array_map(fn (string $text) => ['text' => $text, 'confidence' => 0.98, 'bounding_box' => []], $values);
    }
}
