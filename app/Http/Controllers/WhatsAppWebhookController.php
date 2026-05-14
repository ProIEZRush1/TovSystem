<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaignMessage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $verifyToken) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $payload = $request->all();

        Log::warning('WhatsApp webhook received', ['payload' => $payload]);

        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response('OK', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (!$phoneNumberId) {
                    continue;
                }

                $account = WhatsAppAccount::where('phone_number_id', $phoneNumberId)->first();
                if (!$account) {
                    Log::warning('WhatsApp webhook: Unknown phone_number_id', ['id' => $phoneNumberId]);
                    continue;
                }

                foreach ($value['messages'] ?? [] as $msg) {
                    $this->handleIncomingMessage($account, $msg, $value['contacts'] ?? []);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleStatusUpdate($status);
                }
            }
        }

        return response('OK', 200);
    }

    private function handleIncomingMessage(WhatsAppAccount $account, array $msg, array $contacts): void
    {
        $from = $msg['from'] ?? '';
        $wamid = $msg['id'] ?? '';
        $type = $msg['type'] ?? 'text';
        $content = '';
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;

        switch ($type) {
            case 'text':
                $content = $msg['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $mediaId = $msg[$type]['id'] ?? null;
                $mediaMimeType = $msg[$type]['mime_type'] ?? null;
                $mediaFilename = $msg[$type]['filename'] ?? null;
                $content = $msg[$type]['caption'] ?? "[{$type}]";

                if ($mediaId) {
                    try {
                        $whatsApp = app(WhatsAppService::class);
                        $mediaUrl = $whatsApp->downloadMedia($account, $mediaId);
                    } catch (\Throwable $e) {
                        Log::error('Failed to download media', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
                        $mediaUrl = null;
                    }
                }
                break;
            case 'location':
                $lat = $msg['location']['latitude'] ?? '';
                $lng = $msg['location']['longitude'] ?? '';
                $locName = $msg['location']['name'] ?? '';
                $content = $locName ? "{$locName} ({$lat}, {$lng})" : "({$lat}, {$lng})";
                break;
            case 'reaction':
                $content = $msg['reaction']['emoji'] ?? '';
                break;
            default:
                $content = "[{$type}]";
        }

        $contact = Contact::where('phone', 'like', '%' . substr($from, -10))->first();

        if ($contact && empty($contact->name) && !empty($contacts)) {
            $profileName = $contacts[0]['profile']['name'] ?? null;
            if ($profileName) {
                $contact->update(['name' => $profileName]);
            }
        }

        // Find or create conversation
        $conversation = WhatsAppConversation::firstOrCreate(
            ['whatsapp_account_id' => $account->id, 'remote_phone' => $from],
            [
                'contact_id' => $contact?->id,
                'contact_name' => $contact?->name ?? ($contacts[0]['profile']['name'] ?? null),
            ]
        );

        // Update conversation metadata
        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => mb_substr($content, 0, 100),
            'last_message_direction' => 'inbound',
            'unread_count' => $conversation->unread_count + 1,
            'window_expires_at' => now()->addHours(24),
            'contact_name' => $contact?->name ?? $conversation->contact_name ?? ($contacts[0]['profile']['name'] ?? null),
            'contact_id' => $contact?->id ?? $conversation->contact_id,
        ]);

        WhatsAppMessage::updateOrCreate(
            ['wamid' => $wamid],
            [
                'whatsapp_account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'contact_id' => $contact?->id,
                'remote_phone' => $from,
                'direction' => 'inbound',
                'type' => $type,
                'content' => $content,
                'media_url' => $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'media_filename' => $mediaFilename,
                'status' => 'received',
                'sent_at' => isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp($msg['timestamp']) : now(),
            ]
        );
    }

    private function handleStatusUpdate(array $status): void
    {
        $wamid = $status['id'] ?? '';
        $newStatus = $status['status'] ?? '';

        if (!$wamid || !$newStatus) {
            return;
        }

        $update = ['status' => $newStatus];

        if ($newStatus === 'failed' && !empty($status['errors'])) {
            $err = $status['errors'][0] ?? [];
            $update['error_code'] = $err['code'] ?? null;
            $update['error_message'] = trim(($err['title'] ?? '') . ': ' . ($err['error_data']['details'] ?? $err['message'] ?? ''), ': ');
        }

        // Update in whatsapp_messages
        WhatsAppMessage::where('wamid', $wamid)->update($update);

        // Also update campaign messages if applicable
        WhatsAppCampaignMessage::where('wamid', $wamid)->update([
            'status' => $newStatus,
            'error_message' => $update['error_message'] ?? null,
            'error_code' => $update['error_code'] ?? null,
        ]);

        // Update conversation delivered/read counts on campaign
        if (in_array($newStatus, ['delivered', 'read'])) {
            $campaignMsg = WhatsAppCampaignMessage::where('wamid', $wamid)->first();
            if ($campaignMsg) {
                $campaign = $campaignMsg->campaign;
                if ($campaign) {
                    if ($newStatus === 'delivered') {
                        $campaign->increment('delivered_count');
                    } elseif ($newStatus === 'read') {
                        $campaign->increment('read_count');
                    }
                }
            }
        }
    }
}
