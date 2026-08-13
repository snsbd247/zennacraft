<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CityController extends Controller
{
    public function index(): View
    {
        return view('studio.cities.index', ['cities' => City::withCount('subCities')->orderBy('sort_order')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        return view('studio.cities.form', ['city' => new City(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        City::create($this->validated($request));

        return redirect()->route('cities.index')->with('success', 'City added.');
    }

    public function edit(City $city): View
    {
        return view('studio.cities.form', ['city' => $city]);
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $city->update($this->validated($request));

        return redirect()->route('cities.index')->with('success', 'City updated.');
    }

    public function toggleStatus(Request $request, City $city): JsonResponse|RedirectResponse
    {
        $city->update(['status' => $city->isActive() ? 'inactive' : 'active']);

        return $request->expectsJson() ? response()->json(['message' => 'Status updated.', 'status' => $city->status]) : back();
    }

    public function destroy(Request $request, City $city): JsonResponse|RedirectResponse
    {
        $city->delete();

        return $request->expectsJson() ? response()->json(['message' => 'City deleted.']) : back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
