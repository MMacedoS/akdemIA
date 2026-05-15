<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\Workout\WorkoutCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutCatalogPricingController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $catalogs = WorkoutCatalog::query()
            ->withCount('exercises')
            ->with('owner:id,name,email')
            ->where('is_public', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('web.v1.system_admin.workouts.catalogs.index', [
            'catalogs' => $catalogs,
            'search' => $search,
        ]);
    }

    public function updatePrice(Request $request, WorkoutCatalog $catalog): RedirectResponse
    {
        if (! (bool) $catalog->is_public) {
            return redirect()->route('system-admin.workouts.catalogs.index')
                ->withErrors(['catalog' => 'Apenas catalogos publicos podem ter preco definido pelo system-admin.']);
        }

        $payload = $request->validate([
            'price' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

        $catalog->price = (int) $payload['price'];
        $catalog->save();

        return redirect()->route('system-admin.workouts.catalogs.index')
            ->with('status', 'Preco do catalogo publico atualizado com sucesso.');
    }
}
