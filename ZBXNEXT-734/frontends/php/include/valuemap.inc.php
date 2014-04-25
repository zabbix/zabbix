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
 * Add value map with mappings.
 *
 * @param array $valueMap
 * @param array $mappings
 *
 * @throws Exception
 *
 * @return bool
 */
function addValueMap(array $valueMap, array $mappings) {
	$mappings = cleanValueMapMappings($mappings);
	checkValueMapMappings($mappings);

	// check duplicate name
	$sql = 'SELECT v.valuemapid FROM valuemaps v WHERE v.name='.zbx_dbstr($valueMap['name']);

	if (DBfetch(DBselect($sql))) {
		throw new Exception(_s('Value map "%1$s" already exists.', $valueMap['name']));
	}

	$valueMapIds = DB::insert('valuemaps', array($valueMap));
	$valueMapId = reset($valueMapIds);

	return addValueMapMappings($valueMapId, $mappings);
}

/**
 * Update value map and rewrite mappings.
 *
 * @param array $valueMap
 * @param array $mappings
 *
 * @throws Exception
 *
 * @return bool
 */
function updateValueMap(array $valueMap, array $mappings) {
	$mappings = cleanValueMapMappings($mappings);
	checkValueMapMappings($mappings);

	$valueMapId = $valueMap['valuemapid'];
	unset($valueMap['valuemapid']);

	// check existence
	$sql = 'SELECT v.valuemapid FROM valuemaps v WHERE v.valuemapid='.zbx_dbstr($valueMapId);

	if (!DBfetch(DBselect($sql))) {
		throw new Exception(_s('Value map with valuemapid "%1$s" does not exist.', $valueMapId));
	}

	// check duplicate name
	$dbValueMap = DBfetch(DBselect(
		'SELECT v.valuemapid'.
		' FROM valuemaps v'.
		' WHERE v.name='.zbx_dbstr($valueMap['name'])
	));
	if ($dbValueMap && bccomp($valueMapId, $dbValueMap['valuemapid']) != 0) {
		throw new Exception(_s('Value map "%1$s" already exists.', $valueMap['name']));
	}

	$result = rewriteValueMapMappings($valueMapId, $mappings);

	$result &= DB::update('valuemaps', array(
		'values' => $valueMap,
		'where' => array('valuemapid' => $valueMapId)
	));

	return (bool) $result;
}

/**
 * Delete value map.
 *
 * @param int $valueMapId
 *
 * @return bool
 */
function deleteValueMap($valueMapId) {
	$result = DB::update('items', array(
		'values' => array('valuemapid' => 0),
		'where' => array('valuemapid' => $valueMapId)
	));
	$result &= DB::delete('valuemaps', array('valuemapid' => $valueMapId));

	return (bool) $result;
}

/**
 * Clean value map mappings array from empty records when both value and newvalue are empty strings.
 *
 * @param array $mappings
 *
 * @return array
 */
function cleanValueMapMappings(array $mappings) {
	$cleanedMappings = $mappings;

	foreach ($cleanedMappings as $key => $mapping) {
		if (zbx_empty($mapping['value']) && zbx_empty($mapping['newvalue'])) {
			unset($cleanedMappings[$key]);
		}
	}

	return $cleanedMappings;
}

/**
 * Check value map mappings.
 * 1. check if at least one is defined
 * 2. check if value is numeric
 * 3. check if mappend value is not empty string
 * 4. check for duplicate values
 *
 * @param array $mappings
 *
 * @throws Exception
 */
function checkValueMapMappings(array $mappings) {
	if (empty($mappings)) {
		throw new Exception(_('Value mapping must have at least one mapping.'));
	}

	foreach ($mappings as $mapping) {
		if (zbx_empty($mapping['newvalue'])) {
			throw new Exception(_('Value cannot be mapped to empty string.'));
		}
	}

	$valueCount = array_count_values(zbx_objectValues($mappings, 'value'));
	foreach ($valueCount as $value => $count) {
		if ($count > 1) {
			throw new Exception(_s('Mapping value "%1$s" is not unique.', $value));
		}
	}
}

/**
 * Rewrite value map mappings.
 *
 * @param int   $valueMapId
 * @param array $mappings
 *
 * @return bool
 */
