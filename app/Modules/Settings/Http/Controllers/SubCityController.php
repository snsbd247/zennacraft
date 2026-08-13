<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Models\City;
use App\Modules\Settings\Models\SubCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SubCityController extends Controller
{
    public function index(): View
    {
        return view('studio.subcities.index', ['subCities' => SubCity::with('city')->orderBy('sort_order')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        return view('studio.subcities.form', ['subCity' => new SubCity(['status' => 'active']), 'cities' => $this->cities()]);
    }

    public function store(Request $request): RedirectResponse
    {
        SubCity::create($this->validated($request));

        return redirect()->route('subcities.index')->with('success', 'Sub city added.');
    }

    public function edit(SubCity $subcity): View
    {
        return view('studio.subcities.form', ['subCity' => $subcity, 'cities' => $this->cities()]);
    }

    public function update(Request $request, SubCity $subcity): RedirectResponse
    {
        $subcity->update($this->validated($request));

        return redirect()->route('subcities.index')->with('success', 'Sub city updated.');
    }

    public function toggleStatus(Request $request, SubCity $subcity): JsonResponse|RedirectResponse
    {
        $subcity->update(['status' => $subcity->isActive() ? 'inactive' : 'active']);

        return $request->expectsJson() ? response()->json(['message' => 'Status updated.', 'status' => $subcity->status]) : back();
    }

    public function destroy(Request $request, SubCity $subcity): JsonResponse|RedirectResponse
    {
        $subcity->delete();

        return $request->expectsJson() ? response()->json(['message' => 'Sub city deleted.']) : back();
    }

    private function cities()
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'city_id' => ['required', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
