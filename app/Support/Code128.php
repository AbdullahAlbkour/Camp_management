<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Code 128 (subset B) barcode rendered as inline SVG.
 *
 * Checkpoint staff scan the badge printed from a refugee profile, so the code has
 * to be a real, scannable symbol rather than a decorative graphic. SVG keeps it
 * crisp at any printer resolution and needs no image library.
 */
final class Code128
{
    /**
     * Module widths for values 0-106, alternating bar/space. Value 106 is the stop
     * pattern, which carries a trailing bar and is therefore 7 elements long.
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * Render $text as an SVG barcode.
     *
     * @param  int  $moduleWidth  Width of one module in user units; 2 prints reliably on office printers.
     */
    public static function svg(string $text, int $moduleWidth = 2, int $height = 60): string
    {
        $modules = self::modules($text);
        $width = array_sum($modules) * $moduleWidth;

        $rects = '';
        $x = 0;
        $isBar = true;

        foreach ($modules as $moduleCount) {
            $barWidth = $moduleCount * $moduleWidth;

            if ($isBar) {
                $rects .= '<rect x="'.$x.'" y="0" width="'.$barWidth.'" height="'.$height.'"/>';
            }

            $x += $barWidth;
            $isBar = ! $isBar;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
            .'width="'.$width.'" height="'.$height.'" role="img" aria-label="'.e($text).'" '
            .'shape-rendering="crispEdges" fill="#000">'.$rects.'</svg>';
    }

    /**
     * The alternating bar/space module widths for the whole symbol.
     *
     * @return list<int>
     */
    public static function modules(string $text): array
    {
        $values = self::values($text);
        $modules = [];

        foreach ($values as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $width) {
                $modules[] = (int) $width;
            }
        }

        return $modules;
    }

    /**
     * Start value, payload values, checksum and stop, in order.
     *
     * @return list<int>
     */
    public static function values(string $text): array
    {
        if ($text === '') {
            throw new InvalidArgumentException('لا يمكن ترميز نص فارغ في الباركود.');
        }

        $values = [self::START_B];
        // Subset B covers ASCII 32-126; a code containing anything else cannot be encoded.
        $checksum = self::START_B;
        $position = 1;

        foreach (str_split($text) as $character) {
            $code = ord($character);

            if ($code < 32 || $code > 126) {
                throw new InvalidArgumentException('الباركود يقبل الأحرف اللاتينية والأرقام فقط.');
            }

            $value = $code - 32;
            $values[] = $value;
            $checksum += $value * $position;
            $position++;
        }

        $values[] = $checksum % 103;
        $values[] = self::STOP;

        return $values;
    }
}
