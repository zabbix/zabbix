<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

$output_file = getenv('ZABBIX_CONNECTOR_TEST_OUTPUT');

if ($output_file === false || $output_file === '') {
	http_response_code(500);
	echo 'ZABBIX_CONNECTOR_TEST_OUTPUT environment variable not set';
	exit(1);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo 'Method not allowed';
	exit(1);
}

$input = file_get_contents('php://input');

if ($input === false) {
	http_response_code(500);
	echo 'Failed to read request body';
	exit(1);
}

$result = file_put_contents($output_file, $input . "\n", FILE_APPEND | LOCK_EX);

if ($result === false) {
	http_response_code(500);
	echo 'Failed to write to output file';
	exit(1);
}

http_response_code(200);
echo 'OK';
