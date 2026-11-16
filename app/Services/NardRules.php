<?php

namespace App\Services;

/**
 * Uzun nərdin qaydaları — public/js/games/nard.js-dəki mühərrikin PHP surəti.
 * İki nəfərlik oyunda gedişləri server yoxlayır ki, heç kim qaydaları pozub hiylə edə bilməsin.
 *
 * Lövhə: 24 xana (0..23). Hər oyunçunun öz yolu var (0 = baş, 23 = son xana);
 * oyunçu üçün yol mövqeyi p lövhədə (p + OFFSET[pl]) % 24 xanasıdır.
 *
 * Vəziyyət (state):
 *  pts       — 24 xana, hər biri [sahib (-1 boş / 0 / 1), daş sayı]
 *  off       — [oyunçu 0-ın çıxardığı, oyunçu 1-in çıxardığı]
 *  turn      — kimin növbəsidir (0 / 1), oyun bitəndə null
 *  rolled    — bu növbədə atılan zərlər [d1, d2] (hələ atılmayıbsa [])
 *  dice      — oynanmamış zərlər (cüt düşəndə 4 ədəd)
 *  headUsed  — bu növbədə başdan götürülən daş sayı, headLimit — icazə verilən
 *  firstTurn — oyunçunun ilk növbəsidirmi (ilk gedişdə 6-6, 4-4, 3-3 düşsə başdan 2 daş olar)
 *  opening   — kimin başlayacağını təyin edən zərlər [oyunçu 0, oyunçu 1]
 *  last      — rəqibin son növbədə etdiyi gedişlər [[from, to], ...] (ekranda göstərmək üçün)
 */
class NardRules
{
    public const OFFSET = [0, 12];
    public const HOME_START = 18;

    public static function toBoard(int $pl, int $p): int
    {
        return ($p + self::OFFSET[$pl]) % 24;
    }

    public static function toPath(int $pl, int $b): int
    {
        return ($b - self::OFFSET[$pl] + 24) % 24;
    }

    /** Yeni oyun: hər tərəfin 15 daşı başında; kimin başlayacağını zər təyin edir */
    public static function newGame(?callable $roll = null): array
    {
        $roll ??= fn () => random_int(1, 6);
        do {
            $a = $roll();
            $b = $roll();
        } while ($a === $b);

        $pts = array_fill(0, 24, [-1, 0]);
        $pts[self::toBoard(0, 0)] = [0, 15];
        $pts[self::toBoard(1, 0)] = [1, 15];

        return [
            'pts' => $pts,
            'off' => [0, 0],
            'turn' => $a > $b ? 0 : 1,
            'rolled' => [],
            'dice' => [],
            'headUsed' => 0,
            'headLimit' => 1,
            'firstTurn' => [true, true],
            'opening' => [$a, $b],
            'last' => [],
        ];
    }

    /** Növbəsi olan oyunçu zər atdı: zərləri və bu növbənin qaydalarını yazır */
    public static function roll(array $s, int $d1, int $d2): array
    {
        $pl = $s['turn'];
        $s['rolled'] = [$d1, $d2];
        $s['dice'] = $d1 === $d2 ? [$d1, $d1, $d1, $d1] : [$d1, $d2];
        $s['headUsed'] = 0;
        $s['headLimit'] = $s['firstTurn'][$pl] && $d1 === $d2 && in_array($d1, [6, 4, 3], true) ? 2 : 1;
        $s['firstTurn'][$pl] = false;
        $s['turnMoves'] = [];
        unset($s['skip']);

        return $s;
    }

    public static function allInHome(array $s, int $pl): bool
    {
        for ($b = 0; $b < 24; $b++) {
            if ($s['pts'][$b][0] === $pl && self::toPath($pl, $b) < self::HOME_START) {
                return false;
            }
        }

        return true;
    }

    /** Rəqibin qarşısında olan, ötüb keçilə bilməyən 6 xanalıq çəpər varmı */
    public static function hasIllegalPrime(array $s, int $pl): bool
    {
        $opp = 1 - $pl;
        $oppMax = -1; // rəqibin ən qabaqdakı daşının mövqeyi (öz yolunda)
        for ($b = 0; $b < 24; $b++) {
            if ($s['pts'][$b][0] === $opp) {
                $oppMax = max($oppMax, self::toPath($opp, $b));
            }
        }
        for ($start = 0; $start <= 18; $start++) {
            $run = true;
            for ($k = 0; $k < 6 && $run; $k++) {
                $run = $s['pts'][self::toBoard($opp, $start + $k)][0] === $pl;
            }
            if ($run && $oppMax <= $start + 5) {
                return true; // çəpərin qabağında rəqib daşı yoxdur
            }
        }

        return false;
    }

