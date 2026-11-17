<?php

namespace App\Services;

/**
 * Krossvord qurucusu: config('crossword.words') siyahısından sözləri götürüb klassik
 * krossvord şəbəkəsi qurur — sözlər bir-birini kəsir, yan-yana "təsadüfi" söz yaranmır,
 * hər sözün başladığı xana nömrələnir (Üfüqi / Şaquli).
 *
 * Eyni $seed həmişə eyni krossvordu verir: gündəlik krossvord tarixdən, məşq krossvordu
 * ünvandakı rəqəmdən qurulur.
 */
class Crossword
{
    private const ACROSS = 'across';
    private const DOWN = 'down';

    /** Gündəlik krossvordun nömrəsi (Bakı vaxtı ilə tarixdən) */
    public static function dailySeed(?string $date = null): int
    {
        return crc32('krossvord-' . ($date ?? now('Asia/Baku')->toDateString())) & 0x7fffffff;
    }

    /**
     * @return array{seed:int, rows:int, cols:int, grid:array, numbers:array, across:array, down:array}
     */
    public static function make(int $seed): array
    {
        $words = config('crossword.words', []);
        $target = (int) config('crossword.target_words', 12);
        $max = (int) config('crossword.max_size', 13);

        mt_srand($seed);
        $best = null;
        // bir neçə cəhd: ən çox söz yerləşən, sonra ən çox kəsişməsi olan, sonra ən yığcam variant seçilir
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $layout = self::attempt($words, $target, $max);
            if (! $best || self::better($layout, $best)) {
                $best = $layout;
            }
            if (count($best['placed']) >= $target && $best['crossings'] >= $target) {
                break;
            }
        }
        mt_srand(); // başqa kodun təsadüfi ədədlərinə təsir etməsin

