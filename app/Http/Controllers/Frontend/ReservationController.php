<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Masa rezervasiyası: qonaq planda masanı seçir, pəncərədə tarix, saat, ad-soyad və nömrəsini yazır.
 * Masa bir rezervasiyadan sonra config('reservations.hours') saat ərzində başqasına verilmir.
 * Boş-boşuna rezerv olmasın deyə yalnız təsdiqlənmiş hesab rezerv edə bilər (route: auth + phone.verified);
 * nömrə formda hər dəfə yazılır (hesabdakı nömrə sadəcə əvvəlcədən doldurulur).
 */
class ReservationController extends Controller
{
    /** Bir nömrəyə / hesaba eyni vaxtda ən çox neçə aktiv (gələcək) rezervasiya */
    public const MAX_PER_PHONE = 3;

    public function index(Request $request): View
    {
        $user = $request->user();
        $access = ! $user ? 'guest' : ($user->isVerified() ? 'ok' : 'unverified');

        return view('frontend.reservations.index', [
            'floors' => config('reservations.floors'),
            'kinds' => config('reservations.kinds'),
            'booking' => [
                'today' => self::now()->toDateString(),
                'maxDate' => self::now()->addDays((int) config('reservations.days_ahead'))->toDateString(),
                'slots' => self::slots(),
                'now' => self::now()->format('H:i'),
                'hours' => (float) config('reservations.hours'),
                'busyUrl' => route('reservations.busy'),
                'storeUrl' => route('reservations.store'),
                // ok — rezerv edə bilər; guest — daxil olmalıdır; unverified — profili təsdiqləməlidir
                'access' => $access,
                'tables' => collect(Reservation::tables())->map(fn ($t) => [
                    'label' => $t['label'],
                    'floor' => $t['floor_name'],
                    'kind' => $t['kind'],
                    'kindName' => config("reservations.kinds.{$t['kind']}.name"),
                    'icon' => config("reservations.kinds.{$t['kind']}.icon"),
                ]),
                'prefill' => [
                    'name' => trim((string) $user?->name),
                    'phone' => $user?->phone ? Phone::format($user->phone) : '',
                ],
            ],
        ]);
    }

    /**
     * Pəncərədəki "Daxil ol / Qeydiyyatdan keç / Profili təsdiqlə" düymələri buradan keçir:
     * giriş və ya təsdiqdən sonra istifadəçi rezervasiya səhifəsinə qayıdır, yarımçıq rezervasiya açılır.
     */
    public function continue(Request $request)
    {
        $back = route('reservations');
        $request->session()->put('url.intended', $back);
        $request->session()->put('reservation.return', $back);

        $user = $request->user();
        if ($user) {
            return $user->isVerified() ? redirect($back) : redirect()->route($user->verificationRoute());
        }

        return redirect()->route($request->query('to') === 'register' ? 'register' : 'login');
    }

    /** Seçilmiş günün tutulmuş saatları: {table_key: ["19:00", ...]} — şəxsi məlumat göndərilmir */
    public function busy(Request $request): JsonResponse
    {
        $date = $this->validDate((string) $request->query('date'));
        abort_unless($date, 422);

        return response()->json(['date' => $date, 'busy' => self::busyFor($date)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'table' => ['required', 'string', Rule::in(array_keys(Reservation::tables()))],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'string', Rule::in(self::slots())],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
        ], [
            'table.*' => 'Masanı seçin.',
            'date.*' => 'Tarixi seçin.',
            'time.*' => 'Saatı seçin.',
            'name.*' => 'Adınızı və soyadınızı yazın.',
            'phone.required' => 'Nömrənizi yazın.',
        ]);

        $user = $request->user();
        $phone = Phone::normalize($data['phone']);
        $errors = [];
        if (! $phone) {
            $errors['phone'] = 'Nömrəni düzgün yazın, məs. 050 123 45 67.';
        }
        if (! $this->validDate($data['date'])) {
            $errors['date'] = 'Bu tarixə rezerv etmək olmur.';
        } elseif ($data['date'] === self::now()->toDateString() && $data['time'] <= self::now()->format('H:i')) {
            $errors['time'] = 'Bu saat artıq keçib — başqa saat seçin.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $reservation = DB::transaction(function () use ($data, $phone, $user) {
            // eyni masaya eyni vaxtda iki nəfər yazılmasın
            $taken = Reservation::active()->where('table_key', $data['table'])->whereDate('date', $data['date'])
                ->lockForUpdate()->pluck('time');
            $gap = (float) config('reservations.hours') * 60;
            foreach ($taken as $time) {
                if (abs(Reservation::minutes($time) - Reservation::minutes($data['time'])) < $gap) {
                    throw ValidationException::withMessages(['time' => "Bu masa {$time}-da artıq rezerv olunub — başqa saat və ya masa seçin."]);
                }
            }

            $upcoming = Reservation::active()->whereDate('date', '>=', self::now()->toDateString())
                ->where(fn ($q) => $q->where('phone', $phone)->orWhere('user_id', $user->id))->count();
            if ($upcoming >= self::MAX_PER_PHONE) {
                throw ValidationException::withMessages(['phone' => 'Sizin artıq ' . self::MAX_PER_PHONE . ' rezervasiyanız var. Dəyişiklik üçün bizə zəng edin.']);
            }

            return Reservation::create([
                'table_key' => $data['table'],
                'date' => $data['date'],
                'time' => $data['time'],
                'name' => preg_replace('/\s+/u', ' ', trim($data['name'])),
                'phone' => $phone,
                'user_id' => $user->id,
            ]);
        });

        $message = sprintf('%s, %s %s üçün %s rezerv olundu. Gözləyirik!',
            \Illuminate\Support\Str::before($reservation->name, ' '),
            $reservation->date->format('d.m.Y'),
            $reservation->time,
            $reservation->table_name,
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'busy' => self::busyFor($data['date'])]);
        }

        return redirect()->route('reservations')->with('success', $message);
    }

    /* ---------------------------------------------------------------- */

    public static function now(): Carbon
    {
        return now(config('reservations.timezone', 'Asia/Baku'));
    }

    /** Gəliş saatları: ["10:00", "10:30", ... "22:00"] */
    public static function slots(): array
    {
        $step = max(5, (int) config('reservations.step', 30));
        $from = Reservation::minutes((string) config('reservations.open', '10:00'));
        $to = Reservation::minutes((string) config('reservations.last', '22:00'));

        $slots = [];
        for ($m = $from; $m <= $to; $m += $step) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $slots;
    }

    public static function busyFor(string $date): array
    {
        return Reservation::active()->whereDate('date', $date)->orderBy('time')->get(['table_key', 'time'])
            ->groupBy('table_key')->map(fn ($rows) => $rows->pluck('time')->values())->all();
    }

    /** Bu gündən days_ahead günə qədər olan tarix (Y-m-d) və ya null */
    private function validDate(string $date): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $today = self::now()->toDateString();
        $max = self::now()->addDays((int) config('reservations.days_ahead'))->toDateString();

        return $date >= $today && $date <= $max && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            ? $date : null;
    }
}
