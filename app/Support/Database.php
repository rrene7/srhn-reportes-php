<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(string $connection = 'default'): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        // Compatibilidad con la configuración plana original.
        if (isset($config['host'])) {
            $config = ['default' => $config];
        }

        if (!isset($config[$connection]) || !is_array($config[$connection])) {
            throw new RuntimeException('Conexión de base de datos no configurada: ' . $connection);
        }

        $selected = $config[$connection];
        $database = (string) ($selected['database'] ?? '');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $selected['host'] ?? '127.0.0.1',
            $selected['port'] ?? '3306',
            $database,
            $selected['charset'] ?? 'utf8mb4'
        );

        try {
            return new PDO($dsn, $selected['username'] ?? 'root', $selected['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf(
                    'No se pudo conectar a la base de datos %s mediante la conexión %s: %s',
                    $database !== '' ? $database : '(sin nombre)',
                    $connection,
                    $e->getMessage()
                )
            );
        }
    }
}
