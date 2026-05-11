<?php ob_start(); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* ─── Design System ───────────────────────────────────────────── */
    :root {
        --bg-base:    #0d0f17;
        --bg-card:    #13161f;
        --bg-card2:   #1a1e2e;
        --border:     rgba(99,115,177,0.18);
        --accent1:    #6366f1;   /* indigo */
        --accent2:    #22d3ee;   /* cyan   */
        --accent3:    #f59e0b;   /* amber  */
        --accent4:    #10b981;   /* emerald*/
        --accent-vid: #ec4899;   /* pink   */
        --text-main:  #e8eaf6;
        --text-muted: #7b84b0;
        --glow1: rgba(99,102,241,0.30);
        --glow2: rgba(34,211,238,0.25);
        --radius: 16px;
    }

    body { background: var(--bg-base); font-family: 'Inter', sans-serif; }

    /* ─── Page header ────────────────────────────────────────────── */
    .dash-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 32px;
    }
    .dash-header-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--accent1), var(--accent2));
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; color: #fff;
        box-shadow: 0 0 24px var(--glow1);
    }
    .dash-header h1 { color: var(--text-main); font-size: 1.75rem; font-weight: 700; margin: 0; }
    .dash-header p  { color: var(--text-muted); margin: 0; font-size: .9rem; }

    /* ─── KPI cards ──────────────────────────────────────────────── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 22px;
        position: relative;
        overflow: hidden;
        transition: transform .2s, box-shadow .2s;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,.4); }
    .kpi-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: var(--kpi-color, var(--accent1));
    }
    .kpi-icon {
        font-size: 22px;
        color: var(--kpi-color, var(--accent1));
        margin-bottom: 10px;
    }
    .kpi-value { font-size: 2rem; font-weight: 700; color: var(--text-main); line-height: 1; }
    .kpi-label { font-size: .8rem; color: var(--text-muted); margin-top: 6px; }

    /* ─── Chart cards ────────────────────────────────────────────── */
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .chart-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        transition: box-shadow .2s;
    }
    .chart-card:hover { box-shadow: 0 6px 30px rgba(0,0,0,.35); }
    .chart-card.wide { grid-column: 1 / -1; }

    .card-title {
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--text-muted);
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 20px;
    }
    .card-title-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--dot-color, var(--accent1));
        box-shadow: 0 0 8px var(--dot-color, var(--accent1));
    }

    /* ─── Chart wrapper ──────────────────────────────────────────── */
    .chart-wrap { position: relative; height: 220px; }
    .chart-wrap-sm { position: relative; height: 180px; }

    /* ─── Ranking list ───────────────────────────────────────────── */
    .ranking-list { list-style: none; padding: 0; margin: 0; }
    .ranking-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
        animation: fadeInUp .35s ease both;
    }
    .ranking-item:last-child { border-bottom: none; }
    .ranking-pos {
        width: 28px; height: 28px; border-radius: 8px;
        font-size: .8rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        background: var(--bg-card2);
        color: var(--text-muted);
    }
    .ranking-pos.gold   { background: rgba(245,158,11,.15); color: #f59e0b; }
    .ranking-pos.silver { background: rgba(148,163,184,.15); color: #94a3b8; }
    .ranking-pos.bronze { background: rgba(180,108,64,.15);  color: #b46c40; }

    .ranking-info { flex: 1; min-width: 0; }
    .ranking-phone {
        font-size: .85rem; font-weight: 600; color: var(--text-main);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ranking-name { font-size: .75rem; color: var(--text-muted); }

    .ranking-bar-wrap { flex-shrink: 0; width: 90px; }
    .ranking-bar-bg {
        height: 6px; background: var(--bg-card2);
        border-radius: 99px; overflow: hidden;
    }
    .ranking-bar-fill {
        height: 100%; border-radius: 99px;
        background: linear-gradient(90deg, var(--accent1), var(--accent2));
        transition: width .8s cubic-bezier(.4,0,.2,1);
    }
    .ranking-count { font-size: .8rem; font-weight: 600; color: var(--accent2); text-align: right; margin-top: 3px; }

    /* ─── Media type doughnuts ───────────────────────────────────── */
    .media-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 8px;
    }
    .media-item {
        background: var(--bg-card2);
        border-radius: 12px;
        padding: 18px 12px;
        text-align: center;
        border: 1px solid var(--border);
        transition: transform .2s;
    }
    .media-item:hover { transform: scale(1.03); }
    .media-icon { font-size: 28px; margin-bottom: 8px; }
    .media-count { font-size: 1.75rem; font-weight: 700; color: var(--text-main); }
    .media-label { font-size: .75rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .06em; }

    /* ─── Animations ─────────────────────────────────────────────── */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .chart-card { animation: fadeInUp .4s ease both; }
    .kpi-card   { animation: fadeInUp .3s ease both; }

    /* ─── Number counter animation ───────────────────────────────── */
    .kpi-value[data-target] { transition: all .1s; }

    /* ─── Responsive ─────────────────────────────────────────────── */
    @media (max-width: 600px) {
        .media-grid { grid-template-columns: 1fr; }
        .chart-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- ══════════════════ PAGE ══════════════════ -->

<div class="dash-header">
    <div class="dash-header-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
    <div>
        <h1>Dashboard</h1>
        <p>Acompanhamento em tempo real · <?= date('d \d\e F \d\e Y') ?></p>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card" style="--kpi-color: var(--accent1);">
        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-value" data-target="<?= $totalContacts ?>">0</div>
        <div class="kpi-label">Total de Contatos</div>
    </div>
    <div class="kpi-card" style="--kpi-color: var(--accent2);">
        <div class="kpi-icon"><i class="bi bi-chat-text-fill"></i></div>
        <div class="kpi-value" data-target="<?= $totalMessages ?>">0</div>
        <div class="kpi-label">Mensagens Recebidas</div>
    </div>
    <div class="kpi-card" style="--kpi-color: var(--accent4);">
        <div class="kpi-icon"><i class="bi bi-activity"></i></div>
        <div class="kpi-value" data-target="<?= $activeToday ?>">0</div>
        <div class="kpi-label">Conversas Ativas Hoje</div>
    </div>
    <div class="kpi-card" style="--kpi-color: var(--accent3);">
        <div class="kpi-icon"><i class="bi bi-mic-fill"></i></div>
        <div class="kpi-value" data-target="<?= $mediaTypes['audio'] ?>">0</div>
        <div class="kpi-label">Áudios (semana)</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="chart-grid">

    <!-- Novos contatos por dia (30 dias) -->
    <div class="chart-card" style="animation-delay:.05s">
        <div class="card-title">
            <span class="card-title-dot" style="--dot-color: var(--accent1)"></span>
            Novos Contatos por Dia <small style="margin-left:auto;font-weight:400;text-transform:none;letter-spacing:0">últimos 30 dias</small>
        </div>
        <div class="chart-wrap">
            <canvas id="chartContacts"></canvas>
        </div>
    </div>

    <!-- Novas mensagens por dia (7 dias) -->
    <div class="chart-card" style="animation-delay:.10s">
        <div class="card-title">
            <span class="card-title-dot" style="--dot-color: var(--accent2)"></span>
            Mensagens por Dia <small style="margin-left:auto;font-weight:400;text-transform:none;letter-spacing:0">últimos 7 dias</small>
        </div>
        <div class="chart-wrap">
            <canvas id="chartMessages"></canvas>
        </div>
    </div>

</div>

<!-- Charts Row 2 -->
<div class="chart-grid">

    <!-- Ranking -->
    <div class="chart-card" style="animation-delay:.15s">
        <div class="card-title">
            <span class="card-title-dot" style="--dot-color: var(--accent3)"></span>
            Ranking — quem mais se comunicou
        </div>
        <ul class="ranking-list" id="rankingList">
            <?php
            $maxMsgs = !empty($ranking) ? $ranking[0]['msg_count'] : 1;
            $medals  = ['gold','silver','bronze'];
            foreach ($ranking as $i => $person):
                $phone   = preg_replace('/^wa:/', '', $person['user_id']);
                $pct     = round(($person['msg_count'] / $maxMsgs) * 100);
                $posClass = $medals[$i] ?? '';
                $delay   = $i * 0.05;
            ?>
            <li class="ranking-item" style="animation-delay:<?= $delay ?>s">
                <div class="ranking-pos <?= $posClass ?>"><?= $i + 1 ?></div>
                <div class="ranking-info">
                    <div class="ranking-phone"><?= htmlspecialchars($phone) ?></div>
                    <div class="ranking-name">WhatsApp</div>
                </div>
                <div class="ranking-bar-wrap">
                    <div class="ranking-bar-bg">
                        <div class="ranking-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="ranking-count"><?= number_format($person['msg_count']) ?> msg</div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Tipo de mensagem semanal -->
    <div class="chart-card" style="animation-delay:.20s">
        <div class="card-title">
            <span class="card-title-dot" style="--dot-color: var(--accent4)"></span>
            Tipos de Mensagem <small style="margin-left:auto;font-weight:400;text-transform:none;letter-spacing:0">últimos 7 dias</small>
        </div>

        <div class="media-grid">
            <div class="media-item">
                <div class="media-icon">💬</div>
                <div class="media-count" data-target="<?= $mediaTypes['texto'] ?>">0</div>
                <div class="media-label">Texto</div>
            </div>
            <div class="media-item">
                <div class="media-icon">🎙️</div>
                <div class="media-count" data-target="<?= $mediaTypes['audio'] ?>">0</div>
                <div class="media-label">Áudio</div>
            </div>
            <div class="media-item">
                <div class="media-icon">🎬</div>
                <div class="media-count" data-target="<?= $mediaTypes['video'] ?>">0</div>
                <div class="media-label">Vídeo</div>
            </div>
        </div>

        <div class="chart-wrap-sm" style="margin-top:24px">
            <canvas id="chartMediaTypes"></canvas>
        </div>
    </div>

</div>

<script>
/* ══════════ DATA FROM PHP ══════════ */
const contactLabels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($contactsByDay))) ?>;
const contactData   = <?= json_encode(array_values($contactsByDay)) ?>;

const msgLabels     = <?= json_encode(array_map(fn($d) => date('d/m (D)', strtotime($d)), array_keys($messagesByDay))) ?>;
const msgData       = <?= json_encode(array_values($messagesByDay)) ?>;

const mediaData = {
    texto: <?= $mediaTypes['texto'] ?>,
    audio: <?= $mediaTypes['audio'] ?>,
    video: <?= $mediaTypes['video'] ?>,
};

/* ══════════ CHART DEFAULTS ══════════ */
Chart.defaults.color = '#7b84b0';
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 11;

const gridColor  = 'rgba(99,115,177,0.10)';
const tickColor  = '#7b84b0';

function makeGrad(ctx, color1, color2) {
    const g = ctx.createLinearGradient(0, 0, 0, 220);
    g.addColorStop(0, color1);
    g.addColorStop(1, color2);
    return g;
}

/* ══════════ CONTACTS CHART (area) ══════════ */
(function(){
    const ctx = document.getElementById('chartContacts').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: contactLabels,
            datasets: [{
                label: 'Contatos',
                data: contactData,
                fill: true,
                backgroundColor: makeGrad(ctx, 'rgba(99,102,241,0.30)', 'rgba(99,102,241,0.01)'),
                borderColor: '#6366f1',
                borderWidth: 2,
                pointBackgroundColor: '#6366f1',
                pointRadius: 3,
                pointHoverRadius: 6,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { callbacks: {
                label: c => ` ${c.parsed.y} contato(s)`
            }}},
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: tickColor, maxTicksLimit: 8 } },
                y: { grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, stepSize: 1 }, min: 0 }
            }
        }
    });
})();

