<?php

namespace App\Http\Controllers\Capital;

use App\Http\Controllers\Controller;
use App\Models\CompanyCapital;
use Illuminate\Http\Request;

class CapitalController extends Controller
{
    public function index(Request $request)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();
        $companyId = $company?->id;

        $query = CompanyCapital::where('company_id', $companyId)
            ->with('user')
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at');

        $entries = $query->paginate(25)->withQueryString();

        $totals = CompanyCapital::where('company_id', $companyId)
            ->selectRaw("
                SUM(CASE WHEN type = 'input'      THEN amount ELSE 0 END) as total_input,
                SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawal
            ")
            ->first();

        $totalInput      = (float) ($totals->total_input ?? 0);
        $totalWithdrawal = (float) ($totals->total_withdrawal ?? 0);
        $netCapital      = $totalInput - $totalWithdrawal;

        return view('capital.index', compact(
            'entries',
            'totalInput',
            'totalWithdrawal',
            'netCapital',
            'company'
        ));
    }

    public function store(Request $request)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        $validated = $request->validate([
            'type'        => 'required|in:input,withdrawal',
            'amount'      => 'required|integer|min:1|max:999999999',
            'description' => 'nullable|string|max:500',
            'entry_date'  => 'required|date|before_or_equal:today',
        ]);

        CompanyCapital::create([
            'company_id'  => $company->id,
            'user_id'     => $user->id,
            'type'        => $validated['type'],
            'amount'      => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'entry_date'  => $validated['entry_date'],
        ]);

        $label = $validated['type'] === 'input' ? 'Aporte' : 'Retiro';
        return redirect()->route('capital.index')
            ->with('success', "{$label} registrado correctamente.");
    }

    public function destroy(CompanyCapital $capital)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        if ($capital->company_id !== $company?->id && !$user->is_super_admin) {
            abort(403);
        }

        $capital->delete();

        return redirect()->route('capital.index')
            ->with('success', 'Registro eliminado.');
    }
}
