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
 * Get mapping for value.
 *
 * @param string $value			Value that mapping should be applied to.
 * @param string $valuemapid	Value map ID which should be used.
 *
 * @return string|bool			 If there is no mapping return false, return mapped value otherwise.
 */
function getMappedValue($value, $valuemapid) {
	static $valuemaps = [];

	if ($valuemapid == 0) {
		return false;
	}

	if (isset($valuemaps[$valuemapid][$value])) {
		return $valuemaps[$valuemapid][$value];
	}

	$valuemap = API::ValueMap()->get([
		'output' => [],
		'selectMappings' => ['value', 'newvalue'],
		'valuemapids' => [$valuemapid]
	]);

	if ($valuemap) {
		$valuemap = reset($valuemap);

		foreach ($valuemap['mappings'] as $mapping) {
			if ($mapping['value'] === $value) {
				$valuemaps[$valuemapid][$value] = $mapping['newvalue'];
				return $mapping['newvalue'];
			}
		}
	}

	return false;
}

/**
 * Apply value mapping to value.
 * If value map or mapping is not found, unchanged value is returned,
 * otherwise mapped value returned in format: "<mapped_value> (<initial_value>)".
 *
 * @param string $value			Value that mapping should be applied to.
 * @param string $valuemapid	Value map ID which should be used.
 *
 * @return string
 */
function applyValueMap($value, $valuemapid) {
	$mapping = getMappedValue($value, $valuemapid);

	return ($mapping === false) ? $value : $mapping.' ('.$value.')';
}
