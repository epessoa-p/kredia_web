<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Manual de usuario (Ayuda). Es público para que también se pueda abrir desde la app móvil.
 * Cada capítulo es una vista en resources/views/help/chapters; la misma vista se usa en la
 * página y en el PDF.
 */
class HelpController extends Controller
{
    /** slug => [título, ícono de Bootstrap Icons] (en el orden del manual). */
    public const CHAPTERS = [
        'primeros-pasos' => ['Primeros pasos', 'bi-flag'],
        'caja' => ['Caja: abrir, gastos y cierre', 'bi-cash-stack'],
        'clientes' => ['Clientes', 'bi-people'],
        'prestamos' => ['Préstamos', 'bi-wallet2'],
        'cobros' => ['Cobros de préstamos', 'bi-cash-coin'],
        'mora' => ['Mora, multa e incumplimiento', 'bi-exclamation-triangle'],
        'ventas' => ['Ventas', 'bi-bag-check'],
        'compras' => ['Compras', 'bi-cart-plus'],
        'inventario' => ['Inventario y productos', 'bi-boxes'],
        'catalogos' => ['Catálogos', 'bi-journal-richtext'],
        'reportes' => ['Movimientos y reportes', 'bi-bar-chart'],
        'capital' => ['Capital de la empresa', 'bi-piggy-bank'],
        'administracion' => ['Administración del sistema', 'bi-gear'],
        'app-movil' => ['App móvil', 'bi-phone'],
        'preguntas' => ['Preguntas frecuentes', 'bi-question-circle'],
        'menu' => ['Referencia por menú', 'bi-list-ul'],
    ];

    public function index()
    {
        return view('help.index', ['chapters' => self::CHAPTERS]);
    }

    public function pdf()
    {
        $pdf = Pdf::loadView('help.pdf', ['chapters' => self::CHAPTERS])
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download('manual-de-usuario.pdf');
    }
}
