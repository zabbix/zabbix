<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * Class for converting YAML data stream to PHP array.
 */
class CYamlImportReader extends CImportReader {

	/**
	 * Convert YAML data stream to PHP array. Suppress PHP notices when executing yaml_parse() with custom
	 * error handler. Display only first error since that is where the syntax in file is incorrect.
	 *
	 * @param string $string
	 *
	 * @throws ErrorException
	 *
	 * @return array
	 */
	public function read($string): array {
		$error = '';

		set_error_handler(function ($errno, $errstr) use (&$error) {
			if ($error === '' && $errstr !== '') {
				$error = str_replace('yaml_parse(): ', '', $errstr);
			}
		});

		$data = yaml_parse($string);

		restore_error_handler();

		/*
		 * Unfortunately yaml_parse() not always returns FALSE. If file is empty, it returns NULL and if file contains
		 * gibberish and not a "zabbix_export" array, $data contains same input string, but Import Validator expects
		 * $data to be an array. Create a custom error message for these cases.
		 */
		if (!is_array($data) && $data !== false) {
			$data = false;
			$error = _('Invalid file content');
		}

		if ($data === false) {
			throw new ErrorException(_s('Cannot read YAML: %1$s.', $error));
		}

		return $data;
	}
}