        return self::build($best, $seed);
    }

    /** Bir cəhd: sözləri qarışdırır və bir-bir yerləşdirməyə çalışır */
    private static function attempt(array $words, int $target, int $max): array
    {
        $list = array_map(fn ($w) => ['word' => $w[0], 'clue' => $w[1], 'letters' => mb_str_split($w[0])], $words);
        self::shuffle($list);

        // ilk söz: ilk bir neçə söz arasından ən uzunu, üfüqi, mərkəzdə
        $head = array_slice($list, 0, 6);
        usort($head, fn ($a, $b) => count($b['letters']) <=> count($a['letters']));
        $first = $head[0];
        $list = array_values(array_filter($list, fn ($w) => $w['word'] !== $first['word']));

        $L = ['cells' => [], 'dirs' => [], 'placed' => [], 'crossings' => 0, 'box' => null];
        self::place($L, $first, 0, 0, self::ACROSS);

        // hər dövrədə yerləşə bilməyən sözlərə yenidən baxılır (yeni sözlər yeni kəsişmə imkanı yaradır)
        for ($round = 0; $round < 3 && count($L['placed']) < $target; $round++) {
            foreach ($list as $i => $w) {
                if (count($L['placed']) >= $target) {
                    break;
                }
                $spot = self::bestSpot($L, $w['letters'], $max);
                if ($spot) {
                    self::place($L, $w, $spot[0], $spot[1], $spot[2]);
                    $L['crossings'] += $spot[3];
                    unset($list[$i]);
                }
            }
        }

        return $L;
    }

    /** Sözün ən yaxşı yeri: [r, c, istiqamət, kəsişmə sayı] və ya null */
    private static function bestSpot(array $L, array $letters, int $max): ?array
    {
        $n = count($letters);
        $options = [];
        foreach ($L['cells'] as $key => $ch) {
            [$r, $c] = array_map('intval', explode(',', $key));
            foreach ($letters as $i => $letter) {
                if ($letter !== $ch) {
                    continue;
                }
                // xananı keçən söz hansı istiqamətdə deyilsə, yeni söz o istiqamətdə qoyulur
                foreach ([self::ACROSS, self::DOWN] as $dir) {
                    if (in_array($dir, $L['dirs'][$key] ?? [], true)) {
                        continue;
                    }
                    $sr = $dir === self::DOWN ? $r - $i : $r;
                    $sc = $dir === self::ACROSS ? $c - $i : $c;
                    $cross = self::fits($L, $letters, $sr, $sc, $dir, $max);
                    if ($cross > 0) {
                        $options[] = [$sr, $sc, $dir, $cross];
                    }
                }
            }
        }
        if (! $options) {
            return null;
        }
        // ən çox kəsişən yer; bərabərdirsə — təsadüfi (eyni seed həmişə eyni seçir)
        $top = max(array_column($options, 3));
        $options = array_values(array_filter($options, fn ($o) => $o[3] === $top));

        return $options[mt_rand(0, count($options) - 1)];
    }

    /**
     * Söz bu yerə sığırmı? Qaytarır: kəsişmə sayı (0 — sığmır).
     * Qaydalar: üst-üstə düşən xanada hərf eyni olmalıdır; kəsişməyən xananın yan qonşuları boş olmalıdır;
     * sözün əvvəlindən və sonundan sonrakı xana boş olmalıdır; şəbəkə $max-dan böyük olmamalıdır.
     */
    private static function fits(array $L, array $letters, int $r, int $c, string $dir, int $max): int
    {
        $n = count($letters);
        [$dr, $dc] = $dir === self::ACROSS ? [0, 1] : [1, 0];
        if (isset($L['cells'][($r - $dr) . ',' . ($c - $dc)]) || isset($L['cells'][($r + $dr * $n) . ',' . ($c + $dc * $n)])) {
            return 0;
        }

        $cross = 0;
        for ($i = 0; $i < $n; $i++) {
            $rr = $r + $dr * $i;
            $cc = $c + $dc * $i;
            $key = "$rr,$cc";
            if (isset($L['cells'][$key])) {
                if ($L['cells'][$key] !== $letters[$i] || in_array($dir, $L['dirs'][$key], true)) {
                    return 0;
                }
                $cross++;
                continue;
            }
            // yan qonşular (sözə perpendikulyar) boş olmalıdır — yoxsa yanaşı yeni "söz" yaranar
            $n1 = ($rr + $dc) . ',' . ($cc + $dr);
            $n2 = ($rr - $dc) . ',' . ($cc - $dr);
            if (isset($L['cells'][$n1]) || isset($L['cells'][$n2])) {
                return 0;
            }
        }

        // ölçü
        [$r0, $c0, $r1, $c1] = $L['box'];
        $r0 = min($r0, $r);
        $c0 = min($c0, $c);
        $r1 = max($r1, $r + $dr * ($n - 1));
        $c1 = max($c1, $c + $dc * ($n - 1));
        if ($r1 - $r0 + 1 > $max || $c1 - $c0 + 1 > $max) {
            return 0;
        }

        return $cross;
    }

    private static function place(array &$L, array $w, int $r, int $c, string $dir): void
    {
        [$dr, $dc] = $dir === self::ACROSS ? [0, 1] : [1, 0];
        foreach ($w['letters'] as $i => $ch) {
            $key = ($r + $dr * $i) . ',' . ($c + $dc * $i);
            $L['cells'][$key] = $ch;
            $L['dirs'][$key][] = $dir;
        }
        $n = count($w['letters']);
        $end = [$r + $dr * ($n - 1), $c + $dc * ($n - 1)];
        $L['box'] = $L['box']
            ? [min($L['box'][0], $r), min($L['box'][1], $c), max($L['box'][2], $end[0]), max($L['box'][3], $end[1])]
            : [$r, $c, $end[0], $end[1]];
        $L['placed'][] = ['word' => $w['word'], 'clue' => $w['clue'], 'r' => $r, 'c' => $c, 'dir' => $dir, 'len' => $n];
    }

    private static function better(array $a, array $b): bool
    {
        $area = fn ($L) => ($L['box'][2] - $L['box'][0] + 1) * ($L['box'][3] - $L['box'][1] + 1);

        return [count($a['placed']), $a['crossings'], -$area($a)] > [count($b['placed']), $b['crossings'], -$area($b)];
    }

    /** Şəbəkəni 0-dan başlayan cədvələ çevirir və nömrələyir */
    private static function build(array $L, int $seed): array
    {
        [$r0, $c0, $r1, $c1] = $L['box'];
        $rows = $r1 - $r0 + 1;
        $cols = $c1 - $c0 + 1;

        $grid = array_fill(0, $rows, array_fill(0, $cols, null));
        foreach ($L['cells'] as $key => $ch) {
            [$r, $c] = array_map('intval', explode(',', $key));
            $grid[$r - $r0][$c - $c0] = $ch;
        }

        // nömrələr: soldan sağa, yuxarıdan aşağı — sözün başladığı hər xanaya bir nömrə
        $starts = [];
        foreach ($L['placed'] as $p) {
            $starts[($p['r'] - $r0) . ',' . ($p['c'] - $c0)] = true;
        }
        $numbers = array_fill(0, $rows, array_fill(0, $cols, null));
        $num = 0;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                if (isset($starts["$r,$c"])) {
                    $numbers[$r][$c] = ++$num;
                }
            }
        }

        $across = $down = [];
        foreach ($L['placed'] as $p) {
            $r = $p['r'] - $r0;
            $c = $p['c'] - $c0;
            $item = ['n' => $numbers[$r][$c], 'clue' => $p['clue'], 'len' => $p['len'], 'r' => $r, 'c' => $c, 'answer' => $p['word']];
            if ($p['dir'] === self::ACROSS) {
                $across[] = $item;
            } else {
                $down[] = $item;
            }
        }
        usort($across, fn ($a, $b) => $a['n'] <=> $b['n']);
        usort($down, fn ($a, $b) => $a['n'] <=> $b['n']);

        return compact('seed', 'rows', 'cols', 'grid', 'numbers', 'across', 'down');
    }

    /** mt_rand ilə qarışdırma — eyni seed eyni sıranı verir (PHP-nin shuffle()-i seed-ə tabe deyil) */
    private static function shuffle(array &$a): void
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }
    }
}
