<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Planes comerciales del operador. Solo superadmin. */
class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('subscriptions')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan(['active' => true, 'billing_period' => 'monthly', 'features' => []])]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));

        return redirect()->route('plans.index')->with('success', 'Plan creado.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('plans.index')->with('success', 'Plan actualizado.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un plan con empresas suscritas. Desactívalo en su lugar.']);
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan eliminado.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', Rule::in(['monthly', 'yearly'])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0'],
            'max_products' => ['nullable', 'integer', 'min:0'],
            'max_loans' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_keys(Plan::MODULES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.unique' => 'Ya existe un plan con ese identificador.',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['features'] = array_values($data['features'] ?? []);
        $data['active'] = $request->boolean('active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
