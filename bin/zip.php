<?php
/**
 * Portable zip builder — the `zip` command does not exist on Windows.
 *
 * Usage: php bin/zip.php <source-dir> <target.zip> <archive-root-name>
 */

if ( $argc < 4 ) {
	fwrite( STDERR, "Usage: php bin/zip.php <source> <target.zip> <root>\n" );
	exit( 1 );
}

list( , $source, $target, $root ) = $argv;

$zip = new ZipArchive();
if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not open the zip: $target\n" );
	exit( 1 );
}

/** realpath returns backslashes on Windows; archive paths must always use forward slashes. */
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

echo "$count files packaged.\n";
