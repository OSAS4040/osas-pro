<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZATCA integration mode
    |--------------------------------------------------------------------------
    |
    | When true (default), clearance/submit APIs record a local log only — no call
    | to ZATCA/Fatoora. Status endpoints report simulation_mode so UIs must not
    | imply production compliance. Set false only when a real integration is wired.
    |
    */
    'simulation_mode' => filter_var(env('ZATCA_SIMULATION_MODE', 'true'), FILTER_VALIDATE_BOOLEAN),

];
