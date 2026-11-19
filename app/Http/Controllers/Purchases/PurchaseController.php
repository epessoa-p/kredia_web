<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\CreditCategory;
use App\Models\DocumentTemplate;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\PurchaseException;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-permission:purchases.view');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $branchesQuery = Branch::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
        }
        $branches = $branchesQuery->get();

        $selectedBranchId = $request->integer('sucursal_id');

        // Restrict to personal's branch for non-managers
        if (!$user->is_super_admin) {
            $personal = Personal::where('user_id', $user->id)->first();
            if ($personal && !$personal->isManager() && $personal->branch_id) {
                $branches = $branches->where('id', $personal->branch_id)->values();
                $selectedBranchId = (int) $personal->branch_id;
            }
        }

        if ($selectedBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = 0;
        }

        $hasActiveCashSession = $this->resolveOpenCashSession((int) $company?->id) !== null;

        $query = Purchase::with(['client', 'product', 'creditCategory', 'branch', 'createdBy', 'cashRegister'])
            ->latest();

        if (!$user->is_super_admin && $company) {
            $query->where('company_id', $company->id);
        }
        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $status = $request->get('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        if ($dateFrom) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $products = $user->is_super_admin
            ? Product::where('active', true)->orderBy('name')->get()
            : Product::where('company_id', $company?->id)->where('active', true)->orderBy('name')->get();

        $productId = $request->integer('product_id');
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $purchases = $query->paginate(15)->withQueryString();

        return view('purchases.index', compact(
            'purchases', 'branches', 'selectedBranchId', 'products',
            'q', 'status', 'dateFrom', 'dateTo', 'productId',
            'hasActiveCashSession'
        ));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para registrar compras.']);
        }

        $branches = $user->is_super_admin
            ? Branch::where('active', true)->orderBy('name')->get()
            : Branch::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        $defaultBranchId = old('branch_id', $request->integer('sucursal_id'));
        if (empty($defaultBranchId) && $branches->isNotEmpty()) {
            $defaultBranchId = $branches->first()->id;
        }
        if ($defaultBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains((int) $defaultBranchId)) {
            $defaultBranchId = $branches->first()->id;
        }

        $creditCategories = $user->is_super_admin
            ? CreditCategory::where('active', true)->orderBy('name')->get()
            : CreditCategory::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        $clients = $user->is_super_admin
            ? Client::where('active', true)->orderBy('name')->get()
            : $company->clients()->where('active', true)->orderBy('name')->get();

        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);
        $cashSessionError = $this->getCashSessionError((int) $company->id);

        $activeCashSession?->load('cashRegister.branch');
        $sessionBranch = $activeCashSession?->cashRegister?->branch;
        if ($sessionBranch) {
            $defaultBranchId = $sessionBranch->id;
        }

        $cashAvailable = $activeCashSession?->currentBalance();

        $fromContext = (string) old('from_context', $request->string('from')->toString());
        $backUrl = route('purchases.index');
        $backLabel = 'Volver a compras';
        $clientIdFromRequest = $request->integer('client_id');
        if ($fromContext === 'client' && $clientIdFromRequest > 0) {
            $backUrl = route('clients.show', $clientIdFromRequest);
            $backLabel = 'Volver al cliente';
        }

        return view('purchases.create', compact(
            'company',
            'clients',
            'creditCategories',
            'branches',
            'defaultBranchId',
            'activeCashSession',
            'cashSessionError',
            'sessionBranch',
            'cashAvailable',
            'fromContext',
            'backUrl',
            'backLabel'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para registrar compras.']);
        }

        if ($limitError = $this->planLimitError((int) $company->id, ['products'])) {
            return back()->withInput()->withErrors(['error' => $limitError]);
        }

        $cashError = $this->getCashSessionError((int) $company->id);
        if ($cashError) {
            return back()->withInput()->withErrors(['error' => $cashError]);
        }
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);

        $validated = $request->validate(
            PurchaseService::validationRules() + ['branch_id' => 'nullable|exists:branches,id'],
            PurchaseService::validationMessages()
        );

        try {
            $purchase = app(PurchaseService::class)->create(
                $company,
                (int) $user->id,
                $activeCashSession,
                $validated,
                $request->file('images') ?? [],
            );
        } catch (PurchaseException $e) {
            return back()->withInput()->withErrors([$e->field ?? 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar compra', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la compra.']);
        }

        $fromContext = (string) $request->input('from_context', '');

        $defaultPrintTemplate = $this->purchaseTemplateQuery((int) $company->id)
            ->orderByRaw('company_id IS NULL')
            ->orderByRaw("CASE WHEN paper_size = 'pequeno' THEN 0 WHEN paper_size = 'mediano' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->first(['id', 'paper_size']);

        $redirectParams = ['purchase' => $purchase];
        if ($fromContext === 'client' && $purchase->client_id) {
            $redirectParams['from'] = 'client';
        }
        if ($defaultPrintTemplate) {
            $redirectParams['auto_print'] = 1;
            $redirectParams['template_id'] = $defaultPrintTemplate->id;
        }

        return redirect()->route('purchases.show', $redirectParams)->with('success', 'Compra registrada exitosamente');
    }

    public function show(Purchase $purchase)
    {
        $this->authorizeCompany($purchase);

        $purchase->load('client', 'product', 'creditCategory', 'branch', 'createdBy', 'cashRegister', 'images');

        $purchasePrintTemplates = $this->purchaseTemplateQuery((int) $purchase->company_id)
            ->orderByRaw('company_id IS NULL')
            ->orderByRaw("CASE WHEN paper_size = 'pequeno' THEN 0 WHEN paper_size = 'mediano' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->unique(fn (DocumentTemplate $template) => mb_strtolower(trim((string) $template->name)) . '|' . $template->paper_size)
            ->values();

        $defaultPurchasePrintTemplateId = optional($purchasePrintTemplates->firstWhere('paper_size', 'pequeno'))->id
            ?? optional($purchasePrintTemplates->first())->id;

        $purchasePrintTemplateCatalog = $purchasePrintTemplates->map(function (DocumentTemplate $template) use ($purchase) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'paper_size' => $template->paper_size,
                'paper_size_label' => $template->paper_size_label,
                'type' => $template->type,
                'html' => $template->applyToPurchase($purchase),
            ];
        })->values();

        return view('purchases.show', compact(
            'purchase',
            'purchasePrintTemplates',
            'defaultPurchasePrintTemplateId',
            'purchasePrintTemplateCatalog'
        ));
    }

    public function destroy(Purchase $purchase)
    {
        $this->authorizeCompany($purchase);

        if (!auth()->user()->hasPermissionInCompany('purchases.delete', $purchase->company)) {
            return back()->withErrors(['error' => 'No tienes permisos para eliminar compras']);
        }

        $service = app(PurchaseService::class);
        if ($reason = $service->deleteBlockReason($purchase)) {
            return back()->with('error', $reason);
        }

        // El dinero pagado debe volver a la caja aperturada del usuario logueado.
        $cashError = $this->getCashSessionError((int) $purchase->company_id);
        if ($cashError) {
            return back()->with('error', 'Debes tener una caja abierta para eliminar la compra: el dinero pagado debe volver a tu caja. (' . $cashError . ')');
        }

        $session = $this->resolveOpenCashSession((int) $purchase->company_id);
        if (!$session) {
            return back()->with('error', 'No se encontró una caja abierta para registrar la devolución del dinero.');
        }

        try {
            $spent = $service->delete($purchase, (int) auth()->id(), $session);
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Error al eliminar compra: ' . $e->getMessage(), ['purchase_id' => $purchase->id]);

            return back()->with('error', 'No se pudo eliminar la compra. Intenta nuevamente.');
        }

        $message = $spent > 0
            ? 'Compra eliminada. Se devolvieron ' . \App\Support\Currency::format($spent, $purchase->company) . ' a tu caja.'
            : 'Compra eliminada exitosamente';

        return redirect()->route('purchases.index')->with('success', $message);
    }

    protected function authorizeCompany(Purchase $purchase): void
    {
        if (!auth()->user()->is_super_admin && $purchase->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected function resolveOpenCashSession(int $companyId): ?\App\Models\CashRegisterSession
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) return null;

        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    protected function getCashSessionError(int $companyId): ?string
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) {
            return 'Tu usuario no tiene un personal asociado al sistema. Contacta al administrador.';
        }
        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');
        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador para que te asigne una.';
        }
        $hasOpen = \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();
        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar operaciones.';
        }
        return null;
    }

    protected function purchaseTemplateQuery(int $companyId)
    {
        return DocumentTemplate::query()
            ->where('active', true)
            ->whereIn('type', ['recibo', 'boleta', 'otro'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%Compra%')
                    ->orWhere('description', 'like', '%compra%')
                    ->orWhere('content', 'like', '%COMPROBANTE DE COMPRA%')
                    ->orWhere('content', 'like', '%{{compra_codigo}}%');
            });
    }
}
