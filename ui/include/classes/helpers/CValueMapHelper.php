<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CValueMapHelper {

	/**
	 * Apply value mapping to value.
	 * If value map or mapping is not found, unchanged value is returned,
	 * otherwise mapped value returned in format: "<mapped_value> (<initial_value>)".
	 *
	 * @param string $value                 Value that mapping should be applied to.
	 * @param array  $valuemap              Valuemap array.
	 * @param array  $valuemap['mappings']  (optional) Valuemap mappings array.
	 *
	 * @return string
	 */
	static public function applyValueMap(string $value, array $valuemap): string {
		$newvalue = static::getMappedValue($value, $valuemap);

		return ($newvalue !== false) ? $newvalue.' ('.$value.')' : $value;
	}

	/**
	 * Get mapping for value.
	 *
	 * @param string $value                 Value that mapping should be applied to.
	 * @param array  $valuemap              Valuemap array.
	 * @param array  $valuemap['mappings']  (optional) Valuemap mappings array.
	 *
	 * @return string|bool     If there is no mapping return false, return mapped value otherwise.
	 */
	static public function getMappedValue(string $value, array $valuemap) {
		if (!$valuemap) {
			return false;
		}

		$mappings = array_column($valuemap['mappings'], 'newvalue', 'value');

		return array_key_exists($value, $mappings) ? $mappings[$value] : false;
	}
}
