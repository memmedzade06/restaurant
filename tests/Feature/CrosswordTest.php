<?php

namespace Tests\Feature;

use App\Services\Crossword;
use Tests\TestCase;

/**
 * Krossvord: şəbəkə düzgün qurulurmu (sözlər kəsişir, yanaşı təsadüfi "söz" yaranmır,
 * hamısı bir-birinə bağlıdır) və səhifə açılırmı.
 */
class CrosswordTest extends TestCase
{
    public function test_pages_open(): void
    {
        $this->get('/oyunlar')->assertOk()->assertSee('Krossvord');
        $this->get('/oyunlar/krossvord')->assertOk()->assertSee('Günün krossvordu')->assertSee('js/games/crossword.js', false);
        $this->get('/oyunlar/krossvord?n=12345')->assertOk()->assertSee('Məşq krossvordu');
        // yararsız nömrə — günün krossvordu açılır
        $this->get('/oyunlar/krossvord?n=abc')->assertOk()->assertSee('Günün krossvordu');
    }

    public function test_daily_puzzle_is_same_for_everyone_today(): void
    {
        $this->assertSame(Crossword::make(Crossword::dailySeed()), Crossword::make(Crossword::dailySeed()));
        $this->assertNotSame(Crossword::dailySeed('2026-11-17'), Crossword::dailySeed('2026-11-18'));
    }

    public function test_generated_grids_are_valid(): void
    {
        $dict = collect(config('crossword.words'))->pluck(1, 0);
        $max = (int) config('crossword.max_size');

        foreach (range(1, 60) as $seed) {
            $cw = Crossword::make($seed);
            $words = array_merge(
                array_map(fn ($w) => $w + ['dir' => 'across'], $cw['across']),
                array_map(fn ($w) => $w + ['dir' => 'down'], $cw['down'])
            );

            $this->assertGreaterThanOrEqual(8, count($words), "seed $seed: söz azdır");
            $this->assertLessThanOrEqual($max, $cw['rows']);
            $this->assertLessThanOrEqual($max, $cw['cols']);

            // hər söz siyahıdandır, izahı düzgündür və hərfləri şəbəkədədir
            $runs = [];
            foreach ($words as $w) {
                $this->assertSame($dict[$w['answer']], $w['clue']);
                $letters = mb_str_split($w['answer']);
                $this->assertCount($w['len'], $letters);
                foreach ($letters as $i => $ch) {
                    [$r, $c] = $w['dir'] === 'across' ? [$w['r'], $w['c'] + $i] : [$w['r'] + $i, $w['c']];
                    $this->assertSame($ch, $cw['grid'][$r][$c], "seed $seed: {$w['answer']} şəbəkəyə uyğun deyil");
                }
                $this->assertSame($w['n'], $cw['numbers'][$w['r']][$w['c']]);
                $runs[$w['dir'] . ':' . $w['r'] . ',' . $w['c']] = $w['answer'];
            }

            // şəbəkədəki hər 2+ hərfli ardıcıllıq məhz bir sözdür — yanaşı təsadüfi "söz" yoxdur
            foreach (['across' => [0, 1], 'down' => [1, 0]] as $dir => [$dr, $dc]) {
                for ($r = 0; $r < $cw['rows']; $r++) {
                    for ($c = 0; $c < $cw['cols']; $c++) {
                        $prev = $cw['grid'][$r - $dr][$c - $dc] ?? null;
                        if ($cw['grid'][$r][$c] === null || $prev !== null) {
                            continue;
                        }
                        $s = '';
                        for ($rr = $r, $cc = $c; ($cw['grid'][$rr][$cc] ?? null) !== null; $rr += $dr, $cc += $dc) {
                            $s .= $cw['grid'][$rr][$cc];
                        }
                        if (mb_strlen($s) >= 2) {
                            $this->assertSame($runs["$dir:$r,$c"] ?? null, $s, "seed {$seed}: gözlənilməz söz «{$s}»");
                        }
                    }
                }
            }

            // bütün hərflər bir-birinə bağlıdır
            $cells = [];
            foreach ($cw['grid'] as $r => $row) {
                foreach ($row as $c => $ch) {
                    if ($ch !== null) {
                        $cells["$r,$c"] = true;
                    }
                }
            }
            $seen = [array_key_first($cells) => true];
            $queue = [array_key_first($cells)];
            while ($queue) {
                [$r, $c] = explode(',', array_shift($queue));
                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dr, $dc]) {
                    $key = ($r + $dr) . ',' . ($c + $dc);
                    if (isset($cells[$key]) && ! isset($seen[$key])) {
                        $seen[$key] = true;
                        $queue[] = $key;
                    }
                }
            }
            $this->assertCount(count($cells), $seen, "seed $seed: şəbəkə bölünüb");

            // nömrələr 1-dən ardıcıl
            $nums = array_values(array_filter(array_merge(...$cw['numbers'])));
            $this->assertSame(range(1, count($nums)), $nums);
        }
    }
}
