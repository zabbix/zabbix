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


/**
 * An object that can perform a sequential conversion using an array of conversions.
 */
class CConverterChain  {

	/**
	 * Converters to use.
	 *
	 * @var CConverter[]
	 */
	protected $converters = [];

	/**
	 * Convert the data starting from the converter given in $startFrom.
	 *
	 * @param mixed     $data
	 * @param string    $startFrom
	 *
	 * @return mixed
	 */
	public function convert($data, $startFrom) {
		$convert = false;
		foreach ($this->converters as $key => $converter) {
			if ($key === $startFrom) {
				$convert = true;
			}

			if ($convert) {
				$data = $converter->convert($data);
			}
		}

		return $data;
	}

	/**
	 * Add a new converter to the chain.
	 *
	 * @param string $name
	 * @param CConverter $converter
	 */
	public function addConverter($name, CConverter $converter) {
		$this->converters[$name] = $converter;
	}
}
