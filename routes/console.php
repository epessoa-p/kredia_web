<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualiza mora/multa/prenda de los préstamos todos los días, sin depender
// de que alguien abra la pantalla de Préstamos.
// Requiere en el servidor: * * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('loans:check-overdue')->dailyAt('01:00')->withoutOverlapping();

// Recordatorio a cada cobrador de lo que debe cobrar hoy (préstamos en cuotas; plan con "notifications").
Schedule::command('collections:remind')->dailyAt('07:00')->withoutOverlapping();
