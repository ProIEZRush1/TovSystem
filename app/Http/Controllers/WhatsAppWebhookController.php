<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
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

                // Handle incoming messages
                foreach ($value['messages'] ?? [] as $msg) {
                    $this->handleIncomingMessage($account, $msg, $value['contacts'] ?? []);
                }

                // Handle status updates
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

        switch ($type) {
            case 'text':
                $content = $msg['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $content = '[' . $type . ']';
                break;
            case 'reaction':
                $content = $msg['reaction']['emoji'] ?? '';
                break;
            default:
                $content = '[' . $type . ']';
        }

        // Find linked contact
        $contact = Contact::where('phone', 'like', '%' . substr($from, -10))->first();

        // Update contact name from WhatsApp profile if missing
        if ($contact && empty($contact->name) && !empty($contacts)) {
            $profileName = $contacts[0]['profile']['name'] ?? null;
            if ($profileName) {
                $contact->update(['name' => $profileName]);
            }
        }

        WhatsAppMessage::updateOrCreate(
            ['wamid' => $wamid],
            [
                'whatsapp_account_id' => $account->id,
                'contact_id' => $contact?->id,
                'remote_phone' => $from,
                'direction' => 'inbound',
                'type' => $type,
                'content' => $content,
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

        WhatsAppMessage::where('wamid', $wamid)->update(['status' => $newStatus]);
    }
}
