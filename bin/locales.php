<?php
/**
 * Desteklenen yerel ayarlar (locale) kaydı.
 *
 * Kaynak dil İNGİLİZCEDİR ve burada yer ALMAZ: koddaki dizeler zaten
 * İngilizcedir, İngilizce bir katalog üretmenin anlamı yok. WordPress
 * çeviri bulamazsa msgid'i olduğu gibi basar — yani varsayılan dil,
 * kurulum ekranı dahil, her zaman İngilizcedir.
 *
 * Anahtar = WordPress locale kodu. Katalog dosyası `bin/translations/<kod>.php`,
 * üretilen dosyalar `languages/<slug>-<kod>.po|.mo` olur. `ar` gibi ülke soneki
 * olmayan kodlar WordPress'te de tek parçadır — uydurma bir `ar_AR` YAZMAYIN,
 * o dosya hiç yüklenmez.
 *
 * `plural` değerleri translate.wordpress.org'daki tanımlarla birebir aynıdır.
 * Şu an çoğul içeren metin yok (`_n()` kullanılmıyor), ama başlık yanlışsa
 * ileride eklenecek ilk çoğul sessizce bozulur.
 *
 * @package no404
 */

return array(
	'tr_TR' => array(
		'name'   => 'Türkçe',
		'plural' => 'nplurals=2; plural=(n > 1);',
	),
	'de_DE' => array(
		'name'   => 'Deutsch',
		'plural' => 'nplurals=2; plural=(n != 1);',
	),
	'fr_FR' => array(
		'name'   => 'Français',
		'plural' => 'nplurals=2; plural=(n > 1);',
	),
	'es_ES' => array(
		'name'   => 'Español',
		'plural' => 'nplurals=2; plural=(n != 1);',
	),
	'ru_RU' => array(
		'name'   => 'Русский',
		'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n%10<=4 && (n%100<12 || n%100>14)) ? 1 : 2);',
	),
	'hi_IN' => array(
		'name'   => 'हिन्दी',
		'plural' => 'nplurals=2; plural=(n != 1);',
	),
	'ar'    => array(
		'name'   => 'العربية',
		'plural' => 'nplurals=6; plural=(n==0) ? 0 : ((n==1) ? 1 : ((n==2) ? 2 : ((n%100>=3 && n%100<=10) ? 3 : ((n%100>=11 && n%100<=99) ? 4 : 5))));',
	),
);
