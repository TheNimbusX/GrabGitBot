<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050608;
            --ink: #0c0e12;
            --elev-1: rgba(18, 22, 30, .72);
            --elev-2: rgba(24, 30, 42, .85);
            --glass: rgba(255, 255, 255, .04);
            --glass-border: rgba(255, 255, 255, .09);
            --text: #f2f5fa;
            --text-dim: #8b93a8;
            --cyan: #22d3ee;
            --cyan-dim: rgba(34, 211, 238, .14);
            --mint: #34d399;
            --mint-dim: rgba(52, 211, 153, .12);
            --amber: #fbbf24;
            --amber-dim: rgba(251, 191, 36, .12);
            --rose: #fb7185;
            --rose-dim: rgba(251, 113, 133, .12);
            --violet: #a78bfa;
            --violet-dim: rgba(167, 139, 250, .12);
            --radius: 16px;
            --radius-sm: 10px;
            --font: "Outfit", system-ui, -apple-system, sans-serif;
            --mono: "IBM Plex Mono", ui-monospace, monospace;
            --nav-w: 248px;
            --glow-cyan: 0 0 80px rgba(34, 211, 238, .12);
            --shadow-card: 0 4px 24px rgba(0, 0, 0, .45), 0 0 0 1px var(--glass-border);
        }
        * { box-sizing: border-box; }
        html {
            scroll-behavior: smooth;
            color-scheme: dark;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font);
            color: var(--text);
            line-height: 1.5;
            background: var(--void);
            position: relative;
        }
        .app-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 100% 70% at 15% -10%, rgba(34, 211, 238, .16), transparent 45%),
                radial-gradient(ellipse 80% 50% at 95% 10%, rgba(167, 139, 250, .12), transparent 42%),
                radial-gradient(ellipse 60% 40% at 50% 100%, rgba(52, 211, 153, .08), transparent 50%),
                linear-gradient(180deg, var(--ink) 0%, var(--void) 100%);
            pointer-events: none;
        }
        .app-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .018) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 85% 70% at 50% 0%, black 20%, transparent 75%);
        }
        .layout {
            position: relative;
            z-index: 1;
            display: flex;
            max-width: 1760px;
            margin: 0 auto;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--nav-w);
            flex-shrink: 0;
            padding: 1.5rem 1rem 1.5rem 1.35rem;
            position: sticky;
            top: 0;
            align-self: flex-start;
            height: 100vh;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--glass-border);
            background: linear-gradient(165deg, rgba(12, 14, 18, .94) 0%, rgba(8, 10, 14, .88) 100%);
            backdrop-filter: blur(20px);
        }
        .brand-lockup {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.75rem;
        }
        .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--cyan), #0891b2);
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--void);
            box-shadow: 0 4px 20px rgba(34, 211, 238, .35);
        }
        .brand-text .brand {
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--cyan);
            margin: 0;
        }
        .brand-text h1 {
            font-size: 1.2rem;
            font-weight: 800;
            margin: .15rem 0 0;
            letter-spacing: -.03em;
            line-height: 1.15;
        }
        .nav {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }
        .nav a {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .62rem .85rem;
            border-radius: var(--radius-sm);
            color: var(--text-dim);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 600;
            transition: color .15s, background .2s, transform .15s;
            border: 1px solid transparent;
        }
        .nav a:hover {
            color: var(--text);
            background: var(--glass);
            border-color: var(--glass-border);
        }
        .nav a .ic {
            font-size: 1.05rem;
            width: 1.35rem;
            text-align: center;
            opacity: .95;
        }
        .sidebar-foot {
            margin-top: auto;
            padding-top: 1.5rem;
        }
        .sidebar-foot .meta {
            font-family: var(--mono);
            font-size: .68rem;
            color: var(--text-dim);
            margin-bottom: .75rem;
            line-height: 1.45;
            padding: .65rem .75rem;
            background: var(--glass);
            border-radius: var(--radius-sm);
            border: 1px solid var(--glass-border);
        }
        .btn-ghost {
            width: 100%;
            background: transparent;
            color: var(--text-dim);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            padding: .55rem .85rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: color .15s, border-color .15s, background .15s;
        }
        .btn-ghost:hover {
            color: var(--text);
            border-color: rgba(34, 211, 238, .35);
            background: var(--cyan-dim);
        }
        .main-area {
            flex: 1;
            min-width: 0;
            padding: 1.35rem 1.75rem 3.5rem;
        }
        .topbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.75rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--glass-border);
        }
        .topbar-kicker {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--cyan);
            margin: 0 0 .35rem;
        }
        .topbar-title {
            font-size: clamp(1.65rem, 3.5vw, 2.15rem);
            font-weight: 800;
            letter-spacing: -.04em;
            margin: 0;
            line-height: 1.1;
            background: linear-gradient(120deg, #fff 30%, rgba(34, 211, 238, .85));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .topbar-meta {
            display: flex;
            align-items: center;
            gap: .85rem;
            flex-wrap: wrap;
        }
        .pill-live {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-family: var(--mono);
            font-size: .68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--mint);
            padding: .35rem .65rem;
            background: var(--mint-dim);
            border-radius: 999px;
            border: 1px solid rgba(52, 211, 153, .25);
        }
        .pill-live .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--mint);
            box-shadow: 0 0 10px var(--mint);
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .6; transform: scale(.85); }
        }
        #admin-clock {
            font-family: var(--mono);
            font-size: .8rem;
            color: var(--text-dim);
        }
        .section {
            margin-bottom: 2.25rem;
        }
        .section-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .65rem;
            margin-bottom: 1.1rem;
        }
        .section-head h2 {
            display: flex;
            align-items: center;
            gap: .65rem;
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -.02em;
        }
        .section-idx {
            font-family: var(--mono);
            font-size: .65rem;
            font-weight: 600;
            color: var(--void);
            background: linear-gradient(135deg, var(--cyan), #06b6d4);
            padding: .2rem .45rem;
            border-radius: 6px;
        }
        .hint-inline {
            font-size: .8rem;
            color: var(--text-dim);
            max-width: 420px;
            text-align: right;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 1100px) {
            .hero-grid { grid-template-columns: 1fr; }
        }
        .hero-card {
            position: relative;
            padding: 1.35rem 1.4rem;
            border-radius: var(--radius);
            background: var(--elev-1);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .hero-card::before {
            content: "";
            position: absolute;
            top: 0; right: 0;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, var(--card-glow, rgba(34, 211, 238, .15)) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-card.cyan { --card-glow: rgba(34, 211, 238, .22); }
        .hero-card.mint { --card-glow: rgba(52, 211, 153, .2); }
        .hero-card.violet { --card-glow: rgba(167, 139, 250, .18); }
        .hero-label {
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-dim);
            margin-bottom: .5rem;
        }
        .hero-value {
            font-family: var(--mono);
            font-size: 2.35rem;
            font-weight: 600;
            line-height: 1;
            letter-spacing: -.03em;
        }
        .hero-sub {
            margin-top: .75rem;
            font-size: .78rem;
            color: var(--text-dim);
            line-height: 1.45;
        }
        .hero-badge {
            display: inline-block;
            margin-top: .65rem;
            font-size: .7rem;
            font-weight: 700;
            padding: .2rem .5rem;
            border-radius: 6px;
            background: var(--mint-dim);
            color: var(--mint);
        }
        .bento {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .85rem;
        }
        @media (max-width: 1200px) {
            .bento { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .bento { grid-template-columns: 1fr; }
        }
        .mini-kpi {
            padding: 1rem 1.1rem;
            border-radius: var(--radius-sm);
            background: var(--elev-2);
            border: 1px solid var(--glass-border);
            transition: transform .15s, border-color .15s;
        }
        .mini-kpi:hover {
            transform: translateY(-2px);
            border-color: rgba(34, 211, 238, .2);
        }
        .mini-kpi .mk-ic { font-size: 1.1rem; margin-bottom: .35rem; }
        .mini-kpi strong {
            font-family: var(--mono);
            font-size: 1.45rem;
            font-weight: 600;
            display: block;
        }
        .mini-kpi .mk-label { font-size: .75rem; color: var(--text-dim); margin-top: .15rem; }
        .mini-kpi .mk-sub { font-size: .68rem; color: var(--text-dim); margin-top: .45rem; line-height: 1.35; opacity: .9; }
        .mini-kpi.warn { border-left: 3px solid var(--amber); }
        .mini-kpi.danger { border-left: 3px solid var(--rose); }
        .panel {
            border-radius: var(--radius);
            background: var(--elev-1);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            padding: 1.35rem 1.5rem;
        }
        .panel-glow {
            box-shadow: var(--shadow-card), var(--glow-cyan);
            border-color: rgba(34, 211, 238, .2);
        }
        .hint { font-size: .84rem; color: var(--text-dim); margin: 0 0 1rem; line-height: 1.55; }
        .flash {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            padding: 1rem 1.15rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            font-size: .88rem;
            border: 1px solid transparent;
        }
        .flash::before { font-size: 1.1rem; line-height: 1; }
        .flash-ok { background: var(--mint-dim); border-color: rgba(52, 211, 153, .3); }
        .flash-ok::before { content: "✓"; color: var(--mint); }
        .flash-info { background: var(--cyan-dim); border-color: rgba(34, 211, 238, .28); }
        .flash-info::before { content: "ⓘ"; color: var(--cyan); }
        .flash-err { background: var(--rose-dim); border-color: rgba(251, 113, 133, .35); }
        .flash-err::before { content: "!"; color: var(--rose); font-weight: 800; }
        .funnel-bars { display: flex; flex-direction: column; gap: .65rem; }
        .funnel-row {
            display: grid;
            grid-template-columns: minmax(150px, 1.1fr) 2fr auto;
            gap: 1rem;
            align-items: center;
            padding: .55rem .65rem;
            border-radius: var(--radius-sm);
            background: rgba(0, 0, 0, .2);
            border: 1px solid var(--glass-border);
            font-size: .84rem;
        }
        @media (max-width: 640px) {
            .funnel-row { grid-template-columns: 1fr; gap: .5rem; }
        }
        .funnel-row .name { font-weight: 600; }
        .funnel-row .bar-wrap {
            height: 10px;
            background: rgba(255, 255, 255, .06);
            border-radius: 999px;
            overflow: hidden;
        }
        .funnel-row .bar {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--cyan), var(--violet));
            box-shadow: 0 0 12px rgba(34, 211, 238, .35);
            min-width: 6px;
        }
        .funnel-row .cnt {
            font-family: var(--mono);
            font-weight: 600;
            color: var(--cyan);
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .85rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .toolbar .field label {
            font-size: .72rem;
            font-weight: 600;
            color: var(--text-dim);
            display: block;
            margin-bottom: .3rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .toolbar input[type="text"], .toolbar select, .toolbar textarea {
            background: rgba(0, 0, 0, .35);
            border: 1px solid var(--glass-border);
            color: var(--text);
            border-radius: var(--radius-sm);
            padding: .55rem .75rem;
            font: inherit;
            min-width: 200px;
        }
        .toolbar input:focus, .toolbar select:focus, .toolbar textarea:focus {
            outline: none;
            border-color: rgba(34, 211, 238, .45);
            box-shadow: 0 0 0 3px var(--cyan-dim);
        }
        .btn {
            background: linear-gradient(135deg, #22d3ee, #06b6d4);
            color: var(--void);
            border: 0;
            border-radius: var(--radius-sm);
            padding: .58rem 1.2rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(34, 211, 238, .3);
            transition: filter .15s, transform .1s;
        }
        .btn:hover { filter: brightness(1.08); }
        .btn:active { transform: scale(.98); }
        .btn-secondary {
            background: rgba(255, 255, 255, .06);
            color: var(--text);
            border: 1px solid var(--glass-border);
            box-shadow: none;
        }
        .btn-secondary:hover { background: rgba(255, 255, 255, .1); filter: none; }
        .btn-danger {
            background: linear-gradient(135deg, #fb7185, #e11d48);
            color: #fff;
            box-shadow: 0 4px 20px rgba(251, 113, 133, .25);
        }
        form.inline { display: inline; }
        textarea.msg {
            width: 100%;
            min-height: 140px;
            resize: vertical;
            font-family: var(--mono);
            font-size: .82rem;
        }
        .preview-box {
            background: rgba(0, 0, 0, .35);
            border: 1px solid rgba(34, 211, 238, .25);
            border-radius: var(--radius-sm);
            padding: 1rem 1.15rem;
            margin-top: .75rem;
        }
        .preview-box pre {
            white-space: pre-wrap;
            margin: .65rem 0 0;
            font-family: var(--mono);
            font-size: .82rem;
            color: var(--text);
        }
        .scroll-table {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--glass-border);
            background: rgba(0, 0, 0, .25);
        }
        table { width: 100%; border-collapse: collapse; font-size: .78rem; }
        th, td {
            padding: .65rem .75rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
            vertical-align: middle;
        }
        thead th {
            background: rgba(34, 211, 238, .06);
            font-weight: 700;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-dim);
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tbody tr {
            transition: background .12s;
        }
        tbody tr:hover { background: rgba(34, 211, 238, .04); }
        tbody tr:last-child td { border-bottom: 0; }
        .num { font-family: var(--mono); font-variant-numeric: tabular-nums; }
        .ok { color: var(--mint); }
        .no { color: var(--text-dim); }
        .pulse {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .68rem;
            font-weight: 700;
            padding: .22rem .55rem;
            border-radius: 999px;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .pulse-dot { width: 6px; height: 6px; border-radius: 50%; }
        .pulse-hot { background: var(--mint-dim); color: var(--mint); border-color: rgba(52, 211, 153, .25); }
        .pulse-hot .pulse-dot { background: var(--mint); box-shadow: 0 0 8px var(--mint); }
        .pulse-warm { background: var(--cyan-dim); color: var(--cyan); border-color: rgba(34, 211, 238, .25); }
        .pulse-warm .pulse-dot { background: var(--cyan); }
        .pulse-cool { background: var(--amber-dim); color: var(--amber); border-color: rgba(251, 191, 36, .25); }
        .pulse-cool .pulse-dot { background: var(--amber); }
        .pulse-cold { background: rgba(255, 255, 255, .05); color: var(--text-dim); border-color: var(--glass-border); }
        .pulse-cold .pulse-dot { background: var(--text-dim); }
        .pulse-new { background: var(--violet-dim); color: var(--violet); border-color: rgba(167, 139, 250, .28); }
        .pulse-new .pulse-dot { background: var(--violet); }
        .pulse-onboarding { background: rgba(34, 211, 238, .1); color: #7dd3fc; border-color: rgba(34, 211, 238, .2); }
        .pulse-onboarding .pulse-dot { background: var(--cyan); }
        .pill {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            padding: .18rem .5rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, .08);
            color: var(--text-dim);
        }
        .pill-full { background: var(--mint-dim); color: var(--mint); }
        .pill-disc { background: var(--amber-dim); color: var(--amber); }
        .copy-tg {
            font-family: var(--mono);
            font-size: .65rem;
            padding: .2rem .45rem;
            border-radius: 6px;
            border: 1px solid var(--glass-border);
            background: rgba(0, 0, 0, .35);
            color: var(--text-dim);
            cursor: pointer;
            margin-left: .3rem;
            transition: color .15s, border-color .15s;
        }
        .copy-tg:hover { color: var(--cyan); border-color: rgba(34, 211, 238, .4); }
        .foot-note {
            font-size: .74rem;
            color: var(--text-dim);
            margin-top: 1rem;
            line-height: 1.55;
            padding: 1rem 1.1rem;
            background: rgba(0, 0, 0, .2);
            border-radius: var(--radius-sm);
            border: 1px dashed var(--glass-border);
        }
        .tier-cell { font-size: .78rem; line-height: 1.35; max-width: 210px; }
        .tier-cell .e { font-size: 1.1rem; margin-right: .2rem; }
        .support-msg {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: var(--mono);
            font-size: .8rem;
            line-height: 1.45;
            max-height: 200px;
            overflow: auto;
            padding: .75rem;
            background: rgba(0, 0, 0, .35);
            border-radius: var(--radius-sm);
            border: 1px solid var(--glass-border);
        }
        details.danger-zone summary {
            cursor: pointer;
            color: var(--rose);
            font-weight: 700;
            font-size: .75rem;
            list-style: none;
        }
        details.danger-zone summary::-webkit-details-marker { display: none; }
        details.danger-zone form {
            margin-top: .65rem;
            padding: .85rem;
            background: rgba(251, 113, 133, .06);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(251, 113, 133, .2);
        }
        /* Таблица пользователей: горизонтальный скролл + липкие первые колонки на десктопе */
        .table-users-shell {
            overflow-x: auto;
            overflow-y: hidden;
            border-radius: var(--radius);
            border: 1px solid var(--glass-border);
            background: rgba(0, 0, 0, .28);
            position: relative;
            -webkit-overflow-scrolling: touch;
        }
        .table-users-shell::-webkit-scrollbar { height: 10px; }
        .table-users-shell::-webkit-scrollbar-thumb {
            background: rgba(34, 211, 238, .25);
            border-radius: 999px;
        }
        table.table-users {
            min-width: 2100px;
            font-size: .8rem;
        }
        table.table-users tbody tr:nth-child(even) td {
            background: rgba(255, 255, 255, .025);
        }
        table.table-users tbody tr:nth-child(even) td.sticky-col--a,
        table.table-users tbody tr:nth-child(even) td.sticky-col--b {
            background: rgba(18, 20, 26, 0.98) !important;
        }
        table.table-users tbody tr:hover td {
            background: rgba(34, 211, 238, .07);
        }
        table.table-users tbody tr:hover td.sticky-col--a,
        table.table-users tbody tr:hover td.sticky-col--b {
            background: rgba(22, 32, 42, 0.99) !important;
        }
        .sticky-col--a, .sticky-col--b {
            position: sticky;
            z-index: 2;
            background: rgba(12, 14, 18, .97) !important;
            box-shadow: 6px 0 20px -6px rgba(0, 0, 0, .65);
        }
        thead .sticky-col--a, thead .sticky-col--b {
            z-index: 4;
            background: rgba(16, 22, 30, .98) !important;
        }
        .sticky-col--a {
            left: 0;
            min-width: 11.5rem;
            max-width: 13rem;
        }
        .sticky-col--b {
            left: 12.5rem;
            min-width: 11rem;
            max-width: 15rem;
        }
        .sticky-col--a .pulse {
            white-space: normal;
            max-width: 12rem;
            line-height: 1.3;
        }
        @media (max-width: 1100px) {
            .sticky-col--a, .sticky-col--b {
                position: relative !important;
                left: auto !important;
                min-width: unset !important;
                max-width: unset !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            thead .sticky-col--a, thead .sticky-col--b {
                background: rgba(34, 211, 238, .06) !important;
            }
            table.table-users { min-width: 100%; font-size: .78rem; }
        }
        /* Рассылка: тёмные поля (не «белое» системное) */
        .broadcast-form .field { margin-bottom: 0; }
        .broadcast-form textarea.msg {
            width: 100%;
            min-height: 190px;
            margin-top: .35rem;
            background: rgba(5, 7, 10, 0.95) !important;
            color: #e8edf5 !important;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 14px;
            padding: 1.05rem 1.15rem;
            font-family: var(--mono);
            font-size: .86rem;
            line-height: 1.55;
            caret-color: var(--cyan);
            resize: vertical;
            box-shadow: inset 0 2px 16px rgba(0, 0, 0, .4);
        }
        .broadcast-form textarea.msg::placeholder {
            color: rgba(139, 147, 168, 0.75);
        }
        .broadcast-form textarea.msg:focus {
            outline: none;
            border-color: rgba(34, 211, 238, 0.45);
            box-shadow: inset 0 2px 16px rgba(0, 0, 0, .4), 0 0 0 3px rgba(34, 211, 238, 0.12);
        }
        select.select-tg {
            appearance: none;
            -webkit-appearance: none;
            background-color: rgba(8, 10, 14, 0.95) !important;
            color: var(--text) !important;
            border: 1px solid var(--glass-border);
            padding: 0.62rem 2.6rem 0.62rem 0.95rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.84rem;
            cursor: pointer;
            width: 100%;
            max-width: 100%;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='%2322d3ee' viewBox='0 0 16 16'%3E%3Cpath d='M8 12L2 5h12L8 12z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
        }
        select.select-tg:focus {
            outline: none;
            border-color: rgba(34, 211, 238, 0.45);
            box-shadow: 0 0 0 3px var(--cyan-dim);
        }
        select.select-tg option {
            background: #12151c;
            color: var(--text);
        }
        .support-row--read { opacity: .72; }
        .support-row--read .support-msg { opacity: .9; }
        .support-badge {
            font-family: var(--mono);
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: .2rem .45rem;
            border-radius: 6px;
        }
        .support-badge--new {
            background: var(--cyan-dim);
            color: var(--cyan);
            border: 1px solid rgba(34, 211, 238, .3);
        }
        .support-badge--done {
            background: rgba(255, 255, 255, .06);
            color: var(--text-dim);
            border: 1px solid var(--glass-border);
        }
        .support-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            align-items: center;
        }
        .support-actions form { margin: 0; }
        .btn-mini {
            padding: .32rem .55rem;
            font-size: .68rem;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
            cursor: pointer;
            font-family: var(--font);
            background: rgba(255, 255, 255, .06);
            color: var(--text);
            transition: background .15s, border-color .15s;
        }
        .btn-mini:hover { background: rgba(255, 255, 255, .1); }
        .btn-mini--primary {
            background: var(--cyan-dim);
            border-color: rgba(34, 211, 238, .35);
            color: var(--cyan);
        }
        .btn-mini--danger {
            background: var(--rose-dim);
            border-color: rgba(251, 113, 133, .35);
            color: var(--rose);
        }
        .mobile-nav {
            display: none;
            position: sticky;
            top: 0;
            z-index: 20;
            padding: .75rem 0 1rem;
            margin: -0.5rem 0 1.25rem;
            background: linear-gradient(180deg, var(--void) 70%, transparent);
        }
        .mobile-nav select {
            width: 100%;
            background: var(--elev-2);
            border: 1px solid var(--glass-border);
            color: var(--text);
            border-radius: var(--radius-sm);
            padding: .65rem .75rem;
            font: inherit;
            font-weight: 600;
        }
        @media (max-width: 960px) {
            .layout { flex-direction: column; }
            .sidebar { display: none; }
            .mobile-nav { display: block; }
            .main-area { padding: 1rem 1.1rem 2.5rem; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .hint-inline { text-align: left; }
        }
        @media (max-width: 1100px) {
            table.responsive thead { display: none; }
            table.responsive tbody tr {
                display: block;
                border-bottom: 1px solid var(--glass-border);
                padding: .85rem 0;
                margin: 0 .35rem;
            }
            table.responsive tbody td {
                display: grid;
                grid-template-columns: minmax(110px, 36%) 1fr;
                gap: .4rem .8rem;
                padding: .4rem .5rem;
                border: 0;
            }
            table.responsive tbody td::before {
                content: attr(data-label);
                font-size: .62rem;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: var(--text-dim);
                font-weight: 700;
            }
        }
    </style>
</head>
<body>
    <div class="app-bg" aria-hidden="true"></div>
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
            <div class="brand-lockup">
                <div class="brand-mark">F</div>
                <div class="brand-text">
                    <p class="brand">FitBot</p>
                    <h1>Control</h1>
                </div>
            </div>
            <nav class="nav">
                <a href="#metrics"><span class="ic">◆</span> Метрики</a>
                <a href="#funnel"><span class="ic">▤</span> Воронка</a>
                <a href="#broadcast"><span class="ic">✦</span> Рассылка</a>
                <a href="#support"><span class="ic">✉</span> Поддержка</a>
                <a href="#users"><span class="ic">◎</span> Пользователи</a>
            </nav>
            <div class="sidebar-foot">
                <div class="meta">snapshot<br>{{ $generatedAt->format('d.m.Y H:i') }}</div>
                <form class="inline" method="post" action="{{ url('/admin/logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Выйти</button>
                </form>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                <div>
                    <p class="topbar-kicker">Операционная панель</p>
                    <h1 class="topbar-title">FitBot Control</h1>
                </div>
                <div class="topbar-meta">
                    <span class="pill-live"><span class="dot"></span> live</span>
                    <time id="admin-clock" datetime="{{ $generatedAt->toIso8601String() }}">—</time>
                </div>
            </header>

            <div class="mobile-nav">
                <label for="jump" class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">Раздел</label>
                <select id="jump" onchange="if(this.value) location.hash=this.value">
                    <option value="">Раздел…</option>
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
                    <h2><span class="section-idx">01</span> Сводка</h2>
                    <span class="hint-inline">База, активность, чек-ины и планы в одном взгляде</span>
                </div>
                <div class="hero-grid">
                    <div class="hero-card cyan">
                        <div class="hero-label">Пользователи</div>
                        <div class="hero-value">{{ $stats['users_total'] }}</div>
                        <div class="hero-sub">Онбординг готов: {{ $stats['users_completed_onboarding'] }} · в процессе: {{ $stats['users_in_onboarding'] }}</div>
                        @if ($stats['users_new_7d'] > 0)
                            <span class="hero-badge">+{{ $stats['users_new_7d'] }} за 7 дней</span>
                        @endif
                    </div>
                    <div class="hero-card mint">
                        <div class="hero-label">Активность 7 дней</div>
                        <div class="hero-value">{{ $stats['users_active_7d'] }}</div>
                        <div class="hero-sub">Из завершивших онбординг: {{ $stats['users_active_7d_completed'] }} с чек-ином за неделю</div>
                        @if ($engPct !== null)
                            <span class="hero-badge">{{ $engPct }}% вовлечённость</span>
                        @endif
                    </div>
                    <div class="hero-card violet">
                        <div class="hero-label">Чек-ины сегодня</div>
                        <div class="hero-value">{{ $stats['checks_today'] }}</div>
                        <div class="hero-sub">Всего закрытых за всё время: {{ $stats['checks_completed_total'] }}</div>
                    </div>
                </div>
                <div class="bento">
                    <div class="mini-kpi warn">
                        <div class="mk-ic">⏸</div>
                        <strong>{{ $stats['users_dormant_7d_completed'] }}</strong>
                        <div class="mk-label">тишина 7+ дней</div>
                        <div class="mk-sub">14+ дней: {{ $stats['users_dormant_14d_completed'] }}</div>
                    </div>
                    <div class="mini-kpi danger">
                        <div class="mk-ic">✕</div>
                        <strong>{{ $stats['users_completed_never_checked'] }}</strong>
                        <div class="mk-label">не чекались после онбординга</div>
                        <div class="mk-sub">нужен пинок или сегмент</div>
                    </div>
                    <div class="mini-kpi">
                        <div class="mk-ic">📆</div>
                        <strong>{{ $stats['checks_week'] }}</strong>
                        <div class="mk-label">чек-инов (календ. неделя)</div>
                        <div class="mk-sub">баллов: {{ $stats['points_week_all_users'] }} · ср./чек: {{ $stats['avg_score_per_check_week'] ?? '—' }}</div>
                    </div>
                    <div class="mini-kpi">
                        <div class="mk-ic">🖼</div>
                        <strong>{{ $stats['photos_total'] }}</strong>
                        <div class="mk-label">фото · TG лог</div>
                        <div class="mk-sub">{{ $stats['telegram_logged_messages'] }} исходящих</div>
                    </div>
                    <div class="mini-kpi">
                        <div class="mk-ic">⚙</div>
                        <strong>{{ $stats['plan_mode_full'] }}</strong>
                        <div class="mk-label">режим FitBot</div>
                        <div class="mk-sub">свой: {{ $stats['plan_mode_discipline'] }} · legacy ккал: {{ $stats['plan_legacy_calories'] }}</div>
                    </div>
                    <div class="mini-kpi">
                        <div class="mk-ic">✉️</div>
                        <strong>{{ $supportMessagesTotal }}</strong>
                        <div class="mk-label">обращения</div>
                        <div class="mk-sub">
                            @if ($supportUnreadCount !== null && $supportUnreadCount > 0)
                                <span style="color:var(--cyan);font-weight:700;">непрочитано: {{ $supportUnreadCount }}</span>
                            @elseif ($supportUnreadCount !== null)
                                все обработаны
                            @else
                                <span class="no">миграция <code style="font-family:var(--mono);font-size:.9em;">read_at</code> — см. раздел поддержки</span>
                            @endif
                            · <a href="#support" style="color:var(--cyan);text-decoration:none;font-weight:700;">открыть ↓</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="funnel">
                <div class="section-head">
                    <h2><span class="section-idx">02</span> Воронка онбординга</h2>
                    <span class="hint-inline">Шаги с непустым <code style="font-family:var(--mono);font-size:.75em;opacity:.8;">onboarding_step</code></span>
                </div>
                <div class="panel">
                    @if (count($onboardingFunnel) === 0)
                        <p class="hint" style="margin:0;">Онбординг пуст — все либо прошли, либо ещё не стартовали.</p>
                    @else
                        <p class="hint">Длина полосы относительно самого частого шага ({{ $funnelMax }}).</p>
                        <div class="funnel-bars">
                            @foreach ($onboardingFunnel as $f)
                                <div class="funnel-row">
                                    <span class="name">{{ $f['label'] }}</span>
                                    <div class="bar-wrap">
                                        <div class="bar" style="width: {{ max(5, round(100 * $f['count'] / $funnelMax)) }}%"></div>
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
                    <h2><span class="section-idx">03</span> Рассылка Telegram</h2>
                    <span class="hint-inline">Предпросмотр → подтверждение · без HTML</span>
                </div>
                <div class="panel panel-glow">
                    <p class="hint"><strong>Поток:</strong> сегмент + текст → <strong>Показать получателей</strong> → сверка числа → галочка → отправка. До ~4090 символов; между юзерами есть задержка.</p>

                    @if (is_array($broadcastPending))
                        <div class="preview-box">
                            <strong>Черновик</strong><br>
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
                                    <span>Отправить <strong>{{ $broadcastPending['recipient_count'] }}</strong> пользователям</span>
                                </label>
                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.65rem;">
                                    <button type="submit" class="btn">Подтвердить</button>
                                </div>
                            </form>
                            <form method="post" action="{{ route('admin.broadcast.cancel') }}" style="margin-top:.5rem;">
                                @csrf
                                <button type="submit" class="btn btn-secondary">Сбросить черновик</button>
                            </form>
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.broadcast.preview') }}" class="broadcast-form" style="margin-top:1rem;">
                        @csrf
                        <div class="toolbar" style="margin-bottom:.85rem;">
                            <div class="field" style="flex:1;min-width:0;width:100%;">
                                <label for="seg">Сегмент аудитории</label>
                                <select id="seg" name="segment" class="select-tg">
                                    @foreach ($segmentLabels as $sid => $slabel)
                                        <option value="{{ $sid }}" @selected(old('segment', is_array($broadcastPending) ? $broadcastPending['segment'] : 'all_completed') === $sid)>{{ $slabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="msg">Текст рассылки</label>
                            <textarea id="msg" class="msg" name="message" rows="8" required placeholder="Пиши сюда текст как в Telegram (без HTML)…">{{ old('message', is_array($broadcastPending) ? $broadcastPending['message'] : '') }}</textarea>
                        </div>
                        <button type="submit" class="btn" style="margin-top:.85rem;">Показать получателей</button>
                    </form>
                </div>
            </section>

            <section class="section" id="support">
                <div class="section-head">
                    <h2><span class="section-idx">04</span> Поддержка</h2>
                    <span class="hint-inline">
                        {{ $supportMessagesTotal }} всего
                        @if ($supportUnreadCount !== null)
                            · <strong style="color:var(--cyan);">{{ $supportUnreadCount }}</strong> непрочитано
                        @endif
                    </span>
                </div>
                <div class="panel">
                    @if ($supportMessages->isEmpty())
                        <p class="hint" style="margin:0;">Пока пусто — ждём первые сообщения из бота.</p>
                    @else
                        @if (! $supportHasReadAt)
                            <p class="hint" style="border:1px dashed var(--amber);padding:.75rem;border-radius:10px;">
                                Выполни миграцию: <code style="font-family:var(--mono);">php artisan migrate</code> — появятся «прочитано» и сортировка непрочитанных.
                            </p>
                        @endif
                        <p class="hint">Сверху непрочитанные (если колонка есть). До 100 строк.</p>
                        <div class="scroll-table">
                            <table class="responsive">
                                <thead>
                                    <tr>
                                        <th>Статус</th>
                                        <th>Когда</th>
                                        <th>User</th>
                                        <th>Telegram</th>
                                        <th>Текст</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supportMessages as $sm)
                                        @php($su = $sm->user)
                                        @php($isRead = $supportHasReadAt && $sm->read_at !== null)
                                        <tr class="{{ $isRead ? 'support-row--read' : 'support-row--unread' }}">
                                            <td data-label="Статус">
                                                @if ($supportHasReadAt)
                                                    @if ($isRead)
                                                        <span class="support-badge support-badge--done">прочитано</span>
                                                        <div class="no" style="font-size:.65rem;margin-top:.25rem;">{{ $sm->read_at?->format('d.m H:i') }}</div>
                                                    @else
                                                        <span class="support-badge support-badge--new">новое</span>
                                                    @endif
                                                @else
                                                    <span class="no">—</span>
                                                @endif
                                            </td>
                                            <td class="no num" style="white-space:nowrap;" data-label="Когда">{{ $sm->created_at?->format('Y-m-d H:i') }}</td>
                                            <td class="num" data-label="User">
                                                @if ($su)
                                                    #{{ $su->id }} · {{ $su->first_name ?? '—' }}
                                                    @if ($su->username)
                                                        <span class="no">{{ '@'.$su->username }}</span>
                                                    @endif
                                                @else
                                                    <span class="no">удалён</span>
                                                @endif
                                            </td>
                                            <td class="num" data-label="TG">{{ $sm->telegram_id }}</td>
                                            <td data-label="Текст"><pre class="support-msg">{{ $sm->body }}</pre></td>
                                            <td data-label="Действия">
                                                <div class="support-actions">
                                                    @if ($supportHasReadAt)
                                                        @if (! $isRead)
                                                            <form class="inline" method="post" action="{{ route('admin.support.read', $sm) }}">
                                                                @csrf
                                                                <button type="submit" class="btn-mini btn-mini--primary">Прочитано</button>
                                                            </form>
                                                        @else
                                                            <form class="inline" method="post" action="{{ route('admin.support.unread', $sm) }}">
                                                                @csrf
                                                                <button type="submit" class="btn-mini">Не новое</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                    <form class="inline" method="post" action="{{ route('admin.support.destroy', $sm) }}" onsubmit="return confirm('Удалить обращение навсегда?');">
                                                        @csrf
                                                        <button type="submit" class="btn-mini btn-mini--danger">Удалить</button>
                                                    </form>
                                                </div>
                                            </td>
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
                    <h2><span class="section-idx">05</span> Пользователи</h2>
                    <span class="hint-inline">До {{ $rows->count() }} строк · серия: до 800 по ID при сортировке</span>
                </div>
                <div class="panel" style="padding-bottom:1.35rem;">
                    <form method="get" action="{{ url('/admin') }}" class="toolbar" id="user-filters">
                        <div class="field">
                            <label for="q">Поиск</label>
                            <input id="q" type="text" name="q" value="{{ $filters['q'] }}" placeholder="Имя, @username, id…" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="filter">Срез</label>
                            <select id="filter" name="filter" class="select-tg">
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
                            <select id="sort" name="sort" class="select-tg">
                                <option value="id_desc" @selected($filters['sort'] === 'id_desc')>ID ↓</option>
                                <option value="created_asc" @selected($filters['sort'] === 'created_asc')>Регистрация ↑</option>
                                <option value="checks_desc" @selected($filters['sort'] === 'checks_desc')>Чек-инов ↓</option>
                                <option value="points_week_desc" @selected($filters['sort'] === 'points_week_desc')>Баллов за 7 дн ↓</option>
                                <option value="last_check_desc" @selected($filters['sort'] === 'last_check_desc')>Последний чек ↓</option>
                                <option value="last_message_desc" @selected($filters['sort'] === 'last_message_desc')>Сообщение боту ↓</option>
                                <option value="streak_desc" @selected($filters['sort'] === 'streak_desc')>Серия ↓</option>
                                <option value="streak_asc" @selected($filters['sort'] === 'streak_asc')>Серия ↑</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn">Применить</button>
                        </div>
                    </form>

                    <p class="hint" style="margin-top:0;">На широком экране закреплены «Пульс» и «Имя» — листай вправо для уровня, цифр и дат. «Сообщение боту» — последнее обращение к боту: текст/фото/команда, правка сообщения или нажатие inline-кнопки (время кнопки — по серверу).</p>
                    <div class="table-users-shell">
                        <table class="responsive table-users">
                            <thead>
                                <tr>
                                    <th class="sticky-col--a">Пульс</th>
                                    <th class="sticky-col--b">Имя</th>
                                    <th>ID</th>
                                    <th>Telegram</th>
                                    <th>Уровень</th>
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
                                    <th>Сообщение боту</th>
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
                                        <td class="sticky-col--a" data-label="Пульс">
                                            <span class="pulse pulse-{{ $pulse }}"><span class="pulse-dot"></span>{{ $pulseLabels[$pulse] ?? $pulse }}</span>
                                            @if ($row['onboarding_hint'])
                                                <div class="no" style="font-size:.72rem;margin-top:.25rem;">{{ $row['onboarding_hint'] }}</div>
                                            @endif
                                        </td>
                                        <td class="sticky-col--b" data-label="Имя">{{ $u->first_name }} @if($u->username) <span class="no">{{ '@'.$u->username }}</span> @endif</td>
                                        <td class="num" data-label="ID">{{ $u->id }}</td>
                                        <td class="num" data-label="Telegram">
                                            {{ $u->telegram_id }}
                                            <button type="button" class="copy-tg" data-copy="{{ $u->telegram_id }}" title="Копировать">⎘</button>
                                        </td>
                                        <td class="tier-cell" data-label="Уровень">
                                            <span class="e">{{ $tier->emoji() }}</span><b>{{ $tier->labelRu() }}</b>
                                            <div class="no" style="font-size:.7rem;margin-top:.2rem;">серия {{ $row['streak'] }} · {{ $tier->criteriaRu() }}</div>
                                        </td>
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
                                        <td data-label="Сообщение боту">
                                            @if ($row['last_message_to_bot'])
                                                {{ $row['last_message_to_bot']->format('Y-m-d H:i') }}
                                                @if ($row['days_since_message_to_bot'] !== null)
                                                    <span class="no" style="font-size:.72rem;display:block;">
                                                        @if ($row['days_since_message_to_bot'] === 0)
                                                            сегодня
                                                        @elseif ($row['days_since_message_to_bot'] === 1)
                                                            вчера
                                                        @else
                                                            {{ $row['days_since_message_to_bot'] }} дн. назад
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
                                                    <p class="hint" style="margin-top:0;">Аккаунт и лог исходящих в чате — по максимуму.</p>
                                                    <label style="display:flex;gap:.4rem;align-items:flex-start;margin:.4rem 0;">
                                                        <input type="checkbox" name="confirm_delete" value="1" required>
                                                        <span>Подтверждаю</span>
                                                    </label>
                                                    <label style="display:flex;gap:.4rem;align-items:flex-start;margin:.4rem 0;">
                                                        <input type="hidden" name="notify_user" value="0">
                                                        <input type="checkbox" name="notify_user" value="1" checked>
                                                        <span>Уведомить в Telegram</span>
                                                    </label>
                                                    <button type="submit" class="btn btn-danger" style="margin-top:.4rem;">Удалить</button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="foot-note"><b>Пульс</b> — свежесть чек-инов и давность в боте (не игровой уровень). <b>Уровень</b> — серия закрытых дней подряд: 0–7 / 8–14 / 15–30 / 31–60 / 61+. <b>7 дн</b> — сумма баллов за 7 календарных дней.</p>
                </div>
            </section>
        </main>
    </div>
    <script>
        (function () {
            function tick() {
                var el = document.getElementById('admin-clock');
                if (!el) return;
                var d = new Date();
                el.textContent = d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
            tick();
            setInterval(tick, 1000);
        })();
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
