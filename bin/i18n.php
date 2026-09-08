<?php
/**
 * Çeviri kataloglarını üretir.
 *
 * Kullanım: php bin/i18n.php
 *
 * KAYNAK DİL İNGİLİZCEDİR — eklentinin kurulum ekranı dahil varsayılan dili
 * budur. WordPress.org (GlotPress) çevirileri İngilizce kaynaktan üretir;
 * kaynak başka bir dilde olursa dizinin çeviri altyapısı çalışmaz. Diğer
 * diller koddan çıkarılıp `bin/translations/<locale>.php` içinde katalog
 * olarak yaşar; hangi dillerin üretileceğini `bin/locales.php` söyler.
 *
 * Üretilenler (hepsi `languages/` altında):
 *   - no404-auto-404-redirect.pot           → çevirmenler için şablon (msgstr boş)
 *   - no404-auto-404-redirect-<locale>.po   → katalog (okunabilir)
 *   - no404-auto-404-redirect-<locale>.mo   → katalog (WordPress bunu okur)
 *
 * Betik şunlardan biri olursa hata verip DURUR — katalog sessizce eksik
 * üretilmez: çevirisi olmayan metin, artık kodda bulunmayan kayıt, boş msgstr,
 * ya da kaynakla uyuşmayan `%s` / `%1$s` yer tutucusu.
 *
 * @package no404
 */

$root = dirname( __DIR__ );

/** Metin alanı — eklenti slug'ı ile AYNI olmak zorunda (WordPress.org kuralı). */
const NO404_TEXT_DOMAIN = 'no404-auto-404-redirect';

// ---------------------------------------------------------------- metinleri çıkar

/**
 * Kaynak dosyalardan çevrilebilir metinleri toplar (token tabanlı, regex değil).
 *
 * @param string $root Eklenti kökü.
 * @return array msgid => referans listesi
 */
function no404_extract( $root ) {
	$funcs   = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
	$strings = array();
	$files   = array();

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
	foreach ( $it as $file ) {
		$path = str_replace( chr( 92 ), '/', $file->getPathname() );
		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		// Testler, üretim betikleri ve paket çıktısı katalogda yer almaz.
		if ( preg_match( '#/(tests|bin|dist)/#', $path ) ) {
			continue;
		}
		$files[] = $path;
	}
	sort( $files );

	foreach ( $files as $file ) {
		$tokens = token_get_all( file_get_contents( $file ) );
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $funcs, true ) ) {
				continue;
			}

			$j = $i + 1;
			while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				$j++;
			}
			if ( $j >= $count || '(' !== $tokens[ $j ] ) {
				continue;
			}
			$j++;
			while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				$j++;
			}
			if ( $j >= $count || ! is_array( $tokens[ $j ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $j ][0] ) {
				continue;
			}

			$raw   = $tokens[ $j ][1];
			$value = substr( $raw, 1, -1 );
			if ( "'" === $raw[0] ) {
				// Tek tırnaklı literalde yalnızca \' ve \\ kaçışları geçerlidir.
				$value = str_replace( array( "\\'", chr( 92 ) . chr( 92 ) ), array( "'", chr( 92 ) ), $value );
			}

			$strings[ $value ][] = basename( $file ) . ':' . $token[2];
		}
	}

	return $strings;
}

$strings = no404_extract( $root );$strings = no404_extract( $root );

// ---------------------------------------------------------------- tutarlılık denetimi

/**
 * Bir katalogda eksik ya da fazla kayıt var mı?
 *
 * Sessizce eksik katalog üretmektense durmak istiyoruz: WordPress çevirisi
 * bulunmayan metni İngilizce basar, yani hata KULLANICIYA hiç görünmez.
 *
 * @param string $locale       Yerel ayar kodu.
 * @param array  $catalog      msgid => çeviri.
 * @param array  $strings      Koddan çıkarılan msgid'ler.
 * @return bool Katalog tutarlı mı?
 */
