<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — вход в админку</title>
    <style>
        :root {
            --bg0: #0a0e14;
            --surface: #141c2b;
            --surface2: #1c2739;
            --text: #e8edf5;
            --muted: #8b9cb3;
            --accent: #4d9fff;
            --danger: #f07178;
            --warn: #f0b429;
            --border: rgba(255, 255, 255, .08);
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg0);
            background-image:
                radial-gradient(ellipse 100% 80% at 50% -30%, rgba(77, 159, 255, .22), transparent 55%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(199, 146, 234, .1), transparent 50%);
            color: var(--text);
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem 1.85rem 2rem;
            box-shadow: 0 16px 48px rgba(0, 0, 0, .4);
        }
        .brand {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: .35rem;
        }
        h1 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0 0 1.35rem;
            letter-spacing: -.02em;
        }
        label { display: block; margin-bottom: .45rem; font-size: .82rem; color: var(--muted); font-weight: 500; }
        input[type=password] {
            width: 100%;
            padding: .65rem .8rem;
            margin-bottom: 1.1rem;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font: inherit;
        }
        input[type=password]:focus {
            outline: none;
            border-color: rgba(77, 159, 255, .5);
            box-shadow: 0 0 0 3px rgba(77, 159, 255, .12);
        }
        button {
            width: 100%;
            background: linear-gradient(180deg, #5aa8ff, var(--accent));
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: .65rem 1.2rem;
            font: inherit;
            font-weight: 650;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(77, 159, 255, .3);
        }
        button:hover { filter: brightness(1.05); }
        .err {
            background: rgba(240, 113, 120, .12);
            border: 1px solid rgba(240, 113, 120, .35);
            color: var(--danger);
            padding: .75rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: .88rem;
        }
        .warn {
            background: rgba(240, 180, 41, .1);
            border: 1px solid rgba(240, 180, 41, .35);
            padding: .8rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: .85rem;
            line-height: 1.45;
            color: var(--text);
        }
        code { background: var(--surface2); padding: .12rem .4rem; border-radius: 6px; font-size: .82em; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">FitBot</div>
        <h1>Вход в админку</h1>
        @if (! $configured)
            <p class="warn">Задайте <code>FITBOT_ADMIN_PASSWORD</code> в <code>.env</code> на сервере.</p>
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
