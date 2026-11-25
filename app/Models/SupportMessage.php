<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adminlə yazışma mesajı: user_id — söhbətin sahibi, from_admin — cavabı admin yazıb */
class SupportMessage extends Model
{
    public const MAX_LENGTH = 2000;

    protected $fillable = ['user_id', 'sender_id', 'from_admin', 'body', 'read_at'];

    protected $casts = [
        'from_admin' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** İstifadəçinin hələ oxumadığı admin cavabları */
    public function scopeUnreadForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->where('from_admin', true)->whereNull('read_at');
    }

    /** Adminin hələ oxumadığı istifadəçi mesajları */
    public function scopeUnreadForAdmin(Builder $query): Builder
    {
        return $query->where('from_admin', false)->whereNull('read_at');
    }

    /** Brauzerə göndərilən forma. $viewerIsAdmin — kim baxır (öz mesajı sağda görünür) */
    public function toChat(bool $viewerIsAdmin): array
    {
        $local = $this->created_at->timezone('Asia/Baku');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->from_admin === $viewerIsAdmin,
            'author' => $this->from_admin ? ($viewerIsAdmin ? ($this->sender?->short_name ?? 'Admin') : '50 Qəpik') : null,
            'time' => $local->isToday() ? $local->format('H:i') : $local->format('d.m.Y H:i'),
        ];
    }
}
