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
function add_mapping_to_valuemap($valuemapid, $mappings) {
	DBexecute('DELETE FROM mappings WHERE valuemapid='.$valuemapid);

	foreach ($mappings as $map) {
		$mappingid = get_dbid('mappings', 'mappingid');

		$result = DBexecute('INSERT INTO mappings (mappingid,valuemapid, value, newvalue)'.
			' VALUES ('.$mappingid.','.$valuemapid.','.zbx_dbstr($map['value']).','.zbx_dbstr($map['newvalue']).')'
		);
		if (!$result) {
			return $result;
		}
	}
	return true;
}

function add_valuemap($name, $mappings) {
	if (!is_array($mappings)) {
		return false;
	}

	$valuemapid = get_dbid('valuemaps', 'valuemapid');

	$result = DBexecute("INSERT INTO valuemaps (valuemapid,name) VALUES ($valuemapid,".zbx_dbstr($name).")");
	if (!$result) {
		return $result;
	}

	$result = add_mapping_to_valuemap($valuemapid, $mappings);
	if (!$result) {
		delete_valuemap($valuemapid);
	}
	else {
		$result = $valuemapid;
	}
	return $result;
}

function update_valuemap($valuemapid, $name, $mappings) {
	if (!is_array($mappings)) {
		return false;
	}

	$result = DBexecute('UPDATE valuemaps SET name='.zbx_dbstr($name).' WHERE valuemapid='.$valuemapid);
	if (!$result) {
		return $result;
	}

	$result = add_mapping_to_valuemap($valuemapid, $mappings);
	if (!$result) {
		delete_valuemap($valuemapid);
	}
	return $result;
}

function delete_valuemap($valuemapid) {
	$result = DBexecute('UPDATE items SET valuemapid=NULL WHERE valuemapid='.$valuemapid);
	$result &= DBexecute('DELETE FROM mappings WHERE valuemapid='.$valuemapid);
	$result &= DBexecute('DELETE FROM valuemaps WHERE valuemapid='.$valuemapid);
	return $result;
}

function replace_value_by_map($value, $valuemapid) {
	if ($valuemapid < 1) {
		return $value;
	}

	static $valuemaps = array();

	if (isset($valuemaps[$valuemapid][$value])) {
		return $valuemaps[$valuemapid][$value];
	}

	$db_mappings = DBselect(
		'SELECT m.newvalue'.
		' FROM mappings m'.
		' WHERE m.valuemapid='.$valuemapid.
			' AND m.value='.zbx_dbstr($value)
	);
	if ($mapping = DBfetch($db_mappings)) {
		$valuemaps[$valuemapid][$value] = $mapping['newvalue'].' '.'('.$value.')';
		return $valuemaps[$valuemapid][$value];
	}
	return $value;
}

function getValuemapByName($name) 	{
	$result = DBselect(
		'SELECT v.valuemapid, v.name' .
			' FROM valuemaps v' .
			' WHERE v.name=' . zbx_dbstr($name)
	);
	if ($row = DBfetch($result)) {
		return $row;
	}
	return 0;
}
?>
