<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class ReportTemplateModel
{
    public function __construct(private PDO $db) {}

    public function existeTabla(): bool
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'report_templates'");
        return (bool) $stmt->fetchColumn();
    }

    public function listar(): array
    {
        if (!$this->existeTabla()) {
            return [];
        }

        return $this->db
            ->query("SELECT id, name, source, query_string, is_active, created_at, updated_at FROM report_templates WHERE is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 50")
            ->fetchAll();
    }

    public function guardar(string $name, string $source, array $columns, array $filters, string $queryString): void
    {
        if (!$this->existeTabla()) {
            throw new RuntimeException('La tabla report_templates no existe en la base de datos configurada del sistema.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO report_templates (name, source, columns_json, filters_json, query_string, is_active) VALUES (:name, :source, :columns_json, :filters_json, :query_string, 1)'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':source', $source);
        $stmt->bindValue(':columns_json', json_encode(array_values($columns), JSON_UNESCAPED_UNICODE));
        $stmt->bindValue(':filters_json', json_encode($filters, JSON_UNESCAPED_UNICODE));
        $stmt->bindValue(':query_string', $queryString);
        $stmt->execute();
    }
}