function no404_check_catalog( $locale, array $catalog, array $strings ) {
	$missing = array_diff( array_keys( $strings ), array_keys( $catalog ) );
	$extra   = array_diff( array_keys( $catalog ), array_keys( $strings ) );
	$empty   = array();

	foreach ( $catalog as $msgid => $msgstr ) {
		if ( '' === trim( (string) $msgstr ) ) {
			$empty[] = $msgid;
		}
	}

	if ( ! empty( $missing ) ) {
		fwrite( STDERR, "[$locale] EKSİK ÇEVİRİ — bin/translations/$locale.php dosyasına ekleyin:\n" );
		foreach ( $missing as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}
	if ( ! empty( $extra ) ) {
		fwrite( STDERR, "[$locale] ARTIK KULLANILMAYAN ÇEVİRİ — bin/translations/$locale.php dosyasından silin:\n" );
		foreach ( $extra as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}
	if ( ! empty( $empty ) ) {
		fwrite( STDERR, "[$locale] BOŞ ÇEVİRİ — msgstr boşsa WordPress İngilizceye düşer:\n" );
		foreach ( $empty as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}

	return empty( $missing ) && empty( $extra ) && empty( $empty );
}

/**
 * `%s`, `%1$s`, `%d` gibi yer tutucular çeviride korunmuş mu?
 *
 * Yer tutucu düşerse `sprintf()` çalışma anında uyarı verir ya da metni yanlış
 * kurar; bu tür bir hata yalnızca o dili kullanan kullanıcıda ortaya çıkar.
 *
 * @param string $locale  Yerel ayar kodu.
 * @param array  $catalog msgid => çeviri.
 * @return bool Yer tutucular tutuyor mu?
 */
function no404_check_placeholders( $locale, array $catalog ) {
	$ok = true;

	foreach ( $catalog as $msgid => $msgstr ) {
		preg_match_all( '/%(?:\d+\$)?[sd]|%%/', $msgid, $src );
		preg_match_all( '/%(?:\d+\$)?[sd]|%%/', (string) $msgstr, $dst );

		$a = $src[0];
		$b = $dst[0];
		sort( $a );
		sort( $b );

		if ( $a !== $b ) {
			$ok = false;
			fwrite( STDERR, "[$locale] YER TUTUCU UYUŞMUYOR: \"$msgid\"\n" );
			fwrite( STDERR, '          kaynak: ' . implode( ' ', $src[0] ) . "\n" );
			fwrite( STDERR, '          çeviri: ' . implode( ' ', $dst[0] ) . "\n" );
		}
	}

	return $ok;
}

$locales  = require __DIR__ . '/locales.php';
$catalogs = array();
$failed   = false;

foreach ( $locales as $locale => $meta ) {
	$file = __DIR__ . '/translations/' . $locale . '.php';
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "[$locale] KATALOG DOSYASI YOK: bin/translations/$locale.php\n" );
		$failed = true;
		continue;
	}

	$catalog = require $file;
	if ( ! is_array( $catalog ) ) {
		fwrite( STDERR, "[$locale] KATALOG DİZİ DÖNDÜRMÜYOR: bin/translations/$locale.php\n" );
		$failed = true;
		continue;
	}

	if ( ! no404_check_catalog( $locale, $catalog, $strings ) ) {
		$failed = true;
	}
	if ( ! no404_check_placeholders( $locale, $catalog ) ) {
		$failed = true;
	}

	$catalogs[ $locale ] = $catalog;
}

if ( $failed ) {
	exit( 1 );
}

// ---------------------------------------------------------------- katalogları yaz

/** PO biçimi için kaçış. */
function no404_po_escape( $text ) {
	return str_replace(
		array( chr( 92 ), '"', "\n", "\t" ),
		array( chr( 92 ) . chr( 92 ), chr( 92 ) . '"', chr( 92 ) . 'n', chr( 92 ) . 't' ),
		$text
	);
}

/**
 * PO/POT başlığı.
 *
 * @param string $language Boşsa POT (şablon) başlığı üretilir.
 * @param string $plural   Bu dilin `Plural-Forms` tanımı.
 * @return string
 */
function no404_po_header( $language, $plural ) {
	$nl   = chr( 92 ) . 'n';
	$out  = '# Copyright (C) ' . gmdate( 'Y' ) . " no404\n";
	$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
	$out .= "# ÜRETİLMİŞ DOSYA — elle düzenlemeyin, `php bin/i18n.php` çalıştırın.\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n";
	$out .= "\"Project-Id-Version: no404 - Auto 404 Redirect 1.0.0{$nl}\"\n";
	$out .= "\"Report-Msgid-Bugs-To: https://no404.tr{$nl}\"\n";
	$out .= "\"MIME-Version: 1.0{$nl}\"\n";
	$out .= "\"Content-Type: text/plain; charset=UTF-8{$nl}\"\n";
	$out .= "\"Content-Transfer-Encoding: 8bit{$nl}\"\n";
	$out .= '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i' ) . "+0000{$nl}\"\n";
	if ( '' !== $language ) {
		$out .= '"PO-Revision-Date: ' . gmdate( 'Y-m-d H:i' ) . "+0000{$nl}\"\n";
		$out .= "\"Language: {$language}{$nl}\"\n";
	}
	$out .= "\"Plural-Forms: {$plural}{$nl}\"\n";
	$out .= '"X-Domain: ' . NO404_TEXT_DOMAIN . "{$nl}\"\n\n";

	return $out;
}

