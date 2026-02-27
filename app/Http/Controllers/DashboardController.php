<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Import;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totalContacts = Contact::count();

        $byStatus = Status::withCount('contacts')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Status $s) => [
                'name' => $s->name,
                'color' => $s->color,
                'count' => $s->contacts_count,
            ]);

        $byCountry = Contact::select('country', DB::raw('COUNT(*) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        $recentImports = Import::with('user:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalContacts' => $totalContacts,
                'totalStatuses' => Status::count(),
                'byStatus' => $byStatus,
                'byCountry' => $byCountry,
                'recentImports' => $recentImports,
            ],
        ]);
    }
}