function rewriteValueMapMappings($valueMapId, array $mappings) {
	$result = true;
	$dbValueMaps = getValueMapMappings($valueMapId);

	$mappingsToAdd = array();
	$mappingsToUpdate = array();
	foreach ($mappings as $mapping) {
		if (!isset($mapping['mappingid'])) {
			$mappingsToAdd[] = $mapping;
		}
		elseif (isset($dbValueMaps[$mapping['mappingid']])) {
			$mappingsToUpdate[] = $mapping;
			unset($dbValueMaps[$mapping['mappingid']]);
		}
	}

	if (!empty($dbValueMaps)) {
		$dbMappingIds = zbx_objectValues($dbValueMaps, 'mappingid');
		$result &= deleteValueMapMappings($dbMappingIds);
	}

	if (!empty($mappingsToAdd)) {
		$result &= addValueMapMappings($valueMapId, $mappingsToAdd);
	}

	if (!empty($mappingsToUpdate)) {
		$result &= updateValueMapMappings($mappingsToUpdate);
	}

	return (bool) $result;
}

/**
 * Add new mappings to value map.
 *
 * @param int   $valueMapId
 * @param array $mappings
 *
 * @return bool
 */
function addValueMapMappings($valueMapId, array $mappings) {
	foreach ($mappings as &$mapping) {
		$mapping['valuemapid'] = $valueMapId;
	}
	unset($mapping);

	return (bool) DB::insert('mappings', $mappings);
}

/**
 * Update value map mappings.
 *
 * @param array $mappings
 *
 * @return bool
 */
function updateValueMapMappings(array $mappings) {
	$result = true;
	foreach ($mappings as &$mapping) {
		$mappingid = $mapping['mappingid'];
		unset($mapping['mappingid']);

		$result &= DB::update('mappings', array(
			'values' => $mapping,
			'where' => array('mappingid' => $mappingid)
		));
	}
	unset($mapping);

	return (bool) $result;
}

/**
 * Delete value map mappings.
 *
 * @param array $mappingIds
 *
 * @return bool
 */
function deleteValueMapMappings(array $mappingIds) {
	return (bool) DB::delete('mappings', array('mappingid' => $mappingIds));
}

/**
 * Get all value map mappings.
 *
 * @param int $valueMapId
 *
 * @return array
 */
function getValueMapMappings($valueMapId) {
	$mappings = array();

	$dbMappings = DBselect(
		'SELECT m.mappingid,m.value,m.newvalue'.
		' FROM mappings m'.
		' WHERE m.valuemapid='.zbx_dbstr($valueMapId)
	);
	while ($mapping = DBfetch($dbMappings)) {
		$mappings[$mapping['mappingid']] = $mapping;
	}

	return $mappings;
}

/**
 * Get mapping for value.
 * If there is no mapping return false.
 *
 * @param string $value			value that mapping should be applied to
 * @param int    $valueMapId	value map id which should be used
 *
 * @return string|bool
 */
function getMappedValue($value, $valueMapId) {
	static $valueMaps = array();

	if ($valueMapId < 1) {
		return false;
	}

	if (isset($valueMaps[$valueMapId][$value])) {
		return $valueMaps[$valueMapId][$value];
	}

	$dbMappings = DBselect(
		'SELECT m.newvalue'.
		' FROM mappings m'.
		' WHERE m.valuemapid='.zbx_dbstr($valueMapId).
			' AND m.value='.zbx_dbstr($value)
	);
	if ($mapping = DBfetch($dbMappings)) {
		$valueMaps[$valueMapId][$value] = $mapping['newvalue'];

		return $mapping['newvalue'];
	}

	return false;
}

/**
 * Apply value mapping to value.
 * If value map or mapping is not found unchanged value returned,
 * otherwise mapped value returned in format: "<mapped_value> (<initial_value>)".
 *
 * @param string $value			value that mapping should be applied to
 * @param int    $valueMapId	value map id which should be used
 *
 * @return string
 */
function applyValueMap($value, $valueMapId) {
	$mapping = getMappedValue($value, $valueMapId);

	return ($mapping === false) ? $value : $mapping.' ('.$value.')';
}
