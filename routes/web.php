<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CreditCategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\CargoController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientRatingSettingsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Loans\LoanActionController;
use App\Http\Controllers\Loans\LoanController;
use App\Http\Controllers\Loans\LoanContractController;
use App\Http\Controllers\Loans\LoanPaymentController;
use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleInstallmentController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Catalogs\CatalogController;
use App\Http\Controllers\DocumentTemplates\DocumentTemplateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Capital\CapitalController;
use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

// Manual de usuario (público: también se abre desde la app móvil)
Route::get('/ayuda', [HelpController::class, 'index'])->name('help.index');
Route::get('/ayuda/pdf', [HelpController::class, 'pdf'])->name('help.pdf');

// Archivo de documento de cliente: solo con enlace firmado y vigente (lo genera ClientDocument::url).
Route::get('/client-documents/{document}/file', \App\Http\Controllers\Clients\ClientDocumentFileController::class)
    ->name('client-documents.file')
    ->middleware('signed');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Company selection (multi-empresa)
    Route::get('/select-company', [LoginController::class, 'selectCompany'])->name('select-company');
    Route::post('/set-company/{companyId}', [LoginController::class, 'setCompany'])->name('set-company');

    // Suscripción no vigente
    Route::get('/subscription/blocked', [\App\Http\Controllers\SubscriptionController::class, 'blocked'])->name('subscription.blocked');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Super Admin - Company Management
    Route::middleware('super-admin')->prefix('admin/companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');
        Route::get('/{company}/subscription', [CompanyController::class, 'editSubscription'])->name('subscription.edit');
        Route::put('/{company}/subscription', [CompanyController::class, 'updateSubscription'])->name('subscription.update');
    });

    // Super Admin - Planes
    Route::middleware('super-admin')->prefix('admin/plans')->name('plans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\PlanController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('store');
        Route::get('/{plan}/edit', [\App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('edit');
        Route::put('/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('update');
        Route::delete('/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('destroy');
    });

    // User Management
    Route::middleware('check-permission:users.view')->prefix('admin/users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create')->middleware('check-permission:users.create');
        Route::post('/', [UserController::class, 'store'])->name('store')->middleware('check-permission:users.create');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit')->middleware('check-permission:users.edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update')->middleware('check-permission:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy')->middleware('check-permission:users.delete');
        Route::post('/{user}/assign-role/{company}/{role}', [UserController::class, 'assignRole'])->name('assign-role')->middleware('check-permission:users.edit');
    });

    // Role Management (Super Admin only)
    Route::middleware('super-admin')->prefix('admin/roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}', [RoleController::class, 'show'])->name('show');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('check-permission:cargos.view')->prefix('admin/cargos')->name('cargos.')->group(function () {
        Route::get('/', [CargoController::class, 'index'])->name('index');
        Route::get('/create', [CargoController::class, 'create'])->name('create')->middleware('check-permission:cargos.create');
        Route::post('/', [CargoController::class, 'store'])->name('store')->middleware('check-permission:cargos.create');
        Route::get('/role-permissions/{role}', [CargoController::class, 'rolePermissions'])->name('role-permissions');
        Route::get('/{cargo}/edit', [CargoController::class, 'edit'])->name('edit')->middleware('check-permission:cargos.edit');
        Route::put('/{cargo}', [CargoController::class, 'update'])->name('update')->middleware('check-permission:cargos.edit');
        Route::delete('/{cargo}', [CargoController::class, 'destroy'])->name('destroy')->middleware('check-permission:cargos.delete');
    });

    Route::middleware('check-permission:personal.view')->prefix('admin/personal')->name('personal.')->group(function () {
        Route::get('/', [PersonalController::class, 'index'])->name('index');
        Route::get('/create', [PersonalController::class, 'create'])->name('create')->middleware('check-permission:personal.create');
        Route::post('/', [PersonalController::class, 'store'])->name('store')->middleware('check-permission:personal.create');
        Route::get('/{personal}/edit', [PersonalController::class, 'edit'])->name('edit')->middleware('check-permission:personal.edit');
        Route::put('/{personal}', [PersonalController::class, 'update'])->name('update')->middleware('check-permission:personal.edit');
        Route::delete('/{personal}', [PersonalController::class, 'destroy'])->name('destroy')->middleware('check-permission:personal.delete');
    });

    Route::middleware('check-permission:branches.view')->prefix('admin/branches')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/create', [BranchController::class, 'create'])->name('create')->middleware('check-permission:branches.create');
        Route::post('/', [BranchController::class, 'store'])->name('store')->middleware('check-permission:branches.create');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit')->middleware('check-permission:branches.edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update')->middleware('check-permission:branches.edit');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy')->middleware('check-permission:branches.delete');
        // Reinicio destructivo de todos los registros operativos de la sucursal
        Route::post('/{branch}/reset', [BranchController::class, 'reset'])->name('reset')->middleware('super-admin');
    });

    Route::middleware(['plan:inventory,pawn,sales,purchases', 'check-permission:products.view'])->prefix('admin/products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create')->middleware('check-permission:products.create');
        // Solo super admin (se comprueba en el controlador): separa los productos compartidos por varios préstamos/compras.
        Route::post('/split-shared', [ProductController::class, 'splitShared'])->name('split-shared');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit')->middleware('check-permission:products.edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update')->middleware('check-permission:products.edit');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy')->middleware('check-permission:products.delete');
    });

    Route::middleware(['plan:pawn,installments', 'check-permission:credit-categories.view'])->prefix('admin/credit-categories')->name('credit-categories.')->group(function () {
        Route::get('/', [CreditCategoryController::class, 'index'])->name('index');
        Route::get('/create', [CreditCategoryController::class, 'create'])->name('create')->middleware('check-permission:credit-categories.create');
        Route::post('/', [CreditCategoryController::class, 'store'])->name('store')->middleware('check-permission:credit-categories.create');
        Route::get('/{creditCategory}', [CreditCategoryController::class, 'show'])->name('show');
        Route::get('/{creditCategory}/edit', [CreditCategoryController::class, 'edit'])->name('edit')->middleware('check-permission:credit-categories.edit');
        Route::put('/{creditCategory}', [CreditCategoryController::class, 'update'])->name('update')->middleware('check-permission:credit-categories.edit');
        Route::delete('/{creditCategory}', [CreditCategoryController::class, 'destroy'])->name('destroy')->middleware('check-permission:credit-categories.delete');
    });

    Route::middleware(['plan:inventory,pawn,sales,purchases', 'check-permission:warehouses.view'])->prefix('admin/warehouses')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::get('/create', [WarehouseController::class, 'create'])->name('create')->middleware('check-permission:warehouses.create');
        Route::post('/', [WarehouseController::class, 'store'])->name('store')->middleware('check-permission:warehouses.create');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
        Route::get('/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('edit')->middleware('check-permission:warehouses.edit');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update')->middleware('check-permission:warehouses.edit');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy')->middleware('check-permission:warehouses.delete');
        Route::post('/{warehouse}/movements', [WarehouseController::class, 'storeMovement'])->name('movements.store')->middleware('check-permission:warehouses.edit');
    });

    // Cash Registers (Cajas)
    Route::middleware('check-permission:cash-registers.view')->prefix('admin/cash-registers')->name('cash-registers.')->group(function () {
        Route::get('/', [CashRegisterController::class, 'index'])->name('index');
        Route::get('/create', [CashRegisterController::class, 'create'])->name('create')->middleware('check-permission:cash-registers.create');
        Route::post('/', [CashRegisterController::class, 'store'])->name('store')->middleware('check-permission:cash-registers.create');
        Route::get('/{cashRegister}', [CashRegisterController::class, 'show'])->name('show');
        Route::get('/{cashRegister}/edit', [CashRegisterController::class, 'edit'])->name('edit')->middleware('check-permission:cash-registers.edit');
        Route::put('/{cashRegister}', [CashRegisterController::class, 'update'])->name('update')->middleware('check-permission:cash-registers.edit');
        Route::delete('/{cashRegister}', [CashRegisterController::class, 'destroy'])->name('destroy')->middleware('check-permission:cash-registers.delete');
    });

    // Cash Register Open/Close
    Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->name('cash-register.open')->middleware('check-permission:cash-registers.operate');
    Route::post('/cash-register/close', [CashRegisterController::class, 'close'])->name('cash-register.close')->middleware('check-permission:cash-registers.operate');

    // Cash Register Close Adjustment (ajuste de cierre)
    Route::post('/cash-register/sessions/{session}/adjust-amount', [CashRegisterController::class, 'adjustCloseAmount'])->name('cash-register.session.adjust-amount')->middleware('check-permission:cash-registers.adjust');
    Route::post('/cash-register/sessions/{session}/adjust-movement', [CashRegisterController::class, 'adjustCloseMovement'])->name('cash-register.session.adjust-movement')->middleware('check-permission:cash-registers.adjust');

    // Ingreso de efectivo a una caja abierta
    Route::post('/cash-register/sessions/{session}/deposit', [CashRegisterController::class, 'depositToSession'])->name('cash-register.session.deposit')->middleware('check-permission:cash-registers.deposit');

    // Registrar un gasto (egreso) en una caja abierta
    Route::post('/cash-register/sessions/{session}/expense', [CashRegisterController::class, 'registerExpense'])->name('cash-register.session.expense')->middleware('check-permission:cash-registers.expense');

    // Loans Management
    Route::middleware(['plan:pawn,installments', 'check-permission:loans.view'])->prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/create', [LoanController::class, 'create'])->name('create')->middleware('check-permission:loans.create');
        Route::post('/', [LoanController::class, 'store'])->name('store')->middleware('check-permission:loans.create');
        Route::get('/payments', [LoanPaymentController::class, 'index'])->name('payments.index')->middleware('check-permission:payments.view');

        // Préstamos en cuotas (módulo de plan "installments")
        Route::middleware('plan:installments')->prefix('installments')->name('installments.')->group(function () {
            Route::get('/create', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'create'])->name('create')->middleware('check-permission:loans.create');
            Route::post('/preview', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'preview'])->name('preview')->middleware('check-permission:loans.create');
            Route::post('/', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'store'])->name('store')->middleware('check-permission:loans.create');
            Route::get('/{loan}', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'show'])->name('show');
            Route::post('/{loan}/payments', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'pay'])->name('pay')->middleware('check-permission:payments.create');
            Route::post('/{loan}/payments/{payment}/void', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'void'])->name('void')->middleware('check-permission:payments.void');
            Route::post('/{loan}/refinance', [\App\Http\Controllers\Loans\InstallmentLoanController::class, 'refinance'])->name('refinance')->middleware('check-permission:loans.refinance');
        });
        Route::get('/{loan}/payments/create', [LoanPaymentController::class, 'create'])->name('payments.create')->middleware('check-permission:payments.create');
        Route::post('/{loan}/payments', [LoanPaymentController::class, 'store'])->name('payments.store')->middleware('check-permission:payments.create');
        Route::get('/{loan}/amortize', [LoanActionController::class, 'amortize'])->name('amortize')->middleware('check-permission:loans.edit,loans.amortize,payments.create');
        Route::post('/{loan}/amortize', [LoanActionController::class, 'storeAmortization'])->name('amortize.store')->middleware('check-permission:loans.edit,loans.amortize,payments.create');
        Route::get('/{loan}/liquidate', [LoanActionController::class, 'liquidate'])->name('liquidate')->middleware('check-permission:loans.edit,loans.liquidate,payments.create');
        Route::post('/{loan}/liquidate', [LoanActionController::class, 'storeLiquidation'])->name('liquidate.store')->middleware('check-permission:loans.edit,loans.liquidate,payments.create');
        Route::post('/{loan}/interest-payment', [LoanActionController::class, 'storeInterestPayment'])->name('interest.store')->middleware('check-permission:payments.create');
        Route::post('/{loan}/penalty-payment', [LoanActionController::class, 'storePenaltyPayment'])->name('penalty.store')->middleware('check-permission:payments.create');
        Route::delete('/{loan}/interest-payment/{interestPayment}', [LoanActionController::class, 'destroyInterestPayment'])->name('interest.destroy')->middleware('check-permission:loans.interest.delete');
        Route::post('/{loan}/interest-payment/{interestPayment}/restore', [LoanActionController::class, 'restoreInterestPayment'])->name('interest.restore')->middleware('check-permission:loans.interest.restore')->withTrashed();
        Route::get('/{loan}/contract', [LoanContractController::class, 'edit'])->name('contract.edit');
        Route::post('/{loan}/contract', [LoanContractController::class, 'update'])->name('contract.update')->middleware('check-permission:loans.edit');
        Route::get('/{loan}/contract/download/pdf', [LoanContractController::class, 'downloadPdf'])->name('contract.download.pdf');
        Route::post('/{loan}/contract/download/pdf', [LoanContractController::class, 'downloadPdf'])->middleware('check-permission:loans.edit');
        Route::get('/{loan}/contract/download/word', [LoanContractController::class, 'downloadWord'])->name('contract.download.word');
        Route::post('/{loan}/contract/download/word', [LoanContractController::class, 'downloadWord'])->middleware('check-permission:loans.edit');
        Route::get('/{loan}/contract/download/excel', [LoanContractController::class, 'downloadExcel'])->name('contract.download.excel');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show')->withTrashed();
        Route::get('/{loan}/edit', [LoanController::class, 'edit'])->name('edit')->middleware('check-permission:loans.edit');
        Route::put('/{loan}', [LoanController::class, 'update'])->name('update')->middleware('check-permission:loans.edit');
        Route::post('/{loan}/approve', [LoanController::class, 'approve'])->name('approve')->middleware('check-permission:loans.approve');
        Route::post('/{loan}/activate', [LoanController::class, 'activate'])->name('activate')->middleware('check-permission:loans.approve');
        Route::post('/{loan}/payment', [LoanController::class, 'recordPayment'])->name('payment')->middleware('check-permission:payments.create');
        Route::delete('/{loan}', [LoanController::class, 'destroy'])->name('destroy')->middleware('check-permission:loans.delete');
    });

    // Purchases Management
    Route::middleware(['plan:purchases', 'check-permission:purchases.view'])->prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create')->middleware('check-permission:purchases.create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store')->middleware('check-permission:purchases.create');
        Route::get('/{purchase}', [PurchaseController::class, 'show'])->name('show');
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy'])->name('destroy')->middleware('check-permission:purchases.delete');
    });

    // Sales Management
    Route::middleware(['plan:sales', 'check-permission:sales.view'])->prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('index');
        Route::get('/quick-catalog', [SaleController::class, 'quickCatalog'])->name('quick-catalog')->middleware('check-permission:sales.create');
        Route::get('/create', [SaleController::class, 'create'])->name('create')->middleware('check-permission:sales.create');
        Route::post('/', [SaleController::class, 'store'])->name('store')->middleware('check-permission:sales.create');
        Route::get('/{sale}', [SaleController::class, 'show'])->name('show');
        // Sin middleware de permiso: processReturn() verifica en el controlador
        // (permiso 'sales.delete' O autorización puntual vigente para ESA venta).
        Route::post('/{sale}/return', [SaleController::class, 'processReturn'])->name('return');
        Route::post('/{sale}/liquidate', [SaleController::class, 'processLiquidation'])->name('liquidate')->middleware('check-permission:sales.create');
        Route::delete('/{sale}', [SaleController::class, 'destroy'])->name('destroy')->middleware('check-permission:sales.delete');
        // Autorización por única vez para eliminar una venta
        Route::post('/{sale}/delete-grant', [SaleController::class, 'storeDeleteGrant'])->name('delete-grant.store')->middleware('check-permission:sales.delegate_delete');
        Route::delete('/delete-grants/{grant}', [SaleController::class, 'revokeDeleteGrant'])->name('delete-grant.revoke')->middleware('check-permission:sales.delegate_delete');
        // Installment payments
        Route::post('/installments/{installment}/pay', [SaleInstallmentController::class, 'pay'])->name('installments.pay')->middleware('check-permission:sales.create');
        Route::get('/installments/{installment}/receipt', [SaleInstallmentController::class, 'receipt'])->name('installments.receipt');
    });

    // Document Templates
    Route::middleware('check-permission:document-templates.view')->prefix('document-templates')->name('document-templates.')->group(function () {
        Route::get('/', [DocumentTemplateController::class, 'index'])->name('index');
        Route::get('/create', [DocumentTemplateController::class, 'create'])->name('create')->middleware('check-permission:document-templates.create');
        Route::post('/', [DocumentTemplateController::class, 'store'])->name('store')->middleware('check-permission:document-templates.create');
        Route::get('/{documentTemplate}/download/word', [DocumentTemplateController::class, 'downloadWord'])->name('download.word');
        Route::get('/{documentTemplate}/export/pdf', [DocumentTemplateController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{documentTemplate}', [DocumentTemplateController::class, 'show'])->name('show');
        Route::get('/{documentTemplate}/edit', [DocumentTemplateController::class, 'edit'])->name('edit')->middleware('check-permission:document-templates.edit');
        Route::put('/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('update')->middleware('check-permission:document-templates.edit');
        Route::delete('/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('destroy')->middleware('check-permission:document-templates.delete');
        Route::post('/{documentTemplate}/apply-to-loan', [DocumentTemplateController::class, 'applyToLoan'])->name('apply-to-loan')->middleware('check-permission:document-templates.edit');
    });

    // Clients Module
    Route::middleware('check-permission:clients.view')->prefix('clients')->name('clients.')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/create', [ClientController::class, 'create'])->name('create')->middleware('check-permission:clients.create');
        Route::post('/', [ClientController::class, 'store'])->name('store')->middleware('check-permission:clients.create');
        Route::middleware('check-permission:clients.rating.settings')->prefix('rating-settings')->name('rating-settings.')->group(function () {
            Route::get('/', [ClientRatingSettingsController::class, 'edit'])->name('edit');
            Route::put('/', [ClientRatingSettingsController::class, 'update'])->name('update');
            Route::post('/preview', [ClientRatingSettingsController::class, 'preview'])->name('preview');
            Route::post('/tags', [ClientRatingSettingsController::class, 'storeTag'])->name('tags.store');
            Route::put('/tags/{tag}', [ClientRatingSettingsController::class, 'updateTag'])->name('tags.update');
            Route::delete('/tags/{tag}', [ClientRatingSettingsController::class, 'destroyTag'])->name('tags.destroy');
        });
        Route::patch('/{client}/tag', [ClientController::class, 'updateTag'])->name('tag.update')->middleware('check-permission:clients.tag');
        Route::get('/{client}', [ClientController::class, 'show'])->name('show');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('edit')->middleware('check-permission:clients.edit');
        Route::put('/{client}', [ClientController::class, 'update'])->name('update')->middleware('check-permission:clients.edit');
        Route::delete('/documents/{document}', [ClientController::class, 'destroyDocument'])->name('documents.destroy')->middleware('check-permission:clients.edit');
    });

    // Inventory Module
    Route::middleware(['plan:inventory,pawn,sales,purchases', 'check-permission:warehouses.view'])->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/product/{productId}/warehouse/{warehouseId}', [InventoryController::class, 'showProduct'])->name('product');
        // Formulario único para ingresar (crear) un producto: también lo abre Productos › Nuevo producto.
        Route::get('/stock-entry', [InventoryController::class, 'createEntry'])->name('stock-entry')->middleware('check-permission:products.create');
        Route::post('/stock-entry', [InventoryController::class, 'storeEntry'])->name('stock-entry.store')->middleware('check-permission:products.create');
        Route::post('/regularize', [InventoryController::class, 'regularizeOrphans'])->name('regularize')->middleware('check-permission:warehouses.edit');
        Route::post('/product/{product}/price', [InventoryController::class, 'updatePrice'])->name('product.price')->middleware('check-permission:warehouses.edit');
    });

    // Catalogs Module
    Route::middleware(['plan:catalogs', 'check-permission:catalogs.view'])->prefix('catalogs')->name('catalogs.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])->name('index');
        Route::get('/create', [CatalogController::class, 'create'])->name('create')->middleware('check-permission:catalogs.create');
        Route::post('/', [CatalogController::class, 'store'])->name('store')->middleware('check-permission:catalogs.create');
        Route::get('/{catalog}', [CatalogController::class, 'show'])->name('show');
        Route::get('/{catalog}/edit', [CatalogController::class, 'edit'])->name('edit')->middleware('check-permission:catalogs.create');
        Route::put('/{catalog}', [CatalogController::class, 'update'])->name('update')->middleware('check-permission:catalogs.create');
        Route::post('/{catalog}/toggle-active', [CatalogController::class, 'toggleActive'])->name('toggle-active')->middleware('check-permission:catalogs.create');
        Route::post('/{catalog}/regenerate-slug', [CatalogController::class, 'regenerateSlug'])->name('regenerate-slug')->middleware('check-permission:catalogs.create');
        Route::get('/{catalog}/export-pdf', [CatalogController::class, 'exportPdf'])->name('export-pdf');
        Route::delete('/{catalog}', [CatalogController::class, 'destroy'])->name('destroy')->middleware('check-permission:catalogs.delete');
    });

    // Exports
    Route::prefix('exports')->name('exports.')->group(function () {
        Route::get('/loans', [ExportController::class, 'loans'])->name('loans')->middleware('check-permission:loans.view');
        Route::get('/payments', [ExportController::class, 'payments'])->name('payments')->middleware('check-permission:reports.view');
        Route::get('/clients', [ExportController::class, 'clients'])->name('clients')->middleware('check-permission:clients.view');
        Route::get('/clients/{client}/loans', [ExportController::class, 'clientLoans'])->name('client-loans')->middleware('check-permission:clients.view');
        Route::get('/warehouses', [ExportController::class, 'warehouses'])->name('warehouses')->middleware('check-permission:warehouses.view');
        Route::get('/movements', [ExportController::class, 'movements'])->name('movements')->middleware('check-permission:reports.view');
        Route::get('/closures', [ExportController::class, 'closures'])->name('closures')->middleware('check-permission:reports.view');
    });

    // Capital Module
    Route::middleware('plan:capital')->prefix('capital')->name('capital.')->group(function () {
        Route::get('/',            [CapitalController::class, 'index'])->name('index')->middleware('check-permission:capital.view');
        Route::post('/',           [CapitalController::class, 'store'])->name('store')->middleware('check-permission:capital.create');
        Route::delete('/{capital}',[CapitalController::class, 'destroy'])->name('destroy')->middleware('check-permission:capital.create');
    });

    // Reports Module
    Route::middleware('check-permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/movements', [ReportController::class, 'movements'])->name('movements');
        Route::get('/loans', [ReportController::class, 'loans'])->name('loans');
        Route::get('/payments', [ReportController::class, 'payments'])->name('payments');
        Route::get('/cash-register-sessions', [ReportController::class, 'cashRegisterSessions'])->name('cash-register-sessions')->middleware('check-permission:cash-registers.report');
        Route::get('/clients', [ReportController::class, 'clients'])->name('clients');
        Route::get('/warehouses', [ReportController::class, 'warehouses'])->name('warehouses');
        // Cartera por tramos de atraso (morosidad)
        Route::get('/aging', function (\Illuminate\Http\Request $request, \App\Services\Reports\PortfolioAgingReport $report) {
            return view('reports.aging', $report->build($request->integer('branch_id') ?: null) + [
                'branches' => \App\Models\Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
                'branchId' => $request->integer('branch_id') ?: null,
            ]);
        })->name('aging');
    });

    // Cobranza: lista del día y rutas (módulo de plan "collections")
    Route::middleware(['plan:collections', 'check-permission:collections.view'])->prefix('collections')->name('collections.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Collections\CollectionController::class, 'today'])->name('today');
        Route::middleware('check-permission:collections.manage')->prefix('routes')->name('routes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Collections\CollectionController::class, 'routes'])->name('index');
            Route::post('/', [\App\Http\Controllers\Collections\CollectionController::class, 'storeRoute'])->name('store');
            Route::put('/{collectionRoute}', [\App\Http\Controllers\Collections\CollectionController::class, 'updateRoute'])->name('update');
            Route::delete('/{collectionRoute}', [\App\Http\Controllers\Collections\CollectionController::class, 'destroyRoute'])->name('destroy');
            Route::get('/{collectionRoute}/clients', [\App\Http\Controllers\Collections\CollectionController::class, 'editRouteClients'])->name('clients');
            Route::put('/{collectionRoute}/clients', [\App\Http\Controllers\Collections\CollectionController::class, 'updateRouteClients'])->name('clients.update');
        });
    });
});

// Public catalog view (no auth)
Route::get('/c/{slug}', [CatalogController::class, 'publicView'])
    ->where('slug', '[A-Za-z0-9_-]{6,64}')
    ->name('catalog.public')
    ->middleware('no-tenant');

// Fallback
Route::redirect('/', '/dashboard');

