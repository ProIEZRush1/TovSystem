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
            'audience_source' => 'nullable|in:contacts,file',
            'audience_file' => 'nullable|file|mimes:xlsx,xls,csv|max:10240',
            'audience_rows' => 'nullable|array',
            'audience_rows.*.phone' => 'required_with:audience_rows|string',
            'audience_rows.*.name' => 'nullable|string',
        ]);

        $recipients = collect();

        if (($validated['audience_source'] ?? 'contacts') === 'file' && !empty($validated['audience_rows'])) {
            $recipients = collect($validated['audience_rows'])->map(fn ($r) => [
                'contact_id' => null,
                'phone' => PhoneCountryHelper::normalize($r['phone']),
                'name' => $r['name'] ?? null,
            ])->unique('phone')->values();
        } elseif (($validated['audience_source'] ?? 'contacts') === 'file' && $request->hasFile('audience_file')) {
            $recipients = $this->parseAudienceFile($request->file('audience_file'));
        } else {
            // Build audience from system contact filters
            $query = Contact::query();
            $filters = $validated['audience_filters'] ?? [];

            if (!empty($filters['status_ids'])) {
                $ids = is_string($filters['status_ids']) ? explode(',', $filters['status_ids']) : $filters['status_ids'];
                $query->whereIn('status_id', array_filter($ids));
            }
            if (!empty($filters['countries'])) {
                $countries = is_string($filters['countries']) ? explode(',', $filters['countries']) : $filters['countries'];
                $query->whereIn('country', array_filter($countries));
            }
            if (!empty($filters['label_ids'])) {
                $ids = is_string($filters['label_ids']) ? explode(',', $filters['label_ids']) : $filters['label_ids'];
                $query->whereHas('labels', fn ($q) => $q->whereIn('labels.id', array_filter($ids)));
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

            $recipients = $query->whereNotNull('phone')->get(['id', 'phone', 'name'])
                ->map(fn ($c) => ['contact_id' => $c->id, 'phone' => $c->phone, 'name' => $c->name]);
        }

        if ($recipients->isEmpty()) {
            return back()->with('error', 'No se encontraron contactos. Revisa los filtros o el archivo.');
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
            'total_recipients' => $recipients->count(),
        ]);

        // Create campaign message rows
        $rows = $recipients->map(fn ($r) => [
            'whatsapp_campaign_id' => $campaign->id,
            'contact_id' => $r['contact_id'] ?? null,
            'phone' => $r['phone'],
            'contact_name' => $r['name'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        foreach (array_chunk($rows, 500) as $chunk) {
            WhatsAppCampaignMessage::insert($chunk);
        }

        return redirect()->route('whatsapp.campaigns.show', [$account->id, $campaign->id])
            ->with('success', "Campana creada con {$recipients->count()} destinatarios.");
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

    private function parseAudienceFile($file): \Illuminate\Support\Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $recipients = collect();

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            fgetcsv($handle); // skip header
            while (($row = fgetcsv($handle)) !== false) {
                $phone = $this->findPhoneInRow($row);
                $name = $this->findNameInRow($row);
                if ($phone) {
                    $recipients->push(['contact_id' => null, 'phone' => $phone, 'name' => $name]);
                }
            }
            fclose($handle);
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            try {
                $spreadsheet = $reader->load($file->getRealPath());
            } catch (\Throwable $e) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file->getRealPath());
            }
            $sheet = $spreadsheet->getActiveSheet();
            $phoneCol = null;
            $nameCol = null;

            // Auto-detect columns from header row
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = strtolower(trim($cell->getValue() ?? ''));
                    if (in_array($val, ['telefono', 'teléfono', 'phone', 'tel', 'celular', 'numero'])) {
                        $phoneCol = $cell->getColumn();
                    }
                    if (in_array($val, ['nombre', 'name', 'contacto', 'contact'])) {
                        $nameCol = $cell->getColumn();
                    }
                }
            }

            // If no header match, try col B=phone, col C=name (common format)
            if (!$phoneCol) {
                $phoneCol = 'B';
                $nameCol = $nameCol ?: 'C';
            }

            $maxRow = $sheet->getHighestRow();
            for ($r = 2; $r <= $maxRow; $r++) {
                $rawPhone = $sheet->getCell($phoneCol . $r)->getValue();
                $rawName = $nameCol ? $sheet->getCell($nameCol . $r)->getValue() : null;

                if (!$rawPhone || !is_string($rawPhone) && !is_numeric($rawPhone)) continue;

                $phone = \App\Helpers\PhoneCountryHelper::normalize((string) $rawPhone);
                if (preg_match('/[a-zA-Z]/', $phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) continue;

                $name = is_string($rawName) ? trim($rawName) : null;
                $recipients->push(['contact_id' => null, 'phone' => $phone, 'name' => $name]);
            }
        }

        // Deduplicate by phone
        return $recipients->unique('phone')->values();
    }

    private function findPhoneInRow(array $row): ?string
    {
        foreach ($row as $cell) {
            if (!$cell) continue;
            $cleaned = preg_replace('/[^0-9+]/', '', $cell);
            if (strlen($cleaned) >= 7 && preg_match('/^\+?\d{7,}$/', $cleaned)) {
                return \App\Helpers\PhoneCountryHelper::normalize($cleaned);
            }
        }
        return null;
    }

    private function findNameInRow(array $row): ?string
    {
        foreach ($row as $cell) {
            if (!$cell) continue;
            $trimmed = trim($cell);
            if (strlen($trimmed) > 1 && !preg_match('/^\+?\d/', $trimmed) && !str_contains($trimmed, '@')) {
                return $trimmed;
            }
        }
        return null;
    }

    public function destroy(WhatsAppAccount $account, WhatsAppCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('whatsapp.campaigns.index', $account->id)
            ->with('success', 'Campana eliminada.');
    }
}
