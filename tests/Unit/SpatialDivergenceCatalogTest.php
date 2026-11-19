<?php

namespace Tests\Unit;

use Tests\TestCase;

class SpatialDivergenceCatalogTest extends TestCase
{
    public function test_catalogo_utiliza_a_lista_correta_das_23_divergencias(): void
    {
        $expected = [
            'A' => [6, 20],
            'B' => [12, 105, 106, 107, 109, 110, 111, 114, 166],
            'C' => [7, 11, 13, 14, 15, 22, 24, 26, 27, 29, 120, 122],
        ];

        $this->assertSame($expected, config('map.spatial_divergence_audit'));
        $this->assertSame([2, 9, 12], array_values(array_map('count', $expected)));

        $ids = array_merge(...array_values($expected));

        $this->assertCount(23, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
    }
}
