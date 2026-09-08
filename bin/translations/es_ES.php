<?php
/**
 * Español (es_ES) — catálogo de traducción. EL IDIOMA DE ORIGEN ES EL INGLÉS.
 *
 * Tratamiento de «tú», igual que la traducción del núcleo de WordPress en
 * es_ES. Se usan comillas angulares y signos de apertura (¿ ¡) donde procede.
 *
 * @package no404
 */

return array(
	'Settings' => 'Ajustes',
	'no404 – Auto 404 Redirect' => 'no404 – Redirección automática de 404',
	'no404' => 'no404',
	'Connection' => 'Conexión',
	'Behaviour' => 'Comportamiento',
	'no404 address' => 'Dirección de no404',
	'API key' => 'Clave de API',
	'Redirecting' => 'Redirección',
	'Permanent redirects' => 'Redirecciones permanentes',
	'Cache lifetime' => 'Duración de la caché',
	'Timeout' => 'Tiempo de espera',
	'Excluded paths' => 'Rutas excluidas',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'Consigue tu clave de API en la página de ajustes del sitio de tu escritorio de no404. La clave se usa solo en el servidor y nunca aparece en el código fuente de tu sitio.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'La caché evita que los bots que piden una y otra vez la misma URL muerta agoten tu cuota mensual de eventos. Cuanto menor sea la duración, mayor será el consumo de cuota.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'No deberías necesitar tocar esto: el valor por defecto, %s, es la dirección correcta. Cámbialo solo si alojas no404 en tu propio servidor. Deja el campo vacío para restaurar el valor por defecto.',
	'Paste your key' => 'Pega tu clave',
	'Saved key: %s — leave this field empty to keep it.' => 'Clave guardada: %s. Deja este campo vacío para conservarla.',
	'Apply no404 redirects on pages that are not found' => 'Aplicar las redirecciones de no404 en las páginas no encontradas',
	'Send every match as a 301 (permanent)' => 'Enviar todas las coincidencias como 301 (permanente)',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'Por defecto solo se envían como 301 las redirecciones definidas manualmente y las coincidencias con puntuación alta; las coincidencias dudosas se envían como 302. Los navegadores guardan una 301 en caché de forma permanente y no se puede deshacer: marca esta casilla solo si tienes la certeza de que tu catálogo está completo.',
	'seconds' => 'segundos',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'Por defecto 3600 (1 hora). Mínimo 60, máximo 604800 (7 días).',
	'milliseconds' => 'milisegundos',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'Si no404 no responde en ese tiempo, la petición se descarta y tu sitio muestra su propia página 404. Los visitantes nunca se quedan esperando.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'Un prefijo de ruta por línea. Las URL que empiecen por estos prefijos nunca se envían a no404. Los archivos estáticos (.css, .js, .png …) y rutas como /wp-admin y /wp-json se excluyen automáticamente.',
	'Testing…' => 'Probando…',
	'The test could not be completed. Reload the page and try again.' => 'No se ha podido completar la prueba. Recarga la página e inténtalo de nuevo.',
	'The plugin is not running yet: no API key has been entered.' => 'El plugin todavía no está funcionando: no se ha introducido ninguna clave de API.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'La redirección está desactivada. Las páginas 404 se muestran tal cual.',
	'Active. Pages that are not found are redirected server-side.' => 'Activo. Las páginas no encontradas se redirigen en el servidor.',
	'Connection test' => 'Prueba de conexión',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'Envía una petición real a no404 con tus ajustes guardados. Guarda antes tus cambios.',
	'Path to test' => 'Ruta que probar',
	'Test the connection' => 'Probar la conexión',
	'You do not have permission to do this.' => 'No tienes permisos para hacer esto.',
	'The client could not be initialised.' => 'No se ha podido inicializar el cliente.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'Conexión correcta. Destino sugerido para esta ruta: %1$s (origen: %2$s, puntuación: %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'Conexión correcta. Tu clave de API es válida; no se ha encontrado ninguna coincidencia para esta ruta de prueba, que es justo lo esperado.',
	'No API key has been entered. Save your key and try again.' => 'No se ha introducido ninguna clave de API. Guarda tu clave e inténtalo de nuevo.',
	'The no404 address is empty.' => 'La dirección de no404 está vacía.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'Clave de API no válida (404). Copia la clave desde la página de ajustes del sitio de tu escritorio de no404; si la has renovado hace poco, introduce aquí también la nueva.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Acceso denegado (403): %s. Puede que tu suscripción esté inactiva, que la monitorización de este sitio esté en pausa o que tu cuenta esté suspendida.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Acceso denegado (403). Puede que tu suscripción esté inactiva, que la monitorización de este sitio esté en pausa o que tu cuenta esté suspendida.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'Se ha superado el límite de peticiones o se ha agotado tu cuota mensual de eventos (429). Revisa tu cuota en el escritorio de no404; si has enviado muchas peticiones en poco tiempo, inténtalo de nuevo dentro de un minuto.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'La ruta de prueba no es válida (422). Introduce una ruta que empiece por «/».',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'No se ha podido contactar con el servidor de no404: %s. Asegúrate de que tu servidor tiene permiso para hacer peticiones HTTPS salientes.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'No se ha podido contactar con el servidor de no404. Asegúrate de que tu servidor tiene permiso para hacer peticiones HTTPS salientes.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'no404 ha tenido un error temporal (5xx). Tu sitio no se ve afectado; vuelve a intentarlo en un momento.',
	'Unexpected response (HTTP %d).' => 'Respuesta inesperada (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'La dirección de no404 debe ser una URL http(s) válida. Se ha conservado el valor anterior. Deja el campo vacío para restaurar el valor por defecto.',
);
