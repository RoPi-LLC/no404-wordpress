<?php
/**
 * Builds the translation catalogues.
 *
 * Usage: php bin/i18n.php
 *
 * THE SOURCE LANGUAGE IS ENGLISH — it is the plugin's default language, setup
 * screen included. WordPress.org (GlotPress) generates translations *from*
 * English; with a source in any other language the directory's translation
 * infrastructure does not work at all. Every other language is kept out of the
 * code and lives as a catalogue in `bin/translations/<locale>.php`;
 * `bin/locales.php` decides which locales get built.
 *
 * What it produces (all under `languages/`):
 *   - no404-auto-404-redirect.pot           → template for translators (empty msgstr)
 *   - no404-auto-404-redirect-<locale>.po   → catalogue (human readable)
 *   - no404-auto-404-redirect-<locale>.mo   → catalogue (what WordPress reads)
 *
 * The script STOPS with an error — rather than quietly emitting an incomplete
 * catalogue — on any of these: an untranslated string, an entry that no longer
 * exists in the code, an empty msgstr, or a `%s` / `%1$s` placeholder that does
 * not match the source.
 *
 * @package no404
 */

$root = dirname( __DIR__ );

/** Text domain — MUST equal the plugin slug (a WordPress.org rule). */
const NO404_TEXT_DOMAIN = 'no404-auto-404-redirect';

// ---------------------------------------------------------------- extract strings

/**
 * Collects translatable strings from the source files (token-based, not regex).
 *
 * @param string $root Plugin root.
 * @return array msgid => list of references
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
		// Tests, build scripts and package output never appear in the catalogue.
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
				// In a single-quoted literal only the \' and \\ escapes are meaningful.
				$value = str_replace( array( "\\'", chr( 92 ) . chr( 92 ) ), array( "'", chr( 92 ) ), $value );
			}

			$strings[ $value ][] = basename( $file ) . ':' . $token[2];
		}
	}

	return $strings;
}

$strings = no404_extract( $root );$strings = no404_extract( $root );

// ---------------------------------------------------------------- consistency checks

/**
 * Does a catalogue have missing or leftover entries?
 *
 * We would rather stop than emit an incomplete catalogue: WordPress prints an
 * untranslated string in English, so the mistake is NEVER visible to the user.
 *
 * @param string $locale       Locale code.
 * @param array  $catalog      msgid => translation.
 * @param array  $strings      msgids extracted from the code.
 * @return bool Is the catalogue consistent?
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
		fwrite( STDERR, "[$locale] MISSING TRANSLATION — add it to bin/translations/$locale.php:\n" );
		foreach ( $missing as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}
	if ( ! empty( $extra ) ) {
		fwrite( STDERR, "[$locale] UNUSED TRANSLATION — remove it from bin/translations/$locale.php:\n" );
		foreach ( $extra as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}
	if ( ! empty( $empty ) ) {
		fwrite( STDERR, "[$locale] EMPTY TRANSLATION — an empty msgstr makes WordPress fall back to English:\n" );
		foreach ( $empty as $item ) {
			fwrite( STDERR, "  - $item\n" );
		}
	}

	return empty( $missing ) && empty( $extra ) && empty( $empty );
}

/**
 * Are placeholders such as `%s`, `%1$s` and `%d` preserved in the translation?
 *
 * If one is dropped, `sprintf()` warns at runtime or builds the wrong string —
 * and that failure only ever shows up for users of that one language.
 *
 * @param string $locale  Locale code.
 * @param array  $catalog msgid => translation.
 * @return bool Do the placeholders line up?
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
			fwrite( STDERR, "[$locale] PLACEHOLDER MISMATCH: \"$msgid\"\n" );
			fwrite( STDERR, '          source:      ' . implode( ' ', $src[0] ) . "\n" );
			fwrite( STDERR, '          translation: ' . implode( ' ', $dst[0] ) . "\n" );
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
		fwrite( STDERR, "[$locale] CATALOGUE FILE MISSING: bin/translations/$locale.php\n" );
		$failed = true;
		continue;
	}

	$catalog = require $file;
	if ( ! is_array( $catalog ) ) {
		fwrite( STDERR, "[$locale] CATALOGUE DOES NOT RETURN AN ARRAY: bin/translations/$locale.php\n" );
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

// ---------------------------------------------------------------- write catalogues

/** Escaping for the PO format. */
function no404_po_escape( $text ) {
	return str_replace(
		array( chr( 92 ), '"', "\n", "\t" ),
		array( chr( 92 ) . chr( 92 ), chr( 92 ) . '"', chr( 92 ) . 'n', chr( 92 ) . 't' ),
		$text
	);
}

/**
 * The PO/POT header.
 *
 * @param string $language Empty produces the POT (template) header.
 * @param string $plural   This language's `Plural-Forms` definition.
 * @return string
 */
function no404_po_header( $language, $plural ) {
	$nl   = chr( 92 ) . 'n';
	$out  = '# Copyright (C) ' . gmdate( 'Y' ) . " no404\n";
	$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
	$out .= "# GENERATED FILE — do not edit by hand; run `php bin/i18n.php`.\n";
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
 * The gettext MO binary format (little-endian). No hash table is written — it is optional.
 *
 * @param array $pairs msgid => msgstr
 * @return string
 */
function no404_compile_mo( array $pairs ) {
	ksort( $pairs ); // The MO table must be sorted by msgid.

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

	$out  = pack( 'V', 0x950412de ); // Magic number.
	$out .= pack( 'V', 0 );          // Revision.
	$out .= pack( 'V', $count );
	$out .= pack( 'V', $id_off );
	$out .= pack( 'V', $str_off );
	$out .= pack( 'V', 0 );          // Hash table size.
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
 * Reads a written MO file back.
 *
 * WordPress ignores a corrupt MO silently (no translation loads, and no error is
 * raised), so we verify each file immediately after writing it.
 *
 * @param string $path Path to the MO file.
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

// Template: msgstr entries stay empty; translators and translate.wordpress.org
// consume this. Plural-Forms in the template is English's rule (the source language).
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

	// The MO's empty msgid is the header; Language and Plural-Forms are read from it.
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
		fwrite( STDERR, "[$locale] MO VERIFICATION FAILED: read " . count( $readback ) . " entries, expected $expected.\n" );
		exit( 1 );
	}

	foreach ( $mo_pairs as $msgid => $msgstr ) {
		if ( ! isset( $readback[ $msgid ] ) || $readback[ $msgid ] !== $msgstr ) {
			fwrite( STDERR, "[$locale] MO VERIFICATION FAILED: could not read \"$msgid\" back.\n" );
			exit( 1 );
		}
	}

	$written[] = 'languages/' . NO404_TEXT_DOMAIN . '-' . $locale . '.po';
	$written[] = 'languages/' . NO404_TEXT_DOMAIN . '-' . $locale . '.mo';
}

echo 'Catalogues rebuilt and verified: ' . count( $strings ) . ' strings x ' . count( $catalogs ) . " locales\n";
foreach ( $written as $file ) {
	echo "  $file\n";
}
