<?php

namespace App\Http\Controllers;

/** Pantalla que ve una empresa cuando su suscripción no permite el acceso. */
class SubscriptionController extends Controller
{
    public function blocked()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        $subscription = $company?->subscription;

        // Si ya está vigente (p. ej. la renovaron), volver al inicio.
        if ($user->is_super_admin || ($subscription && $subscription->allowsRead())) {
            return redirect()->route('dashboard');
        }

        return view('subscription.blocked', [
            'company' => $company,
            'subscription' => $subscription,
            'hasOtherCompanies' => $user->activeCompanies()->count() > 1,
        ]);
    }
}
