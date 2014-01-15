<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * Enable or dsable output formatting. Enabled by default.
	 *
	 * @param bool $value
	 *
	 * @return mixed
	 */
	public function formatOutput($value) {
		$this->formatOutput = $value;
	}
}
