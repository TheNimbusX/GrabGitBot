<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FitBot — админка</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --surface2: #243044;
            --text: #e7ecf3;
            --muted: #8b9cb3;
            --accent: #3d8bfd;
            --ok: #3ecf8e;
            --warn: #f0b429;
            --danger: #e85d6f;
            --border: rgba(255,255,255,.08);
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            margin: 0;
            padding: 1.25rem 1.5rem 2.5rem;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0; letter-spacing: -.02em; }
        h2 { font-size: 1rem; font-weight: 600; margin: 0 0 .75rem; color: var(--text); }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .badge {
            font-size: .75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.1rem;
        }
        .stat strong {
            display: block;
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.1;
            color: #fff;
        }
        .stat .sub { font-size: .78rem; color: var(--muted); margin-top: .35rem; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.15rem;
            margin-bottom: 1.25rem;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            align-items: flex-end;
            margin-bottom: .85rem;
        }
        .toolbar label { font-size: .78rem; color: var(--muted); display: block; margin-bottom: .2rem; }
        .toolbar input[type="text"], .toolbar select {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: .45rem .65rem;
            font: inherit;
            min-width: 200px;
        }
        .toolbar button, .top button {
            background: var(--accent);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: .5rem 1rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        .top button { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
        .funnel {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            font-size: .82rem;
        }
        .funnel span {
            background: var(--surface2);
            padding: .35rem .6rem;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .funnel .n { color: var(--accent); font-weight: 700; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            font-size: .82rem;
        }
        th, td { padding: .55rem .65rem; text-align: left; border-bottom: 1px solid var(--border); }
        th {
            background: var(--surface2);
            font-weight: 600;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
        }
        th a { color: var(--accent); text-decoration: none; }
        th a:hover { text-decoration: underline; }
        tr:hover td { background: rgba(61,139,253,.06); }
        .ok { color: var(--ok); }
        .no { color: var(--muted); }
        .num { font-variant-numeric: tabular-nums; }
        .flash-ok { background: rgba(62,207,142,.15); border: 1px solid rgba(62,207,142,.35); padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .flash-info { background: rgba(61,139,253,.12); border: 1px solid rgba(61,139,253,.3); padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .flash-err { background: rgba(232,93,111,.12); border: 1px solid rgba(232,93,111,.35); padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        form.inline { display: inline; }
        textarea { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 8px; padding: .6rem; font: inherit; }
        .hint { font-size: .8rem; color: var(--muted); margin: 0 0 .65rem; }
        .scroll { overflow-x: auto; border-radius: 10px; }
        details summary { cursor: pointer; color: var(--accent); }
        details form { margin-top: .5rem; padding: .6rem; background: var(--surface2); border-radius: 8px; font-size: .8rem; border: 1px solid var(--border); }
        .del-btn { background: var(--danger) !important; }
    </style>
</head>
<body>
    <div class="top">
        <div>
            <div class="badge">FitBot</div>
            <h1>Панель</h1>
        </div>
        <form class="inline" method="post" action="{{ url('/admin/logout') }}">
            @csrf
            <button type="submit">Выйти</button>
        </form>
    </div>

    <div class="stats">
        <div class="stat">
            <strong>{{ $stats['users_total'] }}</strong>
            пользователей
            <div class="sub">Новых за 7 дней: {{ $stats['users_new_7d'] }}</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['users_completed_onboarding'] }}</strong>
            онбординг готов
            <div class="sub">В процессе: {{ $stats['users_in_onboarding'] }}</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['users_active_7d'] }}</strong>
            активны 7 дней
            <div class="sub">Был завершённый чек-ин</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['checks_today'] }}</strong>
            чек-инов сегодня
            <div class="sub">Всего завершённых: {{ $stats['checks_completed_total'] }}</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['checks_week'] }}</strong>
            чек-инов (календ. неделя)
            <div class="sub">Баллов за тот же период: {{ $stats['points_week_all_users'] }}</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['avg_score_per_check_week'] ?? '—' }}</strong>
            ср. балл / чек
            <div class="sub">Только завершённые, календарная неделя</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['photos_total'] }}</strong>
            фото в базе
            <div class="sub">Лог исходящих в TG: {{ $stats['telegram_logged_messages'] }}</div>
        </div>
        <div class="stat">
            <strong>{{ $stats['plan_mode_full'] }}</strong>
            план FitBot
            <div class="sub">Свой режим: {{ $stats['plan_mode_discipline'] }} · legacy ккал: {{ $stats['plan_legacy_calories'] }}</div>
        </div>
    </div>

    @if (session('broadcast_status'))
        <p class="flash-ok">{{ session('broadcast_status') }}</p>
    @endif
    @if (session('admin_status'))
        <p class="flash-info">{{ session('admin_status') }}</p>
    @endif
    @if ($errors->has('delete'))
        <p class="flash-err">{{ $errors->first('delete') }}</p>
    @endif

    <div class="panel">
        <h2>Воронка онбординга (кто «застрял»)</h2>
        <p class="hint">Только пользователи с непустым шагом. Сортировка по количеству.</p>
        @if (count($onboardingFunnel) === 0)
            <p class="hint" style="margin:0;">Никто не в онбординге — ок.</p>
        @else
            <div class="funnel">
                @foreach ($onboardingFunnel as $f)
                    <span>{{ $f['label'] }}: <span class="n">{{ $f['count'] }}</span></span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="panel" style="max-width:720px;">
        <h2>Рассылка в Telegram</h2>
        <p class="hint">Без HTML, до ~4090 символов. Сегмент — кому уходит (по умолчанию все с готовым онбордингом).</p>
        <form method="post" action="{{ url('/admin/broadcast') }}">
            @csrf
            <div class="toolbar" style="margin-bottom:.5rem;">
                <div>
                    <label>Сегмент</label>
                    <select name="segment">
                        <option value="all_completed" @selected(old('segment', 'all_completed') === 'all_completed')>Онбординг готов — все</option>
                        <option value="in_onboarding" @selected(old('segment') === 'in_onboarding')>Застряли в онбординге</option>
                        <option value="active_7d" @selected(old('segment') === 'active_7d')>Активны 7 дней (был чек-ин)</option>
                        <option value="inactive_7d" @selected(old('segment') === 'inactive_7d')>Готовы, но 7+ дней без чек-ина</option>
                        <option value="inactive_14d" @selected(old('segment') === 'inactive_14d')>Готовы, но 14+ дней без чек-ина</option>
                        <option value="new_7d" @selected(old('segment') === 'new_7d')>Новые за 7 дней (любой этап)</option>
                        <option value="completed_never_checked" @selected(old('segment') === 'completed_never_checked')>Онбординг готов, ни одного чек-ина</option>
                        <option value="streak_3_plus" @selected(old('segment') === 'streak_3_plus')>Серия чек-инов ≥ 3 дня</option>
                        <option value="plan_full" @selected(old('segment') === 'plan_full')>Режим плана FitBot</option>
                        <option value="discipline_only" @selected(old('segment') === 'discipline_only')>Только дисциплина (свой план)</option>
                        <option value="low_activity_14d" @selected(old('segment') === 'low_activity_14d')>≤ 1 чек-ин за 14 дней</option>
                    </select>
                </div>
            </div>
            <textarea name="message" rows="5" required placeholder="Текст…">{{ old('message') }}</textarea>
            <button type="submit" style="margin-top:.5rem;">Отправить</button>
        </form>
    </div>

    <div class="panel">
        <h2>Пользователи</h2>
        <form method="get" action="{{ url('/admin') }}" class="toolbar">
            <div>
                <label>Поиск</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Имя, @username, telegram id…">
            </div>
            <div>
                <label>Фильтр</label>
                <select name="filter">
                    <option value="all" @selected($filters['filter'] === 'all')>Все</option>
                    <option value="completed" @selected($filters['filter'] === 'completed')>Онбординг готов</option>
                    <option value="onboarding" @selected($filters['filter'] === 'onboarding')>В онбординге</option>
                    <option value="active7" @selected($filters['filter'] === 'active7')>Активны 7 дней</option>
                    <option value="inactive7" @selected($filters['filter'] === 'inactive7')>Готовы, но 7 дней без чек-ина</option>
                    <option value="new7" @selected($filters['filter'] === 'new7')>Новые за 7 дней</option>
                </select>
            </div>
            <div>
                <label>Сортировка таблицы</label>
                <select name="sort">
                    <option value="id_desc" @selected($filters['sort'] === 'id_desc')>ID ↓</option>
                    <option value="created_asc" @selected($filters['sort'] === 'created_asc')>Регистрация ↑</option>
                    <option value="checks_desc" @selected($filters['sort'] === 'checks_desc')>Чек-инов ↓</option>
                    <option value="points_week_desc" @selected($filters['sort'] === 'points_week_desc')>Баллов за 7 дн ↓</option>
                    <option value="last_check_desc" @selected($filters['sort'] === 'last_check_desc')>Последний чек ↓</option>
                    <option value="streak_desc" @selected($filters['sort'] === 'streak_desc')>Серия ↓</option>
                    <option value="streak_asc" @selected($filters['sort'] === 'streak_asc')>Серия ↑</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit">Применить</button>
            </div>
        </form>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Telegram</th>
                        <th>Имя</th>
                        <th class="num">Возраст</th>
                        <th>Режим</th>
                        <th>Онбординг</th>
                        <th class="num">Чек-ины</th>
                        <th class="num">Баллы ∑</th>
                        <th class="num">7 дн</th>
                        <th class="num">Ср/чек</th>
                        <th class="num">Фото</th>
                        <th class="num">Серия</th>
                        <th>Последний чек</th>
                        <th>Ккал / цели</th>
                        <th>Создан</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($u = $row['user'])
                        <tr>
                            <td class="num">{{ $u->id }}</td>
                            <td class="num">{{ $u->telegram_id }}</td>
                            <td>{{ $u->first_name }} @if($u->username) <span class="no">{{ '@'.$u->username }}</span> @endif</td>
                            <td class="num">{{ $u->age ?? '—' }}</td>
                            <td>
                                @if($u->plan_mode === 'discipline')
                                    свой план
                                @elseif($u->plan_mode === 'full' || $u->daily_calories_target)
                                    FitBot
                                @else
                                    —
                                @endif
                            </td>
                            <td class="{{ $row['onboarding_done'] ? 'ok' : 'no' }}">{{ $row['onboarding_done'] ? 'да' : 'нет' }}</td>
                            <td class="num">{{ $row['completed_checks'] }}</td>
                            <td class="num">{{ $row['lifetime_points'] }}</td>
                            <td class="num">{{ $row['week_points'] }}</td>
                            <td class="num">{{ $row['avg_check_score'] ?? '—' }}</td>
                            <td class="num">{{ $row['photos_count'] }}</td>
                            <td class="num">{{ $row['streak'] }}</td>
                            <td>{{ $row['last_check'] ? $row['last_check']->format('Y-m-d') : '—' }}</td>
                            <td style="max-width:140px;font-size:.78rem;" class="no">
                                @if($u->daily_calories_target)
                                    {{ $u->daily_calories_target }} ккал
                                @elseif($u->isDisciplineOnlyMode())
                                    вода {{ $u->water_goal_ml ?? '—' }} · сон {{ $u->sleep_target_hours ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="no">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                            <td style="white-space:nowrap;">
                                <details style="max-width:240px;">
                                    <summary>Удалить…</summary>
                                    <form method="post" action="{{ route('admin.user.destroy', $u) }}">
                                        @csrf
                                        <p class="hint" style="margin-top:0;">Удаляет аккаунт и запомненные сообщения бота в чате.</p>
                                        <label style="display:flex;gap:.35rem;align-items:flex-start;margin:.35rem 0;">
                                            <input type="checkbox" name="confirm_delete" value="1" required>
                                            <span>Подтверждаю</span>
                                        </label>
                                        <label style="display:flex;gap:.35rem;align-items:flex-start;margin:.35rem 0;">
                                            <input type="hidden" name="notify_user" value="0">
                                            <input type="checkbox" name="notify_user" value="1" checked>
                                            <span>Уведомить в Telegram</span>
                                        </label>
                                        <button type="submit" class="del-btn" style="margin-top:.35rem;">Удалить</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint" style="margin:.75rem 0 0;">Показано до {{ $rows->count() }} строк. Колонка «7 дн» — сумма баллов за последние 7 календарных дней (не то же самое, что «календ. неделя» в плитках). Сортировка по серии: среди последних 800 пользователей по ID.</p>
    </div>
</body>
</html>
