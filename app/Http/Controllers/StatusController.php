<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusController extends Controller
{
    public function index(): Response
    {
        $statuses = Status::withCount('contacts')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Statuses/Index', [
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:statuses,name',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
            'sort_order' => 'integer',
        ]);

        Status::create($validated);

        return back()->with('success', 'Status created.');
    }

    public function update(Request $request, Status $status): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:statuses,name,' . $status->id,
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
            'sort_order' => 'integer',
        ]);

        $status->update($validated);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Status $status): RedirectResponse
    {
        if ($status->contacts()->count() > 0) {
            return back()->with('error', 'Cannot delete status with assigned contacts.');
        }

        $status->delete();

        return back()->with('success', 'Status deleted.');
    }
}
