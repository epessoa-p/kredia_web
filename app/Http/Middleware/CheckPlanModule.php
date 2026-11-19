<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el plan de la empresa incluya al menos UNO de los módulos (OR).
 * Uso: ->middleware('plan:sales')  ·  ->middleware('plan:pawn,installments')
 * Sirve para web y API (responde JSON si la petición lo espera o viene de /api).
 */
class CheckPlanModule
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();

        if (!$user || $user->is_super_admin) {
            return $next($request);
        }

        $company = $request->attributes->get('apiCompany') ?? $user->getCurrentCompany();

        if ($company && $company->planAllows(...$features)) {
            return $next($request);
        }

        $labels = collect($features)->map(fn ($f) => Plan::MODULES[$f] ?? $f)->implode(' / ');
        $message = "Tu plan no incluye el módulo: {$labels}. Contacta a tu proveedor para ampliarlo.";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'code' => 'plan_module_disabled', 'modules' => $features], 403);
        }

        return response()->view('errors.403', ['message' => $message], 403);
    }
}