    /** Daşı from xanasından to xanasına (və ya 'off' — çıxarılır) aparır */
    public static function apply(array $s, int $pl, int $from, int|string $to): array
    {
        $s['pts'][$from][1]--;
        if ($s['pts'][$from][1] === 0) {
            $s['pts'][$from][0] = -1;
        }
        if ($to === 'off') {
            $s['off'][$pl]++;
        } else {
            $s['pts'][$to][0] = $pl;
            $s['pts'][$to][1]++;
        }

        return $s;
    }

    /** Bir zərlə mümkün olan bütün tək gedişlər: [from, to, die, head, ns] */
    private static function genMoves(array $s, array $ctx): array
    {
        $pl = $ctx['pl'];
        $moves = [];
        $home = self::allInHome($s, $pl);
        $primeBefore = self::hasIllegalPrime($s, $pl);

        // evdə ən arxada olan daş (çıxarma qaydası üçün)
        $rearmost = 24;
        if ($home) {
            for ($b = 0; $b < 24; $b++) {
                if ($s['pts'][$b][0] === $pl) {
                    $rearmost = min($rearmost, self::toPath($pl, $b));
                }
            }
        }

        foreach (array_values(array_unique($ctx['dice'])) as $d) {
            for ($b = 0; $b < 24; $b++) {
                if ($s['pts'][$b][0] !== $pl) {
                    continue;
                }
                $p = self::toPath($pl, $b);
                $fromHead = $p === 0;
                if ($fromHead && $ctx['headUsed'] >= $ctx['headLimit']) {
                    continue;
                }
                $q = $p + $d;
                if ($q < 24) {
                    $to = self::toBoard($pl, $q);
                    if ($s['pts'][$to][0] === 1 - $pl) {
                        continue;
                    }
                } else {
                    if (! $home) {
                        continue;
                    }
                    if ($q > 24 && $p !== $rearmost) {
                        continue; // böyük zərlə yalnız ən arxadakı daş çıxır
                    }
                    $to = 'off';
                }
                $ns = self::apply($s, $pl, $b, $to);
                if ($to !== 'off' && ! $primeBefore && self::hasIllegalPrime($ns, $pl)) {
                    continue;
                }
                $moves[] = ['from' => $b, 'to' => $to, 'die' => $d, 'head' => $fromHead, 'ns' => $ns];
            }
        }

        return $moves;
    }

    private static function nextCtx(array $ctx, array $m): array
    {
        $dice = $ctx['dice'];
        array_splice($dice, array_search($m['die'], $dice, true), 1);

        return ['pl' => $ctx['pl'], 'dice' => $dice, 'headUsed' => $ctx['headUsed'] + ($m['head'] ? 1 : 0), 'headLimit' => $ctx['headLimit']];
    }

    private static function key(array $s): string
    {
        return json_encode($s['pts']) . '|' . implode(',', $s['off']);
    }

    /** Bu vəziyyətdən maksimum neçə zər oynana bilər */
    private static function maxUsable(array $s, array $ctx, array &$memo): int
    {
        if (! $ctx['dice']) {
            return 0;
        }
        $k = self::key($s) . '#' . implode('', $ctx['dice']) . '#' . $ctx['headUsed'];
        if (isset($memo[$k])) {
            return $memo[$k];
        }
        $best = 0;
        foreach (self::genMoves($s, $ctx) as $m) {
            $best = max($best, 1 + self::maxUsable($m['ns'], self::nextCtx($ctx, $m), $memo));
            if ($best === count($ctx['dice'])) {
                break;
            }
        }

        return $memo[$k] = $best;
    }

    private static function ctxOf(array $s): array
    {
        return ['pl' => $s['turn'], 'dice' => $s['dice'], 'headUsed' => $s['headUsed'], 'headLimit' => $s['headLimit']];
    }

