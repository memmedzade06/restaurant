<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** İstifadəçi: adminə sual yazır və cavabları görür (bir söhbət — "Mesajlar") */
class SupportController extends Controller
{
    /** Söhbətdə göstərilən son mesajların sayı */
    private const HISTORY = 200;

    /** Mesajdakı fayl: şəkil brauzerdə açılır, qalanı yüklənir. Başqasının faylı görünmür (404) */
    public function file(Request $request, SupportMessage $message): StreamedResponse
    {
        $user = $request->user();
        abort_unless($message->attachment_path && ($user->is_admin || $message->user_id === $user->id), 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        $headers = ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'];

        return $message->isImageAttachment()
            ? Storage::disk('local')->response($message->attachment_path, $message->attachment_name, $headers)
            : Storage::disk('local')->download($message->attachment_path, $message->attachment_name, $headers);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->is_admin) {
            return redirect()->route('admin.support'); // admin cavabları paneldən yazır
        }

        $messages = SupportMessage::where('user_id', $user->id)
            ->latest('id')->take(self::HISTORY)->get()->reverse()->values();

        SupportMessage::unreadForUser($user->id)->update(['read_at' => now()]);

        return view('frontend.support.index', [
            'messages' => $messages->map->toChat(false),
            'adminOnline' => SupportMessage::adminOnline(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user->is_admin, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . SupportMessage::MAX_LENGTH],
        ], [
            'body.required' => 'Mesajı yazın.',
            'body.max' => 'Mesaj ən çox ' . SupportMessage::MAX_LENGTH . ' simvol ola bilər.',
        ]);

        $message = SupportMessage::create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'from_admin' => false,
            'body' => trim($data['body']),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toChat(false)], 201);
        }

        return redirect()->route('support');
    }

    /** Küncdəki pəncərə bağlı olanda: oxunmamış cavabların sayı (nişan üçün) */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => SupportMessage::unreadForUser($request->user()->id)->count(),
            'admin_online' => SupportMessage::adminOnline(),
        ]);
    }

    /** Açıq səhifə yeni cavabları soruşur (?after=son mesajın id-si) */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $after = max(0, (int) $request->query('after'));

        $messages = SupportMessage::where('user_id', $user->id)->where('id', '>', $after)
            ->orderBy('id')->take(self::HISTORY)->get();

        if ($messages->contains('from_admin', true)) {
            SupportMessage::unreadForUser($user->id)->where('id', '>', $after)->update(['read_at' => now()]);
        }

        return response()->json([
            'messages' => $messages->map->toChat(false)->values(),
            'read_until' => SupportMessage::readUntil($user->id, false), // admin oxuyubsa — ✓✓
            'admin_online' => SupportMessage::adminOnline(),
        ]);
    }
}
