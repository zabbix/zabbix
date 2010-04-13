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
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('screens' => 's.screenid'),
			'from' => array('screens s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'pattern'					=> '',
// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'select_screenitems'		=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_screenitems'])){
				$options['select_screenitems'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

// screenids
		if(!is_null($options['screenids'])){
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = DBcondition('s.screenid', $options['screenids']);
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['screens'] = 's.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.screenid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(s.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['screenid'])){
				$sql_parts['where']['screenid'] = 's.screenid='.$options['filter']['screenid'];
			}
			if(isset($options['filter']['name'])){
				$sql_parts['where']['name'] = 's.name='.zbx_dbstr($options['filter']['name']);
			}
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
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.screenid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($screen = DBfetch($res)){
			if(!is_null($options['count'])){
				$result = $screen;
			}
			else{
				$screenids[$screen['screenid']] = $screen['screenid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$screen['screenid']] = array('screenid' => $screen['screenid']);
				}
				else{
					if(!isset($result[$screen['screenid']])) $result[$screen['screenid']]= array();

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

			$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			while($sitem = DBfetch($db_sitems)){
				if($sitem['resourceid'] == 0) continue;

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
/*
sdii($graphs_to_check);
sdii($items_to_check);
sdii($maps_to_check);
sdii($screens_to_check);
//*/
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


			$restr_groups = array_diff($groups_to_check, $allowed_groups);
			$restr_hosts = array_diff($hosts_to_check, $allowed_hosts);
			$restr_graphs = array_diff($graphs_to_check, $allowed_graphs);
			$restr_items = array_diff($items_to_check, $allowed_items);
			$restr_maps = array_diff($maps_to_check, $allowed_maps);
			$restr_screens = array_diff($screens_to_check, $allowed_screens);


/*
SDI('---------------------------------------');
SDII($restr_graphs);
SDII($restr_items);
SDII($restr_maps);
SDII($restr_screens);
SDI('/////////////////////////////////');
//*/
// group
			foreach($restr_groups as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO,SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW,SCREEN_RESOURCE_HOSTGROUP_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// host
			foreach($restr_hosts as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_HOST_TRIGGERS))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// graph
			foreach($restr_graphs as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_GRAPH)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// item
			foreach($restr_items as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if(($screen_item['resourceid'] == $resourceid) &&
						uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))
					){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// map
			foreach($restr_maps as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_MAP)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
// screen
			foreach($restr_screens as $resourceid){
				foreach($screens_items as $screen_itemid => $screen_item){
					if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN)){
						unset($result[$screen_item['screenid']]);
						unset($screens_items[$screen_itemid]);
					}
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding ScreenItems
		if(!is_null($options['select_screenitems']) && str_in_array($options['select_screenitems'], $subselects_allowed_outputs)){
			if(!isset($screens_items)){
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

/**
 * Get Sysmap IDs by Sysmap params
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $sysmap_data
 * @param array $sysmap_data['name']
 * @param array $sysmap_data['sysmapid']
 * @return string sysmapid
 */

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
		$options = array(
			'filter' => $data,
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1
		);

		if(isset($data['node']))
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		else if(isset($data['nodeids']))
			$options['nodeids'] = $data['nodeids'];

		$sysmaps = self::get($options);

	return !empty($sysmaps);
	}
/**
 * Add Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $screens
 * @param string $screens['name']
 * @param array $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean | array
 */
	public static function create($screens){
		$screens = zbx_toArray($screens);
		$screenid = array();

		$errors = array();
		$result = false;

		try{
			$transaction = self::BeginTransaction(__METHOD__);
			foreach($screens as $snum => $screen){

				$screen_db_fields = array(
					'name' => null,
					'hsize' => 2,
					'vsize' => 2,
					'screenitems' => array()
				);

				if(!check_db_fields($screen_db_fields, $screen)){
					$result = false;
					$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for screen [ '.$screen['name'].' ]');
					break;
				}

				$sql = 'SELECT screenid '.
					' FROM screens '.
					' WHERE name='.zbx_dbstr($screen['name']).
						' AND '.DBin_node('screenid', false);
				if(DBfetch(DBselect($sql))){
					$result = false;
					$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					break;
				}

				$screenid = get_dbid('screens', 'screenid');
				$values = array(
					'screenid' => $screenid,
					'name' => zbx_dbstr($screen['name']),
					'hsize' => $screen['hsize'],
					'vsize' => $screen['vsize']
				);
				$sql = 'INSERT INTO screens ('.implode(',',array_keys($values)).') '.
						' VALUES ('.implode(',',array_values($values)).')';
				$result = DBexecute($sql);

				if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Failed on screen['.$screen['name'].'] creation');

				$data = array(
					'screenids' => array($screenid),
					'screenitems' => $screen['screenitems']
				);

				$result = self::addItems($data);
				if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Failed on screen['.$screen['name'].'] creation');

				$screenids[] = $screenid;
			}

			$result = self::EndTransaction($result, __METHOD__);
			if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Transaction failed on screens creation');

			return array('screenids' => $screenids);
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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
		$screenids = array();

		$result = true;
		$errors = array();

		$options = array(
			'screenids'=>zbx_objectValues($screens, 'screenid'),
			'editable'=>1,
			'output'=>API_OUTPUT_EXTEND,
			'preservekeys'=>1
		);
		$upd_screens = self::get($options);

		foreach($screens as $gnum => $screen){
			if(!isset($screen['screenid']) || !isset($upd_screens[$screen['screenid']])){
				throw new APIException(ZBX_API_ERROR_PERMISSIONS, 'No permisssions for screen update');
			}

			$upd_screens[$screen['screenid']]['screenitems'] = array();
			$screenids[] = $screen['screenid'];
		}

		try{
			$transaction = self::BeginTransaction(__METHOD__);
			foreach($screens as $snum => $screen){
				$screen_db_fields = $upd_screens[$screen['screenid']];

				if(!check_db_fields($screen_db_fields, $screen)){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen [ '.$screen['name'].' ]');
				}

				$options = array(
					'filter' => array('name' => $screen['name']),
					'preservekeys' => 1,
					'nopermissions' => 1
				);
				$exist_screens = self::get($options);
				foreach($exist_screens as $esnum => $exist_screen){
					if(bccomp($exist_screen['screenid'], $screen['screenid']) != 0){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					}
				}

				$values = array(
					'name' => zbx_dbstr($screen['name']),
					'hsize' => $screen['hsize'],
					'vsize' => $screen['vsize']
				);

				$sql = 'UPDATE screens '.
						' SET '.zbx_implodeHash(',','=',$values).
						' WHERE	screenid='.$screen['screenid'];
				$result = DBexecute($sql);

// Screen items
				$data = array(
					'screenids' => array($screen['screenid']),
				);
				$result = self::deleteItems($data);
				if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Failed on screen['.$screen['name'].'] update');

				$data = array(
					'screenids' => array($screen['screenid']),
					'screenitems' => $screen['screenitems']
				);

				$result = self::addItems($data);
				if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Failed on screen['.$screen['name'].'] update');
			}

			$result = self::EndTransaction($result, __METHOD__);
			if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Transaction failed on screens update');

			return array('screenids' => $screenids);
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}


/**
 * Delete Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screens
 * @param array $screens[0,...]['screenid']
 * @return boolean
 */
	public static function delete($screens){
		$screens = zbx_toArray($screens);
		$screenids = zbx_objectValues($screens, 'screenid');
		$result = true;

		$options = array(
			'screenids'=>zbx_objectValues($screens, 'screenid'),
			'editable'=>1,
			'preservekeys'=>1
		);
		$del_screens = self::get($options);

		foreach($screens as $gnum => $screen){
			if(!isset($del_screens[$screen['screenid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$screenids[] = $screen['screenid'];
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($screenids)){
			$result = DBexecute('DELETE FROM screens_items WHERE '.DBcondition('screenid'.$screenids));
			$result &= DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid'.$screenids).' AND resourcetype='.SCREEN_RESOURCE_SCREEN);
			$result &= DBexecute('DELETE FROM slides WHERE '.DBcondition('screenid'.$screenids));
			$result &= DBexecute('DELETE FROM profiles '.
								' WHERE idx='.zbx_dbstr('web.favorite.screenids').
									' AND source='.zbx_dbstr('screenid').
									' AND '.DBcondition('value_id'.$screenids));
			$result &= DBexecute('DELETE FROM screens WHERE '.DBcondition('screenid'.$screenids));
			if(!$result) break;
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ screenids ]');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('screenids' => $screenids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

/**
 * add ScreenItem
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screen_items
 * @param int $screen_items['screenid']
 * @param int $screen_items['resourcetype']
 * @param int $screen_items['x']
 * @param int $screen_items['y']
 * @param int $screen_items['resourceid']
 * @param int $screen_items['width']
 * @param int $screen_items['height']
 * @param int $screen_items['colspan']
 * @param int $screen_items['rowspan']
 * @param int $screen_items['elements']
 * @param int $screen_items['valign']
 * @param int $screen_items['halign']
 * @param int $screen_items['style']
 * @param int $screen_items['url']
 * @param int $screen_items['dynamic']
 * @return boolean
 */
	public static function addItems($data){
		$result = true;

		$screenids = $data['screenids'];
		$screenItems = $data['screenitems'];

		try{
			$transaction = self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => $screenids,
				'editable' => 1,
				'preservekeys' => 1
			);
			$upd_screens = self::get($options);
			foreach($screenids as $snum => $screenid){
				if(!isset($upd_screens[$screenid])){
					throw new APIException(ZBX_API_ERROR_PERMISSIONS, 'No permisssions for screen update');
				}
			}

			foreach($screenItems as $sinum => $screenItem){
				$db_fields = array(
					'resourcetype' => null,
					'resourceid'=> null,
					'width'=>0,
					'height'=>0,
					'x'=>null,
					'y'=>null,
					'colspan'=>0,
					'rowspan'=>0,
					'elements'=>0,
					'valign'=>0,
					'halign'=>0,
					'style'=>0,
					'url'=>'',
					'dynamic'=>0
				);

				if(!check_db_fields($db_fields, $screenItem)){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for screen items');
				}

				foreach($screenids as $snum => $screenid){
					$screenitemid = get_dbid('screens_items', 'screenitemid');
					$values = array(
						'screenitemid' => $screenitemid,
						'screenid' => $screenid,
						'resourcetype' => $screenItem['resourcetype'],
						'resourceid' => $screenItem['resourceid'],
						'width' => $screenItem['width'],
						'height' => $screenItem['height'],
						'x' => $screenItem['x'],
						'y' => $screenItem['y'],
						'colspan' => $screenItem['colspan'],
						'rowspan' => $screenItem['rowspan'],
						'elements' => $screenItem['elements'],
						'valign' => $screenItem['valign'],
						'halign' => $screenItem['halign'],
						'style' => $screenItem['style'],
						'url' => zbx_dbstr($screenItem['url']),
						'dynamic' => $screenItem['dynamic']
					);


					$sql = 'INSERT INTO screens_items ('.implode(',',array_keys($values)).') '.
							' VALUES ('.implode(',',array_values($values)).')';
					$result = DBexecute($sql);

					if(!$result) break;
				}
			}

			$result = self::EndTransaction($result, __METHOD__);
			if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Transaction failed on screen item creation');

			return true;
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * delete ScreenItem
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screen_itemids
 * @return boolean
 */
	public static function deleteItems($data){
		$result = true;

		$screenids = array();
		$screenitemids = array();
		$perm_screenids = array();

		if(isset($data['screenids'])){
			$screenids = zbx_toArray($data['screenids']);
			$perm_screenids = $screenids;
		}

		if(isset($data['screenitemids'])){
			$screenitemids = zbx_toArray($data['screenitemids']);
			$sql = 'SELECT DISTINCT si.screenidd '.
					' FROM screens_items si '.
					' WHERE '.DBcondition('si.screenitemid',$screenitemids);
			$res = DBselect($sql);
			while($screen = DBfetch($res)){
				$perm_screenids[] = $screen['screenid'];
			}
		}

		if(empty($perm_screenids)) return true;

		try{
			$transaction = self::BeginTransaction(__METHOD__);

			$options = array(
				'screenids' => $perm_screenids,
				'editable' => 1,
				'preservekeys' => 1
			);
			$del_screens = self::get($options);
			foreach($perm_screenids as $snum => $screenid){
				if(!isset($del_screens[$screenid])){
					throw new APIException(ZBX_API_ERROR_PERMISSIONS, 'No permisssions for screen update');
				}
			}
			
			if(!empty($screenids))	$result&= DBexecute('DELETE FROM screens_items WHERE '.DBcondition('screenid', $screenids));
			if(!empty($screenitemids)) $result&= DBexecute('DELETE FROM screens_items WHERE '.DBcondition('screenitemid', $screenitemids));

			$result = self::EndTransaction($result, __METHOD__);
			if(!$result) throw new APIException(ZBX_API_ERROR_INTERNAL, 'Transaction failed on screen item deletion');

			return true;
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
}
?>