    /** Növbəsi olan oyunçunun qaydalara uyğun gedişləri (zərlərin maksimum istifadəsi nəzərə alınmaqla) */
    public static function legalMoves(array $s): array
    {
        if ($s['turn'] === null || ! $s['dice']) {
            return [];
        }
        $ctx = self::ctxOf($s);
        $moves = self::genMoves($s, $ctx);
        if (! $moves) {
            return [];
        }
        $memo = [];
        $target = self::maxUsable($s, $ctx, $memo);
        $legal = array_values(array_filter($moves, fn ($m) => 1 + self::maxUsable($m['ns'], self::nextCtx($ctx, $m), $memo) === $target));

        // iki fərqli zərdən yalnız biri oynana bilirsə — böyüyü oynanmalıdır
        if ($target === 1 && count($ctx['dice']) === 2 && $ctx['dice'][0] !== $ctx['dice'][1]) {
            $big = max(array_column($legal, 'die'));
            $legal = array_values(array_filter($legal, fn ($m) => $m['die'] === $big));
        }

        return $legal;
    }

    /**
     * Gedişi edir. Qaydalara uyğun deyilsə null qaytarır.
     * Gedişdən sonra oynanacaq zər qalmayıbsa (və ya qalan zərlə gediş yoxdursa) növbə rəqibə keçir.
     */
    public static function move(array $s, int $from, int|string $to, ?int $die = null): ?array
    {
        $options = array_filter(self::legalMoves($s), fn ($m) => $m['from'] === $from && $m['to'] === $to && ($die === null || $m['die'] === $die));
        if (! $options) {
            return null;
        }
        usort($options, fn ($a, $b) => $a['die'] <=> $b['die']); // çıxarmada eyni yerə bir neçə zərlə getmək olursa — kiçik zər
        $m = $options[0];

        $ctx = self::nextCtx(self::ctxOf($s), $m);
        $ns = $m['ns'];
        $ns['dice'] = $ctx['dice'];
        $ns['headUsed'] = $ctx['headUsed'];
        $ns['turnMoves'][] = [$from, $to];

        return self::afterMove($ns);
    }

    /** Oyun bitibmi, yoxsa növbə keçməlidirmi — yoxlayır */
    public static function afterMove(array $s): array
    {
        if (self::winner($s) !== null) {
            $s['last'] = $s['turnMoves'] ?? [];
            $s['turn'] = null;
            $s['dice'] = [];

            return $s;
        }
        if (! $s['dice'] || ! self::legalMoves($s)) {
            return self::passTurn($s);
        }

        return $s;
    }

    /** Növbəni rəqibə verir (gediş qalmayanda və ya zərlər bitəndə) */
    public static function passTurn(array $s): array
    {
        $s['last'] = $s['turnMoves'] ?? [];
        $s['turnMoves'] = [];
        $s['turn'] = 1 - $s['turn'];
        $s['rolled'] = [];
        $s['dice'] = [];
        $s['headUsed'] = 0;
        $s['headLimit'] = 1;

        return $s;
    }

    /** Qalib (0 / 1) — 15 daşın hamısını çıxaran; yoxdursa null */
    public static function winner(array $s): ?int
    {
        foreach ([0, 1] as $pl) {
            if ($s['off'][$pl] === 15) {
                return $pl;
            }
        }

        return null;
    }

    /** Lövhəni oyunçu 1-in baxışına çevirir: o özünü həmişə ağ daşlarla, aşağıda görsün deyə */
    public static function viewFor(array $s, int $seat): array
    {
        if ($seat === 0) {
            return $s;
        }
        $swap = fn ($o) => $o < 0 ? $o : 1 - $o;
        $pts = [];
        for ($b = 0; $b < 24; $b++) {
            [$o, $n] = $s['pts'][($b + 12) % 24];
            $pts[$b] = [$swap($o), $n];
        }
        $mapB = fn ($b) => $b === 'off' ? 'off' : ($b + 12) % 24;
        $s['pts'] = $pts;
        $s['off'] = [$s['off'][1], $s['off'][0]];
        $s['turn'] = $s['turn'] === null ? null : 1 - $s['turn'];
        $s['firstTurn'] = [$s['firstTurn'][1], $s['firstTurn'][0]];
        $s['opening'] = [$s['opening'][1], $s['opening'][0]];
        $s['last'] = array_map(fn ($m) => [$mapB($m[0]), $mapB($m[1])], $s['last'] ?? []);
        $s['turnMoves'] = array_map(fn ($m) => [$mapB($m[0]), $mapB($m[1])], $s['turnMoves'] ?? []);

        return $s;
    }

    /** Oyunçu 1-in gördüyü xananı əsl lövhədəki xanaya çevirir */
    public static function fromView(int|string $b, int $seat): int|string
    {
        return $seat === 0 || $b === 'off' ? $b : ((int) $b + 12) % 24;
    }
}
