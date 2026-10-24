<?php

namespace Tests\Unit;

use App\Services\Catalog\ModelGrouper;
use App\Services\Catalog\SpecNormalizer;
use Tests\TestCase;

class SpecNormalizerTest extends TestCase
{
    public function test_turns_spec_text_into_typed_facts(): void
    {
        $facts = (new SpecNormalizer)->normalize('smartphones', [
            'RAM' => '8 GB',
            'Internal Storage' => '1 TB',
            '5G' => 'Yes',
            'Display Type' => 'Super AMOLED',
            'Colour' => 'Blue',   // no rule for this label: ignored
        ]);

        $this->assertSame(8.0, $facts['ram_gb']['num']);
        $this->assertSame(1024.0, $facts['storage_gb']['num']);
        $this->assertTrue($facts['network_5g']['bool']);
        $this->assertSame('super amoled', $facts['display_type']['text']);
        $this->assertArrayNotHasKey('colour', $facts);
    }

    public function test_contradicting_labels_store_no_value(): void
    {
        $facts = (new SpecNormalizer)->normalize('earphones', [
            'With Microphone' => 'Yes',
            'Microphone' => 'No',
        ]);

        $this->assertTrue($facts['has_mic']['conflict']);
        $this->assertNull($facts['has_mic']['bool']);
    }

    public function test_not_available_is_a_no_not_a_conflict(): void
    {
        $facts = (new SpecNormalizer)->normalize('smartwatches', ['GPS' => 'Not Available']);

        $this->assertFalse($facts['gps']['bool']);
        $this->assertFalse($facts['gps']['conflict']);
    }

    public function test_units_are_converted(): void
    {
        $facts = (new SpecNormalizer)->normalize('laptops', ['Weight' => '1450 g']);
        $this->assertSame(1.45, $facts['weight_kg']['num']);

        $shoes = (new SpecNormalizer)->normalize('running-shoes', ['Ideal For' => 'Men & Women']);
        $this->assertSame('unisex', $shoes['gender']['text']);
    }

    public function test_variants_share_a_model_group(): void
    {
        $grouper = new ModelGrouper;

        $this->assertSame(
            $grouper->group('Vantro V5 5G (Midnight Blue, 128 GB)'),
            $grouper->group('Vantro V5 5G (Silver, 256 GB)'),
        );
        $this->assertNotSame($grouper->group('Vantro V5 5G'), $grouper->group('Vantro V3'));
    }
}
