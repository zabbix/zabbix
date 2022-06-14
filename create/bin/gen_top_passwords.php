<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


define('DEST_FILENAME', __DIR__.'/top_passwords.txt');

$files = [];
for ($i = 1; $argc > $i; $i++) {
	if (!is_readable($argv[$i]) || !is_file($argv[$i])) {
		fwrite(STDERR, 'The file "'.$argv[$i].'" does not exist or is not readable.'."\n");
		exit(1);
	}

	$files[$argv[$i]] = true;
}

if (!$files) {
	fwrite(STDERR, 'No input file specified.'."\n");
	exit(1);
}

// Make a list of unique passwords from all source files.
$passwords = [];
foreach (array_keys($files) as $filename) {
	$passwords += array_flip(file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}
$passwords = array_keys($passwords);

// Remove passwords containing non-printable characters.
$passwords = array_filter($passwords, function ($password) {
	return !preg_match('/[^\x20-\x7e]/', $password);
});

// Base64-encode each password and add quotes around it.
$passwords = array_map('base64_encode', $passwords);

// Generate file.
$source = "#\n".
	"# This file is meant to strengthen password validation for internal users. Passwords included in the list are considered\n".
	"# weak due to their common use and are not allowed to be chosen by Zabbix internal users for security reasons. The file \n".
	"# is generated automatically from the list of NCSC \"Top 100k passwords\", the list of SecLists \"Top 1M passwords\" and the\n".
	"# list of Zabbix context-specific passwords.\n".
	"#\n".
	"# The list of passwords is used to check for commonly used passwords according to the password policy. Passwords are\n".
	"# stored as base64-encoded strings. There must be two newlines before passwords.\n".
	"#\n".
	"\n".
	"\n".
	implode("\n", $passwords).
	"\n";

if (file_put_contents(DEST_FILENAME, $source)) {
	fwrite(STDOUT, 'File written: "'.DEST_FILENAME.'"'."\n");
	exit(0);
}
else {
	fwrite(STDERR, 'Cannot write file: "'.DEST_FILENAME.'"'."\n");
	exit(1);
}
