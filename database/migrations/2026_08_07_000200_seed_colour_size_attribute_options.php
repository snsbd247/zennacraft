<?php

use App\Modules\Product\Models\ProductAttribute;
use App\Modules\Product\Models\ProductAttributeValue;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the "Colour" and "Size" attribute catalogs with the options that used
 * to be hard-coded on the product form, so those pickers become real,
 * editable data (add/remove from the form). Idempotent: an attribute is only
 * seeded when it exists with no values yet, so a store that already curated
 * its own options is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sizes = [
            'M', 'L', 'XL', 'XXL', 'S', '28', '32', '31', '34', '36', '38', '40', '20',
            '45', 'PXL', '42', 'XXXL', '37', '39', '50', '51', '30', 'FREE SIZE',
            '8-10 YEARS', '10-12 YEARS', '12-14 YEARS', '14-16 YEARS', '52', '54', '56',
        ];
        $colours = [
            'BLACK', 'WHITE', 'RED', 'BLUE', 'GREEN', 'YELLOW', 'MAROON', 'NAVY',
            'GREY', 'PINK', 'PURPLE', 'ORANGE', 'BROWN', 'OLIVE', 'SKY', 'BEIGE',
        ];

        $seed = function (string $name, array $values): void {
            $attr = ProductAttribute::firstOrCreate(['name' => $name], ['status' => 'active']);
            if ($attr->values()->count() === 0) {
                foreach (array_values($values) as $i => $v) {
                    ProductAttributeValue::create([
                        'attribute_id' => $attr->id,
                        'name' => $v,
                        'status' => 'active',
                        'sort_order' => $i,
                    ]);
                }
            }
        };

        $seed('Colour', $colours);
        $seed('Size', $sizes);
    }

    public function down(): void
    {
        // Catalog data is left in place on rollback — the store may have edited it.
    }
};
