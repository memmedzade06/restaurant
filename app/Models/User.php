<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'telegram_token',
        'google_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'phone_verified_at' => 'datetime',
        'telegram_token_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_admin' => 'boolean',
        'referral_rewarded_at' => 'datetime',
        'points' => 'float', // bazada decimal(10,2): 0.5 və 0.25 xallar
    ];

    /** Bu istifadəçini dəvət edən */
    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** Bu istifadəçinin linki/QR kodu ilə qeydiyyatdan keçənlər */
    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /** Dəvət kodu (link və QR kodda); ilk dəfə lazım olanda yaradılır */
    public function referralCode(): string
    {
        if (! $this->referral_code) {
            do {
                // oxunuşu çaşdıran simvollar (0/O, 1/I) yoxdur
                $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 4)), 0, 8);
            } while (self::where('referral_code', $code)->exists());
            $this->forceFill(['referral_code' => $code])->save();
        }

        return $this->referral_code;
    }

    /** Oyunlardan qazandığı hədiyyə kodları */
    public function prizes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WheelSpin::class)->whereNotNull('code');
    }

    public function gameResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GameResult::class);
    }

    /** Hesab təsdiqlənibmi: telefon Telegram ilə və ya e-poçt kod ilə */
    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null || $this->email_verified_at !== null;
    }

    /** Təsdiqlənməmiş hesab üçün təsdiq səhifəsi: nömrə ilə qeydiyyat — Telegram, e-poçt ilə — kod */
    public function verificationRoute(): string
    {
        return $this->phone || ! $this->email ? 'verify.telegram' : 'verify.email';
    }

    /** Təsdiqlənmiş istifadəçilər (Telegram və ya e-poçt ilə) */
    public function scopeVerified($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('phone_verified_at')->orWhereNotNull('email_verified_at'));
    }

    /**
     * Açıq səhifə hər 25 saniyədən bir siqnal göndərir. Bu müddətdə siqnal gəlməyibsə,
     * istifadəçi artıq saytda sayılmır (gizli vərəqlərdə brauzer taymerləri yavaşlatdığı üçün ehtiyatla).
     */
    public const ONLINE_SECONDS = 75;

    /** Hazırda saytda olan təsdiqlənmiş istifadəçilər (səhifəsi açıq olanlar) */
    public function scopeOnline($query)
    {
        // xidməti admin hesabı (nömrəsiz, e-poçtu təsdiqlənməmiş) siyahıda görünmür
        return $query->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('phone_verified_at')->whereNotNull('phone'))
                ->orWhereNotNull('email_verified_at'))
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_SECONDS));
    }

    /** İctimai göstəriş üçün qısa ad: soyad + adın baş hərfi, "Əli Məmmədzadə" → "Məmmədzadə Ə" */
    public function getShortNameAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name ?: 'Oyunçu'));

        return isset($parts[1]) ? $parts[1] . ' ' . mb_substr($parts[0], 0, 1) : $parts[0];
    }
}
