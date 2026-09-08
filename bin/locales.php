<?php
/**
 * Registry of supported locales.
 *
 * The source language is ENGLISH and is NOT listed here: the strings in the code
 * are already English, so producing an English catalogue would be pointless. When
 * WordPress finds no translation it prints the msgid as-is — which means the
 * default language, setup screen included, is always English.
 *
 * Key = the WordPress locale code. The catalogue file is
 * `bin/translations/<code>.php`, and the generated files are
 * `languages/<slug>-<code>.po|.mo`. Codes without a country suffix, such as `ar`,
 * are single-part in WordPress too — do NOT invent an `ar_AR`, that file would
 * never load.
 *
 * The `plural` values match the definitions on translate.wordpress.org exactly.
 * Nothing uses plurals yet (`_n()` is not called anywhere), but a wrong header
 * would silently break the first plural string someone adds.
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
