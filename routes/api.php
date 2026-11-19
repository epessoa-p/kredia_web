<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LoanActionController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'ok' => true,
            'message' => 'API conectada',
            'timestamp' => now()->toIso8601String(),
            'license' => \App\Support\MobileLicense::status(),
        ]);
    });

    // Todo lo demás se rechaza (403 app_expired) cuando vence la app (config api.mobile_expires_at).
    Route::middleware('mobile-license')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    // auth-api fija la empresa activa; api.subscription corta si la suscripción no está vigente.
    // Los permisos (api-permission) ya vienen filtrados por los módulos del plan de la empresa.
    Route::middleware(['auth-api', 'api.subscription'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/company', [AuthController::class, 'selectCompany']);
        Route::post('/auth/device-token', [AuthController::class, 'updateDeviceToken']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Caja del usuario (misma lógica que la web: CashSessionService).
        Route::get('/cash-session', [CashSessionController::class, 'show']);
        Route::post('/cash-session/open', [CashSessionController::class, 'open'])->middleware('api-permission:cash-registers.operate');
        Route::post('/cash-session/close', [CashSessionController::class, 'close'])->middleware('api-permission:cash-registers.operate');
        Route::post('/cash-session/expense', [CashSessionController::class, 'expense'])->middleware('api-permission:cash-registers.expense');

        Route::get('/loans', [LoanController::class, 'index'])->middleware('api-permission:loans.view');
        Route::post('/loans', [LoanController::class, 'store'])->middleware('api-permission:loans.create');
        Route::get('/loans/categories', [LoanController::class, 'categories'])->middleware('api-permission:loans.view');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->middleware('api-permission:loans.view');

        Route::get('/payments', [PaymentController::class, 'index'])->middleware('api-permission:payments.view');
        Route::get('/payments/options', [PaymentController::class, 'options'])->middleware('api-permission:payments.view');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->middleware('api-permission:payments.view');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->whereNumber('payment')->middleware('api-permission:payments.view');
        // Obsoleto: lógica antigua (no marca periodos de interés). Lo usan las versiones viejas de la app.
        Route::post('/loans/{loan}/payments', [PaymentController::class, 'store'])->middleware('api-permission:payments.create');

        // Cobros con la misma lógica que la web (LoanPaymentService).
        Route::post('/loans/{loan}/interest-payments', [LoanActionController::class, 'payInterest'])->middleware('api-permission:payments.create');
        Route::post('/loans/{loan}/penalty-payment', [LoanActionController::class, 'payPenalty'])->middleware('api-permission:payments.create');
        Route::post('/loans/{loan}/amortization', [LoanActionController::class, 'amortize'])->middleware('api-permission:loans.edit,loans.amortize,payments.create');
        Route::post('/loans/{loan}/liquidation', [LoanActionController::class, 'liquidate'])->middleware('api-permission:loans.edit,loans.liquidate,payments.create');
        Route::delete('/loans/{loan}/interest-payments/{interestPayment}', [LoanActionController::class, 'deleteInterest'])->whereNumber('interestPayment')->middleware('api-permission:loans.interest.delete');
        Route::post('/loans/{loan}/interest-payments/{interestPayment}/restore', [LoanActionController::class, 'restoreInterest'])->whereNumber('interestPayment')->middleware('api-permission:loans.interest.restore');

        // Préstamos en cuotas y cobranza (InstallmentLoanService). Los permisos ya vienen filtrados por plan.
        Route::get('/installment-loans/options', [InstallmentController::class, 'options'])->middleware('api-permission:loans.create');
        Route::post('/installment-loans/preview', [InstallmentController::class, 'preview'])->middleware('api-permission:loans.create');
        Route::post('/installment-loans', [InstallmentController::class, 'store'])->middleware('api-permission:loans.create');
        Route::get('/loans/{loan}/installments', [InstallmentController::class, 'show'])->middleware('api-permission:loans.view');
        Route::post('/loans/{loan}/installment-payments', [InstallmentController::class, 'pay'])->middleware('api-permission:payments.create');
        Route::post('/loans/{loan}/refinance', [InstallmentController::class, 'refinance'])->middleware('api-permission:loans.refinance');
        Route::post('/loan-payments/{payment}/void', [InstallmentController::class, 'void'])->middleware('api-permission:payments.void');
        Route::get('/collections/today', [InstallmentController::class, 'today'])->middleware('api-permission:collections.view');

        Route::get('/clients', [ClientController::class, 'index'])->middleware('api-permission:clients.view');
        Route::post('/clients', [ClientController::class, 'store'])->middleware('api-permission:clients.create');
        Route::get('/clients/tags', [ClientController::class, 'tags'])->middleware('api-permission:clients.view');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('api-permission:clients.view');
        Route::patch('/clients/{client}/tag', [ClientController::class, 'updateTag'])->middleware('api-permission:clients.tag');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->middleware('api-permission:clients.edit');
        Route::delete('/clients/documents/{document}', [ClientController::class, 'destroyDocument'])->middleware('api-permission:clients.edit');

        Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('api-permission:purchases.view');
        // Compras (misma lógica que la web: PurchaseService). Las rutas fijas van antes de /purchases/{purchase}.
        Route::get('/purchases/options', [PurchaseController::class, 'options'])->middleware('api-permission:purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('api-permission:purchases.create');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('api-permission:purchases.view');
        Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->middleware('api-permission:purchases.delete');

        Route::get('/sales', [SaleController::class, 'index'])->middleware('api-permission:sales.view');
        // Ventas (misma lógica que la web: SaleService). Las rutas fijas van antes de /sales/{sale}.
        Route::get('/sales/options', [SaleController::class, 'options'])->middleware('api-permission:sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->middleware('api-permission:sales.create');
        Route::post('/sales/installments/{installment}/pay', [SaleController::class, 'payInstallment'])->middleware('api-permission:sales.create');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('api-permission:sales.view');
        Route::post('/sales/{sale}/liquidate', [SaleController::class, 'liquidate'])->middleware('api-permission:sales.create');
        Route::post('/sales/{sale}/return', [SaleController::class, 'processReturn'])->middleware('api-permission:sales.view');

        Route::get('/movements', [MovementController::class, 'index'])->middleware('api-permission:reports.view');
        Route::get('/movements/{movement}', [MovementController::class, 'show'])->middleware('api-permission:reports.view');

        Route::get('/inventory/stock', [InventoryController::class, 'index'])->middleware('api-permission:warehouses.view');
        Route::get('/inventory/stock/{product}', [InventoryController::class, 'show'])->middleware('api-permission:warehouses.view');
        Route::get('/inventory/options', [InventoryController::class, 'options'])->middleware('api-permission:warehouses.view');
        Route::post('/inventory/stock-entry', [InventoryController::class, 'storeEntry'])->middleware('api-permission:warehouses.create');
        Route::patch('/inventory/products/{product}/price', [InventoryController::class, 'updatePrice'])->middleware('api-permission:warehouses.edit');

        Route::get('/catalogs', [CatalogController::class, 'index'])->middleware('api-permission:catalogs.view');
        Route::get('/catalogs/options', [CatalogController::class, 'options'])->middleware('api-permission:catalogs.create');
        Route::post('/catalogs', [CatalogController::class, 'store'])->middleware('api-permission:catalogs.create');
        Route::get('/catalogs/{catalog}', [CatalogController::class, 'show'])->whereNumber('catalog')->middleware('api-permission:catalogs.view');
        Route::put('/catalogs/{catalog}', [CatalogController::class, 'update'])->whereNumber('catalog')->middleware('api-permission:catalogs.create');
        Route::post('/catalogs/{catalog}/toggle-active', [CatalogController::class, 'toggleActive'])->whereNumber('catalog')->middleware('api-permission:catalogs.create');
        Route::post('/catalogs/{catalog}/regenerate-slug', [CatalogController::class, 'regenerateSlug'])->whereNumber('catalog')->middleware('api-permission:catalogs.create');
        Route::get('/catalogs/{catalog}/pdf', [CatalogController::class, 'pdf'])->whereNumber('catalog')->middleware('api-permission:catalogs.view');
        Route::delete('/catalogs/{catalog}', [CatalogController::class, 'destroy'])->whereNumber('catalog')->middleware('api-permission:catalogs.delete');

        // Reportes (mismos datos que la web: ReportService / ReportExporter)
        Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('api-permission:reports.view');
        Route::get('/reports/options', [ReportController::class, 'options'])->middleware('api-permission:reports.view');
        Route::get('/reports/closures/{session}', [ReportController::class, 'closure'])->whereNumber('session')->middleware('api-permission:reports.view');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('api-permission:reports.view');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->middleware('api-permission:reports.view');
    });
    }); // mobile-license
});
