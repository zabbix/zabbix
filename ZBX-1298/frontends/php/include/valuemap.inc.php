<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/*************** VALUE MAPPING ******************/
	function add_mapping_to_valuemap($valuemapid, $mappings){
		DBexecute("delete FROM mappings WHERE valuemapid=$valuemapid");

		foreach($mappings as $map){
			$mappingid = get_dbid("mappings","mappingid");

			$result = DBexecute("insert into mappings (mappingid,valuemapid, value, newvalue)".
				" values (".$mappingid.",".$valuemapid.",".zbx_dbstr($map["value"]).",".
				zbx_dbstr($map["newvalue"]).")");

			if(!$result)
				return $result;
		}
		return TRUE;
	}

	function add_valuemap($name, $mappings){
		if(!is_array($mappings))	return FALSE;

		$valuemapid = get_dbid("valuemaps","valuemapid");

		$result = DBexecute("insert into valuemaps (valuemapid,name) values ($valuemapid,".zbx_dbstr($name).")");
		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		else{
			$result = $valuemapid;
		}
		return $result;
	}

	function update_valuemap($valuemapid, $name, $mappings){
		if(!is_array($mappings))	return FALSE;

		$result = DBexecute('UPDATE valuemaps SET name='.zbx_dbstr($name).
			' WHERE valuemapid='.$valuemapid);

		if(!$result)
			return $result;

		$result = add_mapping_to_valuemap($valuemapid, $mappings);
		if(!$result){
			delete_valuemap($valuemapid);
		}
		return $result;
	}

	function delete_valuemap($valuemapid){
		DBexecute('DELETE FROM mappings WHERE valuemapid='.$valuemapid);
		DBexecute('DELETE FROM valuemaps WHERE valuemapid='.$valuemapid);
	return TRUE;
	}

	function replace_value_by_map($value, $valuemapid){
		if($valuemapid < 1) return $value;

		static $valuemaps = array();
		if(isset($valuemaps[$valuemapid][$value])) return $valuemaps[$valuemapid][$value];

		$sql = 'SELECT newvalue '.
				' FROM mappings '.
				' WHERE valuemapid='.$valuemapid.
					' AND value='.zbx_dbstr($value);
		$result = DBselect($sql);
		if($row = DBfetch($result)){
			$valuemaps[$valuemapid][$value] = $row['newvalue'].' '.'('.$value.')';
			return $valuemaps[$valuemapid][$value];
		}

	return $value;
	}


	function getValuemapByName($name) {
		$result = DBselect(
			'SELECT v.valuemapid, v.name'.
				' FROM valuemaps v'.
				' WHERE v.name='.zbx_dbstr($name)
		);
		return DBfetch($result);
	}

/*************** END VALUE MAPPING ******************/
?>