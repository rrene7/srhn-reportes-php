-- Índices recomendados para SRHN Reportes PHP
-- Objetivo: acelerar consultas de reportes, consulta individual y acciones de personal.
-- Ejecutar una sola vez en la base local, por ejemplo:
-- mysql -u root -D rhhgith < database/patches/2026_07_02_indexes_reportes.sql

ALTER TABLE employees
    ADD INDEX idx_employees_legacy_position (legacy_position),
    ADD INDEX idx_employees_external_agent_number (external_agent_number),
    ADD INDEX idx_employees_document_number (document_number),
    ADD INDEX idx_employees_first_name (first_name),
    ADD INDEX idx_employees_last_name (last_name),
    ADD INDEX idx_employees_rank_id (rank_id),
    ADD INDEX idx_employees_unit_id (unit_id),
    ADD INDEX idx_employees_status_id (status_id);

ALTER TABLE employee_actions
    ADD INDEX idx_employee_actions_employee_id (employee_id),
    ADD INDEX idx_employee_actions_action_type_id (action_type_id),
    ADD INDEX idx_employee_actions_action_date (action_date),
    ADD INDEX idx_employee_actions_resolution_number (resolution_number),
    ADD INDEX idx_employee_actions_ogd_number (ogd_number),
    ADD INDEX idx_employee_actions_deleted_at (deleted_at),
    ADD INDEX idx_employee_actions_employee_date (employee_id, action_date),
    ADD INDEX idx_employee_actions_type_date (action_type_id, action_date);

ALTER TABLE ranks
    ADD INDEX idx_ranks_legacy_code (legacy_code),
    ADD INDEX idx_ranks_sort_order (sort_order);

ALTER TABLE units
    ADD INDEX idx_units_legacy_code (legacy_code);

ALTER TABLE statuses
    ADD INDEX idx_statuses_legacy_code (legacy_code);
