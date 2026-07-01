<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'SRHN Reportes PHP'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'base_path' => rtrim(Env::get('APP_BASE_PATH', ''), '/'),
];
