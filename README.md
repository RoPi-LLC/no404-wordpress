# no404 – Auto 404 Redirect (WordPress eklentisi)

404'e düşen ziyaretçiyi, no404 kataloğundaki en uygun adrese **sunucu tarafında
gerçek bir 301/302 ile** yönlendirir.

Kullanıcıya dönük anlatım: [readme.txt](readme.txt) (WordPress.org biçimi).
Bu dosya geliştiriciler içindir.

---

## Neden eklenti (snippet neden yetmiyor)

|  | JS snippet | Bu eklenti |
| --- | --- | --- |
| Yönlendirme türü | `location.replace` — HTTP durumu **404 kalır** | Gerçek **301/302** |
| SEO | Google 404 görür, link değeri aktarılmaz | Link değeri hedefe aktarılır |
| Botlar | JS çalıştırmayan bot yönlenmez | Yönlenir |
| Kurulum | Temaya elle kod yapıştırma | Eklentiyi kur, anahtarı gir |
| API anahtarı | Sayfa kaynağında **görünür** | Yalnızca sunucuda |

Eklentinin varlık sebebi **301**'dir. Sunucu tarafında yapılmayacaksa eklenti
yazmanın anlamı yoktur.

---

## Mimari

```
no404.php                       bootstrap: sabitler, wiring, multisite bağlamı
includes/
  interface-no404-http.php      HTTP taşıma sözleşmesi
  interface-no404-cache.php     önbellek sözleşmesi
  class-no404-client.php        ÇEKİRDEK — davranış sözleşmesi (SAF PHP)
  class-no404-wp-http.php       wp_remote_get adaptörü
  class-no404-wp-cache.php      transient adaptörü
  class-no404-options.php       ayar deposu: varsayılan, sanitize, maskeleme
  class-no404-redirector.php    template_redirect kancası
  class-no404-admin.php         Ayarlar → no404 + bağlantı testi (AJAX)
assets/admin.js                 bağlantı testi (bağımlılıksız)
languages/                      .pot + 7 dil (-tr_TR/-de_DE/-fr_FR/-es_ES/-ru_RU/-hi_IN/-ar)
tests/test-core.php             çekirdek davranış testleri (WordPress gerekmez)
uninstall.php                   option + transient temizliği (multisite dahil)
```

### `No404_Client` — dokunurken dikkat

`includes/class-no404-client.php` WordPress API'sine **bağlı değildir**: HTTP ve
önbellek birer arayüzün (`No404_Http_Interface`, `No404_Cache_Interface`)
arkasındadır, WordPress uygulamaları `class-no404-wp-*.php` içinde durur.
Buraya `wp_*` çağrısı **ekleme** — platforma özel her şey adaptörlere gider.

Bunun karşılığı `tests/test-core.php`: eşleştirme, önbellek, kota koruması ve
301/302 kararı WordPress hiç yüklenmeden, sahte HTTP/cache ile sınanıyor. Tek
istisna `wp_parse_url` — test onun bir satırlık sahtesini tanımlar. Çekirdeğe
her `wp_*` çağrısı eklendiğinde o test ya kırılır ya da bir sahte daha ister;
sınırın kaymadığını böyle görüyoruz.

Çekirdeğin sorumlulukları:

| Sorumluluk | Yöntem |
| --- | --- |
| İstek + kısa zaman aşımı (fail-open) | `resolve()` |
| Yerel önbellek, negatifler dahil | `resolve()` + `cache_key()` |
| Devre kesici (API düştüğünde) | `handle_response()` |
| Statik/yönetim yollarını hiç sormama | `is_ignored_path()` |
| 301/302 kararı | `decide_status()` |
| Açık yönlendirme + döngü koruması | `validate_target()` |
| Sunucuyla aynı yol kanonikleştirmesi | `normalize_path()` |
| Ayar ekranı tanılaması | `ping()` |

---

## Davranış sözleşmesi

### Fail-open — pazarlık konusu değil

no404 yavaşlarsa, düşerse veya bozuk yanıt dönerse `resolve()` **`null`** döner
ve mağaza kendi 404 sayfasını normal şekilde render eder. `resolve()` hiçbir
koşulda exception fırlatmaz (`Throwable` dahil yakalanır).

