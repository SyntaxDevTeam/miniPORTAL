<?php

namespace core\lib\phpqrcode;

require_once '/usr/share/phpqrcode/qrlib.php';

class QRcode
{
    /**
     * Wrapper around the global \QRcode::png from phpqrcode package.
     *
     * @param string $text
     * @param string|false $outfile
     * @param string $level
     * @param int $size
     * @param int $margin
     */
    public static function png(string $text, string|false $outfile = false, string $level = 'H', int $size = 4, int $margin = 2): void
    {
        \QRcode::png($text, $outfile, $level, $size, $margin);
    }
}
