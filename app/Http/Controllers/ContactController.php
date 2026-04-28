<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneCountryHelper;
use App\Models\BulkOperation;
use App\Models\Contact;
use App\Models\Label;
use App\Models\Status;
use App\Services\ContactExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->with(['status', 'labels'])
            ->search($request->input('search'))
            ->filterByStatus($this->parseMultiInt($request->input('status_id')))
            ->filterByCountry($this->parseMultiString($request->input('country')))
            ->filterByLabel($this->parseMultiInt($request->input('label_id')))
            ->filterByDate($request->input('date_from'), $request->input('date_to'))
            ->when($request->input('sort'), function ($query) use ($request) {
                $direction = $request->input('direction', 'asc');
                $query->orderBy($request->input('sort'), $direction);
            }, function ($query) {
                $query->latest('id');
            })
            ->paginate(50)
            ->withQueryString();

        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color', 'slug']);
        $labels = Label::orderBy('sort_order')->get(['id', 'name', 'color']);

        $countries = Contact::select('country')
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'statuses' => $statuses,
            'labels' => $labels,
            'countries' => $countries,
            'filters' => $request->only(['search', 'status_id', 'country', 'label_id', 'sort', 'direction', 'date_from', 'date_to']),
        ]);
    }

    public function page(Request $request): JsonResponse
    {
        $contacts = Contact::query()
            ->with(['status', 'labels'])
            ->search($request->input('search'))
            ->filterByStatus($this->parseMultiInt($request->input('status_id')))
            ->filterByCountry($this->parseMultiString($request->input('country')))
            ->filterByLabel($this->parseMultiInt($request->input('label_id')))
            ->filterByDate($request->input('date_from'), $request->input('date_to'))
            ->when($request->input('sort'), function ($query) use ($request) {
                $direction = $request->input('direction', 'asc');
                $query->orderBy($request->input('sort'), $direction);
            }, function ($query) {
                $query->latest('id');
            })
            ->paginate(50)
            ->withQueryString();

        return response()->json($contacts);
    }

    public function show(Contact $contact): Response
    {
        $contact->load(['status', 'labels']);
        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color']);
        $labels = Label::orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'statuses' => $statuses,
            'labels' => $labels,
            'phoneCountries' => PhoneCountryHelper::allForFrontend(),
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:30',
            'country' => 'nullable|string|max:100',
            'status_id' => 'nullable|exists:statuses,id',
            'source' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Normalize phone and auto-detect country
        $validated['phone'] = PhoneCountryHelper::normalize($validated['phone']);
        $detectedCountry = PhoneCountryHelper::detectCountry($validated['phone']);

        if ($detectedCountry) {
            $validated['country'] = $detectedCountry;
        }

        $contact->update($validated);

        if ($request->has('label_ids')) {
            $contact->labels()->sync($request->input('label_ids', []));
        }

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    /**
     * Apply any combination of status / date / labels in one request,
     * snapshotting previous values for a single undoable operation.
     */
    public function bulkApply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:contacts,id',
            'status_id' => 'nullable|exists:statuses,id',
            'date' => 'nullable|date',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:labels,id',
            'label_action' => 'nullable|in:attach,detach',
        ]);

        $ids = $validated['ids'];
        $hasStatus = !is_null($validated['status_id'] ?? null);
        $hasDate = !is_null($request->input('date'));  // may be empty string for explicit clear
        $hasLabels = !empty($validated['label_ids'] ?? []);

        if (!$hasStatus && !$hasDate && !$hasLabels) {
            return back()->with('error', 'Selecciona al menos un campo para aplicar.');
        }

        // Snapshots for undo.
        $existing = Contact::whereIn('id', $ids)->get(['id', 'status_id', 'date']);
        $prevStatus = $existing->pluck('status_id', 'id')->toArray();
        $prevDate = $existing->mapWithKeys(fn ($c) => [$c->id => $c->date?->format('Y-m-d')])->toArray();

        $existingLabelMap = [];
        if ($hasLabels) {
            foreach (Contact::whereIn('id', $ids)->with('labels:id')->get() as $c) {
                $existingLabelMap[$c->id] = $c->labels->pluck('id')->toArray();
            }
        }

        DB::transaction(function () use ($validated, $ids, $hasStatus, $hasDate, $hasLabels) {
            if ($hasStatus) {
                Contact::whereIn('id', $ids)->update(['status_id' => $validated['status_id']]);
            }
            if ($hasDate) {
                Contact::whereIn('id', $ids)->update(['date' => $validated['date'] ?? null]);
            }
            if ($hasLabels) {
                $action = $validated['label_action'] ?? 'attach';
                foreach (Contact::whereIn('id', $ids)->get() as $c) {
                    if ($action === 'attach') {
                        $c->labels()->syncWithoutDetaching($validated['label_ids']);
                    } else {
                        $c->labels()->detach($validated['label_ids']);
                    }
                }
            }
        });

        // Build description
        $parts = [];
        if ($hasStatus) {
            $parts[] = 'estado "' . (Status::where('id', $validated['status_id'])->value('name') ?? '?') . '"';
        }
        if ($hasDate) {
            $parts[] = 'fecha "' . ($validated['date'] ?? 'vacia') . '"';
        }
        if ($hasLabels) {
            $names = Label::whereIn('id', $validated['label_ids'])->pluck('name')->implode(', ');
            $verb = ($validated['label_action'] ?? 'attach') === 'attach' ? 'agregar etiqueta' : 'quitar etiqueta';
            $parts[] = "{$verb} \"{$names}\"";
        }
        $description = count($ids) . ' contactos: ' . implode(' + ', $parts);

        BulkOperation::create([
            'user_id' => $request->user()?->id,
            'type' => 'bulk_apply',
            'description' => $description,
            'payload' => [
                'fields' => array_filter([
                    'status' => $hasStatus,
                    'date' => $hasDate,
                    'labels' => $hasLabels,
                ]),
                'prev_status' => $hasStatus ? $prevStatus : null,
                'prev_date' => $hasDate ? $prevDate : null,
                'label_action' => $hasLabels ? ($validated['label_action'] ?? 'attach') : null,
                'label_ids' => $hasLabels ? $validated['label_ids'] : null,
                'existing_label_map' => $hasLabels ? $existingLabelMap : null,
            ],
        ]);

        return back()->with('success', count($ids) . ' contactos actualizados.');
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
            'status_id' => 'required|exists:statuses,id',
        ]);

        // Snapshot previous status for undo.
        $previous = Contact::whereIn('id', $validated['ids'])->pluck('status_id', 'id')->toArray();

        Contact::whereIn('id', $validated['ids'])
            ->update(['status_id' => $validated['status_id']]);

        $statusName = Status::where('id', $validated['status_id'])->value('name');
        BulkOperation::create([
            'user_id' => $request->user()?->id,
            'type' => 'bulk_status',
            'description' => count($validated['ids']) . " contactos cambiados a estado \"{$statusName}\"",
            'payload' => ['previous' => $previous],
        ]);

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
    }

    public function bulkDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
            'date' => 'nullable|date',
        ]);

        $previous = Contact::whereIn('id', $validated['ids'])
            ->get(['id', 'date'])
            ->pluck('date', 'id')
            ->map(fn ($d) => $d?->format('Y-m-d'))
            ->toArray();

        Contact::whereIn('id', $validated['ids'])
            ->update(['date' => $validated['date'] ?? null]);

        BulkOperation::create([
            'user_id' => $request->user()?->id,
            'type' => 'bulk_date',
            'description' => count($validated['ids']) . ' contactos con fecha "' . ($validated['date'] ?? 'vacia') . '"',
            'payload' => ['previous' => $previous],
        ]);

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
        ]);

        $contacts = Contact::whereIn('id', $validated['ids'])->get();

        // Snapshot full data + labels for undo
        $snapshots = $contacts->map(function ($c) {
            return [
                'id' => $c->id,
                'phone' => $c->phone,
                'name' => $c->name,
                'country' => $c->country,
                'status_id' => $c->status_id,
                'source' => $c->source,
                'date' => $c->date?->format('Y-m-d'),
                'notes' => $c->notes,
                'created_at' => $c->created_at?->toDateTimeString(),
                'updated_at' => $c->updated_at?->toDateTimeString(),
                'label_ids' => $c->labels()->pluck('labels.id')->toArray(),
            ];
        })->toArray();

        DB::transaction(function () use ($validated) {
            DB::table('contact_label')->whereIn('contact_id', $validated['ids'])->delete();
            Contact::whereIn('id', $validated['ids'])->delete();
        });

        BulkOperation::create([
            'user_id' => $request->user()?->id,
            'type' => 'bulk_delete',
            'description' => count($validated['ids']) . ' contactos eliminados',
            'payload' => ['snapshots' => $snapshots],
        ]);

        return back()->with('success', count($validated['ids']) . ' contactos eliminados.');
    }

    public function bulkLabels(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
            'label_ids' => 'required|array',
            'label_ids.*' => 'integer|exists:labels,id',
            'action' => 'required|in:attach,detach',
        ]);

        $contacts = Contact::whereIn('id', $validated['ids'])->get();

        // For attach: remember which (contact_id, label_id) pairs already existed
        // so undo only detaches the ones WE added.
        $existingBefore = [];
        foreach ($contacts as $contact) {
            $existingBefore[$contact->id] = $contact->labels()->pluck('labels.id')->toArray();
        }

        foreach ($contacts as $contact) {
            if ($validated['action'] === 'attach') {
                $contact->labels()->syncWithoutDetaching($validated['label_ids']);
            } else {
                $contact->labels()->detach($validated['label_ids']);
            }
        }

        $labelNames = Label::whereIn('id', $validated['label_ids'])->pluck('name')->implode(', ');
        $verb = $validated['action'] === 'attach' ? 'etiquetado' : 'quitada etiqueta';
        BulkOperation::create([
            'user_id' => $request->user()?->id,
            'type' => 'bulk_labels',
            'description' => count($validated['ids']) . " contactos {$verb} \"{$labelNames}\"",
            'payload' => [
                'action' => $validated['action'],
                'label_ids' => $validated['label_ids'],
                'existing_before' => $existingBefore,
            ],
        ]);

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
    }

    /**
     * Parse a filter input that may be:
     *   - null / empty
     *   - single value "5"
     *   - comma-separated "5,7,9"
     *   - array [5, 7]
     * Returns null, int, or int[] ready for the scope.
     */
    private function parseMultiInt($input): int|array|null
    {
        if (is_null($input) || $input === '' || $input === []) return null;
        if (is_array($input)) {
            $out = array_values(array_filter(array_map('intval', $input)));
            return empty($out) ? null : (count($out) === 1 ? $out[0] : $out);
        }
        if (is_string($input) && str_contains($input, ',')) {
            $out = array_values(array_filter(array_map('intval', explode(',', $input))));
            return empty($out) ? null : (count($out) === 1 ? $out[0] : $out);
        }
        return (int) $input;
    }

    private function parseMultiString($input): string|array|null
    {
        if (is_null($input) || $input === '' || $input === []) return null;
        if (is_array($input)) {
            $out = array_values(array_filter(array_map('trim', $input)));
            return empty($out) ? null : (count($out) === 1 ? $out[0] : $out);
        }
        if (is_string($input) && str_contains($input, ',')) {
            $out = array_values(array_filter(array_map('trim', explode(',', $input))));
            return empty($out) ? null : (count($out) === 1 ? $out[0] : $out);
        }
        return (string) $input;
    }

    /**
     * Parse a free-form "phones pasted by user" line into [phone, name].
     * Supports comma-separated and whitespace-separated, in either order.
     * Returns [null, null] if no valid phone is found.
     */
    private function parsePhoneLine(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [null, null];
        }

        // Find a phone-shaped token anywhere in the line (optionally leading +,
        // then digits with optional spaces/dashes/dots/parens in between).
        // The captured group is the raw phone; everything else on the line
        // (minus commas) is the name.
        if (!preg_match('/(\+?\d(?:[\d\s\-\(\)\.]{5,}))/', $line, $m, PREG_OFFSET_CAPTURE)) {
            return [null, null];
        }

        $phoneRaw = rtrim($m[1][0], " \t\-\.\(\)");
        $phoneRaw = trim($phoneRaw);
        if (preg_match_all('/\d/', $phoneRaw) < 7) {
            return [null, null];
        }

        $offset = $m[1][1];
        $namePart = substr($line, 0, $offset) . ' ' . substr($line, $offset + strlen($m[1][0]));
        // Remove commas from the name and collapse whitespace.
        $namePart = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $namePart)));

        return [$phoneRaw, $namePart !== '' ? $namePart : null];
    }

    public function quickAdd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phones' => 'required|string',
            'status_id' => 'nullable|exists:statuses,id',
            'date' => 'nullable|date',
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'integer|exists:labels,id',
            'source' => 'nullable|string|max:100',
            // mode: only_new | fill_empty | overwrite
            'mode' => 'nullable|in:only_new,fill_empty,overwrite',
            // legacy boolean still accepted
            'only_new' => 'nullable|boolean',
        ]);

        $mode = $validated['mode'] ?? (!empty($validated['only_new']) ? 'only_new' : 'fill_empty');
        $lines = array_filter(array_map('trim', explode("\n", $validated['phones'])));
        $created = 0;
        $updated = 0;
        $skipped = 0;

        // For undo: track new contact IDs + snapshots of updated contacts + labels we attached
        $createdIds = [];
        $updatedSnapshots = []; // id => [field => previous_value]
        $labelsAttached = [];   // contact_id => [label_ids actually newly attached]

        foreach ($lines as $line) {
            // Parse line. Accepted formats:
            //   "+5215512345678"                    -> phone only
            //   "Name, +5215512345678"               -> name , phone
            //   ", +5215512345678"                   -> , phone
            //   "+5215512345678, Name"               -> phone , name
            //   "5215512345678 Margalit Hamui"       -> phone <whitespace> name
            //   "Margalit Hamui 5215512345678"       -> name <whitespace> phone (last token is digits)
            [$phone, $name] = $this->parsePhoneLine($line);
            if (!$phone) {
                $skipped++;
                continue;
            }

            $phone = PhoneCountryHelper::normalize($phone);

            // Sanity: phones shouldn't contain letters. If they do, the parser
            // failed — skip this row rather than poison the whole batch.
            if (preg_match('/[a-zA-Z]/', $phone)) {
                $skipped++;
                continue;
            }

            if (strlen($phone) > 30 || strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
                $skipped++;
                continue;
            }

            $existing = Contact::where('phone', $phone)->first();
            $isNew = false;
            $target = null;

            if ($existing) {
                if ($mode === 'only_new') {
                    // Leave existing contact completely untouched
                    $skipped++;
                    continue;
                }

                // In fill_empty we only write fields that are currently empty.
                // In overwrite we always write provided fields, replacing existing values.
                $overwrite = $mode === 'overwrite';

                $snapshot = [];
                $changed = false;

                $shouldSetName = !empty($name) && ($overwrite || empty($existing->name));
                if ($shouldSetName && $existing->name !== $name) {
                    $snapshot['name'] = $existing->name;
                    $existing->name = $name;
                    $changed = true;
                }

                $shouldSetStatus = !is_null($validated['status_id']) && ($overwrite || is_null($existing->status_id));
                if ($shouldSetStatus && $existing->status_id != $validated['status_id']) {
                    $snapshot['status_id'] = $existing->status_id;
                    $existing->status_id = $validated['status_id'];
                    $changed = true;
                }

                $shouldSetDate = !empty($validated['date']) && ($overwrite || is_null($existing->date));
                if ($shouldSetDate) {
                    $prevDate = $existing->date?->format('Y-m-d');
                    if ($prevDate !== $validated['date']) {
                        $snapshot['date'] = $prevDate;
                        $existing->date = $validated['date'];
                        $changed = true;
                    }
                }

                $shouldSetSource = !empty($validated['source']) && ($overwrite || empty($existing->source));
                if ($shouldSetSource && $existing->source !== $validated['source']) {
                    $snapshot['source'] = $existing->source;
                    $existing->source = $validated['source'];
                    $changed = true;
                }

                if ($changed) {
                    $existing->save();
                    $updated++;
                    $updatedSnapshots[$existing->id] = $snapshot;
                } else {
                    $skipped++;
                }
                $target = $existing;
            } else {
                $country = PhoneCountryHelper::detectCountry($phone);
                $target = Contact::create([
                    'phone' => $phone,
                    'name' => $name,
                    'country' => $country,
                    'status_id' => $validated['status_id'] ?? null,
                    'date' => $validated['date'] ?? null,
                    'source' => $validated['source'] ?? null,
                ]);
                $created++;
                $isNew = true;
                $createdIds[] = $target->id;
            }

            // Attach labels. In only_new mode, only attach to newly created contacts.
            // In fill_empty / overwrite, attach to every processed contact.
            if ($target && !empty($validated['label_ids'])) {
                if ($mode !== 'only_new' || $isNew) {
                    $alreadyHad = $target->labels()->pluck('labels.id')->toArray();
                    $newlyAttached = array_values(array_diff($validated['label_ids'], $alreadyHad));
                    if (!empty($newlyAttached)) {
                        $target->labels()->syncWithoutDetaching($newlyAttached);
                        $labelsAttached[$target->id] = $newlyAttached;
                    }
                }
            }
        }

        // Log operation for undo (only if something actually happened)
        if (!empty($createdIds) || !empty($updatedSnapshots) || !empty($labelsAttached)) {
            $parts = [];
            if ($created) $parts[] = "{$created} nuevos";
            if ($updated) $parts[] = "{$updated} actualizados";
            BulkOperation::create([
                'user_id' => $request->user()?->id,
                'type' => 'quick_add',
                'description' => 'Agregar telefonos: ' . (implode(', ', $parts) ?: 'sin cambios'),
                'payload' => [
                    'created_ids' => $createdIds,
                    'updated_snapshots' => $updatedSnapshots,
                    'labels_attached' => $labelsAttached,
                ],
            ]);
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($lines),
        ]);
    }

    public function export(Request $request, ContactExportService $exportService): StreamedResponse
    {
        return $exportService->export(
            $request->input('search'),
            $this->parseMultiInt($request->input('status_id')),
            $this->parseMultiString($request->input('country'))
        );
    }

    /**
     * List recent bulk operations that can still be undone.
     */
    public function recentOperations(Request $request): JsonResponse
    {
        $ops = BulkOperation::where('user_id', $request->user()?->id)
            ->whereNull('undone_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'type', 'description', 'created_at']);

        return response()->json($ops);
    }

    /**
     * Undo a specific bulk operation by ID.
     */
    public function undoOperation(BulkOperation $operation, Request $request): JsonResponse
    {
        if ($operation->user_id !== $request->user()?->id) {
            return response()->json(['error' => 'Not allowed'], 403);
        }
        if ($operation->undone_at) {
            return response()->json(['error' => 'Already undone'], 422);
        }

        DB::transaction(function () use ($operation) {
            $payload = $operation->payload;

            switch ($operation->type) {
                case 'bulk_status':
                    foreach ($payload['previous'] as $id => $prevStatusId) {
                        Contact::where('id', $id)->update(['status_id' => $prevStatusId]);
                    }
                    break;

                case 'bulk_date':
                    foreach ($payload['previous'] as $id => $prevDate) {
                        Contact::where('id', $id)->update(['date' => $prevDate]);
                    }
                    break;

                case 'bulk_labels':
                    $action = $payload['action'];
                    $labelIds = $payload['label_ids'];
                    $existingBefore = $payload['existing_before'] ?? [];
                    foreach ($existingBefore as $contactId => $beforeLabelIds) {
                        $contact = Contact::find($contactId);
                        if (!$contact) continue;
                        if ($action === 'attach') {
                            // Detach only the labels that weren't there before
                            $toRemove = array_diff($labelIds, $beforeLabelIds);
                            if (!empty($toRemove)) $contact->labels()->detach($toRemove);
                        } else {
                            // Re-attach ONLY the labels that were detached (i.e. were there before AND are in label_ids)
                            $toReattach = array_intersect($labelIds, $beforeLabelIds);
                            if (!empty($toReattach)) $contact->labels()->syncWithoutDetaching($toReattach);
                        }
                    }
                    break;

                case 'bulk_apply':
                    if (!empty($payload['prev_status'])) {
                        foreach ($payload['prev_status'] as $id => $prev) {
                            Contact::where('id', $id)->update(['status_id' => $prev]);
                        }
                    }
                    if (!empty($payload['prev_date'])) {
                        foreach ($payload['prev_date'] as $id => $prev) {
                            Contact::where('id', $id)->update(['date' => $prev]);
                        }
                    }
                    if (!empty($payload['label_action']) && !empty($payload['label_ids']) && !empty($payload['existing_label_map'])) {
                        $action = $payload['label_action'];
                        $labelIds = $payload['label_ids'];
                        foreach ($payload['existing_label_map'] as $contactId => $beforeLabelIds) {
                            $contact = Contact::find($contactId);
                            if (!$contact) continue;
                            if ($action === 'attach') {
                                $toRemove = array_diff($labelIds, $beforeLabelIds);
                                if (!empty($toRemove)) $contact->labels()->detach($toRemove);
                            } else {
                                $toReattach = array_intersect($labelIds, $beforeLabelIds);
                                if (!empty($toReattach)) $contact->labels()->syncWithoutDetaching($toReattach);
                            }
                        }
                    }
                    break;

                case 'bulk_delete':
                    foreach ($payload['snapshots'] ?? [] as $snap) {
                        // Skip if phone already taken by someone else (maybe re-added in the meantime)
                        if (Contact::where('phone', $snap['phone'])->exists()) {
                            continue;
                        }
                        // Re-insert with the original id so FK-heavy history still points here
                        DB::table('contacts')->insert([
                            'id' => $snap['id'],
                            'phone' => $snap['phone'],
                            'name' => $snap['name'],
                            'country' => $snap['country'],
                            'status_id' => $snap['status_id'],
                            'source' => $snap['source'],
                            'date' => $snap['date'],
                            'notes' => $snap['notes'],
                            'created_at' => $snap['created_at'],
                            'updated_at' => $snap['updated_at'],
                        ]);
                        if (!empty($snap['label_ids'])) {
                            $rows = collect($snap['label_ids'])
                                ->map(fn ($id) => ['contact_id' => $snap['id'], 'label_id' => $id])
                                ->toArray();
                            DB::table('contact_label')->insertOrIgnore($rows);
                        }
                    }
                    break;

                case 'quick_add':
                    // Restore updated contacts' previous field values
                    foreach ($payload['updated_snapshots'] ?? [] as $contactId => $snapshot) {
                        $contact = Contact::find($contactId);
                        if (!$contact) continue;
                        foreach ($snapshot as $field => $value) {
                            $contact->{$field} = $value;
                        }
                        $contact->save();
                    }
                    // Detach labels we attached
                    foreach ($payload['labels_attached'] ?? [] as $contactId => $labelIds) {
                        $contact = Contact::find($contactId);
                        if ($contact && !empty($labelIds)) {
                            $contact->labels()->detach($labelIds);
                        }
                    }
                    // Delete newly created contacts (and cascade their label pivots)
                    $createdIds = $payload['created_ids'] ?? [];
                    if (!empty($createdIds)) {
                        DB::table('contact_label')->whereIn('contact_id', $createdIds)->delete();
                        Contact::whereIn('id', $createdIds)->delete();
                    }
                    break;
            }

            $operation->undone_at = now();
            $operation->save();
        });

        return response()->json(['undone' => true, 'description' => $operation->description]);
    }
}
