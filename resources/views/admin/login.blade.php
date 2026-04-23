<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — вход в админку</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --text: #e7ecf3;
            --muted: #8b9cb3;
            --accent: #3d8bfd;
            --border: rgba(255,255,255,.08);
        }
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            max-width: 400px;
            margin: 4rem auto;
            padding: 0 1.25rem;
            background: var(--bg);
            color: var(--text);
        }
        h1 { font-size: 1.35rem; margin: 0 0 1.25rem; }
        label { display: block; margin-bottom: .4rem; font-size: .85rem; color: var(--muted); }
        input[type=password] {
            width: 100%;
            padding: .6rem .75rem;
            margin-bottom: 1rem;
            box-sizing: border-box;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font: inherit;
        }
        button {
            background: var(--accent);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: .55rem 1.2rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .err { color: #e85d6f; margin-bottom: 1rem; font-size: .9rem; }
        .warn { background: rgba(240,180,41,.12); border: 1px solid rgba(240,180,41,.35); padding: .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        code { background: var(--surface); padding: .1rem .35rem; border-radius: 4px; font-size: .85em; }
    </style>
</head>
<body>
    <h1>FitBot — админка</h1>
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
</body>
</html>