/**
 * Gettext MO ikili biçimi (little-endian). Hash tablosu yazılmaz — isteğe bağlıdır.
 *
 * @param array $pairs msgid => msgstr
 * @return string
 */
function no404_compile_mo( array $pairs ) {
	ksort( $pairs ); // MO tablosu msgid'e göre sıralı olmalı.

	$ids       = '';
	$strs      = '';
	$id_table  = array();
	$str_table = array();

	foreach ( $pairs as $id => $str ) {
		$id_table[]  = array( strlen( $id ), strlen( $ids ) );
		$ids        .= $id . chr( 0 );
		$str_table[] = array( strlen( $str ), strlen( $strs ) );
		$strs       .= $str . chr( 0 );
	}

	$count    = count( $pairs );
	$id_off   = 28;
	$str_off  = $id_off + ( $count * 8 );
	$ids_off  = $str_off + ( $count * 8 );
	$strs_off = $ids_off + strlen( $ids );

	$out  = pack( 'V', 0x950412de ); // Sihirli sayı.
	$out .= pack( 'V', 0 );          // Revizyon.
	$out .= pack( 'V', $count );
	$out .= pack( 'V', $id_off );
	$out .= pack( 'V', $str_off );
	$out .= pack( 'V', 0 );          // Hash tablosu boyutu.
	$out .= pack( 'V', $ids_off );

	foreach ( $id_table as $entry ) {
		$out .= pack( 'VV', $entry[0], $ids_off + $entry[1] );
	}
	foreach ( $str_table as $entry ) {
		$out .= pack( 'VV', $entry[0], $strs_off + $entry[1] );
	}

	return $out . $ids . $strs;
}

/**
 * Yazılan MO dosyasını geri okur.
 *
 * Bozuk bir MO WordPress'te sessizce yok sayılır (çeviri yüklenmez, hata da
 * çıkmaz), bu yüzden üretimden hemen sonra doğruluyoruz.
 *
 * @param string $path MO yolu.
 * @return array msgid => msgstr
 */
