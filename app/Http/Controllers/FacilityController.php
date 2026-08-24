<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $facilities = Facility::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('facility_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('facilities.index', compact('facilities', 'search'));
    }

    public function create(): View
    {
        return view('facilities.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facility_name' => [
                'required',
                'string',
                'max:150',
                'unique:facilities,facility_name',
            ],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', 'in:available,unavailable,maintenance'],
        ]);

        Facility::create($validated);

        return redirect()
            ->route('admin.facilities.index')
            ->with('success', 'Facility added successfully.');
    }

    public function edit(Facility $facility): View
    {
        return view('facilities.edit', compact('facility'));
    }

    public function update(
        Request $request,
        Facility $facility
    ): RedirectResponse {
        $validated = $request->validate([
            'facility_name' => [
                'required',
                'string',
                'max:150',
                'unique:facilities,facility_name,' . $facility->id,
            ],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', 'in:available,unavailable,maintenance'],
        ]);

        $facility->update($validated);

        return redirect()
            ->route('admin.facilities.index')
            ->with('success', 'Facility updated successfully.');
    }

    public function destroy(Facility $facility): RedirectResponse
    {
        if ($facility->reservations()->exists()) {
            return redirect()
                ->route('admin.facilities.index')
                ->with(
                    'error',
                    'This facility cannot be deleted because it is used by one or more reservations. Set its status to unavailable instead.'
                );
        }

        $facility->delete();

        return redirect()
            ->route('admin.facilities.index')
            ->with('success', 'Facility deleted successfully.');
    }

    public function staffIndex(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $facilities = Facility::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('facility_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('facility_name')
            ->paginate(10)
            ->withQueryString();

        return view(
            'staff.facilities.index',
            compact('facilities', 'search')
        );
    }
}
