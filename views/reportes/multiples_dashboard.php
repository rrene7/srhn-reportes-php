<?php
/** @var array $statuses */
/** @var array $ranks */
/** @var array $actionTypes */
/** @var array $years */
?>

<section class="dashboard-hero no-print">
    <div>
        <span class="hero-badge">Panel interactivo</span>
        <h2>Dashboard de Reportes Múltiples</h2>
        <p>Filtros globales: al cambiar una opción, todos los bloques se actualizan automáticamente.</p>
    </div>
    <div class="hero-actions">
        <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        <button type="button" id="btn-refresh">Actualizar ahora</button>
    </div>
</section>

<section class="card no-print filters-card">
    <div class="card-header compact-header">
        <div>
            <h3>Filtros globales</h3>
            <p>Un solo filtro actualiza KPIs, fuerza, acciones, mapa y calidad de datos.</p>
        </div>
    </div>

    <form class="filters dashboard-filters" id="multiFilters">
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

<section class="status-strip no-print">
    <div>
        <span>Alcance</span>
        <strong id="scope-label">Cargando...</strong>
    </div>
    <div>
        <span>Última actualización</span>
        <strong><span id="updated-at">---</span> <small id="loading-state"></small></strong>
    </div>
</section>

<section class="dashboard-section" id="kpis-section">
    <div class="section-title-row">
        <div>
            <h3>Resumen interactivo</h3>
            <p>Métricas principales en tiempo real según los filtros.</p>
        </div>
    </div>
    <div class="dashboard-grid-pro" id="kpis-grid"></div>
</section>

<div class="dashboard-two">
    <section class="viz-card" id="estado-fuerza"><div data-block="estadoFuerza"></div></section>
    <section class="viz-card" id="rangos"><div data-block="rangos"></div></section>
</div>

<div class="dashboard-two">
    <section class="viz-card" id="acciones-tipo"><div data-block="accionesTipo"></div></section>
    <section class="viz-card" id="acciones-mes"><div data-block="accionesMes"></div></section>
</div>

<div class="dashboard-two">
    <section class="viz-card" id="mapa-datos"><div data-block="mapaDatos"></div></section>
    <section class="viz-card" id="calidad"><div data-block="calidad"></div></section>
</div>

<section class="viz-card" id="recientes">
    <div data-block="recientes"></div>
</section>