/* ══════════ MESSAGES CHART (bar) ══════════ */
(function(){
    const ctx = document.getElementById('chartMessages').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: msgLabels,
            datasets: [{
                label: 'Mensagens',
                data: msgData,
                backgroundColor: 'rgba(34,211,238,0.25)',
                borderColor: '#22d3ee',
                borderWidth: 1.5,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { callbacks: {
                label: c => ` ${c.parsed.y} mensagem(ns)`
            }}},
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                y: { grid: { color: gridColor }, ticks: { color: tickColor, precision: 0, stepSize: 1 }, min: 0 }
            }
        }
    });
})();

/* ══════════ MEDIA TYPES CHART (doughnut) ══════════ */
(function(){
    const ctx = document.getElementById('chartMediaTypes').getContext('2d');
    const total = mediaData.texto + mediaData.audio + mediaData.video;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Texto', 'Áudio', 'Vídeo'],
            datasets: [{
                data: [mediaData.texto, mediaData.audio, mediaData.video],
                backgroundColor: ['rgba(99,102,241,0.85)', 'rgba(16,185,129,0.85)', 'rgba(236,72,153,0.85)'],
                borderColor: ['#6366f1', '#10b981', '#ec4899'],
                borderWidth: 1.5,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#7b84b0', boxWidth: 12, padding: 14 }
                },
                tooltip: { callbacks: {
                    label: function(c) {
                        const pct = total > 0 ? Math.round((c.parsed / total) * 100) : 0;
                        return ` ${c.label}: ${c.parsed} (${pct}%)`;
                    }
                }}
            }
        }
    });
})();

/* ══════════ COUNTER ANIMATION ══════════ */
document.querySelectorAll('[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target, 10) || 0;
    const duration = 900;
    const step = 16;
    const steps = Math.ceil(duration / step);
    let current = 0;
    const increment = target / steps;
    const timer = setInterval(() => {
        current = Math.min(current + increment, target);
        el.textContent = Math.floor(current).toLocaleString('pt-BR');
        if (current >= target) clearInterval(timer);
    }, step);
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
