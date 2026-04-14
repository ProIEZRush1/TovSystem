<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(): Response
    {
        $accounts = WhatsAppAccount::withCount('messages')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('WhatsApp/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:30',
            'waba_id' => 'required|string|max:50',
            'access_token' => 'required|string',
        ]);

        $account = WhatsAppAccount::create($validated);

        // Auto-fetch phone number ID
        $whatsApp->fetchPhoneNumbers($account);

        return back()->with('success', 'WhatsApp account added.');
    }

    public function update(Request $request, WhatsAppAccount $account, WhatsAppService $whatsApp): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:30',
            'waba_id' => 'required|string|max:50',
            'access_token' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['access_token'])) {
            unset($validated['access_token']);
        }

        $account->update($validated);

        // Re-fetch phone number ID if token changed
        if (isset($validated['access_token'])) {
            $whatsApp->fetchPhoneNumbers($account);
        }

        return back()->with('success', 'WhatsApp account updated.');
    }

    public function destroy(WhatsAppAccount $account): RedirectResponse
    {
        $account->delete();

        return redirect()->route('whatsapp.index')->with('success', 'WhatsApp account deleted.');
    }

    public function refresh(WhatsAppAccount $account, WhatsAppService $whatsApp): JsonResponse
    {
        $phones = $whatsApp->fetchPhoneNumbers($account->fresh());

        return response()->json([
            'phone_number_id' => $account->fresh()->phone_number_id,
            'verified_name' => $account->fresh()->verified_name,
            'quality_rating' => $account->fresh()->quality_rating,
            'phones' => $phones,
        ]);
    }

    public function chat(WhatsAppAccount $account): Response
    {
        // Get distinct conversations (grouped by remote_phone)
        $conversations = WhatsAppMessage::where('whatsapp_account_id', $account->id)
            ->selectRaw('remote_phone, MAX(created_at) as last_message_at, COUNT(*) as message_count')
            ->groupBy('remote_phone')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(function ($conv) {
                $contact = Contact::where('phone', 'like', '%' . substr($conv->remote_phone, -10))->first();
                return [
                    'remote_phone' => $conv->remote_phone,
                    'last_message_at' => $conv->last_message_at,
                    'message_count' => $conv->message_count,
                    'contact_name' => $contact?->name,
                    'contact_id' => $contact?->id,
                ];
            });

        return Inertia::render('WhatsApp/Chat', [
            'account' => $account->only(['id', 'name', 'phone_number', 'phone_number_id', 'verified_name', 'is_active']),
            'conversations' => $conversations,
        ]);
    }

    public function messages(WhatsAppAccount $account, Request $request): JsonResponse
    {
        $phone = $request->input('phone');

        $messages = WhatsAppMessage::where('whatsapp_account_id', $account->id)
            ->where('remote_phone', $phone)
            ->orderBy('created_at')
            ->limit(200)
            ->get(['id', 'direction', 'type', 'content', 'template_name', 'status', 'sent_at', 'created_at']);

        return response()->json($messages);
    }

    public function send(WhatsAppAccount $account, Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:4096',
        ]);

        $result = $whatsApp->sendTextMessage($account, $validated['phone'], $validated['message']);

        if (!$result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to send message'], 422);
        }

        $wamid = $result['data']['messages'][0]['id'] ?? null;

        // Find linked contact
        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);
        $contact = Contact::where('phone', 'like', '%' . substr($phone, -10))->first();

        $msg = WhatsAppMessage::create([
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact?->id,
            'remote_phone' => $phone,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $validated['message'],
            'wamid' => $wamid,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json($msg);
    }

    public function bulkSend(WhatsAppAccount $account, Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $validated = $request->validate([
            'phones' => 'required|array|min:1',
            'phones.*' => 'string',
            'type' => 'required|in:text,template',
            'message' => 'required_if:type,text|nullable|string|max:4096',
            'template_name' => 'required_if:type,template|nullable|string',
            'language_code' => 'nullable|string',
        ]);

        $sent = 0;
        $failed = 0;

        foreach ($validated['phones'] as $phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 7) {
                $failed++;
                continue;
            }

            if ($validated['type'] === 'template') {
                $result = $whatsApp->sendTemplateMessage(
                    $account,
                    $cleanPhone,
                    $validated['template_name'],
                    $validated['language_code'] ?? 'es_MX'
                );
            } else {
                $result = $whatsApp->sendTextMessage($account, $cleanPhone, $validated['message']);
            }

            if ($result['success']) {
                $wamid = $result['data']['messages'][0]['id'] ?? null;
                $contact = Contact::where('phone', 'like', '%' . substr($cleanPhone, -10))->first();

                WhatsAppMessage::create([
                    'whatsapp_account_id' => $account->id,
                    'contact_id' => $contact?->id,
                    'remote_phone' => $cleanPhone,
                    'direction' => 'outbound',
                    'type' => $validated['type'],
                    'content' => $validated['message'] ?? null,
                    'template_name' => $validated['template_name'] ?? null,
                    'wamid' => $wamid,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $sent++;
            } else {
                $failed++;
            }

            // Rate limit: ~10 messages/second to be safe
            usleep(100000);
        }

        return response()->json(['sent' => $sent, 'failed' => $failed]);
    }

    public function templates(WhatsAppAccount $account, WhatsAppService $whatsApp): JsonResponse
    {
        $templates = $whatsApp->fetchTemplates($account);

        return response()->json($templates);
    }
}
