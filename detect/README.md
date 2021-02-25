# INI Detector

Checks if extension is currently loaded. If not, searches `php.ini` and any
additional INI files for relevant lines containing the name of the extension.
Those lines will be printed to assist the user in debugging their configuration.

The script will display the directory where PHP expects to find extensions and
report whether that directory is readable.

The script will then suggest users install the extension and add the necessary
configuration line to `php.ini`. For Windows users, the script will suggest the
appropriate DLL to download from PECL (e.g. "7.4 Thread Safe (TS) x64"). For
non-Windows users, the script will identify the `pecl` binary that should be
used to install the extension.

## Usage

    $ php detect.php [extension]

The extension name defaults to "mongodb" if not specified.

This script can also be run from a web environment. If so, the default extension
name will be used.

## Testing

    $ PHP_INI_SCAN_DIR=test/conf.d php -c test/ detect.php foo
