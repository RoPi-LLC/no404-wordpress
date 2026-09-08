<?php
/**
 * Taşınabilir zip üretici — `zip` komutu Windows'ta bulunmadığı için.
 *
 * Kullanım: php bin/zip.php <kaynak-dizin> <hedef.zip> <arşiv-kök-adı>
 */

if ( $argc < 4 ) {
	fwrite( STDERR, "Kullanım: php bin/zip.php <kaynak> <hedef.zip> <kök>\n" );
	exit( 1 );
}

list( , $source, $target, $root ) = $argv;

$zip = new ZipArchive();
if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Zip açılamadı: $target\n" );
	exit( 1 );
}

/** Windows'ta realpath ters slash döner; arşiv yolları hep düz slash olmalı. */
function no404_slashes( $path ) {
	return str_replace( chr( 92 ), '/', $path );
}

$source = rtrim( no404_slashes( realpath( $source ) ), '/' );
$files  = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ( $files as $file ) {
	$path     = no404_slashes( $file->getPathname() );
	$relative = $root . '/' . ltrim( substr( $path, strlen( $source ) ), '/' );

	if ( $file->isDir() ) {
		$zip->addEmptyDir( $relative );
		continue;
	}

	$zip->addFile( $path, $relative );
	$count++;
}

$zip->close();

echo "$count dosya paketlendi.\n";
