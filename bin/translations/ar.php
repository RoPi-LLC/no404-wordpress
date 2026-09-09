<?php
/**
 * Arabic (ar) translation catalogue. THE SOURCE LANGUAGE IS ENGLISH.
 *
 * Modern Standard Arabic, second person, as in the WordPress core translation.
 * Technical terms (URL, API, HTTP, 301, no404) keep their Latin script, which is
 * the convention in the Arabic WordPress translation.
 *
 * Note: this is a right-to-left language. WordPress flips the admin direction
 * automatically; the plugin needs no extra CSS.
 *
 * @package no404
 */

return array(
	'Settings' => 'الإعدادات',
	'no404 – Auto 404 Redirect' => 'no404 – إعادة توجيه 404 تلقائيًا',
	'no404' => 'no404',
	'Connection' => 'الاتصال',
	'Behaviour' => 'السلوك',
	'no404 address' => 'عنوان no404',
	'API key' => 'مفتاح API',
	'Redirecting' => 'إعادة التوجيه',
	'Permanent redirects' => 'إعادة توجيه دائمة',
	'Cache lifetime' => 'مدة التخزين المؤقت',
	'Timeout' => 'مهلة الانتظار',
	'Excluded paths' => 'المسارات المستثناة',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'احصل على مفتاح API من صفحة إعدادات الموقع في لوحة تحكّم no404. يُستخدم المفتاح على الخادم فقط ولا يظهر أبدًا في الشيفرة المصدرية لموقعك.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'يمنع التخزين المؤقت الروبوتات التي تطلب العنوان المعطوب نفسه مرارًا وتكرارًا من استهلاك حصّتك الشهرية من الأحداث. وكلّما قصُرت المدة زاد استهلاك الحصّة.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'لا حاجة عادةً إلى تغيير هذا الحقل، فالقيمة الافتراضية %s هي العنوان الصحيح. غيّرها فقط إذا كنت تستضيف no404 على خادمك الخاص. اترك الحقل فارغًا لاستعادة القيمة الافتراضية.',
	'Paste your key' => 'ألصِق مفتاحك هنا',
	'Saved key: %s — leave this field empty to keep it.' => 'المفتاح المحفوظ: %s — اترك هذا الحقل فارغًا للإبقاء عليه.',
	'Apply no404 redirects on pages that are not found' => 'تطبيق إعادة توجيه no404 على الصفحات غير الموجودة',
	'Send every match as a 301 (permanent)' => 'إرسال كل تطابق بالرمز 301 (دائم)',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'افتراضيًا لا تُرسَل بالرمز 301 إلا عمليات إعادة التوجيه المُعرَّفة يدويًا والتطابقات ذات الدرجة العالية، أما التطابقات الظنّية فتُرسَل بالرمز 302. تحفظ المتصفحات الرمز 301 حفظًا دائمًا ولا يمكن التراجع عنه، لذا لا تفعّل هذا الخيار إلا إذا كنت واثقًا من اكتمال فهرس موقعك.',
	'seconds' => 'ثانية',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'القيمة الافتراضية 3600 (ساعة واحدة). الحد الأدنى 60 والحد الأقصى 604800 (7 أيام).',
	'milliseconds' => 'مِلّي ثانية',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'إذا لم يستجب no404 خلال هذه المدة يُلغى الطلب ويعرض موقعك صفحة 404 الخاصة به. ولا يُترك الزائر منتظرًا في أي حال.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'بادئة مسار واحدة في كل سطر. لا تُرسَل إلى no404 أي عناوين تبدأ بهذه البادئات. أما الملفات الثابتة (‎.css و‎.js و‎.png …) والمسارات مثل ‎/wp-admin و‎/wp-json فتُستثنى تلقائيًا.',
	'Testing…' => 'جارٍ الاختبار…',
	'The test could not be completed. Reload the page and try again.' => 'تعذّر إتمام الاختبار. أعد تحميل الصفحة وحاول مرة أخرى.',
	'The plugin is not running yet: no API key has been entered.' => 'الإضافة لا تعمل بعد: لم يُدخَل أي مفتاح API.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'إعادة التوجيه معطّلة. تُعرض صفحات 404 كما هي.',
	'Active. Pages that are not found are redirected server-side.' => 'مُفعَّلة. تُعاد توجيه الصفحات غير الموجودة على مستوى الخادم.',
	'Connection test' => 'اختبار الاتصال',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'يرسل طلبًا حقيقيًا إلى no404 باستخدام إعداداتك المحفوظة. احفظ تغييراتك أولًا.',
	'Path to test' => 'المسار المراد اختباره',
	'Test the connection' => 'اختبار الاتصال',
	'You do not have permission to do this.' => 'لا تملك صلاحية القيام بذلك.',
	'The client could not be initialised.' => 'تعذّر تهيئة العميل.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'نجح الاتصال. الوجهة المقترحة لهذا المسار: %1$s (المصدر: %2$s، الدرجة: %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'نجح الاتصال. مفتاح API صالح، ولم يُعثر على تطابق لمسار الاختبار هذا وهو المتوقّع.',
	'No API key has been entered. Save your key and try again.' => 'لم يُدخَل أي مفتاح API. احفظ مفتاحك وحاول مرة أخرى.',
	'The no404 address is empty.' => 'عنوان no404 فارغ.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'مفتاح API غير صالح (404). انسخ المفتاح من صفحة إعدادات الموقع في لوحة تحكّم no404، وإن كنت قد جدّدت المفتاح مؤخرًا فأدخِل الجديد هنا أيضًا.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'رُفض الوصول (403): %s. قد يكون اشتراكك غير نشط، أو تكون مراقبة هذا الموقع موقوفة مؤقتًا، أو يكون حسابك معلّقًا.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'رُفض الوصول (403). قد يكون اشتراكك غير نشط، أو تكون مراقبة هذا الموقع موقوفة مؤقتًا، أو يكون حسابك معلّقًا.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'تجاوزت حد الطلبات، أو نفدت حصّتك الشهرية من الأحداث (429). راجع حصّتك في لوحة تحكّم no404، وإن كنت قد أرسلت طلبات كثيرة في وقت قصير فحاول مرة أخرى بعد دقيقة.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'مسار الاختبار غير صالح (422). أدخِل مسارًا يبدأ بالشَّرطة المائلة «/».',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'تعذّر الوصول إلى خادم no404: %s. تأكّد من أن خادمك مسموح له بإجراء طلبات HTTPS صادرة.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'تعذّر الوصول إلى خادم no404. تأكّد من أن خادمك مسموح له بإجراء طلبات HTTPS صادرة.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'واجه no404 خطأً مؤقتًا (5xx). موقعك غير متأثر بذلك، وحاول مرة أخرى بعد قليل.',
	'The no404 address redirects somewhere else. Enter %s in the "no404 address" field, save, and test again.' => 'عنوان no404 يعيد التوجيه إلى مكان آخر. أدخل %s في حقل «عنوان no404»، ثم احفظ وأعد الاختبار.',
	'The no404 address redirects somewhere else, so no answer could be read. Check the address in the settings — it is usually the www form of the domain.' => 'عنوان no404 يعيد التوجيه إلى مكان آخر، لذلك تعذّرت قراءة أي رد. تحقّق من العنوان في الإعدادات — عادةً ما يكون صيغة النطاق مع www.',
	'Unexpected response (HTTP %d).' => 'استجابة غير متوقّعة (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'يجب أن يكون عنوان no404 رابط http(s) صالحًا. جرى الإبقاء على القيمة السابقة. اترك الحقل فارغًا لاستعادة القيمة الافتراضية.',
);
