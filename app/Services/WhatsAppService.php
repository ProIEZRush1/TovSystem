<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com';

    /**
     * Fetch phone numbers from the WABA and update the account.
     */
    public function fetchPhoneNumbers(WhatsAppAccount $account): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->waba_id . '/phone_numbers';

        $response = Http::withToken($account->access_token)
            ->get($url);

        if (!$response->successful()) {
            Log::error('WhatsApp API: Failed to fetch phone numbers', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $data = $response->json('data', []);

        // Auto-match and update the account's phone_number_id
        foreach ($data as $phone) {
            $displayPhone = preg_replace('/[^0-9]/', '', $phone['display_phone_number'] ?? '');
            $accountPhone = preg_replace('/[^0-9]/', '', $account->phone_number);

            if ($displayPhone === $accountPhone || str_ends_with($displayPhone, $accountPhone) || str_ends_with($accountPhone, $displayPhone)) {
                $account->update([
                    'phone_number_id' => $phone['id'],
                    'verified_name' => $phone['verified_name'] ?? null,
                    'quality_rating' => $phone['quality_rating'] ?? null,
                ]);
                break;
            }
        }

        return $data;
    }

    /**
     * Send a text message. Returns ['success' => bool, 'data' => ?array, 'error' => ?string].
     */
    public function sendTextMessage(WhatsAppAccount $account, string $to, string $body): array
    {
        if (empty($account->phone_number_id)) {
            return ['success' => false, 'data' => null, 'error' => 'Account not connected: missing phone_number_id. Click Refresh on the account.'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->phone_number_id . '/messages';

        $to = preg_replace('/[^0-9]/', '', $to);

        $response = Http::withToken($account->access_token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::error('WhatsApp API: Failed to send message', [
                'account_id' => $account->id,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(WhatsAppAccount $account, string $to, string $templateName, string $languageCode = 'es_MX', array $components = []): array
    {
        if (empty($account->phone_number_id)) {
            return ['success' => false, 'data' => null, 'error' => 'Account not connected.'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->phone_number_id . '/messages';
        $to = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($account->access_token)->post($url, $payload);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::error('WhatsApp API: Failed to send template', [
                'account_id' => $account->id,
                'to' => $to,
                'template' => $templateName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }

    /**
     * Fetch approved message templates.
     */
    public function fetchTemplates(WhatsAppAccount $account): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->waba_id . '/message_templates';

        $response = Http::withToken($account->access_token)
            ->get($url, ['limit' => 100]);

        if (!$response->successful()) {
            Log::error('WhatsApp API: Failed to fetch templates', [
                'account_id' => $account->id,
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json('data', []);
    }
}
