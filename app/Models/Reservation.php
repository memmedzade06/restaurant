<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['table_key', 'date', 'time', 'name', 'phone', 'user_id', 'status'];

    protected $casts = ['date' => 'date'];

    protected $attributes = ['status' => self::STATUS_ACTIVE];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    /** Bütün masalar: açar => [label, floor, x, y, w, h] */
    public static function tables(): array
    {
        $all = [];
        foreach (config('reservations.floors', []) as $floor => $f) {
            foreach ($f['tables'] as $key => $t) {
                $all[$key] = $t + ['floor' => (int) $floor, 'floor_name' => $f['name']];
            }
        }

        return $all;
    }

    /** "2-ci mərtəbə · Masa 25" */
    public function getTableNameAttribute(): string
    {
        $t = self::tables()[$this->table_key] ?? null;

        return $t ? "{$t['floor_name']} · {$t['label']}" : $this->table_key;
    }

    /** "HH:MM" → gün başlanğıcından dəqiqə */
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => 0]);

        return $h * 60 + $m;
    }
}
