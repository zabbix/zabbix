<?php declare(strict_types = 1);
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


use Symfony\Component\Yaml\Yaml;

/**
 * Class for converting array with export data to YAML format.
 */
class CYamlExportWriter extends CExportWriter {

	/**
	 * Converts array with export data to YAML format.
	 * Known issues:
	 *   - Symfony dumpers second parameter makes the YAML output either too vertical or too horizontal.
	 *
	 * @param mixed $input  Input data. Array or string.
	 *
	 * @return string
	 */
	public function write($input): string {
		return Yaml::dump($input, 100, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
	}
}