Ek olarak bir **devre kesici** vardır: taşıma hatası veya 5xx sonrası 60 saniye,
429 (kota/limit) veya 403/404 (yapılandırma) sonrası 300 saniye boyunca hiç
istek gönderilmez. Böylece no404 kesintisi mağazanın her 404'üne zaman aşımı
maliyeti bindirmez.

### Kota koruması

Paket limitleri **aylık olay sayısına** bağlıdır, yani bu doğrudan müşterinin
faturasıdır.

* Sonuçlar varsayılan **1 saat** önbelleklenir (`set_transient`).
* **Negatif sonuçlar da** önbelleklenir — aksi hâlde eşleşmeyen bir adres her
  istekte kota harcardı.
* **Sorgu dizesi atılır.** Sunucu zaten `pathname` alıyor; `?utm_source=…`
  tutulsaydı aynı sayfa onlarca ayrı önbellek satırı ve onlarca olay üretirdi.
* Kara listedeki yollar (statik uzantılar, `/wp-admin`, `/wp-json` …) API'ye
  **hiç** sorulmaz.

Önbellek geçersizleştirme **nesil sayacı** ile yapılır (`no404_cache_generation`
option'ı artırılır), `DELETE ... LIKE` ile değil: Redis/Memcached kurulu bir
sitede transient'lar veritabanında durmaz ve LIKE sorgusu hiçbir şey silmez.

### 301 / 302 kararı

| Kaynak | Skor | Durum |
| --- | --- | --- |
| `REDIRECT` (elle tanımlı) | — | **301** |
| `CATALOG` | ≥ 0.5 | **301** |
| `CATALOG` | < 0.5 | 302 |
| `FALLBACK` | — | 302 |

301 tarayıcıda ve Google'da kalıcı önbelleklenir; tahmine dayalı bir eşleşmeyi
301 vermek katalog sonradan düzelse bile geri alınamaz. Ayarlardaki "tüm
eşleşmeleri 301 gönder" seçeneği varsayılan olarak **kapalıdır**.

### Güvenlik

* **API anahtarı sayfaya asla basılmaz.** Ayar ekranında `password` tipi input,
  kayıttan sonra yalnızca maskeli gösterim (`••••••••` + son 4 hane). Alan boş
  veya maskeli gönderilirse mevcut anahtar korunur.
* **Açık yönlendirme koruması:** hedefin host'u izinli listede olmalı
  (`home_url` + `site_url` host'ları, www/non-www varyantlarıyla). Ayrıca
  WordPress'in kendi `wp_validate_redirect()` süzgecinden geçirilir.
  `//evil.com`, `javascript:`, `data:` reddedilir.
* **Başlık enjeksiyonu:** hedefte kontrol karakteri varsa reddedilir.
* **Döngü koruması:** normalize edilmiş hedef, mevcut yola eşitse yönlendirme
  yapılmaz. Zincir yok — istek başına tek yönlendirme, ardından `exit`.
* Sunucudan sunucuya çağrıda `Origin` başlığı gitmez, dolayısıyla no404'ün
  origin kilidi devreye girmez. Anahtarı koruyan tek şey **gizliliği** ve rate
  limit'tir.

### Diğer eklentilerle sıra

`template_redirect` önceliği **9999**. Yoast SEO, Rank Math ve Redirection'ın
kendi yönlendirme tabloları önce çalışır; onlar bir eşleşme bulup yönlendirdiyse
buraya hiç gelinmez. no404 **son çaredir**.

### Multisite

`get_option` / `set_transient` blog bağlamında çalıştığı için her site kendi
anahtarını ve önbelleğini kendiliğinden kullanır. `No404_Plugin::client()`
istemciyi blog kimliğine göre önbelleğe alır; `switch_to_blog()` sonrası
yeniden kurulur. `uninstall.php` tüm siteleri dolaşır.

---

## Kancalar (filtreler)

| Filtre | Amaç |
| --- | --- |
| `no404_skip_request` | `( bool $skip, string $path )` — bu isteği hiç sorma. |
| `no404_redirect_target` | `( string $target, int $status, array $result, string $path )` |
| `no404_redirect_status` | `( int $status, array $result, string $path )` |
| `no404_allowed_hosts` | `( string[] $hosts )` — açık yönlendirme allowlist'i. |
| `no404_client_config` | `( array $config )` — çekirdek yapılandırması. |

---

## Test

```bash
php tests/test-core.php        # çekirdek — 51 kontrol
php tests/test-wordpress.php   # entegrasyon — 45 kontrol
```

İkisi de WordPress kurulumu gerektirmez ve `bin/build.sh` içinde otomatik çalışır.

`test-core.php` çekirdeği sahte HTTP/cache adaptörleriyle çalıştırır: yol
kanonikleştirme, kara liste, 301/302 kararı, açık yönlendirme ve döngü reddi,
önbellek isabeti (kota), negatif önbellek, fail-open, devre kesici, bozuk yanıt.

`test-wordpress.php` eklentiyi **sahte WordPress fonksiyonlarıyla** yükleyip bir
404 isteğini uçtan uca çalıştırır: kanca kaydı ve önceliği, gerçek yönlendirme
durum kodları, `X-Redirect-By`, statik dosya atlama, önbellek/kota, devre
kesici, POST atlama, ayar temizleme ve anahtar maskeleme. Tanımsız veya yanlış
yazılmış WordPress fonksiyon çağrılarını da yakalar.

> İki tuzak, testleri değiştirirken dikkat: (1) yönlendirici `headers_sent()`
> kontrol eder, bu yüzden test çıktısı tamponlanır — tamponu kaldırırsanız
> yönlendirme senaryolarının hepsi sessizce boşa geçer. (2) Devre kesici bir
> senaryodan diğerine sızar; `simulate()` bu yüzden varsayılan olarak önbelleği
> temizler. Temizlemezseniz testler "geçer" ama hiçbir şey doğrulamaz.

### Elle doğrulanması gereken iki nokta

Bunlar test edilemez, insan gözüyle görülmelidir:

1. **Fail-open gerçekten çalışıyor mu.** `api_base`'i erişilemez bir adrese
   çevirin, bir 404 sayfası açın: sayfa normal render edilmeli ve gecikme
   ayarladığınız zaman aşımını aşmamalı.
2. **WooCommerce ve SEO eklentisi çakışması.** Silinmiş bir ürün adresi ile
   Yoast/Rank Math/Redirection kurulu bir sitede sıralamayı doğrulayın.

---

## Ad, slug ve metin alanı — birbirine BAĞLI

| | Değer |
| --- | --- |
| Plugin Name | `no404 – Auto 404 Redirect` |
| WordPress.org slug | `no404-auto-404-redirect` (**addan türetilir**) |
| Text Domain | `no404-auto-404-redirect` (**slug ile aynı olmak ZORUNDA**) |

Slug, eklenti adından otomatik türetilir; text domain ondan saparsa
translate.wordpress.org çevirileri **hiç yüklemez ve bunu sessizce yapar**.
`bin/build.sh` adı okuyup slug'ı türetir ve text domain ile karşılaştırır;
uyuşmazsa paketlemeyi durdurur.

Ad onaylandıktan sonra WordPress.org'da **değiştirilemez**.

`'no404'` dizesinin text domain OLMADIĞI iki yer var, dokunmayın:
`No404_Admin::PAGE_SLUG` (ayar sayfası adresi) ve `wp_redirect( …, 'no404' )`
(`X-Redirect-By` başlık değeri).

## Çeviri

**Kaynak dil İngilizcedir** — kurulum ve ayar ekranları dahil, çeviri
bulunamayan her yerde görünen dil budur. WordPress.org (GlotPress) çevirileri
İngilizce kaynaktan üretir; kaynak başka bir dilde olursa dizinin çeviri
altyapısı hiç çalışmaz.

Paketle birlikte gelen diller (`bin/locales.php` bu listenin TEK kaynağıdır):

| Locale  | Dil                  | Locale  | Dil       |
|---------|----------------------|---------|-----------|
| `tr_TR` | Türkçe               | `ru_RU` | Русский   |
| `de_DE` | Deutsch (senli/du)   | `hi_IN` | हिन्दी      |
| `fr_FR` | Français (vouvoiement) | `ar`  | العربية (RTL) |
| `es_ES` | Español (tú)         |         |           |

```bash
php bin/i18n.php      # .pot + her dil için .po/.mo üretir, .mo'ları geri okuyup doğrular
```

`languages/` altındaki dosyalar **üretilmiştir, elle düzenlenmez.** Karşılıklar
`bin/translations/<locale>.php` içinde (İngilizce → hedef dil) yaşar. Betik şu
durumlarda hata verip DURUR, katalog sessizce eksik üretilmez:

- kodda olup katalogda olmayan metin,
- katalogda kalmış ama artık kodda olmayan kayıt,
- boş `msgstr` (WordPress sessizce İngilizceye düşerdi),
- kaynakla uyuşmayan `%s` / `%1$s` / `%d` yer tutucusu (`sprintf` bozulurdu).

`bin/build.sh` bu adımı kendisi çalıştırır ve `bin/locales.php`'deki HER dilin
`.po`/`.mo` dosyasının pakette olduğunu doğrular.

Yeni bir metin eklerken: koda İngilizcesini yaz, `bin/translations/` altındaki
**her** dosyaya karşılığını ekle, `php bin/i18n.php` çalıştır. Yeni bir dil
eklerken: `bin/locales.php`'ye locale kodunu ve `Plural-Forms` tanımını yaz,
`bin/translations/<locale>.php` dosyasını oluştur.

> Locale kodu WordPress'in kullandığıyla birebir aynı olmalı. Arapça `ar`'dır,
> `ar_AR` diye bir şey yoktur; uydurma kodla üretilen `.mo` hiç yüklenmez.

### `load_plugin_textdomain()` neden yok

Plugin Check o çağrıyı uyarı sayıyor: WordPress 4.6'dan beri çeviriler ilk
`__()` çağrısında kendiliğinden yükleniyor. **Ama kendiliğinden yükleme yalnızca
`WP_LANG_DIR/plugins/` altına bakar** — yani translate.wordpress.org'dan İNEN
dosyalara. Eklentinin kendi `languages/` klasörü o listede değildir
(`WP_Textdomain_Registry::get_paths_for_domain`), oraya yol ekleyen tek şey
`load_plugin_textdomain()`'dir.

Yani çağrıyı silip başka bir şey yapmasaydık **pakete gömülü 7 dilin .mo
dosyası hiç yüklenmezdi** ve arayüz her dilde İngilizce görünürdü — üstelik
hiçbir uyarı çıkmadan. Bu yüzden `No404_Plugin::register_translations_path()`
yolu kayda doğrudan bildiriyor (`set_custom_path`, WordPress 6.1+). Kayıt önce
`WP_LANG_DIR/plugins/` bakıp sonra buraya düştüğü için wp.org'dan inen daha yeni
çeviri paketteki kopyayı ezer — istediğimiz sıra bu.

`tests/test-wordpress.php` hem yolun bildirildiğini hem de kaynakta
`load_plugin_textdomain()` çağrısı KALMADIĞINI doğrular.

## WordPress.org başvurusu

Dizin kurallarına karşı denetim, eksikler ve tüm başvuru metinleri
`_internal/WORDPRESS-ORG-SUBMISSION.md` dosyasında.

> **`_internal/` commit EDİLMEZ.** Bu depo herkese açıktır; başvuru notları,
> hesap bilgileri ve test kimlik bilgileri oraya konur ve `.gitignore` ile
> dışarıda tutulur. Yeni bir dahili not yazarken README'ye değil oraya koyun.

---

## Paketleme

```bash
bash bin/build.sh     # → dist/no404-<sürüm>.zip
```

`tests/`, `bin/`, `README.md` ve `.git` pakete girmez.

---

## Sürüm

Bu eklenti **kendi git deposunda** yaşar; no404 monorepo'suna konmaz. Gerekçe:
eklentinin sürüm döngüsü ve dağıtım kanalı (WordPress.org SVN) ayrıdır.

`/api/v1/resolve` sözleşmesi değişmediği sürece no404 tarafında bir değişiklik
gerekmez.

## Lisans

GPL-2.0-or-later.
