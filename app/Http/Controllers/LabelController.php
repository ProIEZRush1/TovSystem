<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function index(): Response
    {
        $labels = Label::orderBy('sort_order')
            ->withCount('contacts')
            ->get();

        return Inertia::render('Labels/Index', [
            'labels' => $labels,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:labels,name',
            'color' => 'required|string|max:7',
            'sort_order' => 'integer|min:0',
        ]);

        Label::create($validated);

        return back()->with('success', 'Label created.');
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:labels,name,' . $label->id,
            'color' => 'required|string|max:7',
            'sort_order' => 'integer|min:0',
        ]);

        $label->update($validated);

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Label $label): RedirectResponse
    {
        $label->delete();

        return back()->with('success', 'Label deleted.');
    }
}
