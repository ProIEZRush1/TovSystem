<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com';

    /**
     * Fetch phone numbers from the WABA and update the account.
     */
    /**
     * Returns ['success' => bool, 'data' => array, 'error' => ?string].
     */
    public function fetchPhoneNumbers(WhatsAppAccount $account): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->waba_id . '/phone_numbers';

        try {
            $response = Http::withToken($account->access_token)->get($url);
        } catch (\Throwable $e) {
            Log::error('WhatsApp API: Connection failed fetching phone numbers', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'data' => [], 'error' => 'Connection error: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::error('WhatsApp API: Failed to fetch phone numbers', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'data' => [], 'error' => $err];
        }

        $data = $response->json('data', []);
        $matched = false;

        // Auto-match and update the account's phone_number_id
        foreach ($data as $phone) {
            $displayPhone = preg_replace('/[^0-9]/', '', $phone['display_phone_number'] ?? '');
            $accountPhone = preg_replace('/[^0-9]/', '', $account->phone_number);

            $last10Display = substr($displayPhone, -10);
            $last10Account = substr($accountPhone, -10);
            if ($displayPhone === $accountPhone || $last10Display === $last10Account || str_ends_with($displayPhone, $accountPhone) || str_ends_with($accountPhone, $displayPhone)) {
                $account->update([
                    'phone_number_id' => $phone['id'],
                    'verified_name' => $phone['verified_name'] ?? null,
                    'quality_rating' => $phone['quality_rating'] ?? null,
                ]);
                $matched = true;
                break;
            }
        }

        if (!$matched && !empty($data)) {
            $available = collect($data)->pluck('display_phone_number')->implode(', ');
            return ['success' => true, 'data' => $data, 'error' => "Tu numero {$account->phone_number} no coincide con los de esta WABA. Numeros disponibles: {$available}"];
        }

        if (empty($data)) {
            return ['success' => true, 'data' => [], 'error' => 'Esta WABA no tiene numeros de telefono registrados.'];
        }

        return ['success' => true, 'data' => $data, 'error' => null];
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

    /**
     * Create a message template on Meta.
     * Templates go through Meta's review process before they can be used.
     */
    public function createTemplate(WhatsAppAccount $account, array $payload): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->waba_id . '/message_templates';

        $response = Http::withToken($account->access_token)->post($url, $payload);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::error('WhatsApp API: Failed to create template', [
                'account_id' => $account->id,
                'name' => $payload['name'] ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }

    /**
     * Send an image message.
     */
    public function sendImage(WhatsAppAccount $account, string $to, string $imageUrl, ?string $caption = null): array
    {
        return $this->sendMedia($account, $to, 'image', ['link' => $imageUrl, 'caption' => $caption]);
    }

    /**
     * Send a video message.
     */
    public function sendVideo(WhatsAppAccount $account, string $to, string $videoUrl, ?string $caption = null): array
    {
        return $this->sendMedia($account, $to, 'video', ['link' => $videoUrl, 'caption' => $caption]);
    }

    /**
     * Send an audio message.
     */
    public function sendAudio(WhatsAppAccount $account, string $to, string $audioUrl): array
    {
        return $this->sendMedia($account, $to, 'audio', ['link' => $audioUrl]);
    }

    /**
     * Send a document message.
     */
    public function sendDocument(WhatsAppAccount $account, string $to, string $documentUrl, ?string $filename = null, ?string $caption = null): array
    {
        return $this->sendMedia($account, $to, 'document', array_filter([
            'link' => $documentUrl,
            'filename' => $filename,
            'caption' => $caption,
        ]));
    }

    /**
     * Send a location message.
     */
    public function sendLocation(WhatsAppAccount $account, string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null): array
    {
        if (empty($account->phone_number_id)) {
            return ['success' => false, 'data' => null, 'error' => 'Account not connected.'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->phone_number_id . '/messages';
        $to = preg_replace('/[^0-9]/', '', $to);

        $response = Http::withToken($account->access_token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'location',
            'location' => array_filter([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ]),
        ]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }

    /**
     * Generic media sender (image, video, audio, document).
     */
    private function sendMedia(WhatsAppAccount $account, string $to, string $type, array $mediaPayload): array
    {
        if (empty($account->phone_number_id)) {
            return ['success' => false, 'data' => null, 'error' => 'Account not connected.'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->phone_number_id . '/messages';
        $to = preg_replace('/[^0-9]/', '', $to);

        $response = Http::withToken($account->access_token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
            $type => array_filter($mediaPayload),
        ]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::error("WhatsApp API: Failed to send {$type}", [
                'account_id' => $account->id,
                'to' => $to,
                'status' => $response->status(),
            ]);
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }

    /**
     * Download media from Meta's servers using the media ID.
     * Returns the local storage path or null on failure.
     */
    public function downloadMedia(WhatsAppAccount $account, string $mediaId): ?string
    {
        // Step 1: Get the media URL from Meta
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $mediaId;
        $response = Http::withToken($account->access_token)->get($url);

        if (!$response->successful()) {
            Log::error('WhatsApp API: Failed to get media URL', ['media_id' => $mediaId]);
            return null;
        }

        $mediaUrl = $response->json('url');
        $mimeType = $response->json('mime_type', 'application/octet-stream');

        if (!$mediaUrl) return null;

        // Step 2: Download the actual file
        $fileResponse = Http::withToken($account->access_token)->get($mediaUrl);

        if (!$fileResponse->successful()) {
            Log::error('WhatsApp API: Failed to download media', ['url' => $mediaUrl]);
            return null;
        }

        // Step 3: Store locally
        $ext = $this->mimeToExtension($mimeType);
        $path = 'whatsapp/media/' . date('Y/m') . '/' . $mediaId . '.' . $ext;
        Storage::disk('public')->put($path, $fileResponse->body());

        return '/storage/' . $path;
    }

    /**
     * Upload media to Meta for use in template headers.
     */
    public function uploadMedia(WhatsAppAccount $account, string $filePath, string $mimeType): ?string
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->phone_number_id . '/media';

        $response = Http::withToken($account->access_token)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (!$response->successful()) {
            Log::error('WhatsApp API: Failed to upload media', ['status' => $response->status()]);
            return null;
        }

        return $response->json('id');
    }

    private function mimeToExtension(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'mp4') => 'mp4',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'opus') => 'opus',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'docx') => 'docx',
            str_contains($mime, 'xlsx') => 'xlsx',
            default => 'bin',
        };
    }

    /**
     * Delete a message template by name.
     */
    public function deleteTemplate(WhatsAppAccount $account, string $name): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $account->waba_id . '/message_templates';

        $response = Http::withToken($account->access_token)
            ->delete($url, ['name' => $name]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            return ['success' => false, 'data' => null, 'error' => $err];
        }

        return ['success' => true, 'data' => $response->json(), 'error' => null];
    }
}
