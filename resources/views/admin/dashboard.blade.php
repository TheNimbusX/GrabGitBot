<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — админка</title>
    <style>
        :root {
            --bg0: #0a0e14;
            --bg1: #0f1623;
            --surface: #141c2b;
            --surface2: #1c2739;
            --surface3: #243047;
            --text: #e8edf5;
            --muted: #8b9cb3;
            --accent: #4d9fff;
            --accent-dim: rgba(77, 159, 255, .15);
            --ok: #3ecf8e;
            --ok-dim: rgba(62, 207, 142, .12);
            --warn: #f0b429;
            --warn-dim: rgba(240, 180, 41, .12);
            --danger: #f07178;
            --danger-dim: rgba(240, 113, 120, .12);
            --violet: #c792ea;
            --border: rgba(255, 255, 255, .07);
            --shadow: 0 8px 32px rgba(0, 0, 0, .35);
            --radius: 12px;
            --radius-sm: 8px;
            --font: "Segoe UI", "Inter", system-ui, sans-serif;
            --nav-w: 220px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            margin: 0;
            min-height: 100vh;
            background: var(--bg0);
            background-image:
                radial-gradient(ellipse 120% 80% at 10% -20%, rgba(77, 159, 255, .18), transparent 50%),
                radial-gradient(ellipse 80% 60% at 100% 0%, rgba(199, 146, 234, .12), transparent 45%),
                radial-gradient(ellipse 60% 40% at 50% 100%, rgba(62, 207, 142, .06), transparent 50%);
            color: var(--text);
            line-height: 1.5;
        }
        .layout {
            display: flex;
            max-width: 1680px;
            margin: 0 auto;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--nav-w);
            flex-shrink: 0;
            padding: 1.25rem 1rem 2rem 1.25rem;
            position: sticky;
            top: 0;
            align-self: flex-start;
            height: 100vh;
            border-right: 1px solid var(--border);
            background: rgba(10, 14, 20, .65);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
        }
        .brand {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: .25rem;
        }
        .sidebar h1 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 1.25rem;
            letter-spacing: -.02em;
        }
        .nav {
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }
        .nav a {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .65rem;
            border-radius: var(--radius-sm);
            color: var(--muted);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: background .15s, color .15s;
        }
        .nav a:hover { background: var(--surface2); color: var(--text); }
        .nav a .ic { font-size: 1rem; opacity: .85; width: 1.25rem; text-align: center; }
        .sidebar-foot {
            margin-top: auto;
            padding-top: 2rem;
        }
        .sidebar-foot .meta {
            font-size: .72rem;
            color: var(--muted);
            margin-bottom: .65rem;
            line-height: 1.35;
        }
        .btn-ghost {
            width: 100%;
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .5rem .75rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-ghost:hover { background: var(--surface3); }
        main {
            flex: 1;
            padding: 1.25rem 1.5rem 3rem;
            min-width: 0;
        }
        .section {
            margin-bottom: 1.75rem;
        }
        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1rem;
        }
        .section-head h2 {
            font-size: 1.05rem;
            font-weight: 650;
            margin: 0;
        }
        .section-head .hint-inline {
            font-size: .8rem;
            color: var(--muted);
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: .85rem;
        }
        .kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.1rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .kpi::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--violet));
            opacity: .7;
        }
        .kpi.tone-ok::before { background: linear-gradient(90deg, var(--ok), #5fd4a8); }
        .kpi.tone-warn::before { background: linear-gradient(90deg, var(--warn), #ffcc66); }
        .kpi.tone-danger::before { background: linear-gradient(90deg, var(--danger), #ff9a9e); }
        .kpi .ic-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .35rem;
        }
        .kpi .ic {
            font-size: 1.35rem;
            line-height: 1;
            opacity: .9;
        }
        .kpi .delta {
            font-size: .72rem;
            font-weight: 600;
            padding: .2rem .45rem;
            border-radius: 999px;
            background: var(--accent-dim);
            color: var(--accent);
        }
        .kpi .delta.warn { background: var(--warn-dim); color: var(--warn); }
        .kpi .delta.danger { background: var(--danger-dim); color: var(--danger); }
        .kpi .delta.ok { background: var(--ok-dim); color: var(--ok); }
        .kpi strong {
            display: block;
            font-size: 1.75rem;
            font-weight: 750;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .kpi .label { font-size: .82rem; color: var(--muted); margin-top: .2rem; }
        .kpi .sub {
            font-size: .76rem;
            color: var(--muted);
            margin-top: .5rem;
            line-height: 1.35;
        }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.15rem 1.25rem;
            box-shadow: var(--shadow);
        }
        .panel.highlight {
            border-color: rgba(77, 159, 255, .25);
            background: linear-gradient(165deg, rgba(77, 159, 255, .06), var(--surface) 40%);
        }
        .hint { font-size: .82rem; color: var(--muted); margin: 0 0 .85rem; line-height: 1.45; }
        .flash {
            padding: .85rem 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            font-size: .88rem;
            border: 1px solid transparent;
        }
        .flash-ok { background: var(--ok-dim); border-color: rgba(62, 207, 142, .35); }
        .flash-info { background: var(--accent-dim); border-color: rgba(77, 159, 255, .35); }
        .flash-err { background: var(--danger-dim); border-color: rgba(240, 113, 120, .4); }
        .funnel-bars { display: flex; flex-direction: column; gap: .55rem; }
        .funnel-row {
            display: grid;
            grid-template-columns: minmax(140px, 1fr) 2.5fr auto;
            gap: .65rem;
            align-items: center;
            font-size: .82rem;
        }
        @media (max-width: 640px) {
            .funnel-row { grid-template-columns: 1fr; }
        }
        .funnel-row .name { color: var(--text); }
        .funnel-row .bar-wrap {
            height: 8px;
            background: var(--surface3);
            border-radius: 999px;
            overflow: hidden;
        }
        .funnel-row .bar {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), var(--violet));
            min-width: 4px;
        }
        .funnel-row .cnt { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--accent); }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .toolbar .field label {
            font-size: .74rem;
            color: var(--muted);
            display: block;
            margin-bottom: .25rem;
            font-weight: 500;
        }
        .toolbar input[type="text"], .toolbar select, .toolbar textarea {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: var(--radius-sm);
            padding: .5rem .7rem;
            font: inherit;
            min-width: 200px;
        }
        .toolbar textarea { min-width: 100%; width: 100%; }
        .btn {
            background: linear-gradient(180deg, #5aa8ff, var(--accent));
            color: #fff;
            border: 0;
            border-radius: var(--radius-sm);
            padding: .55rem 1.1rem;
            font: inherit;
            font-weight: 650;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(77, 159, 255, .25);
        }
        .btn:hover { filter: brightness(1.06); }
        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
            box-shadow: none;
        }
        .btn-danger {
            background: linear-gradient(180deg, #ff8a90, var(--danger));
            box-shadow: 0 2px 12px rgba(240, 113, 120, .2);
        }
        form.inline { display: inline; }
        textarea.msg { width: 100%; min-height: 120px; resize: vertical; }
        .preview-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .85rem 1rem;
            margin-top: .65rem;
        }
        .preview-box pre {
            white-space: pre-wrap;
            margin: .5rem 0 0;
            font-size: .84rem;
            color: var(--text);
        }
        .scroll-table {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: var(--shadow);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }
        th, td { padding: .6rem .7rem; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
        thead th {
            background: var(--surface2);
            font-weight: 650;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 0 var(--border);
        }
        tbody tr:hover td { background: rgba(77, 159, 255, .05); }
        tbody tr:last-child td { border-bottom: 0; }
        th a { color: var(--accent); text-decoration: none; }
        th a:hover { text-decoration: underline; }
        .num { font-variant-numeric: tabular-nums; }
        .ok { color: var(--ok); }
        .no { color: var(--muted); }
        .pulse {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .72rem;
            font-weight: 650;
            padding: .2rem .5rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .pulse-dot { width: 6px; height: 6px; border-radius: 50%; }
        .pulse-hot { background: var(--ok-dim); color: var(--ok); }
        .pulse-hot .pulse-dot { background: var(--ok); box-shadow: 0 0 8px var(--ok); }
        .pulse-warm { background: rgba(77, 159, 255, .15); color: var(--accent); }
        .pulse-warm .pulse-dot { background: var(--accent); }
        .pulse-cool { background: var(--warn-dim); color: var(--warn); }
        .pulse-cool .pulse-dot { background: var(--warn); }
        .pulse-cold { background: var(--surface3); color: var(--muted); }
        .pulse-cold .pulse-dot { background: var(--muted); }
        .pulse-new { background: rgba(199, 146, 234, .18); color: var(--violet); }
        .pulse-new .pulse-dot { background: var(--violet); }
        .pulse-onboarding { background: var(--accent-dim); color: #9dc6ff; }
        .pulse-onboarding .pulse-dot { background: var(--accent); }
        .pill {
            display: inline-block;
            font-size: .72rem;
            font-weight: 600;
            padding: .15rem .45rem;
            border-radius: 6px;
            background: var(--surface3);
            color: var(--muted);
        }
        .pill-full { background: rgba(62, 207, 142, .15); color: var(--ok); }
        .pill-disc { background: rgba(240, 180, 41, .15); color: var(--warn); }
        .copy-tg {
            font: inherit;
            font-size: .72rem;
            padding: .15rem .4rem;
            border-radius: 4px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--muted);
            cursor: pointer;
            margin-left: .25rem;
        }
        .copy-tg:hover { color: var(--accent); border-color: var(--accent); }
        .foot-note { font-size: .76rem; color: var(--muted); margin-top: .85rem; line-height: 1.45; }
        .tier-cell { font-size: .8rem; line-height: 1.35; max-width: 200px; }
        .tier-cell .e { font-size: 1.15rem; margin-right: .15rem; vertical-align: middle; }
        .support-msg {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: .84rem;
            line-height: 1.45;
            max-height: 220px;
            overflow: auto;
            padding: .65rem;
            background: var(--surface2);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }
        details.danger-zone summary {
            cursor: pointer;
            color: var(--danger);
            font-weight: 600;
            font-size: .78rem;
        }
        details.danger-zone form {
            margin-top: .5rem;
            padding: .75rem;
            background: var(--surface2);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }
        .mobile-nav {
            display: none;
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(10, 14, 20, .92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: .65rem 1rem;
            margin: -1.25rem -1.5rem 1rem;
        }
        .mobile-nav select {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: var(--radius-sm);
            padding: .5rem;
            font: inherit;
        }
        @media (max-width: 960px) {
            .layout { flex-direction: column; }
            .sidebar { display: none; }
            .mobile-nav { display: block; }
            main { padding-top: .5rem; }
        }
        @media (max-width: 1100px) {
            table.responsive thead { display: none; }
            table.responsive tbody tr {
                display: block;
                border-bottom: 1px solid var(--border);
                padding: .75rem 0;
            }
            table.responsive tbody td {
                display: grid;
                grid-template-columns: minmax(100px, 38%) 1fr;
                gap: .35rem .75rem;
                padding: .35rem .85rem;
                border: 0;
                text-align: left;
            }
            table.responsive tbody td::before {
                content: attr(data-label);
                font-size: .68rem;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--muted);
                font-weight: 650;
            }
        }
    </style>
</head>
<body>
    @php
        $broadcastPending = session('fitbot_broadcast_pending');
        $segmentLabels = [
            'all_completed' => 'Онбординг готов — все',
            'in_onboarding' => 'Застряли в онбординге',
            'active_7d' => 'Активны 7 дней (был чек-ин)',
            'inactive_7d' => 'Готовы, но 7+ дней без чек-ина',
            'inactive_14d' => 'Готовы, но 14+ дней без чек-ина',
            'new_7d' => 'Новые за 7 дней (любой этап)',
            'completed_never_checked' => 'Онбординг готов, ни одного чек-ина',
            'streak_3_plus' => 'Серия чек-инов ≥ 3 дня',
            'plan_full' => 'Режим плана FitBot',
            'discipline_only' => 'Только дисциплина (свой план)',
            'low_activity_14d' => '≤ 1 чек-ин за 14 дней',
        ];
        $pulseLabels = [
            'hot' => 'В теме (чек-ин сегодня/вчера)',
            'warm' => 'Недавно (2–7 дн без чек-ина)',
            'cool' => 'Просел (8–14 дн)',
            'cold' => 'Давно нет чек-ина (14+ дн или не было)',
            'new' => 'Новая регистрация (≤7 дн с /start)',
            'onboarding' => 'В онбординге',
        ];
        $engPct = $stats['engagement_completed_7d_pct'];
        $funnelMax = 1;
        foreach ($onboardingFunnel as $f) {
            $funnelMax = max($funnelMax, $f['count']);
        }
    @endphp

    <div class="layout">
        <aside class="sidebar">
            <div class="brand">FitBot</div>
            <h1>Админка</h1>
            <nav class="nav">
                <a href="#metrics"><span class="ic">📊</span> Метрики</a>
                <a href="#funnel"><span class="ic">🪜</span> Воронка</a>
                <a href="#broadcast"><span class="ic">✈️</span> Рассылка</a>
                <a href="#support"><span class="ic">✉️</span> Поддержка</a>
                <a href="#users"><span class="ic">👥</span> Пользователи</a>
            </nav>
            <div class="sidebar-foot">
                <div class="meta">Данные на {{ $generatedAt->format('d.m.Y H:i') }}</div>
                <form class="inline" method="post" action="{{ url('/admin/logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Выйти</button>
                </form>
            </div>
        </aside>

        <main>
            <div class="mobile-nav">
                <label for="jump" class="sr-only" style="position:absolute;clip:rect(0,0,0,0);">Раздел</label>
                <select id="jump" onchange="if(this.value) location.hash=this.value">
                    <option value="">Перейти к разделу…</option>
                    <option value="#metrics">Метрики</option>
                    <option value="#funnel">Воронка</option>
                    <option value="#broadcast">Рассылка</option>
                    <option value="#support">Поддержка</option>
                    <option value="#users">Пользователи</option>
                </select>
            </div>

            @if (session('broadcast_status'))
                <p class="flash flash-ok">{{ session('broadcast_status') }}</p>
            @endif
            @if ($errors->has('broadcast'))
                <p class="flash flash-err">{{ $errors->first('broadcast') }}</p>
            @endif
            @if (session('admin_status'))
                <p class="flash flash-info">{{ session('admin_status') }}</p>
            @endif
            @if ($errors->has('delete'))
                <p class="flash flash-err">{{ $errors->first('delete') }}</p>
            @endif

            <section class="section" id="metrics">
                <div class="section-head">
                    <h2>Сводка</h2>
                    <span class="hint-inline">Живые цифры по базе и активности</span>
                </div>
                <div class="kpi-grid">
                    <div class="kpi">
                        <div class="ic-row">
                            <span class="ic">👤</span>
                            @if ($stats['users_new_7d'] > 0)
                                <span class="delta ok">+{{ $stats['users_new_7d'] }} за 7д</span>
                            @endif
                        </div>
                        <strong>{{ $stats['users_total'] }}</strong>
                        <div class="label">пользователей всего</div>
                        <div class="sub">Онбординг завершён: {{ $stats['users_completed_onboarding'] }} · в процессе: {{ $stats['users_in_onboarding'] }}</div>
                    </div>
                    <div class="kpi tone-ok">
                        <div class="ic-row"><span class="ic">✓</span>
                            @if ($engPct !== null)
                                <span class="delta ok">{{ $engPct }}% онбординг → чек-ин</span>
                            @endif
                        </div>
                        <strong>{{ $stats['users_active_7d'] }}</strong>
                        <div class="label">активны 7 дней (все)</div>
                        <div class="sub">Среди завершивших онбординг: {{ $stats['users_active_7d_completed'] }} · был завершённый чек-ин за 7 календарных дней</div>
                    </div>
                    <div class="kpi tone-warn">
                        <div class="ic-row"><span class="ic">⏸</span></div>
                        <strong>{{ $stats['users_dormant_7d_completed'] }}</strong>
                        <div class="label">«тишина» 7+ дней</div>
                        <div class="sub">Онбординг готов, но без чек-ина 7 дней · 14+ дней: {{ $stats['users_dormant_14d_completed'] }}</div>
                    </div>
                    <div class="kpi tone-danger">
                        <div class="ic-row"><span class="ic">❄</span></div>
                        <strong>{{ $stats['users_completed_never_checked'] }}</strong>
                        <div class="label">ни разу не чекнулись</div>
                        <div class="sub">После онбординга нет ни одного завершённого дня</div>
                    </div>
                    <div class="kpi">
                        <div class="ic-row"><span class="ic">📅</span></div>
                        <strong>{{ $stats['checks_today'] }}</strong>
                        <div class="label">чек-инов сегодня</div>
                        <div class="sub">Всего завершённых за всё время: {{ $stats['checks_completed_total'] }}</div>
                    </div>
                    <div class="kpi">
                        <div class="ic-row"><span class="ic">📆</span></div>
                        <strong>{{ $stats['checks_week'] }}</strong>
                        <div class="label">чек-инов (неделя)</div>
                        <div class="sub">Календарная неделя · баллов: {{ $stats['points_week_all_users'] }} · ср. балл/чек: {{ $stats['avg_score_per_check_week'] ?? '—' }}</div>
                    </div>
                    <div class="kpi">
                        <div class="ic-row"><span class="ic">🖼</span></div>
                        <strong>{{ $stats['photos_total'] }}</strong>
                        <div class="label">фото в базе</div>
                        <div class="sub">Исходящие в TG (лог): {{ $stats['telegram_logged_messages'] }}</div>
                    </div>
                    <div class="kpi">
                        <div class="ic-row"><span class="ic">⚙</span></div>
                        <strong>{{ $stats['plan_mode_full'] }}</strong>
                        <div class="label">режим FitBot</div>
                        <div class="sub">Свой план: {{ $stats['plan_mode_discipline'] }} · legacy ккал: {{ $stats['plan_legacy_calories'] }}</div>
                    </div>
                    <div class="kpi tone-ok">
                        <div class="ic-row"><span class="ic">✉️</span>
                            <a href="#support" class="delta ok" style="text-decoration:none;color:inherit;">открыть ↓</a>
                        </div>
                        <strong>{{ $supportMessagesTotal }}</strong>
                        <div class="label">обращений в поддержку</div>
                        <div class="sub">Кнопка «Написать в поддержку» в боте · ниже последние записи</div>
                    </div>
                </div>
            </section>

            <section class="section" id="funnel">
                <div class="section-head">
                    <h2>Воронка онбординга</h2>
                    <span class="hint-inline">Где застревают (непустой шаг)</span>
                </div>
                <div class="panel">
                    @if (count($onboardingFunnel) === 0)
                        <p class="hint" style="margin:0;">Никто не в онбординге.</p>
                    @else
                        <p class="hint">Ширина полосы относительно самого частого шага.</p>
                        <div class="funnel-bars">
                            @foreach ($onboardingFunnel as $f)
                                <div class="funnel-row">
                                    <span class="name">{{ $f['label'] }}</span>
                                    <div class="bar-wrap">
                                        <div class="bar" style="width: {{ max(4, round(100 * $f['count'] / $funnelMax)) }}%"></div>
                                    </div>
                                    <span class="cnt">{{ $f['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="section" id="broadcast">
                <div class="section-head">
                    <h2>Рассылка в Telegram</h2>
                    <span class="hint-inline">Двухшаговое подтверждение</span>
                </div>
                <div class="panel highlight" style="max-width: 820px;">
                    <p class="hint"><strong>Как отправить:</strong> выбери сегмент и текст → <strong>Показать получателей</strong> → проверь число и черновик → отметь галочку и подтверди. Без HTML, до ~4090 символов. Между отправками есть небольшая задержка, чтобы не упереться в лимиты API.</p>

                    @if (is_array($broadcastPending))
                        <div class="preview-box" style="border-color: rgba(77,159,255,.35);">
                            <strong>Черновик рассылки</strong><br>
                            <span class="no" style="font-size:.82rem;">Сегмент:</span>
                            <strong>{{ $segmentLabels[$broadcastPending['segment']] ?? $broadcastPending['segment'] }}</strong>
                            · получателей: <strong class="num">{{ $broadcastPending['recipient_count'] }}</strong>
                            <pre>{{ $broadcastPending['message'] }}</pre>
                            <form method="post" action="{{ route('admin.broadcast.confirm') }}" style="margin-top:.85rem;">
                                @csrf
                                <input type="hidden" name="message" value="{{ $broadcastPending['message'] }}">
                                <input type="hidden" name="segment" value="{{ $broadcastPending['segment'] }}">
                                <label style="display:flex;gap:.5rem;align-items:flex-start;margin:.4rem 0;">
                                    <input type="checkbox" name="confirm_broadcast" value="1" required>
                                    <span>Отправить ровно <strong>{{ $broadcastPending['recipient_count'] }}</strong> пользователям (текст и сегмент совпадают с черновиком)</span>
                                </label>
                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.65rem;">
                                    <button type="submit" class="btn">Подтвердить отправку</button>
                                </div>
                            </form>
                            <form method="post" action="{{ route('admin.broadcast.cancel') }}" style="margin-top:.5rem;">
                                @csrf
                                <button type="submit" class="btn btn-secondary">Сбросить черновик</button>
                            </form>
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.broadcast.preview') }}" style="margin-top:1rem;">
                        @csrf
                        <div class="toolbar" style="margin-bottom:.65rem;">
                            <div class="field" style="flex:1;min-width:240px;">
                                <label for="seg">Сегмент аудитории</label>
                                <select id="seg" name="segment">
                                    @foreach ($segmentLabels as $sid => $slabel)
                                        <option value="{{ $sid }}" @selected(old('segment', is_array($broadcastPending) ? $broadcastPending['segment'] : 'all_completed') === $sid)>{{ $slabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="msg">Текст сообщения</label>
                            <textarea id="msg" class="msg" name="message" rows="6" required placeholder="Текст для Telegram…">{{ old('message', is_array($broadcastPending) ? $broadcastPending['message'] : '') }}</textarea>
                        </div>
                        <button type="submit" class="btn" style="margin-top:.75rem;">Показать получателей</button>
                    </form>
                </div>
            </section>

            <section class="section" id="support">
                <div class="section-head">
                    <h2>Поддержка из бота</h2>
                    <span class="hint-inline">Баги и идеи · всего {{ $supportMessagesTotal }}</span>
                </div>
                <div class="panel">
                    @if ($supportMessages->isEmpty())
                        <p class="hint" style="margin:0;">Пока нет сообщений. После миграции таблицы и первых обращений они появятся здесь.</p>
                    @else
                        <p class="hint">С новых к старым (до 100 шт.). Пользователь удалён — строка пропадёт вместе с сообщениями (cascade).</p>
                        <div class="scroll-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Когда</th>
                                        <th>User</th>
                                        <th>Telegram</th>
                                        <th>Текст</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supportMessages as $sm)
                                        @php($su = $sm->user)
                                        <tr>
                                            <td class="no num" style="white-space:nowrap;">{{ $sm->created_at?->format('Y-m-d H:i') }}</td>
                                            <td class="num">
                                                @if ($su)
                                                    #{{ $su->id }} · {{ $su->first_name ?? '—' }}
                                                    @if ($su->username)
                                                        <span class="no">{{ '@'.$su->username }}</span>
                                                    @endif
                                                @else
                                                    <span class="no">удалён</span>
                                                @endif
                                            </td>
                                            <td class="num">{{ $sm->telegram_id }}</td>
                                            <td><pre class="support-msg">{{ $sm->body }}</pre></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <section class="section" id="users">
                <div class="section-head">
                    <h2>Пользователи</h2>
                    <span class="hint-inline">До {{ $rows->count() }} строк · сортировка по серии среди последних 800 по ID</span>
                </div>
                <div class="panel" style="padding-bottom:1.25rem;">
                    <form method="get" action="{{ url('/admin') }}" class="toolbar" id="user-filters">
                        <div class="field">
                            <label for="q">Поиск</label>
                            <input id="q" type="text" name="q" value="{{ $filters['q'] }}" placeholder="Имя, @username, id, telegram id…" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="filter">Срез</label>
                            <select id="filter" name="filter">
                                <option value="all" @selected($filters['filter'] === 'all')>Все</option>
                                <option value="completed" @selected($filters['filter'] === 'completed')>Онбординг готов</option>
                                <option value="onboarding" @selected($filters['filter'] === 'onboarding')>В онбординге</option>
                                <option value="active7" @selected($filters['filter'] === 'active7')>Активны 7 дней</option>
                                <option value="inactive7" @selected($filters['filter'] === 'inactive7')>Готовы, без чек-ина 7 дней</option>
                                <option value="inactive14" @selected($filters['filter'] === 'inactive14')>Готовы, без чек-ина 14 дней</option>
                                <option value="never_checked" @selected($filters['filter'] === 'never_checked')>Готовы, ни одного чек-ина</option>
                                <option value="low_activity_14d" @selected($filters['filter'] === 'low_activity_14d')>≤ 1 чек-ин за 14 дней</option>
                                <option value="new7" @selected($filters['filter'] === 'new7')>Новые за 7 дней</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="sort">Сортировка</label>
                            <select id="sort" name="sort">
                                <option value="id_desc" @selected($filters['sort'] === 'id_desc')>ID ↓</option>
                                <option value="created_asc" @selected($filters['sort'] === 'created_asc')>Регистрация ↑</option>
                                <option value="checks_desc" @selected($filters['sort'] === 'checks_desc')>Чек-инов ↓</option>
                                <option value="points_week_desc" @selected($filters['sort'] === 'points_week_desc')>Баллов за 7 дн ↓</option>
                                <option value="last_check_desc" @selected($filters['sort'] === 'last_check_desc')>Последний чек ↓</option>
                                <option value="streak_desc" @selected($filters['sort'] === 'streak_desc')>Серия ↓</option>
                                <option value="streak_asc" @selected($filters['sort'] === 'streak_asc')>Серия ↑</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn">Применить</button>
                        </div>
                    </form>

                    <div class="scroll-table">
                        <table class="responsive">
                            <thead>
                                <tr>
                                    <th>Пульс</th>
                                    <th>Уровень</th>
                                    <th>ID</th>
                                    <th>Telegram</th>
                                    <th>Имя</th>
                                    <th class="num">Возраст</th>
                                    <th class="num">Вес</th>
                                    <th>План</th>
                                    <th>Онбординг</th>
                                    <th class="num">Чек-ины</th>
                                    <th class="num">Баллы ∑</th>
                                    <th class="num">7 дн</th>
                                    <th class="num">Ср/чек</th>
                                    <th class="num">Фото</th>
                                    <th class="num">Серия</th>
                                    <th>Последний чек</th>
                                    <th>Ккал / цели</th>
                                    <th class="num">В боте</th>
                                    <th>Создан</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php($u = $row['user'])
                                    @php($pulse = $row['pulse'])
                                    @php($tier = $row['strike_tier'])
                                    <tr>
                                        <td data-label="Пульс">
                                            <span class="pulse pulse-{{ $pulse }}"><span class="pulse-dot"></span>{{ $pulseLabels[$pulse] ?? $pulse }}</span>
                                            @if ($row['onboarding_hint'])
                                                <div class="no" style="font-size:.72rem;margin-top:.25rem;">{{ $row['onboarding_hint'] }}</div>
                                            @endif
                                        </td>
                                        <td data-label="Уровень" class="tier-cell">
                                            <span class="e">{{ $tier->emoji() }}</span><b>{{ $tier->labelRu() }}</b>
                                            <div class="no" style="font-size:.7rem;margin-top:.2rem;">серия {{ $row['streak'] }} · {{ $tier->criteriaRu() }}</div>
                                        </td>
                                        <td class="num" data-label="ID">{{ $u->id }}</td>
                                        <td class="num" data-label="Telegram">
                                            {{ $u->telegram_id }}
                                            <button type="button" class="copy-tg" data-copy="{{ $u->telegram_id }}" title="Копировать">⎘</button>
                                        </td>
                                        <td data-label="Имя">{{ $u->first_name }} @if($u->username) <span class="no">{{ '@'.$u->username }}</span> @endif</td>
                                        <td class="num" data-label="Возраст">{{ $u->age ?? '—' }}</td>
                                        <td class="num" data-label="Вес">{{ $u->weight_kg !== null ? number_format((float) $u->weight_kg, 1, '.', '') : '—' }}</td>
                                        <td data-label="План">
                                            @if($u->plan_mode === 'discipline')
                                                <span class="pill pill-disc">свой план</span>
                                            @elseif($u->plan_mode === 'full' || $u->daily_calories_target)
                                                <span class="pill pill-full">FitBot</span>
                                            @else
                                                <span class="pill">—</span>
                                            @endif
                                        </td>
                                        <td class="{{ $row['onboarding_done'] ? 'ok' : 'no' }}" data-label="Онбординг">{{ $row['onboarding_done'] ? 'да' : 'нет' }}</td>
                                        <td class="num" data-label="Чек-ины">{{ $row['completed_checks'] }}</td>
                                        <td class="num" data-label="Баллы">{{ $row['lifetime_points'] }}</td>
                                        <td class="num" data-label="7 дн">{{ $row['week_points'] }}</td>
                                        <td class="num" data-label="Ср/чек">{{ $row['avg_check_score'] ?? '—' }}</td>
                                        <td class="num" data-label="Фото">{{ $row['photos_count'] }}</td>
                                        <td class="num" data-label="Серия">{{ $row['streak'] }}</td>
                                        <td data-label="Последний чек">
                                            @if ($row['last_check'])
                                                {{ $row['last_check']->format('Y-m-d') }}
                                                @if ($row['days_since_check'] !== null)
                                                    <span class="no" style="font-size:.72rem;display:block;">
                                                        @if ($row['days_since_check'] === 0)
                                                            сегодня
                                                        @elseif ($row['days_since_check'] === 1)
                                                            вчера
                                                        @else
                                                            {{ $row['days_since_check'] }} дн. назад
                                                        @endif
                                                    </span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td data-label="Цели" style="max-width:160px;font-size:.78rem;" class="no">
                                            @if($u->daily_calories_target)
                                                {{ $u->daily_calories_target }} ккал
                                            @elseif($u->isDisciplineOnlyMode())
                                                вода {{ $u->water_goal_ml ?? '—' }} · сон {{ $u->sleep_target_hours ?? '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="num no" data-label="Дней в боте">{{ $row['days_in_bot'] !== null ? $row['days_in_bot'] : '—' }}</td>
                                        <td class="no" data-label="Создан">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                                        <td data-label="Действия">
                                            <details class="danger-zone">
                                                <summary>Удалить…</summary>
                                                <form method="post" action="{{ route('admin.user.destroy', $u) }}">
                                                    @csrf
                                                    <p class="hint" style="margin-top:0;">Удаляет аккаунт и очищает запомненные сообщения бота в чате.</p>
                                                    <label style="display:flex;gap:.4rem;align-items:flex-start;margin:.4rem 0;">
                                                        <input type="checkbox" name="confirm_delete" value="1" required>
                                                        <span>Подтверждаю удаление</span>
                                                    </label>
                                                    <label style="display:flex;gap:.4rem;align-items:flex-start;margin:.4rem 0;">
                                                        <input type="hidden" name="notify_user" value="0">
                                                        <input type="checkbox" name="notify_user" value="1" checked>
                                                        <span>Уведомить пользователя в Telegram</span>
                                                    </label>
                                                    <button type="submit" class="btn btn-danger" style="margin-top:.4rem;">Удалить навсегда</button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="foot-note"><b>Пульс</b> — насколько недавно человек закрывал чек-ин и давно ли в боте (это не игровой уровень). <b>Уровень</b> — геймификация по <b>текущей серии</b> закрытых чек-инов подряд (как в боте в «Профиль»): 0–7 Новичок, 8–14 Подснежник, 15–30 Любитель, 31–60 Опытный, 61+ Босс. Колонка «7 дн» — сумма баллов за последние 7 календарных дней.</p>
                </div>
            </section>
        </main>
    </div>
    <script>
        document.querySelectorAll('.copy-tg').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var v = btn.getAttribute('data-copy');
                if (!v) return;
                navigator.clipboard.writeText(v).then(function () {
                    var t = btn.textContent;
                    btn.textContent = '✓';
                    setTimeout(function () { btn.textContent = t; }, 1200);
                });
            });
        });
    </script>
</body>
</html>
