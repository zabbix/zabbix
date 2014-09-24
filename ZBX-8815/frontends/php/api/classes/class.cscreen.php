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
/**
 * File containing CScreen class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Screens
 */
class CScreen extends CZBXAPI{
/**
 * Get Screen data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] deprecated parameter (for now)
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('screenid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('screens' => 's.screenid'),
			'from' => array('screens' => 'screens s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'			=> null,
			'screenids'			=> null,
			'screenitemids'			=> null,
			'editable'			=> null,
			'nopermissions'			=> null,

// filter
			'filter'			=> null,
			'search'			=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'extendoutput'			=> null,
			'output'			=> API_OUTPUT_REFER,
			'select_screenitems'		=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'			=> '',
			'sortorder'			=> '',
			'limit'				=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_screenitems'])){
				$options['select_screenitems'] = API_OUTPUT_EXTEND;
			}
		}

// editable + PERMISSION CHECK

// screenids
		if(!is_null($options['screenids'])){
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = DBcondition('s.screenid', $options['screenids']);
		}

// screenitemids
		if(!is_null($options['screenitemids'])){
			zbx_value2array($options['screenitemids']);
			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['screenitemid'] = 'si.screenitemid';
			}
			$sql_parts['from']['screens_items'] = 'screens_items si';
			$sql_parts['where']['ssi'] = 'si.screenid=s.screenid';
			$sql_parts['where'][] = DBcondition('si.screenitemid', $options['screenitemids']);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['screens'] = 's.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.screenid) as rowscount');
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('screens s', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('screens s', $options, $sql_parts);
		}

		// node
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();
		if (!isset($options['screenids']) && !isset($options['screenitemids'])) {
			$sql_parts['where']['node'] = DBin_node('s.screenid', $nodeids);
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

		$screenids = array();

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
		if(!empty($sql_parts['where']))		$sql_where.= ' WHERE '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.
				$sql_where.
				$sql_order;

		$res = DBselect($sql, $sql_limit);
		while($screen = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				$result = $screen['rowscount'];
			}
			else{
				$screenids[$screen['screenid']] = $screen['screenid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$screen['screenid']] = array('screenid' => $screen['screenid']);
				}
				else{
					if(!isset($result[$screen['screenid']])) $result[$screen['screenid']]= array();

					if(!is_null($options['select_screenitems']) && !isset($result[$screen['screenid']]['screenitems'])){
						$result[$screen['screenid']]['screenitems'] = array();
					}

					if(isset($screen['screenitemid']) && is_null($options['select_screenitems'])){
						if(!isset($result[$screen['screenid']]['screenitems']))
							$result[$screen['screenid']]['screenitems'] = array();

						$result[$screen['screenid']]['screenitems'][] = array('screenitemid' => $screen['screenitemid']);
						unset($screen['screenitemid']);
					}

					$result[$screen['screenid']] += $screen;
				}
			}
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else if(!empty($result)){
			$groups_to_check = array();
			$hosts_to_check = array();
			$graphs_to_check = array();
			$items_to_check = array();
			$maps_to_check = array();
			$screens_to_check = array();

			$screens_items = array();
			$screen_item_map = array();

			$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			while($sitem = DBfetch($db_sitems)){
				if($sitem['resourceid'] == 0) continue;

				// scrren item map [type][resourceid][screenid]
				if (!isset($screen_item_map[$sitem['resourcetype']])) {
					$screen_item_map[$sitem['resourcetype']] = array();
				}
				if (!isset($screen_item_map[$sitem['resourcetype']][$sitem['resourceid']])) {
					$screen_item_map[$sitem['resourcetype']][$sitem['resourceid']] = array();
				}
				$screen_item_map[$sitem['resourcetype']][$sitem['resourceid']][$sitem['screenid']] = $sitem['screenitemid'];

				$screens_items[$sitem['screenitemid']] = $sitem;

				switch($sitem['resourcetype']){
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$groups_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$hosts_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_GRAPH:
						$graphs_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$items_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_MAP:
						$maps_to_check[] = $sitem['resourceid'];
					break;
					case SCREEN_RESOURCE_SCREEN:
						$screens_to_check[] = $sitem['resourceid'];
					break;
				}
			}


			$groups_to_check = array_unique($groups_to_check);
			$hosts_to_check = array_unique($hosts_to_check);
			$graphs_to_check = array_unique($graphs_to_check);
			$items_to_check = array_unique($items_to_check);
			$maps_to_check = array_unique($maps_to_check);
			$screens_to_check = array_unique($screens_to_check);

// group
			$group_options = array(
								'nodeids' => $nodeids,
								'groupids' => $groups_to_check,
								'editable' => $options['editable']);
			$allowed_groups = CHostgroup::get($group_options);
			$allowed_groups = zbx_objectValues($allowed_groups, 'groupid');

// host
			$host_options = array(
								'nodeids' => $nodeids,
								'hostids' => $hosts_to_check,
								'editable' => $options['editable']);
			$allowed_hosts = CHost::get($host_options);
			$allowed_hosts = zbx_objectValues($allowed_hosts, 'hostid');

// graph
			$graph_options = array(
								'nodeids' => $nodeids,
								'graphids' => $graphs_to_check,
								'editable' => $options['editable']);
			$allowed_graphs = CGraph::get($graph_options);
			$allowed_graphs = zbx_objectValues($allowed_graphs, 'graphid');

// item
			$item_options = array(
								'nodeids' => $nodeids,
								'itemids' => $items_to_check,
								'webitems' => 1,
								'editable' => $options['editable']);
			$allowed_items = CItem::get($item_options);
			$allowed_items = zbx_objectValues($allowed_items, 'itemid');
// map
			$map_options = array(
								'nodeids' => $nodeids,
								'sysmapids' => $maps_to_check,
								'editable' => $options['editable']);
			$allowed_maps = CMap::get($map_options);
			$allowed_maps = zbx_objectValues($allowed_maps, 'sysmapid');
// screen
			$screens_options = array(
								'nodeids' => $nodeids,
								'screenids' => $screens_to_check,
								'editable' => $options['editable']);
			$allowed_screens = CScreen::get($screens_options);
			$allowed_screens = zbx_objectValues($allowed_screens, 'screenid');

			$restr = array();
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO,SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW,SCREEN_RESOURCE_HOSTGROUP_TRIGGERS),
				'resourceids' => array_diff($groups_to_check, $allowed_groups)
			);
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_HOST_TRIGGERS),
				'resourceids' => array_diff($hosts_to_check, $allowed_hosts)
			);
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_GRAPH),
				'resourceids' => array_diff($graphs_to_check, $allowed_graphs)
			);
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT),
				'resourceids' => array_diff($items_to_check, $allowed_items)
			);
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_MAP),
				'resourceids' => array_diff($maps_to_check, $allowed_maps)
			);
			$restr[] = array(
				'types' => array(SCREEN_RESOURCE_SCREEN),
				'resourceids' => array_diff($screens_to_check, $allowed_screens)
			);

			// unset screens with restricted items
			foreach ($restr as $r) {
				foreach($r['resourceids'] as $resourceid){
					foreach ($r['types'] as $type) {
						if (!empty($screen_item_map[$type][$resourceid])) {
							foreach($screen_item_map[$type][$resourceid] as $screenid => $val) {
								unset($result[$screenid]);
								unset($screens_items[$val]);
							}
						}
					}
				}
			}
		}

		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding ScreenItems
		if(!is_null($options['select_screenitems']) && str_in_array($options['select_screenitems'], $subselects_allowed_outputs)){
			if(!isset($screens_items)){
				$screens_items = array();
				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;
				}
			}

			foreach($screens_items as $snum => $sitem){
				if(!isset($result[$sitem['screenid']]['screenitems'])){
					$result[$sitem['screenid']]['screenitems'] = array();
				}

				$result[$sitem['screenid']]['screenitems'][] = $sitem;
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function getObjects($data){
		$options = array(
			'filter' => $data,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($data){
		$keyFields = array(array('screenid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $data),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$sysmaps = self::get($options);

	return !empty($sysmaps);
	}

	protected static function checkItems($screenitems){
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();
		$maps = array();
		$screens = array();

		$resources = array(SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT,
					SCREEN_RESOURCE_MAP,SCREEN_RESOURCE_SCREEN, SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
					SCREEN_RESOURCE_DATA_OVERVIEW);

		foreach($screenitems as $item){
			if((isset($item['resourcetype']) && !isset($item['resourceid'])) ||
				(!isset($item['resourcetype']) && isset($item['resourceid'])))
			{
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			if(isset($item['resourceid']) && ($item['resourceid'] == 0)){
				if(uint_in_array($item['resourcetype'], $resources))
					throw new Exception(S_INCORRECT_RESOURCE_PROVIDED_FOR_SCREEN_ITEM);
				else
					continue;
			}

			switch($item['resourcetype']){
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$hostgroups[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$hosts[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_GRAPH:
					$graphs[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$items[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_MAP:
					$maps[] = $item['resourceid'];
				break;
				case SCREEN_RESOURCE_SCREEN:
					$screens[] = $item['resourceid'];
				break;
			}
		}

		if(!empty($hostgroups)){
			$result = CHostGroup::get(array(
				'groupids' => $hostgroups,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hostgroups as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Host group identity "'.$id.'" provided for Screens item resource');
			}
		}
		if(!empty($hosts)){
			$result = CHost::get(array(
				'hostids' => $hosts,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($hosts as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Host identity "'.$id.'" provided for Screens item resource');
			}
		}
		if(!empty($graphs)){
			$result = CGraph::get(array(
				'graphids' => $graphs,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($graphs as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Graph identity "'.$id.'" provided for Screens item resource');
			}
		}
		if(!empty($items)){
			$result = CItem::get(array(
				'itemids' => $items,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
				'webitems' => 1,
			));
			foreach($items as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Item identity "'.$id.'" provided for Screens item resource');
			}
		}
		if(!empty($maps)){
			$result = CMap::get(array(
				'sysmapids' => $maps,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($maps as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Map identity "'.$id.'" provided for Screens item resource');
			}
		}
		if(!empty($screens)){
			$result = self::get(array(
				'screenids' => $screens,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			));
			foreach($screens as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Incorrect Screen identity "'.$id.'" provided for Screens item resource');
			}
		}
	}

/**
 * Create Screen
 *
 * @param _array $screens
 * @param string $screens['name']
 * @param array $screens['hsize']
 * @param int $screens['vsize']
 * @return array
 */
	public static function create($screens){
		$screens = zbx_toArray($screens);
		$insert_screens = array();
		$insert_screen_items = array();

		try{
			self::BeginTransaction(__METHOD__);

			$newScreenNames = zbx_objectValues($screens, 'name');
// Exists
			$options = array(
				'filter' => array('name' => $newScreenNames),
				'output' => 'extend',
				'nopermissions' => 1
			);
			$db_screens = self::get($options);
			foreach($db_screens as $dbsnum => $db_screen){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$db_screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
//---

			foreach($screens as $snum => $screen){
				$screen_db_fields = array('name' => null);
				if(!check_db_fields($screen_db_fields, $screen)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen [ '.$screen['name'].' ]');
				}

				$iscr = array('name' => $screen['name']);
				if(isset($screen['hsize'])) $iscr['hsize'] = $screen['hsize'];
				if(isset($screen['vsize'])) $iscr['vsize'] = $screen['vsize'];
				$insert_screens[$snum] = $iscr;
			}
			$screenids = DB::insert('screens', $insert_screens);

			foreach($screens as $snum => $screen){
				if(isset($screen['screenitems'])){
					foreach($screen['screenitems'] as $screenitem){
						$screenitem['screenid'] = $screenids[$snum];
						$insert_screen_items[] = $screenitem;
					}
				}
			}

			// save screen items
			CScreenItem::create($insert_screen_items);

			self::EndTransaction(true, __METHOD__);
			return array('screenids' => $screenids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Screen
 *
 * @param _array $screens multidimensional array with Hosts data
 * @param string $screens['screenid']
 * @param int $screens['name']
 * @param int $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean
 */
	public static function update($screens){
		$screens = zbx_toArray($screens);
		$update = array();

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => zbx_objectValues($screens, 'screenid'),
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_screens = self::get($options);
			foreach($screens as $gnum => $screen){
				if(!isset($screen['screenid'], $upd_screens[$screen['screenid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			foreach($screens as $snum => $screen){
				if(isset($screen['name'])){
					$options = array(
						'filter' => array('name' => $screen['name']),
						'preservekeys' => 1,
						'nopermissions' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$exist_screens = self::get($options);
					$exist_screen = reset($exist_screens);

					if($exist_screen && ($exist_screen['screenid'] != $screen['screenid']))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$screenid = $screen['screenid'];
				unset($screen['screenid']);
				if(!empty($screen)){
					$update[] = array(
						'values' => $screen,
						'where' => array('screenid='.$screenid),
					);
				}
			}

			// udpate screen items
			if (isset($screen['screenitems'])) {
				self::replaceItems($screenid, $screen['screenitems']);
			}
			DB::update('screens', $update);

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete Screen
 *
 * @param array $screenids
 * @return boolean
 */
	public static function delete($screenids){
		$screenids = zbx_toArray($screenids);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => $screenids,
				'editable' => 1,
				'preservekeys' => 1,
			);
			$del_screens = self::get($options);
			foreach($screenids as $screenid){
				if(!isset($del_screens[$screenid])) self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			DB::delete('screens_items', DBcondition('screenid', $screenids));
			DB::delete('screens_items', array(DBcondition('resourceid', $screenids), 'resourcetype='.SCREEN_RESOURCE_SCREEN));
			DB::delete('slides', DBcondition('screenid', $screenids));
			DB::delete('screens', DBcondition('screenid', $screenids));

			self::EndTransaction(true, __METHOD__);
			return array('screenids' => $screenids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	/**
	 * Replaces all of the screen items of the given screen with the new ones.
	 *
	 * @param int $screenid        The ID of the target screen
	 * @param array $screenItems   An array of screen items
	 */
	protected static function replaceItems($screenid, $screenItems){
		// fetch the current screen items
		$dbScreenItems = CScreenItem::get(array(
			'screenids' => $screenid,
			'preservekeys' => true,
		));

		// update the new ones
		foreach ($screenItems as &$screenItem) {
			$screenItem['screenid'] = $screenid;
		}
		$result = CScreenItem::updateByPosition($screenItems);

		// deleted the old items
		$deleteItemIds = array_diff(array_keys($dbScreenItems), $result['screenitemids']);
		CScreenItem::delete($deleteItemIds);
	}

}
?>
