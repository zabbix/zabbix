<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


define('DEST_FILENAME_PATTERN', __DIR__.'/topPasswords%1$d.php');
define('NUMBER_OF_PASSWORDS_PER_FILE', 200000);

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
$passwords = array_map(function ($password) {
	return '\''. base64_encode($password).'\'';
}, $passwords);

$passwords = array_chunk($passwords, NUMBER_OF_PASSWORDS_PER_FILE);
$write_status = [];
foreach ($passwords as $nr => $passwords_chunk) {
	// Generate file.
	$source = "<?php declare(strict_types = 1);\n".
		"/**\n".
		" * This file is meant to strengthen password validation for internal users. Passwords included in the list are\n".
		" * considered weak due to their common use and are not allowed to be chosen by Zabbix internal users for security\n".
		" * reasons. The file is generated automatically from the list of NCSC \"Top 100k passwords\", the list of SecLists \"Top 1M\n".
		" * passwords\" and the list of Zabbix context-specific passwords.\n".
		" *\n".
		" * The list of passwords is used to check for commonly used passwords according to the password policy. Passwords are\n".
		" * stored as array of base64-encoded strings.\n".
		" */\n".
		"\n".
		"\n".
		"return [".implode(', ', $passwords_chunk)."];".
		"\n";

	$filename = sprintf(DEST_FILENAME_PATTERN, ($nr + 1));
	$write_status[$filename] = (bool) file_put_contents($filename, $source);

	// Output results.
	if ($write_status[$filename]) {
		fwrite(STDOUT, 'File written: "'.$filename.'"'."\n");
	}
	else {
		fwrite(STDERR, 'Cannot write file: "'.$filename.'"'."\n");
	}
}

exit((array_sum($write_status) == count($write_status)) ? 0 : 1);
