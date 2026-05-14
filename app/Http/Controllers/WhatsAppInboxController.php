<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppInboxController extends Controller
{
    public function index(WhatsAppAccount $account): Response
    {
        $conversations = $account->conversations()
            ->with('contact:id,name,phone')
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get();

        return Inertia::render('WhatsApp/Inbox', [
            'account' => $account->only(['id', 'name', 'phone_number', 'phone_number_id', 'verified_name']),
            'conversations' => $conversations,
        ]);
    }

    public function messages(WhatsAppAccount $account, WhatsAppConversation $conversation): JsonResponse
    {
        $messages = WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->limit(200)
            ->get([
                'id', 'direction', 'type', 'content', 'media_url', 'media_mime_type',
                'media_filename', 'template_name', 'status', 'error_code',
                'error_message', 'sent_at', 'created_at',
            ]);

        // Mark conversation as read
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return response()->json([
            'messages' => $messages,
            'window_open' => $conversation->isWindowOpen(),
            'window_expires_at' => $conversation->window_expires_at,
        ]);
    }

    public function send(WhatsAppAccount $account, WhatsAppConversation $conversation, Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required_without:media|nullable|string|max:4096',
            'type' => 'nullable|in:text,image,video,audio,document',
            'media_url' => 'nullable|string',
            'media_file' => 'nullable|file|max:16384',
        ]);

        $type = $validated['type'] ?? 'text';
        $phone = $conversation->remote_phone;

        if ($type === 'text') {
            $result = $whatsApp->sendTextMessage($account, $phone, $validated['message']);
            $content = $validated['message'];
            $mediaUrl = null;
        } else {
            // Handle file upload
            $mediaUrl = $validated['media_url'] ?? null;
            if ($request->hasFile('media_file')) {
                $file = $request->file('media_file');
                $path = $file->store('whatsapp/uploads/' . date('Y/m'), 'public');
                $mediaUrl = url('/storage/' . $path);
            }

            if (!$mediaUrl) {
                return response()->json(['error' => 'No media provided'], 422);
            }

            $caption = $validated['message'] ?? null;
            $result = match ($type) {
                'image' => $whatsApp->sendImage($account, $phone, $mediaUrl, $caption),
                'video' => $whatsApp->sendVideo($account, $phone, $mediaUrl, $caption),
                'audio' => $whatsApp->sendAudio($account, $phone, $mediaUrl),
                'document' => $whatsApp->sendDocument($account, $phone, $mediaUrl, null, $caption),
                default => ['success' => false, 'error' => 'Invalid type'],
            };
            $content = $caption ?? "[{$type}]";
        }

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        $wamid = $result['data']['messages'][0]['id'] ?? null;

        $msg = WhatsAppMessage::create([
            'whatsapp_account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'remote_phone' => $phone,
            'direction' => 'outbound',
            'type' => $type,
            'content' => $content,
            'media_url' => $type !== 'text' ? $mediaUrl : null,
            'wamid' => $wamid,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => mb_substr($content, 0, 100),
            'last_message_direction' => 'outbound',
        ]);

        return response()->json($msg);
    }

    public function sendTemplate(WhatsAppAccount $account, WhatsAppConversation $conversation, Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'template_name' => 'required|string',
            'language_code' => 'required|string',
            'components' => 'nullable|array',
        ]);

        $result = $whatsApp->sendTemplateMessage(
            $account,
            $conversation->remote_phone,
            $validated['template_name'],
            $validated['language_code'],
            $validated['components'] ?? []
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        $wamid = $result['data']['messages'][0]['id'] ?? null;

        $msg = WhatsAppMessage::create([
            'whatsapp_account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'remote_phone' => $conversation->remote_phone,
            'direction' => 'outbound',
            'type' => 'template',
            'content' => '[template: ' . $validated['template_name'] . ']',
            'template_name' => $validated['template_name'],
            'wamid' => $wamid,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => '[Plantilla] ' . $validated['template_name'],
            'last_message_direction' => 'outbound',
        ]);

        return response()->json($msg);
    }

    public function newConversation(WhatsAppAccount $account, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);
        $contact = Contact::where('phone', 'like', '%' . substr($phone, -10))->first();

        $conversation = WhatsAppConversation::firstOrCreate(
            ['whatsapp_account_id' => $account->id, 'remote_phone' => $phone],
            [
                'contact_id' => $contact?->id,
                'contact_name' => $contact?->name,
                'last_message_at' => now(),
            ]
        );

        return response()->json($conversation->load('contact:id,name,phone'));
    }

    public function archive(WhatsAppAccount $account, WhatsAppConversation $conversation): JsonResponse
    {
        $conversation->update(['is_archived' => true]);
        return response()->json(['archived' => true]);
    }

    public function unarchive(WhatsAppAccount $account, WhatsAppConversation $conversation): JsonResponse
    {
        $conversation->update(['is_archived' => false]);
        return response()->json(['archived' => false]);
    }

    public function assign(WhatsAppAccount $account, WhatsAppConversation $conversation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $conversation->update(['assigned_to' => $validated['user_id']]);
        return response()->json(['assigned_to' => $validated['user_id']]);
    }
}
