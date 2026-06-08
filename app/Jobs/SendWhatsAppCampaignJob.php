<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    private const BATCH_SIZE = 200;
    private const BATCH_DELAY_SECONDS = 60;

    public function __construct(
        public int $campaignId
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $campaign = WhatsAppCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status === 'cancelled') {
            return;
        }

        $account = $campaign->account;
        if (!$account || !$account->phone_number_id) {
            $campaign->update(['status' => 'failed']);
            return;
        }

        // Fail-closed safety net: never blast a template that requires header media without it.
        $requiredHeader = $whatsApp->templateRequiresHeaderMedia($account, $campaign->template_name);
        if ($requiredHeader && !WhatsAppService::componentsHaveHeaderMedia($campaign->template_components ?? [])) {
            $campaign->campaignMessages()->where('status', 'pending')->update([
                'status' => 'failed',
                'error_message' => "Plantilla requiere encabezado de {$requiredHeader} pero no se adjunto media. Envio abortado.",
                'sent_at' => now(),
            ]);
            $this->refreshCounts($campaign);
            $campaign->update(['status' => 'failed', 'completed_at' => now()]);
            Log::warning("Campaign #{$campaign->id} aborted: template '{$campaign->template_name}' requires {$requiredHeader} header media but none was attached.");
            return;
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);

        $pendingMessages = $campaign->campaignMessages()
            ->where('status', 'pending')
            ->get();

        $batches = $pendingMessages->chunk(self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batch) {
            // Delay between batches (except the first one)
            if ($batchIndex > 0) {
                sleep(self::BATCH_DELAY_SECONDS);
            }

            // Check if cancelled mid-send
            $campaign->refresh();
            if ($campaign->status === 'cancelled') {
                return;
            }

            foreach ($batch as $msg) {
                $this->sendSingleMessage($whatsApp, $account, $campaign, $msg);

                // Small delay between individual messages (100ms)
                usleep(100000);
            }

            // Update progress after each batch
            $this->refreshCounts($campaign);
        }

        $this->refreshCounts($campaign);
        $campaign->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Log::info("Campaign #{$campaign->id} completed", [
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
        ]);
    }

    private function sendSingleMessage(
        WhatsAppService $whatsApp,
        WhatsAppAccount $account,
        WhatsAppCampaign $campaign,
        WhatsAppCampaignMessage $msg
    ): void {
        $components = $campaign->template_components ?? [];

        // Resolve per-contact variables: replace {{nombre}} with actual contact name
        $resolvedComponents = $this->resolveVariables($components, $msg);

        $result = $whatsApp->sendTemplateMessage(
            $account,
            $msg->phone,
            $campaign->template_name,
            $campaign->template_language,
            $resolvedComponents
        );

        if ($result['success']) {
            $wamid = $result['data']['messages'][0]['id'] ?? null;
            $msg->update([
                'status' => 'sent',
                'wamid' => $wamid,
                'sent_at' => now(),
            ]);
        } else {
            $msg->update([
                'status' => 'failed',
                'error_message' => mb_substr((string) $result['error'], 0, 2000),
                'sent_at' => now(),
            ]);
        }
    }

    private function resolveVariables(array $components, WhatsAppCampaignMessage $msg): array
    {
        $contact = $msg->contact;
        $contactName = $msg->contact_name ?? $contact?->name ?? '';

        $replacements = [
            'nombre' => $contactName,
            'name' => $contactName,
            'contact_name' => $contactName,
            'nombre_contacto' => $contactName,
            'phone' => $msg->phone,
            'country' => $contact?->country ?? '',
            'status' => $contact?->status?->name ?? '',
        ];

        foreach ($components as &$component) {
            if (isset($component['parameters'])) {
                foreach ($component['parameters'] as &$param) {
                    if ($param['type'] === 'text' && isset($param['text'])) {
                        foreach ($replacements as $key => $value) {
                            $param['text'] = str_ireplace('{{' . $key . '}}', $value, $param['text']);
                        }
                        if (trim($param['text']) === '' || preg_match('/^\{\{.*\}\}$/', $param['text'])) {
                            $param['text'] = $contactName ?: 'Amigo';
                        }
                    }
                }
            }
        }

        return $components;
    }

    private function refreshCounts(WhatsAppCampaign $campaign): void
    {
        $campaign->update([
            'sent_count' => $campaign->campaignMessages()->where('status', '!=', 'pending')->where('status', '!=', 'failed')->count(),
            'failed_count' => $campaign->campaignMessages()->where('status', 'failed')->count(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $campaign = WhatsAppCampaign::find($this->campaignId);
        $campaign?->update(['status' => 'failed']);
        Log::error("Campaign #{$this->campaignId} failed", ['error' => $exception->getMessage()]);
    }
}
