<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\Status;
use App\Services\ContactExportService;
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
            ->with('status')
            ->search($request->input('search'))
            ->filterByStatus($request->input('status_id') ? (int) $request->input('status_id') : null)
            ->filterByCountry($request->input('country'))
            ->when($request->input('sort'), function ($query) use ($request) {
                $direction = $request->input('direction', 'asc');
                $query->orderBy($request->input('sort'), $direction);
            }, function ($query) {
                $query->latest('id');
            })
            ->paginate(50)
            ->withQueryString();

        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color', 'slug']);

        $countries = Contact::select('country')
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'statuses' => $statuses,
            'countries' => $countries,
            'filters' => $request->only(['search', 'status_id', 'country', 'sort', 'direction']),
        ]);
    }

    public function show(Contact $contact): Response
    {
        $contact->load('status');
        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'statuses' => $statuses,
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

    public function export(Request $request, ContactExportService $exportService): StreamedResponse
    {
        return $exportService->export(
            $request->input('search'),
            $request->input('status_id') ? (int) $request->input('status_id') : null,
            $request->input('country')
        );
    }
}
