<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CImportReaderFactory {

	public const YAML = 'yaml';
	public const XML = 'xml';
	public const JSON = 'json';

	/**
	 * Get reader class for required format.
	 *
	 * @throws Exception
	 *
	 * @param string $format
	 *
	 * @return CImportReader
	 */
	public static function getReader(string $format): CImportReader {
		switch ($format) {
			case self::YAML:
				return new CYamlImportReader();

			case self::XML:
				return new CXmlImportReader();

			case self::JSON:
				return new CJsonImportReader();

			default:
				throw new Exception(_s('Unsupported import format "%1$s".', $format));
		}
	}

	/**
	 * Converts file extension to associated import format.
	 *
	 * @throws Exception
	 *
	 * @param string $ext
	 *
	 * @return string
	 */
	public static function fileExt2ImportFormat(string $ext): string {
		switch ($ext) {
			case 'yaml':
			case 'yml':
				return self::YAML;

			case 'xml':
				return self::XML;

			case 'json':
				return self::JSON;

			default:
				throw new Exception(_s('Unsupported import file extension "%1$s".', $ext));
		}
	}
}
