<?php declare(strict_types = 0);
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
		return Yaml::dump($input, 100, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING);
	}
}
