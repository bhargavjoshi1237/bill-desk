<?php

namespace App\Support;

/**
 * Column counts that keep card grids even, so a row doesn't end half empty
 * when that can be avoided: 8 cards → 4 per row, 6 → 3, 2 → 2.
 */
final class GridColumns
{
    /**
     * The column count, at most $max, that leaves the fewest empty cells in
     * the last row. Ties go to more columns, and it never drops below half of
     * $max so the cards don't stack into one tall, narrow column.
     */
    public static function balanced(int $count, int $max = 4): int
    {
        if ($count <= $max) {
            return max($count, 1);
        }

        $best = $max;
        $fewestGaps = PHP_INT_MAX;

        for ($columns = $max; $columns >= (int) ceil($max / 2); $columns--) {
            $gaps = ($columns - $count % $columns) % $columns;

            if ($gaps < $fewestGaps) {
                $best = $columns;
                $fewestGaps = $gaps;
            }
        }

        return $best;
    }
}
