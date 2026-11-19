<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calificación de clientes — valores por defecto
    |--------------------------------------------------------------------------
    |
    | Reglas para la categoría automática de cada cliente (Fiel, Puntual,
    | Regular, Riesgo, Nuevo). Cada empresa puede cambiarlas desde
    | Clientes → "Configurar categorías" (se guardan en
    | companies.client_rating_settings); estos son los valores que se usan
    | mientras la empresa no los haya cambiado.
    |
    | tolerance_days        Días después del vencimiento de un interés en los
    |                       que el pago todavía cuenta como "a tiempo".
    | punctual_max_late     Intereses con atraso que se permiten y el cliente
    |                       sigue siendo "Puntual".
    | penalty_breaks        true = haber tenido una multa le quita la puntualidad.
    | loyal_min_loans       "Fiel": puntual y con al menos esta cantidad de préstamos…
    | loyal_min_amount      …y/o al menos este monto prestado en total (Bs).
    | loyal_mode            'any' = basta una de las dos condiciones; 'all' = ambas.
    | loyal_min_punctuality "Fiel": % mínimo de intereses pagados a tiempo (0 = no se exige).
    | risk_include_mora     true = un préstamo EN MORA ya lo pone en "Riesgo"
    |                       (los INCUMPLIDOS siempre cuentan).
    |
    */

    'rating' => [
        'tolerance_days' => (int) env('CLIENTS_TOLERANCE_DAYS', 3),
        'punctual_max_late' => 0,
        'penalty_breaks' => true,
        'loyal_min_loans' => (int) env('CLIENTS_LOYAL_MIN_LOANS', 2),
        'loyal_min_amount' => (float) env('CLIENTS_LOYAL_MIN_AMOUNT', 3000),
        'loyal_mode' => 'any',
        'loyal_min_punctuality' => 0,
        'risk_include_mora' => true,
    ],

];
