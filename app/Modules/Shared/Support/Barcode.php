<?php

namespace App\Modules\Shared\Support;

/**
 * Dependency-free Code 39 barcode → inline SVG. Code 39 is self-checking
 * and widely scannable; every element is either a narrow or wide bar/space,
 * which renders crisply at any size (no blurry raster image).
 */
class Barcode
{
    /** 9-element patterns (bar,space,bar,…) per character — 1 = wide, 0 = narrow. */
    private const MAP = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
        '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
        '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
        'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
        'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
        'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
        '/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
    ];

    public static function code39Svg(string $value, int $height = 34, int $narrow = 2, int $wide = 5): string
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z\-. $\/+%]/', '', $value));
        $data = '*'.$clean.'*';

        $x = 0;
        $bars = '';
        foreach (str_split($data) as $char) {
            $pattern = self::MAP[$char] ?? null;
            if ($pattern === null) {
                continue;
            }
            for ($i = 0; $i < 9; $i++) {
                $w = $pattern[$i] === '1' ? $wide : $narrow;
                if ($i % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$height.'"/>';
                }
                $x += $w;
            }
            $x += $narrow; // inter-character narrow gap
        }

        $width = max(1, $x);

        return '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="'.$height.'" '
            .'viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none" fill="#111" '
            .'style="display:block">'.$bars.'</svg>';
    }
}
