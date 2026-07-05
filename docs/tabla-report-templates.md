# Tabla report_templates

Usar esta tabla para guardar plantillas del Editor de REP moderno.

```sql
CREATE TABLE IF NOT EXISTS report_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    source VARCHAR(50) NOT NULL,
    columns_json TEXT NOT NULL,
    filters_json TEXT NOT NULL,
    query_string TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
