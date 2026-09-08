<?php
/**
 * Turkish (tr_TR) translation catalogue. THE SOURCE LANGUAGE IS ENGLISH.
 *
 * WordPress.org (GlotPress) generates translations from the English source, so
 * the strings in the code stay English and every localisation lives here.
 *
 * When you add a new string, add its translation to EVERY file under
 * `bin/translations/` — `bin/i18n.php` stops with an error on a missing or
 * unused entry, so a catalogue is never silently emitted incomplete.
 *
 * @package no404
 */

return array(
	'Settings' => 'Ayarlar',
	'no404 – Auto 404 Redirect' => 'no404 – Otomatik 404 Yönlendirme',
	'no404' => 'no404',
	'Connection' => 'Bağlantı',
	'Behaviour' => 'Davranış',
	'no404 address' => 'no404 adresi',
	'API key' => 'API anahtarı',
	'Redirecting' => 'Yönlendirme',
	'Permanent redirects' => 'Kalıcı yönlendirme',
	'Cache lifetime' => 'Önbellek süresi',
	'Timeout' => 'Zaman aşımı',
	'Excluded paths' => 'Hariç tutulacak yollar',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'API anahtarınızı no404 panelindeki site ayarları sayfasından alın. Anahtar yalnızca sunucu tarafında kullanılır, sitenizin kaynak kodunda görünmez.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'Önbellek, aynı ölü adresi tekrar tekrar çeken botların aylık olay kotanızı tüketmesini engeller. Süreyi kısaltmak kota tüketimini artırır.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'Bu alana dokunmanız gerekmez — varsayılan %s doğru adrestir. Yalnızca no404\'ü kendi sunucunuzda barındırıyorsanız değiştirin. Alanı boş bırakırsanız varsayılana döner.',
	'Paste your key' => 'Anahtarı yapıştırın',
	'Saved key: %s — leave this field empty to keep it.' => 'Kayıtlı anahtar: %s — değiştirmek istemiyorsanız alanı boş bırakın.',
	'Apply no404 redirects on pages that are not found' => 'Bulunamayan sayfalarda no404 yönlendirmesini uygula',
	'Send every match as a 301 (permanent)' => 'Tüm eşleşmeleri 301 (kalıcı) olarak gönder',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'Varsayılan olarak yalnızca elle tanımlanmış yönlendirmeler ve yüksek skorlu eşleşmeler 301 gönderilir; tahmine dayalı eşleşmeler 302 olur. 301 tarayıcıda kalıcı önbelleklenir ve geri alınamaz — bu kutuyu yalnızca kataloğunuzun eksiksiz olduğundan eminseniz işaretleyin.',
	'seconds' => 'saniye',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'Varsayılan 3600 (1 saat). En az 60, en çok 604800 (7 gün).',
	'milliseconds' => 'milisaniye',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'no404 bu süre içinde yanıt vermezse istek iptal edilir ve siteniz kendi 404 sayfasını gösterir. Ziyaretçi hiçbir zaman beklemez.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'Her satıra bir yol öneki. Bu öneklerle başlayan adresler no404\'e hiç sorulmaz. Statik dosyalar (.css, .js, .png …) ve /wp-admin, /wp-json gibi yollar zaten otomatik hariç tutulur.',
	'Testing…' => 'Test ediliyor…',
	'The test could not be completed. Reload the page and try again.' => 'Test tamamlanamadı. Sayfayı yenileyip tekrar deneyin.',
	'The plugin is not running yet: no API key has been entered.' => 'Eklenti henüz çalışmıyor: API anahtarı girilmemiş.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'Yönlendirme kapalı. 404 sayfaları olduğu gibi gösteriliyor.',
	'Active. Pages that are not found are redirected server-side.' => 'Etkin. Bulunamayan sayfalar sunucu tarafında yönlendiriliyor.',
	'Connection test' => 'Bağlantı testi',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'Kayıtlı ayarlarla no404\'e gerçek bir istek gönderir. Önce değişikliklerinizi kaydedin.',
	'Path to test' => 'Test edilecek yol',
	'Test the connection' => 'Bağlantıyı test et',
	'You do not have permission to do this.' => 'Yetkiniz yok.',
	'The client could not be initialised.' => 'İstemci başlatılamadı.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'Bağlantı başarılı. Bu yol için önerilen hedef: %1$s (kaynak: %2$s, skor: %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'Bağlantı başarılı. API anahtarınız geçerli; bu test yolu için bir eşleşme bulunamadı (beklenen davranış).',
	'No API key has been entered. Save your key and try again.' => 'API anahtarı girilmemiş. Anahtarı kaydedip tekrar deneyin.',
	'The no404 address is empty.' => 'no404 adresi boş.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'Geçersiz API anahtarı (404). no404 panelindeki site ayarlarından anahtarı kopyalayın; anahtarı yakın zamanda yenilediyseniz buraya da yenisini girin.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Erişim reddedildi (403): %s. Aboneliğiniz pasif olabilir, site izleme duraklatılmış olabilir veya hesabınız askıya alınmış olabilir.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Erişim reddedildi (403). Aboneliğiniz pasif olabilir, site izleme duraklatılmış olabilir veya hesabınız askıya alınmış olabilir.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'İstek limiti aşıldı ya da aylık olay kotanız doldu (429). Kotanızı no404 panelinden kontrol edin; kısa sürede çok istek gönderdiyseniz bir dakika sonra tekrar deneyin.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'Test yolu geçersiz (422). "/" ile başlayan bir yol girin.',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'no404 sunucusuna ulaşılamadı: %s. Sunucunuzun dışarıya HTTPS isteği yapabildiğinden emin olun.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'no404 sunucusuna ulaşılamadı. Sunucunuzun dışarıya HTTPS isteği yapabildiğinden emin olun.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'no404 tarafında geçici bir hata oluştu (5xx). Siteniz etkilenmez; birazdan tekrar deneyin.',
	'Unexpected response (HTTP %d).' => 'Beklenmeyen yanıt (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'no404 adresi geçerli bir http(s) URL olmalı. Önceki değer korundu. Varsayılana dönmek için alanı boş bırakın.',
);
