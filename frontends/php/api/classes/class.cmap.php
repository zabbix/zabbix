<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
/**
 * File containing CMap class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Maps
 */
class CMap extends CZBXAPI{
/**
 * Get Map data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] HostGroup IDs
 * @param array $options['hostids'] Host IDs
 * @param boolean $options['monitored_hosts'] only monitored Hosts
 * @param boolean $options['templated_hosts'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_monitored_items'] only with monitored items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_monitored_triggers'] only with monitores triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitores http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] depricated parametr (for now)
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('name'); // allowed columns for sorting


		$sql_parts = array(
			'select' => array('sysmaps' => 's.sysmapid'),
			'from' => array('sysmaps s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'sysmapids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// OutPut
			'extendoutput'				=> null,
			'select_elements'			=> null,
			'count'						=> null,
			'pattern'					=> '',
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// sysmapids
		if(!is_null($options['sysmapids'])){
			zbx_value2array($options['sysmapids']);
			$sql_parts['where'][] = DBcondition('s.sysmapid', $options['sysmapids']);
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['sysmaps'] = 's.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.sysmapid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(s.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 's.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('s.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('s.*', $sql_parts['select'])){
				$sql_parts['select'][] = 's.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$sysmapids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.sysmapid', $nodeids).
				$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($sysmap = DBfetch($res)){
			if($options['count'])
				$result = $sysmap;
			else{
				$sysmapids[$sysmap['sysmapid']] = $sysmap['sysmapid'];

				if(is_null($options['extendoutput'])){
					$result[$sysmap['sysmapid']] = $sysmap['sysmapid'];
				}
				else{
					if(!isset($result[$sysmap['sysmapid']])) $result[$sysmap['sysmapid']]= array();

					$result[$sysmap['sysmapid']] += $sysmap;
				}
			}
		}



		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else{
			if(!empty($result)){
				$hosts_to_check = array();
				$maps_to_check = array();
				$triggers_to_check = array();
				$host_groups_to_check = array();
				$map_elements = array();

				$db_elements = DBselect('SELECT * FROM sysmaps_elements WHERE '.DBcondition('sysmapid', $sysmapids));

				while($element = DBfetch($db_elements)){
					$map_elements[$element['elementid']] = $element['elementid'];

					switch($element['elementtype']){
						case SYSMAP_ELEMENT_TYPE_HOST:
							$hosts_to_check[] = $element['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$maps_to_check[] = $element['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$triggers_to_check[] = $element['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$host_groups_to_check[] = $element['elementid'];
						break;
					}
				}
// sdi($hosts_to_check);
// sdi($maps_to_check);
// sdi($triggers_to_check);
// sdi($host_groups_to_check);

				$allowed_hosts = CHost::get(array('hostids' => $hosts_to_check, 'editable' => isset($options['editable'])));
				$allowed_maps = CMap::get(array('sysmapids' => $maps_to_check, 'editable' => isset($options['editable'])));

				$allowed_triggers = CTrigger::get(array('triggerids' => $triggers_to_check, 'editable' => isset($options['editable'])));
				$allowed_host_groups = CHostGroup::get(array('groupids' => $host_groups_to_check, 'editable' => isset($options['editable'])));

				$restr_hosts = array_diff($hosts_to_check, $allowed_hosts);
				$restr_maps = array_diff($maps_to_check, $allowed_maps);
				$restr_triggers = array_diff($triggers_to_check, $allowed_triggers);
				$restr_host_groups = array_diff($host_groups_to_check, $allowed_host_groups);

				foreach($restr_hosts as $elementid){
					foreach($map_elements as $map_elementid => $map_element){
						if(($map_element['elementid'] == $elementid) && ($map_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST)){
							unset($result[$map_element['sysmapid']]);
							unset($map_elements[$map_elementid]);
						}
					}
				}
				foreach($restr_maps as $elementid){
					foreach($map_elements as $map_elementid => $map_element){
						if($map_element['elementid'] == $elementid && ($map_element['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP)){
							unset($result[$map_element['sysmapid']]);
							unset($map_elements[$map_elementid]);
						}
					}
				}
				foreach($restr_triggers as $elementid){
					foreach($map_elements as $map_elementid => $map_element){
						if($map_element['elementid'] == $elementid && ($map_element['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)){
							unset($result[$map_element['sysmapid']]);
							unset($map_elements[$map_elementid]);
						}
					}
				}
				foreach($restr_host_groups as $elementid){
					foreach($map_elements as $map_elementid => $map_element){
						if($map_element['elementid'] == $elementid && ($map_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)){
							unset($result[$map_element['sysmapid']]);
							unset($map_elements[$map_elementid]);
						}
					}
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])) return $result;


// Adding Elements
		if($options['select_elements']){
			if(!isset($map_elements)){
				$db_elements = DBselect('SELECT * FROM sysmaps_elements WHERE '.DBcondition('screenid', $sysmapids));
				while($element = DBfetch($db_elements)){
					$map_elements[$element['sysmapid']] = $element;
				}
			}
			foreach($map_elements as $element){
				if(!isset($result[$element['sysmapid']]['elementids'])){
					$result[$element['sysmapid']]['elementids'] = array();
					$result[$element['sysmapid']]['elements'] = array();
				}
				$result[$element['sysmapid']]['elementids'][$element['elementid']] = $sitem['elementid'];
				$result[$element['sysmapid']]['elements'][$element['elementid']] = $element;
			}
		}

	return $result;
	}

/**
 * Gets all Map data from DB by Map ID
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $map_data
 * @param string $map_data['mapid']
 * @return array|boolean Map data as array or false if error
 */
	public static function getById($map_data){
		$sql = 'SELECT * FROM sysmaps WHERE sysmapid='.$map_data['sysmapid'];
		$map = DBfetch(DBselect($sql));

		$result = $map ? true : false;
		if($result)
			return $map;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'map with id: '.$map_data['screenid'].' doesn\'t exists.');
			return false;
		}
	}


