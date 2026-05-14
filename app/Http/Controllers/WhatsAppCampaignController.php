<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppCampaignJob;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppCampaignController extends Controller
{
    public function index(WhatsAppAccount $account): Response
    {
        $campaigns = $account->campaigns()
            ->orderByDesc('id')
            ->get()
            ->append('progress');

        return Inertia::render('WhatsApp/Campaigns', [
            'account' => $account->only(['id', 'name', 'phone_number']),
            'campaigns' => $campaigns,
        ]);
    }

    public function create(WhatsAppAccount $account, WhatsAppService $whatsApp): Response
    {
        $templates = collect($whatsApp->fetchTemplates($account))
            ->filter(fn ($t) => ($t['status'] ?? '') === 'APPROVED')
            ->values();

        return Inertia::render('WhatsApp/CampaignCreate', [
            'account' => $account->only(['id', 'name', 'phone_number']),
            'templates' => $templates,
        ]);
    }

    public function store(WhatsAppAccount $account, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template_name' => 'required|string',
            'template_language' => 'required|string|max:10',
            'template_components' => 'nullable|array',
            'audience_filters' => 'nullable|array',
        ]);

        // Build audience from filters
        $query = Contact::query();
        $filters = $validated['audience_filters'] ?? [];

        if (!empty($filters['status_ids'])) {
            $query->whereIn('status_id', $filters['status_ids']);
        }
        if (!empty($filters['countries'])) {
            $query->whereIn('country', $filters['countries']);
        }
        if (!empty($filters['label_ids'])) {
            $query->whereHas('labels', fn ($q) => $q->whereIn('labels.id', $filters['label_ids']));
        }
        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('phone', 'like', '%' . $filters['search'] . '%')
                ->orWhere('name', 'like', '%' . $filters['search'] . '%'));
        }

        $contacts = $query->whereNotNull('phone')->get(['id', 'phone', 'name']);

        if ($contacts->isEmpty()) {
            return back()->with('error', 'No se encontraron contactos con los filtros seleccionados.');
        }

        $campaign = WhatsAppCampaign::create([
            'whatsapp_account_id' => $account->id,
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'template_name' => $validated['template_name'],
            'template_language' => $validated['template_language'],
            'template_components' => $validated['template_components'],
            'audience_filters' => $validated['audience_filters'],
            'status' => 'draft',
            'total_recipients' => $contacts->count(),
        ]);

        // Create campaign message rows
        $rows = $contacts->map(fn ($c) => [
            'whatsapp_campaign_id' => $campaign->id,
            'contact_id' => $c->id,
            'phone' => $c->phone,
            'contact_name' => $c->name,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        foreach (array_chunk($rows, 500) as $chunk) {
            WhatsAppCampaignMessage::insert($chunk);
        }

        return redirect()->route('whatsapp.campaigns.show', [$account->id, $campaign->id])
            ->with('success', "Campana creada con {$contacts->count()} destinatarios.");
    }

    public function show(WhatsAppAccount $account, WhatsAppCampaign $campaign): Response
    {
        $campaign->append('progress');

        $statusCounts = $campaign->campaignMessages()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $recentMessages = $campaign->campaignMessages()
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'phone', 'contact_name', 'status', 'error_message', 'sent_at']);

        return Inertia::render('WhatsApp/CampaignShow', [
            'account' => $account->only(['id', 'name', 'phone_number']),
            'campaign' => $campaign,
            'statusCounts' => $statusCounts,
            'recentMessages' => $recentMessages,
        ]);
    }

    public function send(WhatsAppAccount $account, WhatsAppCampaign $campaign): RedirectResponse
    {
        if ($campaign->status !== 'draft') {
            return back()->with('error', 'Esta campana ya fue enviada.');
        }

        SendWhatsAppCampaignJob::dispatch($campaign->id);

        return back()->with('success', 'Envio iniciado. Los mensajes se enviaran en lotes de 200.');
    }

    public function cancel(WhatsAppAccount $account, WhatsAppCampaign $campaign): RedirectResponse
    {
        if (!in_array($campaign->status, ['draft', 'sending'])) {
            return back()->with('error', 'No se puede cancelar.');
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Campana cancelada.');
    }

    public function status(WhatsAppAccount $account, WhatsAppCampaign $campaign): JsonResponse
    {
        $campaign->refresh();

        return response()->json([
            'status' => $campaign->status,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'delivered_count' => $campaign->delivered_count,
            'read_count' => $campaign->read_count,
            'total_recipients' => $campaign->total_recipients,
            'progress' => $campaign->progress,
        ]);
    }

    public function destroy(WhatsAppAccount $account, WhatsAppCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('whatsapp.campaigns.index', $account->id)
            ->with('success', 'Campana eliminada.');
    }
}
