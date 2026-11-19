<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DocumentTemplate extends Model
{
    use Concerns\BelongsToCompany;

    /** Plantillas con company_id NULL son globales del sistema: visibles para todas las empresas. */
    public bool $includeGlobalCompanyRows = true;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'description',
        'content',
        'paper_size',
        'active',
        'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    const TYPES = [
        'contrato'     => 'Contrato',
        'boleta'       => 'Boleta',
        'recibo'       => 'Recibo',
        'amortizacion' => 'Amortización',
        'liquidacion'  => 'Liquidación',
        'otro'         => 'Otro',
    ];

    const TYPE_BADGES = [
        'contrato'     => 'primary',
        'boleta'       => 'success',
        'recibo'       => 'info',
        'amortizacion' => 'warning',
        'liquidacion'  => 'danger',
        'otro'         => 'secondary',
    ];

    const PAPER_SIZES = [
        'grande'  => 'Carta (215.9 × 279.4 mm)',
        'mediano' => 'Media carta (215.9 × 139.7 mm)',
        'pequeno' => 'Recibo térmico (80 mm)',
    ];

    const PAPER_SIZE_CSS = [
        'grande'  => ['width' => '215.9mm', 'min-height' => '279.4mm', 'page' => 'size: letter portrait'],
        'mediano' => ['width' => '215.9mm', 'min-height' => '139.7mm', 'page' => 'size: 215.9mm 139.7mm'],
        'pequeno' => ['width' => '80mm',    'min-height' => 'auto',    'page' => 'size: 80mm auto'],
    ];

    const PLACEHOLDERS = [
        'cliente_nombre'    => 'Nombre del cliente',
        'cliente_cedula'    => 'Cédula del cliente',
        'cliente_telefono'  => 'Teléfono del cliente',
        'cliente_email'     => 'Email del cliente',
        'cliente_direccion' => 'Dirección del cliente',
        'prestamo_id'       => 'ID del préstamo',
        'prestamo_monto'    => 'Monto del préstamo',
        'prestamo_interes_porcentaje' => 'Porcentaje de interés del préstamo',
        'prestamo_producto' => 'Producto asociado al préstamo',
        'prestamo_categoria' => 'Categoría del préstamo',
        'prestamo_tasa'     => 'Tasa de interés',
        'prestamo_periodo'  => 'Periodicidad del interés',
        'prestamo_plazo'    => 'Plazo en meses',
        'prestamo_cuota'    => 'Cuota mensual',
        'prestamo_total'    => 'Total a pagar',
        'prestamo_saldo'    => 'Saldo pendiente',
        'capital_vigente'   => 'Capital vigente mostrado en operaciones',
        'interes_pendiente' => 'Interés pendiente mostrado en operaciones',
        'multa_monto'       => 'Multa aplicada en operaciones',
        'liquidacion_total' => 'Total a liquidar (capital + intereses + multa)',
        'liquidacion_monto' => 'Monto pagado en la liquidación',
        'liquidacion_fecha' => 'Fecha del pago de liquidación',
        'liquidacion_metodo' => 'Método de pago de la liquidación',
        'liquidacion_referencia' => 'Referencia del pago de liquidación',
        'liquidacion_notas' => 'Notas de la liquidación',
        'liquidacion_capital_vigente' => 'Capital vigente al momento de liquidar',
        'liquidacion_interes_pendiente' => 'Intereses pendientes al momento de liquidar',
        'liquidacion_multa' => 'Multa incluida en la liquidación',
        'empresa_nombre'    => 'Nombre de la empresa',
        'sucursal_nombre'   => 'Nombre de la sucursal',
        'sucursal_telefono' => 'Teléfono de la sucursal',
        'fecha_actual'      => 'Fecha actual',
        'fecha_inicio'      => 'Fecha de inicio del préstamo',
        'fecha_fin'         => 'Fecha de vencimiento',
        'producto_nombre'   => 'Nombre del producto',
        'producto_categoria' => 'Categoría del producto',
        'producto_marca'    => 'Marca del producto',
        'producto_modelo_anio' => 'Modelo/año del producto',
        'producto_placa'    => 'Placa del vehículo',
        'producto_chasis'   => 'Chasis del vehículo',
        'producto_serie'    => 'Número de serie del producto',
        'producto_kilataje' => 'Kilataje de joya',
        'producto_tipo'     => 'Tipo de artículo/joya',
        'producto_descripcion' => 'Descripción del producto',
        'producto_detalles_categoria' => 'Resumen de detalles del producto según categoría',
        'recojo_monto'  => 'Capital vigente + intereses pendientes (monto para recuperar el artículo)',
        'compra_codigo'     => 'Código de compra (CO-00001)',
        'compra_id'         => 'ID interno de la compra',
        'compra_monto'      => 'Monto de la compra',
        'compra_fecha'      => 'Fecha de la compra',
        'compra_estado'     => 'Estado de la compra',
        'compra_producto'   => 'Producto de la compra',
        'compra_categoria'  => 'Categoría de crédito asociada',
        'compra_sucursal'   => 'Sucursal de la compra',
        'compra_caja'       => 'Caja registradora usada',
        'compra_registrado_por' => 'Usuario que registró la compra',
        'compra_notas'      => 'Notas de la compra',
        'firma_prestamista' => 'Firma del prestamista (imagen)',
        'firma_cliente'     => 'Firma del cliente (imagen)',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeBadgeAttribute(): string
    {
        return self::TYPE_BADGES[$this->type] ?? 'secondary';
    }

    public function getPaperSizeLabelAttribute(): string
    {
        return self::PAPER_SIZES[$this->paper_size] ?? self::PAPER_SIZES['grande'];
    }

    public function getPaperSizeCssAttribute(): array
    {
        return self::PAPER_SIZE_CSS[$this->paper_size] ?? self::PAPER_SIZE_CSS['grande'];
    }

    /**
     * Replace template placeholders with actual loan / client data.
     */
    public function applyToLoan(Loan $loan): string
    {
        $loan->loadMissing([
            'contract',
            'client',
            'company',
            'branch',
            'product.creditCategory',
            'creditCategory',
        ]);

        $product = $loan->product;
        $productCategory = $product?->creditCategory?->name
            ?? $loan->creditCategory?->name
            ?? '';

        $periodLabel = match ((string) ($loan->interest_period ?? 'monthly')) {
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'semiannual' => 'Semestral',
            'annual' => 'Anual',
            default => ucfirst((string) ($loan->interest_period ?? 'Mensual')),
        };

        $interestPercent = (float) ($loan->interest_rate ?? 0);

        $replacements = [
            '{{cliente_nombre}}'    => $loan->client?->name ?? '',
            '{{cliente_cedula}}'    => $loan->client?->id_number ?? '',
            '{{cliente_telefono}}'  => $loan->client?->phone ?? '',
            '{{cliente_email}}'     => $loan->client?->email ?? '',
            '{{cliente_direccion}}' => $loan->client?->address ?? '',
            '{{prestamo_id}}'       => 'PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
            '{{prestamo_monto}}'    => number_format((float) $loan->amount, 2),
            '{{prestamo_interes_porcentaje}}' => number_format($interestPercent, 2) . '%',
            '{{prestamo_producto}}' => $product?->name ?? '',
            '{{prestamo_categoria}}' => $productCategory,
            '{{prestamo_tasa}}'     => $loan->interest_rate . '%',
            '{{prestamo_periodo}}'  => $periodLabel,
            '{{prestamo_plazo}}'    => $loan->term_months . ' meses',
            '{{prestamo_cuota}}'    => number_format((float) $loan->monthly_payment, 2),
            '{{prestamo_total}}'    => number_format((float) $loan->total_to_pay, 2),
            '{{prestamo_saldo}}'    => number_format(max(0, (float) $loan->total_to_pay - (float) $loan->total_paid), 2),
            '{{empresa_nombre}}'    => $loan->company?->name ?? '',
            '{{sucursal_nombre}}'   => $loan->branch?->name ?? '',
            '{{sucursal_telefono}}' => $loan->branch?->phone ?? '',
            '{{fecha_actual}}'      => now()->format('d/m/Y'),
            '{{fecha_inicio}}'      => $loan->start_date?->format('d/m/Y') ?? '',
            '{{fecha_fin}}'         => $loan->end_date?->format('d/m/Y') ?? '',
            '{{producto_nombre}}'   => $product?->name ?? '',
            '{{producto_categoria}}' => $productCategory,
            '{{producto_marca}}'    => $product?->brand ?? '',
            '{{producto_modelo_anio}}' => $product?->model_year ? (string) $product->model_year : ($product?->item_model ?? ''),
            '{{producto_placa}}'    => $product?->plate ?? '',
            '{{producto_chasis}}'   => $product?->chassis ?? '',
            '{{producto_serie}}'    => $product?->serial_number ?? '',
            '{{producto_kilataje}}' => $product?->karat ?? '',
            '{{producto_tipo}}'     => $product?->item_type ?? '',
            '{{producto_descripcion}}' => $product?->description ?? '',
            '{{producto_detalles_categoria}}' => $this->buildLoanProductDetailsByCategory($loan),
            '{{firma_prestamista}}' => $this->buildSignatureImg($loan->contract?->lender_signature_path, 'Firma del prestamista'),
            '{{firma_cliente}}'     => $this->buildSignatureImg($loan->contract?->client_signature_path, 'Firma del cliente'),
            '{{capital_vigente}}'   => \App\Support\Currency::format($loan->getCurrentCapital(), $loan->company),
            '{{interes_pendiente}}' => \App\Support\Currency::format($loan->getUnpaidInterestTotal(), $loan->company),
            '{{recojo_monto}}'      => \App\Support\Currency::format($loan->getCurrentCapital() + $loan->getUnpaidInterestTotal(), $loan->company),
        ];

        return \App\Support\HtmlSanitizer::clean(str_replace(array_keys($replacements), array_values($replacements), (string) $this->content));
    }

    /**
     * Replace template placeholders with purchase / client data.
     */
    public function applyToPurchase(Purchase $purchase): string
    {
        $purchase->loadMissing('client', 'company', 'branch', 'product', 'creditCategory', 'cashRegister', 'createdBy');

        $purchaseStatus = match ((string) ($purchase->status ?? 'completed')) {
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            default => ucfirst((string) ($purchase->status ?? 'Completada')),
        };

        $purchaseDate = $purchase->purchase_date
            ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y')
            : ($purchase->created_at?->format('d/m/Y') ?? now()->format('d/m/Y'));

        $replacements = [
            '{{cliente_nombre}}'          => $purchase->client?->name ?? '',
            '{{cliente_cedula}}'          => $purchase->client?->id_number ?? '',
            '{{cliente_telefono}}'        => $purchase->client?->phone ?? '',
            '{{cliente_email}}'           => $purchase->client?->email ?? '',
            '{{cliente_direccion}}'       => $purchase->client?->address ?? '',
            '{{empresa_nombre}}'          => $purchase->company?->name ?? '',
            '{{sucursal_nombre}}'         => $purchase->branch?->name ?? '',
            '{{sucursal_telefono}}'       => $purchase->branch?->phone ?? '',
            '{{fecha_actual}}'            => now()->format('d/m/Y'),
            '{{compra_codigo}}'           => 'CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT),
            '{{compra_id}}'               => 'CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT),
            '{{compra_monto}}'            => number_format((float) $purchase->amount, 2),
            '{{compra_fecha}}'            => $purchaseDate,
            '{{compra_estado}}'           => $purchaseStatus,
            '{{compra_producto}}'         => $purchase->product?->name ?? '',
            '{{compra_categoria}}'        => $purchase->creditCategory?->name ?? '',
            '{{compra_sucursal}}'         => $purchase->branch?->name ?? '',
            '{{compra_caja}}'             => $purchase->cashRegister?->name ?? '',
            '{{compra_registrado_por}}'   => $purchase->createdBy?->name ?? '',
            '{{compra_notas}}'            => (string) ($purchase->notes ?? ''),
        ];

        return \App\Support\HtmlSanitizer::clean(str_replace(array_keys($replacements), array_values($replacements), (string) $this->content));
    }

    /**
     * Build an inline <img> tag from a stored signature path.
     * Uses base64 so it works in PDF previews and Word exports.
     */
    protected function buildSignatureImg(?string $path, string $alt): string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return '<span style="color:#9ca3af; font-size:11px; font-style:italic;">[' . e($alt) . ' — pendiente]</span>';
        }

        $binary  = Storage::disk('public')->get($path);
        $base64  = base64_encode($binary);
        $mime    = Storage::disk('public')->mimeType($path) ?: 'image/png';
        $isSmall = ($this->paper_size ?? 'grande') === 'pequeno';
        $height  = $isSmall ? '40px' : '70px';

        return '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="' . e($alt) . '" '
             . 'style="max-height:' . $height . '; max-width:100%; display:block; margin:4px 0;">';
    }

    /**
     * Build a category-aware text summary for the product used as collateral.
     */
    protected function buildLoanProductDetailsByCategory(Loan $loan): string
    {
        $product = $loan->product;
        if (!$product) {
            return '';
        }

        $categoryName = mb_strtolower((string) ($product->creditCategory?->name ?? $loan->creditCategory?->name ?? ''));
        $parts = [];

        if ($product->name) {
            $parts[] = 'Producto: ' . $product->name;
        }

        if (str_contains($categoryName, 'veh')) {
            if ($product->brand) {
                $parts[] = 'Marca: ' . $product->brand;
            }
            if ($product->model_year) {
                $parts[] = 'Modelo/Año: ' . $product->model_year;
            }
            if ($product->plate) {
                $parts[] = 'Placa: ' . $product->plate;
            }
            if ($product->chassis) {
                $parts[] = 'Chasis: ' . $product->chassis;
            }
        } elseif (str_contains($categoryName, 'joy')) {
            if ($product->item_type) {
                $parts[] = 'Tipo: ' . $product->item_type;
            }
            if ($product->karat) {
                $parts[] = 'Kilataje: ' . $product->karat;
            }
        } else {
            if ($product->brand) {
                $parts[] = 'Marca: ' . $product->brand;
            }
            if ($product->item_model) {
                $parts[] = 'Modelo: ' . $product->item_model;
            }
            if ($product->serial_number) {
                $parts[] = 'Serie: ' . $product->serial_number;
            }
            if ($product->item_type) {
                $parts[] = 'Tipo: ' . $product->item_type;
            }
        }

        if ($product->description) {
            $parts[] = 'Descripción: ' . $product->description;
        }

        return implode(' | ', $parts);
    }
}
