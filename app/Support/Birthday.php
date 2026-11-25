<?php

namespace App\Support;

use App\Models\User;
use App\Models\WheelSpin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ad günü hədiyyəsi: ad günü yaxınlaşanda (config/birthday.php) istifadəçi masaya endirim kuponu götürür.
 * İldə bir dəfə (users.birthday_coupon_year), yalnız təsdiqlənmiş qonaq hesabları,
 * doğum tarixi ən azı min_days_since_set gün əvvəl yazılıbsa.
 */
class Birthday
{
    public const NO_DATE = 'no_date';     // doğum tarixi yazılmayıb
    public const TOO_NEW = 'too_new';     // tarix təzə yazılıb — bu il üçün kupon yoxdur
    public const WAIT = 'wait';           // ad günü hələ gəlməyib
    public const AVAILABLE = 'available'; // kuponu indi götürmək olar
    public const CLAIMED = 'claimed';     // bu ilin kuponu götürülüb
    public const NOT_ELIGIBLE = 'not_eligible'; // ofisiant/admin, təsdiqlənməmiş və ya funksiya söndürülüb

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Baku')->startOfDay();
    }

    /** Verilən ildəki ad günü (29 fevral — uzun olmayan ildə 28 fevral) */
    public static function inYear(CarbonImmutable|\DateTimeInterface $birth, int $year): CarbonImmutable
    {
        $birth = CarbonImmutable::instance($birth);
        $day = $birth->month === 2 && $birth->day === 29 && ! CarbonImmutable::create($year)->isLeapYear() ? 28 : $birth->day;

        return CarbonImmutable::create($year, $birth->month, $day, 0, 0, 0, 'Asia/Baku');
    }

    /**
     * Yaxın ad günü: bu ilinki hələ bitməyibsə (pəncərə daxil) o, yoxsa gələn ilinki.
     * Pəncərə ilin sonuna düşə bilər (31 dekabr + 3 gün), ona görə keçən ilinki də yoxlanılır.
     */
    public static function upcoming(User $user): ?CarbonImmutable
    {
        if (! $user->birth_date) {
            return null;
        }
        $today = self::today();
        foreach ([$today->year - 1, $today->year, $today->year + 1] as $year) {
            $day = self::inYear($user->birth_date, $year);
            if ($day->addDays(self::daysAfter())->gte($today)) {
                return $day;
            }
        }

        return null;
    }

    /**
     * Formadakı üç seçimdən (gün, ay, il) tarix: [?CarbonImmutable, ?string xəta].
     * Üçü də boşdursa — [null, null] (tarix yoxdur). $min/$max — icazə verilən aralıq (daxil).
     */
    public static function fromParts(?string $day, ?string $month, ?string $year, CarbonImmutable $min, CarbonImmutable $max): array
    {
        $parts = [trim((string) $day), trim((string) $month), trim((string) $year)];
        if ($parts === ['', '', '']) {
            return [null, null];
        }
        if (in_array('', $parts, true) || ! ctype_digit(implode('', $parts))) {
            return [null, 'Doğum tarixini tam seçin: gün, ay və il.'];
        }
        [$d, $m, $y] = array_map('intval', $parts);
        if (! checkdate($m, $d, $y)) {
            return [null, 'Belə tarix yoxdur — günü yoxlayın (məs. 31 iyun olmur).'];
        }
        $date = CarbonImmutable::create($y, $m, $d, 0, 0, 0, 'Asia/Baku');
        if ($date->lt($min->startOfDay()) || $date->gt($max->startOfDay())) {
            return [null, 'Doğum tarixini düzgün seçin.'];
        }

        return [$date, null];
    }

    /** "14 mart" */
    public static function format(\DateTimeInterface $date, bool $withYear = false): string
    {
        $months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
        $d = CarbonImmutable::instance($date);

        return $d->day . ' ' . $months[$d->month - 1] . ($withYear ? ' ' . $d->year : '');
    }

    /** Doğum tarixini yazmağa görə bonus xal */
    public static function setPoints(): float
    {
        return max(0, (float) config('birthday.set_points', 1));
    }

    public static function daysBefore(): int
    {
        return max(0, (int) config('birthday.days_before', 0));
    }

    public static function daysAfter(): int
    {
        return max(0, (int) config('birthday.days_after', 0));
    }

    /** Kuponu götürməyə icazə verən ilk və son gün */
    public static function window(CarbonImmutable $birthday): array
    {
        return [$birthday->subDays(self::daysBefore()), $birthday->addDays(self::daysAfter())];
    }

    /** İstifadəçi ümumiyyətlə bu hədiyyəni ala bilərmi (tarixdən asılı olmayaraq) */
    public static function eligible(User $user): bool
    {
        return config('birthday.enabled', true) && $user->isVerified() && $user->earnsGamePoints();
    }

    /**
     * Profildə və saytın aşağısındakı zolaqda göstərmək üçün vəziyyət:
     * ['state' => ..., 'birthday' => ?CarbonImmutable, 'days_left' => ?int, 'from' => ?CarbonImmutable, 'until' => ?CarbonImmutable]
     */
    public static function status(User $user): array
    {
        $base = ['birthday' => null, 'days_left' => null, 'from' => null, 'until' => null];
        if (! self::eligible($user)) {
            return ['state' => self::NOT_ELIGIBLE] + $base;
        }
        $birthday = self::upcoming($user);
        if (! $birthday) {
            return ['state' => self::NO_DATE] + $base;
        }

        $today = self::today();
        [$from, $until] = self::window($birthday);
        $info = ['birthday' => $birthday, 'days_left' => max(0, (int) $today->diffInDays($birthday, false)), 'from' => $from, 'until' => $until];

        if ((int) $user->birthday_coupon_year === $birthday->year) {
            return ['state' => self::CLAIMED] + $info;
        }
        if ($today->lt($from)) {
            return ['state' => self::WAIT] + $info;
        }
        // tarix təzə yazılıbsa (pəncərənin əvvəlinə min_days_since_set qalmamış) — bu il kupon yoxdur
        $minDays = max(0, (int) config('birthday.min_days_since_set', 30));
        if (! $user->birth_date_set_at || $user->birth_date_set_at->copy()->addDays($minDays)->gt($from->endOfDay())) {
            return ['state' => self::TOO_NEW] + $info;
        }

        return ['state' => self::AVAILABLE] + $info;
    }

    /**
     * Kuponu verir. Qaytarır: yaradılmış kupon və ya null (artıq götürülüb / vaxtı deyil).
     * İki dəfə basılsa belə bir kupon: istifadəçi sətri kilidlənir, il yazılır.
     */
    public static function claim(User $user): ?WheelSpin
    {
        return DB::transaction(function () use ($user) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            $status = $fresh ? self::status($fresh) : null;
            if (! $status || $status['state'] !== self::AVAILABLE) {
                return null;
            }

            $fresh->forceFill(['birthday_coupon_year' => $status['birthday']->year])->save();
            $user->birthday_coupon_year = $fresh->birthday_coupon_year;

            return WheelSpin::create([
                'user_id' => $fresh->id,
                'game' => 'birthday',
                'prize_key' => 'birthday_50',
                'prize_label' => (string) config('birthday.label', 'Masaya 50% endirim — ad günü hədiyyəsi'),
                'code' => WheelSpin::newCode(),
                'spun_on' => self::today()->toDateString(),
                'expires_at' => now()->addDays(max(1, (int) config('birthday.valid_days', 7))),
            ]);
        });
    }
}