<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #0f2c52, #194b82 55%, #2269a8);
        color: #fff;
        border-radius: 22px;
        padding: 1.45rem 1.6rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 18px 45px rgba(15, 44, 82, .25);
        margin-bottom: 1rem;
    }

    .dashboard-hero h2 {
        margin: .35rem 0 .25rem;
        font-size: 1.65rem;
    }

    .dashboard-hero p {
        color: rgba(255, 255, 255, .82);
        margin: 0;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .3rem .65rem;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .hero-actions {
        display: flex;
        gap: .6rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .filters-card { border-radius: 20px; }
    .compact-header p { margin: .2rem 0 0; color: #64748b; }
    .dashboard-filters { align-items: end; }

    .status-strip {
        display: grid;
        grid-template-columns: 1.3fr .7fr;
        gap: 1rem;
        margin: 1rem 0;
    }

    .status-strip > div {
        background: #fff;
        border: 1px solid #d9e2ef;
        border-radius: 18px;
        padding: 1rem 1.1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    }

    .status-strip span {
        display: block;
        color: #64748b;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .status-strip strong {
        display: block;
        color: #0f172a;
        margin-top: .25rem;
        font-size: .96rem;
    }

    .dashboard-section {
        background: #fff;
        border: 1px solid #d9e2ef;
        border-radius: 22px;
        padding: 1.1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        margin-bottom: 1rem;
    }

    .section-title-row h3,
    .viz-title {
        margin: 0;
        font-size: 1.05rem;
        color: #0f172a;
        font-weight: 900;
    }

    .section-title-row p,
    .viz-subtitle {
        color: #64748b;
        margin: .25rem 0 1rem;
        font-size: .9rem;
    }

    .dashboard-grid-pro {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
        gap: 1rem;
    }

    .kpi-pro {
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        border: 1px solid #d9e2ef;
        border-radius: 20px;
        padding: 1rem 1.05rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
        display: flex;
        flex-direction: column;
        gap: .78rem;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        color: inherit;
        text-decoration: none;
    }

    .kpi-pro:hover {
        transform: translateY(-2px);
        border-color: #b8cae3;
        box-shadow: 0 14px 28px rgba(15, 23, 42, .08);
    }

    .kpi-pro-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .8rem;
    }

    .kpi-pro-title {
        color: #64748b;
        font-size: .78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .kpi-pro-value {
        color: #0f172a;
        font-size: 2rem;
        font-weight: 950;
        line-height: 1.05;
        margin-top: .25rem;
    }

    .kpi-pro-hint {
        color: #475569;
        font-size: .88rem;
        min-height: 2.2rem;
    }

    .metric-circle {
        --size: 64px;
        width: var(--size);
        height: var(--size);
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(#193a6a calc(var(--p) * 1%), #e5edf6 0);
        position: relative;
        flex-shrink: 0;
    }

    .metric-circle::before {
        content: "";
        width: 45px;
        height: 45px;
        background: #fff;
        border-radius: 50%;
        position: absolute;
        box-shadow: inset 0 0 0 1px #e8eef5;
    }

    .metric-circle span {
        position: relative;
        z-index: 1;
        color: #193a6a;
        font-size: .74rem;
        font-weight: 950;
    }

    .progress-line {
        width: 100%;
        height: 8px;
        background: #e8eef5;
        border-radius: 999px;
        overflow: hidden;
    }

    .progress-line > div {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #12335f, #3d7fd6);
    }

    .dashboard-two {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .viz-card {
        background: #fff;
        border: 1px solid #d9e2ef;
        border-radius: 22px;
        padding: 1.1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        min-width: 0;
    }

    .bar-list {
        display: flex;
        flex-direction: column;
        gap: .85rem;
    }

    .bar-row {
        display: grid;
        grid-template-columns: minmax(100px, 1.4fr) 74px minmax(130px, 1fr);
        gap: .75rem;
        align-items: center;
    }

    .bar-label {
        color: #0f172a;
        font-size: .9rem;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .bar-value {
        text-align: right;
        color: #334155;
        font-size: .86rem;
        font-weight: 900;
    }

    .bar-track {
        height: 10px;
        background: #e8eef5;
        border-radius: 999px;
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #12335f, #4b83d6);
    }

    .quality-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: .85rem;
    }

    .quality-item {
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid #dce6f2;
        background: #f8fbff;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .6);
    }

    .quality-item.good { border-left: 6px solid #16a34a; }
    .quality-item.warn { border-left: 6px solid #f59e0b; }
    .quality-item.bad { border-left: 6px solid #dc2626; }

    .quality-item div {
        color: #0f172a;
        font-weight: 850;
        font-size: .9rem;
    }

    .quality-item strong {
        display: block;
        font-size: 1.65rem;
        color: #0f172a;
        margin: .35rem 0 .2rem;
    }

    .quality-item small {
        color: #64748b;
        display: block;
        line-height: 1.35;
    }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
        font-size: .9rem;
    }

    .table-modern th {
        background: #f5f8fc;
        color: #0f172a;
        text-align: left;
        padding: .75rem;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #d9e2ef;
    }

    .table-modern td {
        padding: .75rem;
        border-bottom: 1px solid #edf2f8;
        color: #27364a;
        white-space: nowrap;
    }

    .table-modern tr:hover td { background: #f8fbff; }
    .dashboard-loading { color: #64748b; font-weight: 700; margin-left: .5rem; }
    .dashboard-error { color: #b91c1c; font-weight: 900; }
    .empty { color: #64748b; padding: 1rem; }

    @media (max-width: 1000px) {
        .dashboard-hero,
        .status-strip,
        .dashboard-two {
            grid-template-columns: 1fr;
            flex-direction: column;
            align-items: stretch;
        }

        .bar-row {
            grid-template-columns: 1fr;
            gap: .35rem;
        }

        .bar-value { text-align: left; }
    }
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

    function renderKpis(kpis) {
        kpisGrid.innerHTML = '';
        const values = (kpis || []).map(item => Number(item.value || 0));
        const max = Math.max(...values, 1);

        for (const item of kpis || []) {
            const value = Number(item.value || 0);
            const percent = Math.max(4, Math.min(100, Math.round((value / max) * 100)));
            const a = document.createElement('a');
            a.className = 'kpi-pro';
            a.href = '#' + (item.target || 'kpis-section');
            a.innerHTML = `
                <div class="kpi-pro-top">
                    <div>
                        <div class="kpi-pro-title">${escapeHtml(item.label || '')}</div>
                        <div class="kpi-pro-value">${fmt.format(value)}</div>
                    </div>
                    <div class="metric-circle" style="--p:${percent}"><span>${percent}%</span></div>
                </div>
                <div class="kpi-pro-hint">${escapeHtml(item.hint || '')}</div>
                <div class="progress-line"><div style="width:${percent}%"></div></div>
            `;
            kpisGrid.appendChild(a);
        }
    }

    function renderTable(blockName, block) {
        const target = document.querySelector(`[data-block="${blockName}"]`);
        if (!target) return;
        const columns = block?.columns || {};
        const rows = block?.rows || [];
        const keys = Object.keys(columns);

        let html = `<div class="viz-title">${escapeHtml(block?.title || '')}</div>`;
        html += `<div class="viz-subtitle">${fmt.format(rows.length)} registros</div>`;

        if (rows.length === 0) {
            target.innerHTML = html + '<div class="empty">Sin datos</div>';
            return;
        }

        html += '<div class="table-wrapper"><table class="table-modern"><thead><tr>';
        for (const key of keys) html += `<th>${escapeHtml(columns[key])}</th>`;
        html += '</tr></thead><tbody>';
        for (const row of rows) {
            html += '<tr>';
            for (const key of keys) html += `<td>${escapeHtml(row[key])}</td>`;
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        target.innerHTML = html;
    }

    function renderBarBlock(blockName, block, labelKey, valueKey) {
        const target = document.querySelector(`[data-block="${blockName}"]`);
        if (!target) return;
        const rows = block?.rows || [];

        let html = `<div class="viz-title">${escapeHtml(block?.title || '')}</div>`;
        html += `<div class="viz-subtitle">Distribución visual por cantidad</div>`;

        if (rows.length === 0) {
            target.innerHTML = html + '<div class="empty">Sin datos</div>';
            return;
        }

        const max = Math.max(...rows.map(row => Number(row[valueKey] || 0)), 1);
        html += '<div class="bar-list">';
        for (const row of rows.slice(0, 14)) {
            const label = row[labelKey] ?? row.nombre ?? row.codigo ?? '';
            const value = Number(row[valueKey] || 0);
            const percent = Math.max(2, Math.round((value / max) * 100));
            html += `
                <div class="bar-row">
                    <div class="bar-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
                    <div class="bar-value">${fmt.format(value)}</div>
                    <div class="bar-track"><div class="bar-fill" style="width:${percent}%"></div></div>
                </div>
            `;
        }
        html += '</div>';
        target.innerHTML = html;
    }

    function renderQualityBlock(block) {
        const target = document.querySelector('[data-block="calidad"]');
        if (!target) return;
        const rows = block?.rows || [];

        let html = `<div class="viz-title">${escapeHtml(block?.title || 'Calidad de datos')}</div>`;
        html += '<div class="viz-subtitle">Semáforo de revisión de datos</div>';

        if (rows.length === 0) {
            target.innerHTML = html + '<div class="empty">Sin datos</div>';
            return;
        }

        html += '<div class="quality-grid">';
        for (const row of rows) {
            const total = Number(row.total || 0);
            let level = 'good';
            if (total > 0 && total <= 20) level = 'warn';
            if (total > 20) level = 'bad';
            html += `
                <div class="quality-item ${level}">
                    <div>${escapeHtml(row.indicador || '')}</div>
                    <strong>${fmt.format(total)}</strong>
                    <small>${escapeHtml(row.detalle || '')}</small>
                </div>
            `;
        }
        html += '</div>';
        target.innerHTML = html;
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
            renderBarBlock('estadoFuerza', data.blocks?.estadoFuerza, 'nombre', 'total');
            renderBarBlock('rangos', data.blocks?.rangos, 'nombre', 'total');
            renderBarBlock('accionesTipo', data.blocks?.accionesTipo, 'nombre', 'total');
            renderBarBlock('accionesMes', data.blocks?.accionesMes, 'periodo', 'total');
            renderBarBlock('mapaDatos', data.blocks?.mapaDatos, 'nombre', 'personal');
            renderQualityBlock(data.blocks?.calidad);
            renderTable('recientes', data.blocks?.recientes);
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
    setInterval(loadDashboard, 60000);
})();
</script>