/**
 * Add Map
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $maps
 * @param string $maps['name']
 * @param array $maps['width']
 * @param int $maps['height']
 * @param string $maps['backgroundid']
 * @param array $maps['label_type']
 * @param int $maps['label_location']
 * @return boolean | array
 */
	public static function add($maps){
		$error = 'Unknown ZABBIX internal error';
		$result_ids = array();
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($maps as $map){

			extract($map);

			$map_db_fields = array(
				'name' => null,
				'width' => 3,
				'height' => 2,
				'backgroundid' => 3,
				'label_type' => 2,
				'label_location' => 3
			);

			if(!check_db_fields($map_db_fields, $map)){
				$result = false;
				$error = 'Wrong fields for map [ '.$map['name'].' ]';
				break;
			}

			$sysmapid = get_dbid('sysmaps', 'sysmapid');
			$sql = "INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location)".
				" VALUES ($sysmapid,".zbx_dbstr($name).", $width, $height, $backgroundid, $label_type, $label_location)";

			$result = DBexecute($sql);

			if(!$result) break;

			$result_ids[$sysmapid] = $sysmapid;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return $result_ids;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);//'Internal zabbix error');
			return false;
		}
	}

/**
 * Update Map
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $maps multidimensional array with Hosts data
 * @param string $maps['sysmapid']
 * @param string $maps['name']
 * @param array $maps['width']
 * @param int $maps['height']
 * @param string $maps['backgroundid']
 * @param array $maps['label_type']
 * @param int $maps['label_location']
 * @return boolean
 */
	public static function update($maps){

		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($maps as $map){

			extract($map);

			$map_db_fields = CMap::getById($map['sysmapid']);

			if(!$map_db_fields){
				$result = false;
				break;
			}

			if(!check_db_fields($map_db_fields, $map)){
				$result = false;
				break;
			}

			$sql = 'UPDATE sysmaps SET name='.zbx_dbstr($name).", width=$width, height=$height, backgroundid=$backgroundid,".
				" label_type=$label_type, label_location=$label_location WHERE sysmapid=$sysmapid";
			$result = DBexecute($sql);

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return true;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}


/**
 * Delete Map
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $sysmapids
 * @return boolean
 */
	public static function delete($sysmapids){
		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($sysmapids as $sysmapid){
			$result = delete_sysmaps_elements_with_sysmapid($sysmapids);

			$res = DBselect('SELECT linkid FROM sysmaps_links WHERE '.DBcondition('sysmapid', $sysmapids));
			while($rows = DBfetch($res)){
				$result &= delete_link($rows['linkid']);
			}

			$result &= DBexecute('DELETE FROM sysmaps_elements WHERE '.DBcondition('sysmapid',$sysmapids));
			$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.sysmapids' AND source='sysmapid' AND ".DBcondition('value_id', $sysmapids));
			$result &= DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid', $sysmapids).' AND resourcetype='.SCREEN_RESOURCE_MAP);
			$result &= DBexecute('DELETE FROM sysmaps WHERE '.DBcondition('sysmapid', $sysmapids));

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}


}
?>
