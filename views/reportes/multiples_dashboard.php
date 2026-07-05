<?php
/** @var array $statuses */
/** @var array $ranks */
/** @var array $actionTypes */
/** @var array $years */
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Dashboard de Reportes Múltiples</h2>
            <p>Filtros globales: al cambiar una opción, todos los bloques se actualizan automáticamente.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <button type="button" id="btn-refresh">Actualizar ahora</button>
        </div>
    </div>
</section>

<section class="card no-print">
    <h3>Filtros globales</h3>
    <form class="filters" id="multiFilters">
        <div class="field field-wide">
            <label for="unidad">Zona / Dirección / Unidad</label>
            <input type="text" name="unidad" id="unidad" placeholder="Ej. Chiriquí, Panamá Oeste, Dirección General, Telemática">
        </div>

        <div class="field">
            <label for="year">Año acciones</label>
            <select name="year" id="year">
                <option value="">Todos</option>
                <?php foreach ($years as $row): ?>
                    <?php if (!empty($row['year'])): ?>
                        <option value="<?= e($row['year']) ?>"><?= e($row['year']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="month">Mes acciones</label>
            <select name="month" id="month">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= e($m) ?>"><?= e(str_pad((string) $m, 2, '0', STR_PAD_LEFT)) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="field">
            <label for="status_id">Estado</label>
            <select name="status_id" id="status_id">
                <option value="">Todos</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status['id']) ?>"><?= e(($status['legacy_code'] ?? '') . ' - ' . ($status['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="rank_id">Rango</label>
            <select name="rank_id" id="rank_id">
                <option value="">Todos</option>
                <?php foreach ($ranks as $rank): ?>
                    <option value="<?= e($rank['id']) ?>"><?= e(($rank['legacy_code'] ?? '') . ' - ' . ($rank['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="action_type_id">Tipo acción</label>
            <select name="action_type_id" id="action_type_id">
                <option value="">Todos</option>
                <?php foreach ($actionTypes as $type): ?>
                    <option value="<?= e($type['id']) ?>"><?= e(($type['id'] ?? '') . ' - ' . ($type['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="actions">
            <button type="button" id="btn-clear" class="button-secondary">Limpiar</button>
        </div>
    </form>
</section>

<section class="card muted no-print">
    <p><strong>Alcance:</strong> <span id="scope-label">Cargando...</span></p>
    <p><strong>Última actualización:</strong> <span id="updated-at">---</span> <span id="loading-state"></span></p>
</section>

<section class="card" id="kpis-section">
    <h3>Resumen interactivo</h3>
    <div class="report-menu" id="kpis-grid"></div>
</section>

<div class="grid-2" id="dashboard-grid">
    <section class="card" id="estado-fuerza"><h3>Estado de fuerza</h3><div class="table-wrapper" data-block="estadoFuerza"></div></section>
    <section class="card" id="rangos"><h3>Rangos</h3><div class="table-wrapper" data-block="rangos"></div></section>
    <section class="card" id="acciones-tipo"><h3>Acciones por tipo</h3><div class="table-wrapper" data-block="accionesTipo"></div></section>
    <section class="card" id="acciones-mes"><h3>Acciones por mes</h3><div class="table-wrapper" data-block="accionesMes"></div></section>
    <section class="card" id="mapa-datos"><h3>Mapa / MOI</h3><div class="table-wrapper" data-block="mapaDatos"></div></section>
    <section class="card" id="calidad"><h3>Calidad de datos</h3><div class="table-wrapper" data-block="calidad"></div></section>
</div>

<section class="card" id="recientes">
    <h3>Últimas acciones registradas</h3>
    <div class="table-wrapper" data-block="recientes"></div>
</section>

<style>
    .dashboard-loading { color: #64748b; font-weight: 600; margin-left: .5rem; }
    .dashboard-error { color: #b91c1c; font-weight: 700; }
    .kpi-click { cursor: pointer; }
    .mini-table td, .mini-table th { white-space: nowrap; }
</style>

<script>
(() => {
    const form = document.getElementById('multiFilters');
    const clearBtn = document.getElementById('btn-clear');
    const refreshBtn = document.getElementById('btn-refresh');
    const kpisGrid = document.getElementById('kpis-grid');
    const updatedAt = document.getElementById('updated-at');
    const scopeLabel = document.getElementById('scope-label');
    const loadingState = document.getElementById('loading-state');
    let timer = null;
    let refreshInterval = null;

    const fmt = new Intl.NumberFormat('es-PA');

    function params() {
        const data = new FormData(form);
        const q = new URLSearchParams();
        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') q.set(key, value);
        }
        return q;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderTable(blockName, block) {
        const target = document.querySelector(`[data-block="${blockName}"]`);
        if (!target) return;
        const columns = block?.columns || {};
        const rows = block?.rows || [];
        const keys = Object.keys(columns);

        if (rows.length === 0) {
            target.innerHTML = '<table class="mini-table"><tbody><tr><td class="empty">Sin datos</td></tr></tbody></table>';
            return;
        }

        let html = '<table class="mini-table"><thead><tr>';
        for (const key of keys) html += `<th>${escapeHtml(columns[key])}</th>`;
        html += '</tr></thead><tbody>';
        for (const row of rows) {
            html += '<tr>';
            for (const key of keys) html += `<td>${escapeHtml(row[key])}</td>`;
            html += '</tr>';
        }
        html += '</tbody></table>';
        target.innerHTML = html;
    }

    function renderKpis(kpis) {
        kpisGrid.innerHTML = '';
        for (const item of kpis || []) {
            const a = document.createElement('a');
            a.className = 'report-card kpi-click';
            a.href = '#' + (item.target || 'kpis-section');
            a.innerHTML = `
                <span class="module-status">AUTO</span>
                <strong>${fmt.format(Number(item.value || 0))}</strong>
                <small>${escapeHtml(item.label)}<br><span>${escapeHtml(item.hint || '')}</span></small>
            `;
            kpisGrid.appendChild(a);
        }
    }

    async function loadDashboard() {
        loadingState.textContent = 'Actualizando...';
        loadingState.className = 'dashboard-loading';
        try {
            const response = await fetch('<?= e(url('/reportes/multiples/data')) ?>?' + params().toString(), {
                headers: {'Accept': 'application/json'}
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.error || 'Error desconocido');

            scopeLabel.textContent = data.scope || 'Alcance general';
            updatedAt.textContent = data.updated_at || '---';
            renderKpis(data.kpis);
            for (const [name, block] of Object.entries(data.blocks || {})) {
                renderTable(name, block);
            }
            loadingState.textContent = 'Listo';
            loadingState.className = 'dashboard-loading';
        } catch (error) {
            loadingState.textContent = 'Error: ' + error.message;
            loadingState.className = 'dashboard-error';
        }
    }

    function scheduleLoad() {
        clearTimeout(timer);
        timer = setTimeout(loadDashboard, 450);
    }

    form.addEventListener('input', scheduleLoad);
    form.addEventListener('change', scheduleLoad);
    refreshBtn.addEventListener('click', loadDashboard);
    clearBtn.addEventListener('click', () => {
        form.reset();
        loadDashboard();
    });

    loadDashboard();
    refreshInterval = setInterval(loadDashboard, 60000);
})();
</script>
