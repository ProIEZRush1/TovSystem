<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportService
{
    public function export(?string $search = null, int|array|null $statusId = null, string|array|null $country = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($search, $statusId, $country) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Phone', 'Name', 'Country', 'Status', 'Source', 'Date', 'Notes', 'Created At']);

            Contact::query()
                ->with('status')
                ->search($search)
                ->filterByStatus($statusId)
                ->filterByCountry($country)
                ->orderBy('id')
                ->lazy(1000)
                ->each(function (Contact $contact) use ($handle) {
                    fputcsv($handle, [
                        $contact->phone,
                        $contact->name,
                        $contact->country,
                        $contact->status?->name,
                        $contact->source,
                        $contact->date?->format('Y-m-d'),
                        $contact->notes,
                        $contact->created_at->format('Y-m-d H:i:s'),
                    ]);
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
