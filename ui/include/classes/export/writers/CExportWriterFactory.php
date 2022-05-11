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


class CExportWriterFactory {

	const YAML = 'yaml';
	const XML = 'xml';
	const JSON = 'json';
	const RAW = 'raw';

	/**
	 * Get the writer object for specified type.
	 *
	 * @static
	 * @throws Exception
	 *
	 * @param string $type
	 *
	 * @return CExportWriter
	 */
	public static function getWriter($type) {
		switch ($type) {
			case self::YAML:
				return new CYamlExportWriter();

			case self::XML:
				return new CXmlExportWriter();

			case self::JSON:
				return new CJsonExportWriter();

			case self::RAW:
				return new CRawExportWriter();

			default:
				throw new Exception('Incorrect export writer type.');
		}
	}

	/**
	 * Get content mime-type for specified type.
	 *
	 * @static
	 * @throws Exception
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function getMimeType(string $type): string {
		switch ($type) {
			case self::YAML:
				// See https://github.com/rails/rails/blob/d41d586/actionpack/lib/action_dispatch/http/mime_types.rb#L39
				return 'text/yaml';

			case self::XML:
				// See https://www.ietf.org/rfc/rfc2376.txt
				return 'text/xml';

			case self::JSON:
				// See https://www.ietf.org/rfc/rfc4627.txt
				return 'application/json';

			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
