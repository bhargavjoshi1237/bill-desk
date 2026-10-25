<?php

namespace Tests\Unit;

use App\Support\GridColumns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GridColumnsTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function counts(): array
    {
        return [
            'no cards still gets a column' => [0, 1],
            'one card' => [1, 1],
            'two wallets share the row' => [2, 2],
            'a full row' => [4, 4],
            'five cards: 3 + 2 beats 4 + 1' => [5, 3],
            'operator stats: two rows of 3' => [6, 3],
            'seven cards: 4 + 3' => [7, 4],
            'admin stats: two rows of 4' => [8, 4],
            'nine cards: three rows of 3' => [9, 3],
        ];
    }

    #[DataProvider('counts')]
    public function test_balanced_columns(int $count, int $expected): void
    {
        $this->assertSame($expected, GridColumns::balanced($count));
    }

    public function test_respects_a_smaller_maximum(): void
    {
        $this->assertSame(3, GridColumns::balanced(6, 3));
        $this->assertSame(2, GridColumns::balanced(4, 3));
    }
}
