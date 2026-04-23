<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — вход</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050608;
            --text: #f2f5fa;
            --muted: #8b93a8;
            --cyan: #22d3ee;
            --rose: #fb7185;
            --amber: #fbbf24;
            --border: rgba(255, 255, 255, .1);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Outfit", system-ui, sans-serif;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--void);
            position: relative;
        }
        .bg {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 0%, rgba(34, 211, 238, .2), transparent 50%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(167, 139, 250, .12), transparent 45%);
            pointer-events: none;
        }
        .card {
            position: relative;
            width: 100%;
            max-width: 420px;
            padding: 2rem 2rem 2.25rem;
            border-radius: 20px;
            background: rgba(18, 22, 30, .82);
            border: 1px solid var(--border);
            box-shadow: 0 24px 80px rgba(0, 0, 0, .55), 0 0 0 1px rgba(34, 211, 238, .08);
        }
        .mark {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--cyan), #0891b2);
            display: grid;
            place-items: center;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--void);
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 28px rgba(34, 211, 238, .4);
        }
        .kicker {
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--cyan);
            margin: 0 0 .35rem;
        }
        h1 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -.03em;
            margin: 0 0 1.5rem;
            background: linear-gradient(120deg, #fff 40%, var(--cyan));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        label {
            display: block;
            margin-bottom: .4rem;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        input[type=password] {
            width: 100%;
            padding: .75rem .9rem;
            margin-bottom: 1.2rem;
            background: rgba(0, 0, 0, .35);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font: inherit;
            font-family: "IBM Plex Mono", monospace;
            font-size: .95rem;
        }
        input[type=password]:focus {
            outline: none;
            border-color: rgba(34, 211, 238, .5);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, .12);
        }
        button {
            width: 100%;
            padding: .75rem 1.2rem;
            border: 0;
            border-radius: 12px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(135deg, #22d3ee, #06b6d4);
            color: var(--void);
            box-shadow: 0 6px 24px rgba(34, 211, 238, .35);
            transition: filter .15s;
        }
        button:hover { filter: brightness(1.06); }
        .err {
            background: rgba(251, 113, 133, .1);
            border: 1px solid rgba(251, 113, 133, .35);
            color: var(--rose);
            padding: .85rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: .88rem;
        }
        .warn {
            background: rgba(251, 191, 36, .08);
            border: 1px solid rgba(251, 191, 36, .3);
            padding: .9rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: .85rem;
            line-height: 1.45;
            color: var(--text);
        }
        code { font-family: "IBM Plex Mono", monospace; font-size: .82em; background: rgba(0, 0, 0, .35); padding: .15rem .4rem; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    <div class="card">
        <div class="mark">F</div>
        <p class="kicker">FitBot</p>
        <h1>Вход в Control</h1>
        @if (! $configured)
            <p class="warn">Задайте <code>FITBOT_ADMIN_PASSWORD</code> в <code>.env</code>.</p>
        @endif
        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ url('/admin/login') }}">
            @csrf
            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="submit">Войти</button>
        </form>
    </div>
</body>
</html>
