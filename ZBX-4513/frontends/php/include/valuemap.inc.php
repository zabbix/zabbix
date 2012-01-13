<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
function addValueMap($valueMap, $mappings) {
	// check duplicate name
	$sql = 'SELECT v.valuemapid FROM valuemaps v WHERE v.name='.zbx_dbstr($valueMap['name']);
	if (DBfetch(DBselect($sql))) {
		throw new Exception(_s('Value map "%1$s" already exists.', $valueMap['name']));
	}

	$valueMapIds = DB::insert('valuemaps', array($valueMap));
	$valueMapId = reset($valueMapIds);

	addValueMapMappings($valueMapId, $mappings);
}


function updateValueMap($valueMap, $mappings) {
	$valueMapId = $valueMap['valuemapid'];
	unset($valueMap['valuemapid']);

	// check existance
	if(!DBfetch(DBselect('SELECT v.valuemapid FROM valuemaps v WHERE v.valuemapid='.$valueMapId))) {
		throw new Exception(_('Value map does not exist.'));
	}

	// check duplicate name
	$sql = 'SELECT v.valuemapid FROM valuemaps v WHERE v.name='.zbx_dbstr($valueMap['name']);
	$dbValueMap = DBfetch(DBselect($sql));
	if ($dbValueMap && bccomp($valueMapId, $dbValueMap['valuemapid']) != 0) {
		throw new Exception(_s('Value map "%1$s" already exists.', $valueMap['name']));
	}

	rewriteValueMapMappings($valueMapId, $mappings);

	DB::update('valuemaps', array(
		'values' => $valueMap,
		'where' => array('valuemapid' => $valueMapId)
	));
}


function deleteValueMap($valueMapId) {
	DB::update('items', array(
		'values' => array('valuemapid' => 0),
		'where' => array('valuemapid' => $valueMapId)
	));
	DB::delete('valuemaps', array('valuemapid' => $valueMapId));
}


function rewriteValueMapMappings($valueMapId, array $mappings) {
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
		deleteValueMapMappings($dbMappingIds);
	}

	if (!empty($mappingsToAdd)) {
		addValueMapMappings($valueMapId, $mappingsToAdd);
	}

	if (!empty($mappingsToUpdate)) {
		updateValueMapMappings($mappingsToUpdate);
	}
}


function addValueMapMappings($valueMapId, $mappings) {
	foreach ($mappings as &$mapping) {
		$mapping['valuemapid'] = $valueMapId;
	}
	unset($mapping);

	DB::insert('mappings', $mappings);
}

function updateValueMapMappings(array $mappings) {
	foreach ($mappings as &$mapping) {
		$mappingid = $mapping['mappingid'];
		unset($mapping['mappingid']);

		DB::update('mappings', array(
			'values' => $mapping,
			'where' => array('mappingid' => $mappingid)
		));
	}
	unset($mapping);
}

function deleteValueMapMappings(array $mappingIds) {
	DB::delete('mappings', array('mappingid' => $mappingIds));
}

function getValueMapMappings($valueMapId) {
	$mappings = array();

	$dbMappings = DBselect(
		'SELECT m.mappingid,m.value,m.newvalue'.
				' FROM mappings m'.
				' WHERE valuemapid='.$valueMapId
	);
	while ($mapping = DBfetch($dbMappings)) {
		$mappings[$mapping['mappingid']] = $mapping;
	}

	return $mappings;
}

function applyValueMap($value, $valueMapId) {
	if ($valueMapId < 1) {
		return $value;
	}

	static $valuemaps = array();
	if (isset($valuemaps[$valueMapId][$value])) {
		return $valuemaps[$valueMapId][$value];
	}

	$db_mappings = DBselect(
		'SELECT m.newvalue'.
			' FROM mappings m'.
			' WHERE m.valuemapid='.$valueMapId.
			' AND m.value='.zbx_dbstr($value)
	);
	if ($mapping = DBfetch($db_mappings)) {
		$valuemaps[$valueMapId][$value] = $mapping['newvalue'].' '.'('.$value.')';
		return $valuemaps[$valueMapId][$value];
	}
	return $value;
}
?>
