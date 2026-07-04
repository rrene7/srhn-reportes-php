<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class EstadisticasPersonalModel
{
    public function __construct(private PDO $db) {}

    public function catalogos(): array
    {
        return [
            'rangos' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC')->fetchAll(),
            'unidades' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC')->fetchAll(),
            'estados' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC')->fetchAll(),
        ];
    }
}
