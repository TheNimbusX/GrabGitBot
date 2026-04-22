<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — вход в админку</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 420px; margin: 4rem auto; padding: 0 1rem; }
        label { display: block; margin-bottom: .35rem; font-weight: 600; }
        input[type=password] { width: 100%; padding: .5rem; margin-bottom: 1rem; box-sizing: border-box; }
        button { padding: .55rem 1rem; cursor: pointer; }
        .err { color: #b00020; margin-bottom: 1rem; }
        .warn { background: #fff3cd; padding: .75rem; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <h1>FitBot /admin</h1>
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
