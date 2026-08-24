<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $equipment = Equipment::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('equipment_name', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('equipment.index', compact('equipment', 'search'));
    }

    public function create(): View
    {
        return view('equipment.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_name' => [
                'required',
                'string',
                'max:150',
                'unique:equipment,equipment_name',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'total_quantity' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],
            'unit' => [
                'required',
                'string',
                'max:50',
            ],
            'status' => [
                'required',
                'in:available,unavailable,maintenance',
            ],
        ]);

        Equipment::create($validated);

        return redirect()
            ->route('admin.equipment.index')
            ->with('success', 'Equipment added successfully.');
    }

    public function edit(Equipment $equipment): View
    {
        return view('equipment.edit', compact('equipment'));
    }

    public function update(
        Request $request,
        Equipment $equipment
    ): RedirectResponse {
        $validated = $request->validate([
            'equipment_name' => [
                'required',
                'string',
                'max:150',
                'unique:equipment,equipment_name,' . $equipment->id,
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'total_quantity' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],
            'unit' => [
                'required',
                'string',
                'max:50',
            ],
            'status' => [
                'required',
                'in:available,unavailable,maintenance',
            ],
        ]);

        $equipment->update($validated);

        return redirect()
            ->route('admin.equipment.index')
            ->with('success', 'Equipment updated successfully.');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        if ($equipment->reservations()->exists()) {
            return redirect()
                ->route('admin.equipment.index')
                ->with(
                    'error',
                    'This equipment cannot be deleted because it is used by one or more reservations. Set its status to unavailable instead.'
                );
        }

        $equipment->delete();

        return redirect()
            ->route('admin.equipment.index')
            ->with('success', 'Equipment deleted successfully.');
    }

    public function staffIndex(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $equipment = Equipment::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('equipment_name', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('equipment_name')
            ->paginate(10)
            ->withQueryString();

        return view(
            'staff.equipment.index',
            compact('equipment', 'search')
        );
    }
}
