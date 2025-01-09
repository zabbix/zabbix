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


/**
 * Abstract class for all export writers.
 */
abstract class CExportWriter {

	/**
	 * Determines if output should be formatted.
	 *
	 * @var bool
	 */
	protected  $formatOutput = true;

	/**
	 * Convert array with export data to required format.
	 *
	 * @abstract
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	abstract public function write(array $array);

	/**
	 * Enable or disable output formatting. Enabled by default.
	 *
	 * @param bool $value
	 *
	 * @return mixed
	 */
	public function formatOutput($value) {
		$this->formatOutput = $value;
	}
}
