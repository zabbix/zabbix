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
			'pattern'					=> '',
// OutPut
			'extendoutput'				=> null,
			'output'				=> API_OUTPUT_REFER,
			'select_groups'				=> null,
			'select_templates'			=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_groups'])){
				$options['select_groups'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_items'])){
				$options['select_items'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_templates'])){
				$options['select_templates'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_triggers'])){
				$options['select_triggers'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_graphs'])){
				$options['select_graphs'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_applications'])){
				$options['select_applications'] = API_OUTPUT_EXTEND;
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
		else{
			if(!empty($result)){
				$graphs_to_check = array();
				$items_to_check = array();
				$maps_to_check = array();
				$screens_to_check = array();
				$screens_items = array();

				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;

					if($sitem['resourceid'] == 0) continue;

					switch($sitem['resourcetype']){
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

				$graph_options = array(
									'nodeids' => $nodeids,
									'graphids' => $graphs_to_check,
									'editable' => $options['editable']);
				$allowed_graphs = CGraph::get($graph_options);
				$allowed_graphs = zbx_objectValues($allowed_graphs, 'graphid');

				$item_options = array(
									'nodeids' => $nodeids,
									'itemids' => $items_to_check,
									'editable' => $options['editable']);
				$allowed_items = CItem::get($item_options);
				$allowed_items = zbx_objectValues($allowed_items, 'itemid');

				$map_options = array(
									'nodeids' => $nodeids,
									'sysmapids' => $maps_to_check,
									'editable' => $options['editable']);
				$allowed_maps = CMap::get($map_options);
				$allowed_maps = zbx_objectValues($allowed_maps, 'sysmapid');

				$screens_options = array(
									'nodeids' => $nodeids,
									'screenids' => $screens_to_check,
									'editable' => $options['editable']);
				$allowed_screens = CScreen::get($screens_options);
				$allowed_screens = zbx_objectValues($allowed_screens, 'screenid');


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
				foreach($restr_graphs as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if(($screen_item['resourceid'] == $resourceid) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_GRAPH)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_items as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if(($screen_item['resourceid'] == $resourceid) &&
							(uint_in_array($screen_item['resourcetype'], array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT)))
						){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_maps as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_MAP)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_screens as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding Items
		if(!is_null($options['select_items']) && str_in_array($options['select_items'], $subselects_allowed_outputs)){
			if(!isset($screens_items)){
				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;
				}
			}

			foreach($screens_items as $sitem){
				if(!isset($result[$sitem['screenid']]['items'])){
					$result[$sitem['screenid']]['items'] = array();
				}

				$result[$sitem['screenid']]['items'][] = $sitem;
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
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

		self::BeginTransaction(__METHOD__);
		foreach($screens as $snum => $screen){

			$screen_db_fields = array(
				'name' => null,
				'hsize' => 3,
				'vsize' => 2
			);

			if(!check_db_fields($screen_db_fields, $screen)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for screen [ '.$screen['name'].' ]');
				break;
			}

			$sql = 'SELECT screenid FROM screens WHERE name='.zbx_dbstr($screen['name']).' AND '.DBin_node('screenid', false);
			if(DBfetch(DBselect($sql))){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				break;
			}

			$screenid = get_dbid('screens', 'screenid');
			$sql = 'INSERT INTO screens (screenid, name, hsize, vsize) '.
				' VALUES ('.$screenid.','.zbx_dbstr($screen['name']).','.$screen['hsize'].','.$screen['vsize'].')';
			$result = DBexecute($sql);

			if(!$result) break;

			$screenids[] = $screenid;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_screens = self::get(array('screenids'=>$screenids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $new_screens;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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

		$upd_screens = self::get(array('screenids'=>zbx_objectValues($screens, 'screenid'),
											'editable'=>1,
											'extendoutput'=>1,
											'preservekeys'=>1));

		foreach($screens as $gnum => $screen){
			if(!isset($upd_screens[$screen['screenid']])){

				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$screenids[] = $screen['screenid'];
		}

		self::BeginTransaction(__METHOD__);
		foreach($screens as $snum => $screen){

			$screen_db_fields = CScreen::get(array('screenids' => $screen['screenid'], 'editable' => 1, 'extendoutput' => 1));
			$screen_db_fields = reset($screen_db_fields);

			if(!$screen_db_fields){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_SCREEN.' '.S_WITH_ID_SMALL.' [ '.$screen['screenid'].' ] '.S_DOESNT_EXIST);
				break;
			}

			if(!check_db_fields($screen_db_fields, $screen)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for screen [ '.$screen['name'].' ]');
				break;
			}

			$sql = 'SELECT screenid '.
				' FROM screens '.
				' WHERE name='.zbx_dbstr($screen['name']).
					' AND '.DBin_node('screenid', false).
					' AND screenid<>'.$screen['screenid'];
			if(DBfetch(DBselect($sql))){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_SCREEN.' [ '.$screen['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				break;
			}

			$sql = 'UPDATE screens SET name='.zbx_dbstr($screen['name']).', hsize='.$screen['hsize'].',vsize='.$screen['vsize'].
				' WHERE screenid='.$screen['screenid'];
			$result = DBexecute($sql);

			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_screens = self::get(array('screenids'=>$screenids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $upd_screens;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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

		$del_screens = self::get(array('screenids'=>zbx_objectValues($screens, 'screenid'),
											'editable'=>1,
											'extendoutput'=>1,
											'preservekeys'=>1));

		foreach($screens as $gnum => $screen){
			if(!isset($del_screens[$screen['screenid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$screenids[] = $screen['screenid'];
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($screenids)){
			foreach($screenids as $snum => $screenid){
				$result = DBexecute('DELETE FROM screens_items WHERE screenid='.$screenid);
				$result &= DBexecute('DELETE FROM screens_items WHERE resourceid='.$screenid.' AND resourcetype='.SCREEN_RESOURCE_SCREEN);
				$result &= DBexecute('DELETE FROM slides WHERE screenid='.$screenid);
				$result &= DBexecute('DELETE FROM profiles '.
									' WHERE idx='.zbx_dbstr('web.favorite.screenids').
										' AND source='.zbx_dbstr('screenid').
										' AND value_id='.$screenid);
				$result &= DBexecute('DELETE FROM screens WHERE screenid='.$screenid);
				if(!$result) break;
			}
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ screenids ]');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return zbx_cleanHashes($del_screens);
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
	public static function setItems($screen_items){

		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($screen_items as $snum => $screen_item){

			extract($screen_item);
			$sql="DELETE FROM screens_items WHERE screenid=$screenid AND x=$x AND y=$y";
			DBexecute($sql);

			$screenitemid = get_dbid('screens_items', 'screenitemid');
			$sql = 'INSERT INTO screens_items '.
				'(screenitemid, resourcetype, screenid, x, y, resourceid, width, height, '.
				' colspan, rowspan, elements, valign, halign, style, url, dynamic) '.
				' VALUES '.
				"($screenitemid, $resourcetype, $screenid, $x, $y, $resourceid, $width, $height, $colspan, ".
				"$rowspan, $elements, $valign, $halign, $style, ".zbx_dbstr($url).", $dynamic)";
			$result = DBexecute($sql);

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
	public static function deleteItems($screen_itemids){
		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($screen_items as $snum => $screen_itemid){
			$sql='DELETE FROM screens_items WHERE screenitemid='.$screen_itemid;
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
