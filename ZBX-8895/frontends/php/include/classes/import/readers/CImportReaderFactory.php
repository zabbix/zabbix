<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CImportReaderFactory {

	const XML = 'xml';
	const JSON = 'json';

	/**
	 * Get reader class for required format.
	 *
	 * @static
	 * @throws Exception
	 *
	 * @param string $format
	 *
	 * @return CImportReader
	 */
	public static function getReader($format) {
		switch ($format) {
			case 'xml':
				return new CXmlImportReader();
			case 'json':
				return new CJsonImportReader();
			default:
				throw new Exception(_s('Unsupported import format "%1$s".', $format));
		}
	}

	/**
	 * Converts file extension to associated import format.
	 *
	 * @static
	 * @throws Exception
	 *
	 * @param string $ext
	 *
	 * @return string
	 */
	public static function fileExt2ImportFormat($ext) {
		switch ($ext) {
			case 'xml':
				return CImportReaderFactory::XML;
			case 'json':
				return CImportReaderFactory::JSON;
			default:
				throw new Exception(_s('Unsupported import file extension "%1$s".', $ext));
		}

	}
}
