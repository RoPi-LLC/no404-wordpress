<?php
/**
 * Русский (ru_RU) — каталог перевода. ЯЗЫК ОРИГИНАЛА — АНГЛИЙСКИЙ.
 *
 * Обращение на «вы» со строчной буквы — как в переводе ядра WordPress на
 * ru_RU. Кавычки-ёлочки, тире — длинное (—).
 *
 * @package no404
 */

return array(
	'Settings' => 'Настройки',
	'no404 – Auto 404 Redirect' => 'no404 — автоматическое перенаправление 404',
	'no404' => 'no404',
	'Connection' => 'Подключение',
	'Behaviour' => 'Поведение',
	'no404 address' => 'Адрес no404',
	'API key' => 'Ключ API',
	'Redirecting' => 'Перенаправление',
	'Permanent redirects' => 'Постоянные перенаправления',
	'Cache lifetime' => 'Время жизни кэша',
	'Timeout' => 'Тайм-аут',
	'Excluded paths' => 'Исключённые пути',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'Ключ API можно получить на странице настроек сайта в консоли no404. Ключ используется только на стороне сервера и никогда не попадает в исходный код вашего сайта.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'Кэш не даёт ботам, которые снова и снова запрашивают один и тот же мёртвый URL, израсходовать месячную квоту событий. Чем меньше время жизни кэша, тем быстрее расходуется квота.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'Этот параметр менять не нужно — значение по умолчанию, %s, и есть правильный адрес. Меняйте его, только если размещаете no404 на своём сервере. Оставьте поле пустым, чтобы вернуть значение по умолчанию.',
	'Paste your key' => 'Вставьте ключ',
	'Saved key: %s — leave this field empty to keep it.' => 'Сохранённый ключ: %s — оставьте это поле пустым, чтобы сохранить его.',
	'Apply no404 redirects on pages that are not found' => 'Применять перенаправления no404 на ненайденных страницах',
	'Send every match as a 301 (permanent)' => 'Отправлять все совпадения как 301 (постоянное)',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'По умолчанию как 301 отправляются только заданные вручную перенаправления и совпадения с высокой оценкой; неуверенные совпадения отправляются как 302. Браузеры кэшируют 301 навсегда, и отменить его нельзя — отмечайте этот пункт, только если уверены, что ваш каталог полон.',
	'seconds' => 'секунд',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'По умолчанию 3600 (1 час). Минимум 60, максимум 604800 (7 дней).',
	'milliseconds' => 'миллисекунд',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'Если no404 не ответит за это время, запрос отбрасывается и сайт показывает собственную страницу 404. Посетителям никогда не приходится ждать.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'По одному префиксу пути в строке. URL, начинающиеся с этих префиксов, никогда не отправляются в no404. Статические файлы (.css, .js, .png …) и пути вроде /wp-admin и /wp-json исключаются автоматически.',
	'Testing…' => 'Проверка…',
	'The test could not be completed. Reload the page and try again.' => 'Не удалось завершить проверку. Перезагрузите страницу и попробуйте снова.',
	'The plugin is not running yet: no API key has been entered.' => 'Плагин ещё не работает: не введён ключ API.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'Перенаправление выключено. Страницы 404 показываются как есть.',
	'Active. Pages that are not found are redirected server-side.' => 'Активно. Ненайденные страницы перенаправляются на стороне сервера.',
	'Connection test' => 'Проверка подключения',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'Отправляет в no404 настоящий запрос с вашими сохранёнными настройками. Сначала сохраните изменения.',
	'Path to test' => 'Путь для проверки',
	'Test the connection' => 'Проверить подключение',
	'You do not have permission to do this.' => 'У вас нет прав для этого действия.',
	'The client could not be initialised.' => 'Не удалось инициализировать клиент.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'Подключение успешно. Предлагаемая цель для этого пути: %1$s (источник: %2$s, оценка: %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'Подключение успешно. Ключ API действителен; для этого тестового пути совпадений не найдено — так и должно быть.',
	'No API key has been entered. Save your key and try again.' => 'Ключ API не введён. Сохраните ключ и попробуйте снова.',
	'The no404 address is empty.' => 'Адрес no404 пуст.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'Недействительный ключ API (404). Скопируйте ключ со страницы настроек сайта в консоли no404; если вы недавно обновляли ключ, введите здесь новый.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Доступ запрещён (403): %s. Возможно, ваша подписка неактивна, наблюдение за этим сайтом приостановлено или ваша учётная запись заблокирована.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Доступ запрещён (403). Возможно, ваша подписка неактивна, наблюдение за этим сайтом приостановлено или ваша учётная запись заблокирована.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'Превышен лимит запросов или исчерпана месячная квота событий (429). Проверьте квоту в консоли no404; если вы отправили много запросов за короткое время, повторите попытку через минуту.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'Тестовый путь недопустим (422). Введите путь, начинающийся с «/».',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'Не удалось связаться с сервером no404: %s. Убедитесь, что вашему серверу разрешены исходящие HTTPS-запросы.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'Не удалось связаться с сервером no404. Убедитесь, что вашему серверу разрешены исходящие HTTPS-запросы.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'На стороне no404 произошла временная ошибка (5xx). На вашем сайте это не сказывается; повторите попытку чуть позже.',
	'Unexpected response (HTTP %d).' => 'Неожиданный ответ (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'Адрес no404 должен быть корректным http(s)-адресом. Прежнее значение сохранено. Оставьте поле пустым, чтобы вернуть значение по умолчанию.',
);