function no404_read_mo( $path ) {
	$data = (string) file_get_contents( $path );
	if ( strlen( $data ) < 28 ) {
		return array();
	}

	$magic = unpack( 'V', substr( $data, 0, 4 ) );
	if ( 0x950412de !== $magic[1] ) {
		return array();
	}

	$header = unpack( 'Vrev/Vcount/Vidoff/Vstroff', substr( $data, 4, 16 ) );
	$out    = array();

	for ( $i = 0; $i < $header['count']; $i++ ) {
		$id  = unpack( 'Vlen/Voff', substr( $data, $header['idoff'] + ( $i * 8 ), 8 ) );
		$str = unpack( 'Vlen/Voff', substr( $data, $header['stroff'] + ( $i * 8 ), 8 ) );

		$out[ substr( $data, $id['off'], $id['len'] ) ] = substr( $data, $str['off'], $str['len'] );
	}

	return $out;
}

$lang_dir = $root . '/languages/';
$written  = array();

// Şablon: msgstr'ler boş, çevirmenler ve translate.wordpress.org bunu kullanır.
// Plural-Forms şablonda İngilizcenin (kaynak dilin) kuralıdır.
$pot = no404_po_header( '', 'nplurals=2; plural=(n != 1);' );
foreach ( $strings as $msgid => $refs ) {
	$pot .= '#: ' . implode( ' ', $refs ) . "\n";
	$pot .= 'msgid "' . no404_po_escape( $msgid ) . "\"\nmsgstr \"\"\n\n";
}
file_put_contents( $lang_dir . NO404_TEXT_DOMAIN . '.pot', $pot );
$written[] = 'languages/' . NO404_TEXT_DOMAIN . '.pot';

foreach ( $catalogs as $locale => $catalog ) {
	$plural = $locales[ $locale ]['plural'];

	$po = no404_po_header( $locale, $plural );
	foreach ( $strings as $msgid => $refs ) {
		$po .= '#: ' . implode( ' ', $refs ) . "\n";
		$po .= 'msgid "' . no404_po_escape( $msgid ) . "\"\n";
		$po .= 'msgstr "' . no404_po_escape( $catalog[ $msgid ] ) . "\"\n\n";
	}

	$po_path = $lang_dir . NO404_TEXT_DOMAIN . '-' . $locale . '.po';
	file_put_contents( $po_path, $po );

	// MO'nun boş msgid'i başlıktır; Language ve Plural-Forms buradan okunur.
	$mo_pairs = array(
		'' => "Project-Id-Version: no404 - Auto 404 Redirect 1.0.0\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nLanguage: $locale\nPlural-Forms: $plural\n",
	);
	foreach ( $strings as $msgid => $refs ) {
		$mo_pairs[ $msgid ] = $catalog[ $msgid ];
	}

	$mo_path = $lang_dir . NO404_TEXT_DOMAIN . '-' . $locale . '.mo';
	file_put_contents( $mo_path, no404_compile_mo( $mo_pairs ) );

	$readback = no404_read_mo( $mo_path );
	$expected = count( $mo_pairs );

	if ( count( $readback ) !== $expected ) {
		fwrite( STDERR, "[$locale] MO DOĞRULAMA BAŞARISIZ: " . count( $readback ) . " kayıt okundu, $expected bekleniyordu.\n" );
		exit( 1 );
	}

	foreach ( $mo_pairs as $msgid => $msgstr ) {
		if ( ! isset( $readback[ $msgid ] ) || $readback[ $msgid ] !== $msgstr ) {
			fwrite( STDERR, "[$locale] MO DOĞRULAMA BAŞARISIZ: \"$msgid\" geri okunamadı.\n" );
			exit( 1 );
		}
	}

	$written[] = 'languages/' . NO404_TEXT_DOMAIN . '-' . $locale . '.po';
	$written[] = 'languages/' . NO404_TEXT_DOMAIN . '-' . $locale . '.mo';
}

echo 'Katalog güncellendi ve doğrulandı: ' . count( $strings ) . ' metin × ' . count( $catalogs ) . " dil\n";
foreach ( $written as $file ) {
	echo "  $file\n";
}
