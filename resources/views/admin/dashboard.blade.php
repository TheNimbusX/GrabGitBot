<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — админка</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 1rem 1.5rem 2rem; background: #f4f4f5; }
        h1 { font-size: 1.35rem; }
        .stats { display: flex; flex-wrap: wrap; gap: 1rem; margin: 1rem 0 1.5rem; }
        .stat { background: #fff; padding: 1rem 1.25rem; border-radius: 8px; min-width: 160px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .stat strong { display: block; font-size: 1.5rem; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); font-size: .9rem; }
        th, td { padding: .5rem .65rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; font-weight: 600; }
        tr:hover td { background: #fafafa; }
        .ok { color: #0a7; }
        .no { color: #999; }
        form.inline { display: inline; }
        .top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
    </style>
</head>
<body>
    <div class="top">
        <h1>Пользователи FitBot</h1>
        <form class="inline" method="post" action="{{ url('/admin/logout') }}">
            @csrf
            <button type="submit">Выйти</button>
        </form>
    </div>

    <div class="stats">
        <div class="stat">Всего <strong>{{ $stats['users_total'] }}</strong></div>
        <div class="stat">Завершили онбординг <strong>{{ $stats['users_completed_onboarding'] }}</strong></div>
        <div class="stat">В онбординге <strong>{{ $stats['users_in_onboarding'] }}</strong></div>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Telegram</th>
                    <th>Имя</th>
                    <th>Онбординг</th>
                    <th>Серия дней</th>
                    <th>Последний чек-ин</th>
                    <th>Создан</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($u = $row['user'])
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->telegram_id }}</td>
                        <td>{{ $u->first_name }} @if($u->username) ({{ '@'.$u->username }}) @endif</td>
                        <td class="{{ $row['onboarding_done'] ? 'ok' : 'no' }}">{{ $row['onboarding_done'] ? 'да' : 'нет' }}</td>
                        <td>{{ $row['streak'] }}</td>
                        <td>{{ $row['last_check'] ? $row['last_check']->format('Y-m-d') : '—' }}</td>
                        <td>{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
