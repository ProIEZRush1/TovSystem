<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\Label;
use App\Models\Status;
use App\Services\ContactExportService;
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
            ->filterByStatus($request->input('status_id') ? (int) $request->input('status_id') : null)
            ->filterByCountry($request->input('country'))
            ->filterByLabel($request->input('label_id') ? (int) $request->input('label_id') : null)
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
            ->filterByStatus($request->input('status_id') ? (int) $request->input('status_id') : null)
            ->filterByCountry($request->input('country'))
            ->filterByLabel($request->input('label_id') ? (int) $request->input('label_id') : null)
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

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
            'status_id' => 'required|exists:statuses,id',
        ]);

        Contact::whereIn('id', $validated['ids'])
            ->update(['status_id' => $validated['status_id']]);

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
    }

    public function bulkDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
            'date' => 'nullable|date',
        ]);

        Contact::whereIn('id', $validated['ids'])
            ->update(['date' => $validated['date'] ?? null]);

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
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

        foreach ($contacts as $contact) {
            if ($validated['action'] === 'attach') {
                $contact->labels()->syncWithoutDetaching($validated['label_ids']);
            } else {
                $contact->labels()->detach($validated['label_ids']);
            }
        }

        return back()->with('success', count($validated['ids']) . ' contacts updated.');
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
        ]);

        $lines = array_filter(array_map('trim', explode("\n", $validated['phones'])));
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            // Parse line: could be "Name, +phone" or ", +phone" or just "+phone"
            $name = null;
            $phone = $line;

            if (str_contains($line, ',')) {
                $parts = explode(',', $line, 2);
                $namePart = trim($parts[0]);
                $phonePart = trim($parts[1]);

                if (!empty($phonePart)) {
                    $phone = $phonePart;
                    if (!empty($namePart)) {
                        $name = $namePart;
                    }
                }
            }

            $phone = PhoneCountryHelper::normalize($phone);

            if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
                $skipped++;
                continue;
            }

            $existing = Contact::where('phone', $phone)->first();
            $target = null;

            if ($existing) {
                // Update name if new entry has one and existing doesn't
                $changed = false;
                if (!empty($name) && empty($existing->name)) {
                    $existing->name = $name;
                    $changed = true;
                }
                if (!is_null($validated['status_id']) && is_null($existing->status_id)) {
                    $existing->status_id = $validated['status_id'];
                    $changed = true;
                }
                if (!empty($validated['date']) && is_null($existing->date)) {
                    $existing->date = $validated['date'];
                    $changed = true;
                }
                if (!empty($validated['source']) && empty($existing->source)) {
                    $existing->source = $validated['source'];
                    $changed = true;
                }
                if ($changed) {
                    $existing->save();
                    $updated++;
                    $target = $existing;
                } else {
                    $skipped++;
                    $target = $existing;
                }
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
            }

            // Attach labels to every processed contact (idempotent)
            if ($target && !empty($validated['label_ids'])) {
                $target->labels()->syncWithoutDetaching($validated['label_ids']);
            }
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
            $request->input('status_id') ? (int) $request->input('status_id') : null,
            $request->input('country')
        );
    }
}
