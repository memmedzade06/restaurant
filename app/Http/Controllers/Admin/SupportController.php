<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Admin: istifadəçilərdən gələn mesajlar — söhbətlərin siyahısı, söhbəti açıb cavab yazmaq */
class SupportController extends Controller
{
    private const HISTORY = 300;

    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        // hər istifadəçi üçün bir sətir: son mesaj və oxunmamış mesajların sayı
        $threads = SupportMessage::query()
            ->select('user_id')
            ->selectRaw('MAX(id) AS last_id')
            ->selectRaw('SUM(CASE WHEN from_admin = 0 AND read_at IS NULL THEN 1 ELSE 0 END) AS unread')
            ->groupBy('user_id')
            // "hamıya mesaj" və hədiyyə barədə avtomatik mesajlar hər istifadəçiyə yazılır — siyahı onlarla dolmasın:
            // yalnız istifadəçinin özü yazıbsa və ya admin ona fərdi yazıbsa
            ->havingRaw('SUM(CASE WHEN from_admin = 0 OR (broadcast_id IS NULL AND sender_id IS NOT NULL) THEN 1 ELSE 0 END) > 0')
            ->when($filter === 'unread', fn ($q) => $q->havingRaw('SUM(CASE WHEN from_admin = 0 AND read_at IS NULL THEN 1 ELSE 0 END) > 0'))
            ->orderByDesc('last_id')
            ->paginate(30)->withQueryString();

        $last = SupportMessage::whereIn('id', $threads->pluck('last_id'))->get()->keyBy('id');
        $users = User::whereIn('id', $threads->pluck('user_id'))->get(['id', 'name', 'phone', 'email'])->keyBy('id');

        return view('frontend.admin.support', [
            'threads' => $threads,
            'last' => $last,
            'users' => $users,
            'filter' => $filter,
            'unreadThreads' => SupportMessage::unreadForAdmin()->distinct()->count('user_id'),
        ]);
    }

    public function show(User $user): View
    {
        abort_if($user->is_admin, 404); // adminlər bir-birinə buradan yazmır
        $messages = SupportMessage::with('sender:id,name')->where('user_id', $user->id)
            ->latest('id')->take(self::HISTORY)->get()->reverse()->values();

        SupportMessage::unreadForAdmin()->where('user_id', $user->id)->update(['read_at' => now()]);

        return view('frontend.admin.support-thread', [
            'user' => $user,
            'messages' => $messages->map->toChat(true),
        ]);
    }

    public function reply(Request $request, User $user): JsonResponse|RedirectResponse
    {
        abort_if($user->is_admin, 404);
        $data = $request->validate([
            'body' => ['required_without:file', 'nullable', 'string', 'max:' . SupportMessage::MAX_LENGTH],
            'file' => SupportMessage::fileRules(),
        ], [
            'body.required_without' => 'Cavabı yazın və ya fayl əlavə edin.',
            'body.max' => 'Cavab ən çox ' . SupportMessage::MAX_LENGTH . ' simvol ola bilər.',
        ] + SupportMessage::fileMessages());

        $file = $request->hasFile('file') ? SupportMessage::storeUpload($request->file('file')) : [];

        $message = DB::transaction(function () use ($request, $user, $data, $file) {
            // cavab yazılırsa, istifadəçinin əvvəlki mesajları oxunmuş sayılır
            SupportMessage::unreadForAdmin()->where('user_id', $user->id)->update(['read_at' => now()]);

            return SupportMessage::create([
                'user_id' => $user->id,
                'sender_id' => $request->user()->id,
                'from_admin' => true,
                'body' => trim((string) ($data['body'] ?? '')),
            ] + $file);
        });
        $message->setRelation('sender', $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toChat(true)], 201);
        }

        return redirect()->route('admin.support.show', $user);
    }

    /** Açıq söhbət yeni mesajları soruşur (?after=son mesajın id-si) */
    public function poll(Request $request, User $user): JsonResponse
    {
        $after = max(0, (int) $request->query('after'));

        $messages = SupportMessage::with('sender:id,name')->where('user_id', $user->id)
            ->where('id', '>', $after)->orderBy('id')->take(self::HISTORY)->get();

        if ($messages->contains('from_admin', false)) {
            SupportMessage::unreadForAdmin()->where('user_id', $user->id)->where('id', '>', $after)->update(['read_at' => now()]);
        }

        return response()->json([
            'messages' => $messages->map->toChat(true)->values(),
            'read_until' => SupportMessage::readUntil($user->id, true), // istifadəçi oxuyubsa — ✓✓
        ]);
    }
}
