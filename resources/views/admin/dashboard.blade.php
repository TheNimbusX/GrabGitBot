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

    @if (session('broadcast_status'))
        <p style="background:#d4edda;padding:.75rem 1rem;border-radius:8px;">{{ session('broadcast_status') }}</p>
    @endif

    @if (session('admin_status'))
        <p style="background:#d1ecf1;padding:.75rem 1rem;border-radius:8px;">{{ session('admin_status') }}</p>
    @endif

    @if ($errors->has('delete'))
        <p style="background:#f8d7da;padding:.75rem 1rem;border-radius:8px;">{{ $errors->first('delete') }}</p>
    @endif

    <div style="background:#fff;padding:1rem 1.25rem;border-radius:8px;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:640px;">
        <h2 style="font-size:1.05rem;margin:0 0 .75rem;">Рассылка в Telegram</h2>
        <p style="margin:0 0 .75rem;color:#555;font-size:.9rem;">Текст уйдёт <strong>всем, кто завершил онбординг</strong> (план FitBot или режим «свой план»). Без HTML, до ~4090 символов.</p>
        <form method="post" action="{{ url('/admin/broadcast') }}">
            @csrf
            <textarea name="message" rows="5" style="width:100%;box-sizing:border-box;font-family:inherit;" required placeholder="Текст сообщения…">{{ old('message') }}</textarea>
            <button type="submit" style="margin-top:.5rem;">Отправить всем</button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Telegram</th>
                    <th>Имя</th>
                    <th>Возраст</th>
                    <th>Режим</th>
                    <th>Онбординг</th>
                    <th>Серия дней</th>
                    <th>Последний чек-ин</th>
                    <th>Создан</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($u = $row['user'])
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->telegram_id }}</td>
                        <td>{{ $u->first_name }} @if($u->username) ({{ '@'.$u->username }}) @endif</td>
                        <td>{{ $u->age ?? '—' }}</td>
                        <td>@if($u->plan_mode === 'discipline') свой план @elseif($u->plan_mode === 'full' || $u->daily_calories_target) FitBot @else — @endif</td>
                        <td class="{{ $row['onboarding_done'] ? 'ok' : 'no' }}">{{ $row['onboarding_done'] ? 'да' : 'нет' }}</td>
                        <td>{{ $row['streak'] }}</td>
                        <td>{{ $row['last_check'] ? $row['last_check']->format('Y-m-d') : '—' }}</td>
                        <td>{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                        <td style="white-space:nowrap;">
                            <details style="max-width:220px;">
                                <summary style="cursor:pointer;color:#06c;">Удалить…</summary>
                                <form method="post" action="{{ route('admin.user.destroy', $u) }}" style="margin-top:.5rem;padding:.5rem;background:#fafafa;border-radius:6px;font-size:.85rem;">
                                    @csrf
                                    <p style="margin:0 0 .5rem;color:#555;">Удаляет аккаунт (чек-ины, фото, анкета). Удаляет из чата сообщения <b>бота</b>, которые бот успел запомнить (с момента обновления). Сообщения пользователя Telegram не трогает.</p>
                                    <label style="display:flex;gap:.35rem;align-items:flex-start;margin:.35rem 0;">
                                        <input type="checkbox" name="confirm_delete" value="1" required>
                                        <span>Подтверждаю удаление</span>
                                    </label>
                                    <label style="display:flex;gap:.35rem;align-items:flex-start;margin:.35rem 0;">
                                        <input type="hidden" name="notify_user" value="0">
                                        <input type="checkbox" name="notify_user" value="1" checked>
                                        <span>Отправить в Telegram короткое уведомление</span>
                                    </label>
                                    <button type="submit" style="margin-top:.35rem;background:#c0392b;color:#fff;border:0;padding:.35rem .65rem;border-radius:4px;cursor:pointer;">Удалить аккаунт и очистить сообщения бота</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
