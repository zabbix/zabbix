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
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['sortorder']
 * @param string $options['sortfield']
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('sysmaps' => 's.sysmapid'),
			'from' => array('sysmaps' => 'sysmaps s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'sysmapids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'select_selements'			=> null,
			'select_links'				=> null,
			'countOutput'				=> null,
			'expand_urls' 				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_selements'])){
				$options['select_selements'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_links'])){
				$options['select_links'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// sysmapids
		if(!is_null($options['sysmapids'])){
			zbx_value2array($options['sysmapids']);
			$sql_parts['where']['sysmapid'] = DBcondition('s.sysmapid', $options['sysmapids']);
		}

// search
		if(!is_null($options['search'])){
			zbx_db_search('sysmaps s', $options, $sql_parts);
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['sysmapid']) && !is_null($options['filter']['sysmapid'])){
				zbx_value2array($options['filter']['sysmapid']);
				$sql_parts['where']['sysmapid'] = DBcondition('s.sysmapid', $options['filter']['sysmapid']);
			}

			if(isset($options['filter']['name']) && !is_null($options['filter']['name'])){
				zbx_value2array($options['filter']['name']);
				$sql_parts['where']['name'] = DBcondition('s.name', $options['filter']['name'], false, true);
			}
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['sysmaps'] = 's.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.sysmapid) as rowscount');
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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.sysmapid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($sysmap = DBfetch($res)){
			if($options['countOutput']){
				$result = $sysmap['rowscount'];
			}
			else{
				$sysmapids[$sysmap['sysmapid']] = $sysmap['sysmapid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$sysmap['sysmapid']] = array('sysmapid' => $sysmap['sysmapid']);
				}
				else{
					if(!isset($result[$sysmap['sysmapid']])) $result[$sysmap['sysmapid']]= array();

					if(!is_null($options['select_selements']) && !isset($result[$sysmap['sysmapid']]['selements'])){
						$result[$sysmap['sysmapid']]['selements'] = array();
					}
					if(!is_null($options['select_links']) && !isset($result[$sysmap['sysmapid']]['links'])){
						$result[$sysmap['sysmapid']]['links'] = array();
					}
					if(!isset($result[$sysmap['sysmapid']]['urls'])){
						$result[$sysmap['sysmapid']]['urls'] = array();
					}

					$result[$sysmap['sysmapid']] += $sysmap;
				}
			}
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			if(!empty($result)){

				$link_triggers = array();
				$sql = 'SELECT slt.triggerid, sl.sysmapid'.
					' FROM sysmaps_link_triggers slt, sysmaps_links sl'.
					' WHERE '.DBcondition('sl.sysmapid', $sysmapids).
						' AND sl.linkid=slt.linkid';
				$db_link_triggers = DBselect($sql);
				while($link_trigger = DBfetch($db_link_triggers)){
					$link_triggers[$link_trigger['sysmapid']] = $link_trigger['triggerid'];
				}

				$options = array(
					'triggerids' => $link_triggers,
					'editable' => $options['editable'],
					'output' => API_OUTPUT_SHORTEN,
					'preservekeys' => 1
				);
				$all_triggers = CTrigger::get($options);
				foreach($link_triggers as $id => $triggerid){
					if(!isset($all_triggers[$triggerid])){
						unset($result[$id]);
						unset($sysmapids[$id]);
					}
				}


				$hosts_to_check = array();
				$maps_to_check = array();
				$triggers_to_check = array();
				$host_groups_to_check = array();

				$selements = array();
				$db_selements = DBselect('SELECT * FROM sysmaps_elements WHERE '.DBcondition('sysmapid', $sysmapids));
				while($selement = DBfetch($db_selements)){
					$selements[$selement['selementid']] = $selement;

					switch($selement['elementtype']){
						case SYSMAP_ELEMENT_TYPE_HOST:
							$hosts_to_check[] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$maps_to_check[] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$triggers_to_check[] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$host_groups_to_check[] = $selement['elementid'];
						break;
					}
				}

// sdi($hosts_to_check);
// sdi($maps_to_check);
// sdi($triggers_to_check);
// sdi($host_groups_to_check);

				$nodeids = get_current_nodeid(true);
				$host_options = array('hostids' => $hosts_to_check,
									'nodeids' => $nodeids,
									'editable' => $options['editable'],
									'preservekeys'=>1);
				$allowed_hosts = CHost::get($host_options);
				$allowed_hosts = zbx_objectValues($allowed_hosts, 'hostid');

				$map_options = array('sysmapids' => $maps_to_check,
									'nodeids' => $nodeids,
									'editable' => $options['editable'],
									'preservekeys'=>1);
				$allowed_maps = self::get($map_options);
				$allowed_maps = zbx_objectValues($allowed_maps, 'sysmapid');

				$trigger_options = array('triggerids' => $triggers_to_check,
									'nodeids' => $nodeids,
									'editable' => $options['editable'],
									'preservekeys'=>1);
				$allowed_triggers = CTrigger::get($trigger_options);
				$allowed_triggers = zbx_objectValues($allowed_triggers, 'triggerid');

				$hostgroup_options = array('groupids' => $host_groups_to_check,
									'nodeids' => $nodeids,
									'editable' => $options['editable'],
									'preservekeys'=>1);
				$allowed_host_groups = CHostGroup::get($hostgroup_options);
				$allowed_host_groups = zbx_objectValues($allowed_host_groups, 'groupid');

				$restr_hosts = array_diff($hosts_to_check, $allowed_hosts);
				$restr_maps = array_diff($maps_to_check, $allowed_maps);
				$restr_triggers = array_diff($triggers_to_check, $allowed_triggers);
				$restr_host_groups = array_diff($host_groups_to_check, $allowed_host_groups);
/*
SDI('-------------------------------------');
SDII($restr_hosts);
SDII($restr_maps);
SDII($restr_triggers);
SDII($restr_host_groups);
SDI('///////////////////////////////////////');
//*/
				foreach($restr_hosts as $elementid){
					foreach($selements as $selementid => $selement){
						if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) && ($selement['elementid'] == $elementid)){
							unset($result[$selement['sysmapid']]);
							unset($selements[$selementid]);
						}
					}
				}

				foreach($restr_maps as $elementid){
					foreach($selements as $selementid => $selement){
						if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) && ($selement['elementid'] == $elementid)){
							unset($result[$selement['sysmapid']]);
							unset($selements[$selementid]);
						}
					}
				}

				foreach($restr_triggers as $elementid){
					foreach($selements as $selementid => $selement){
						if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) && ($selement['elementid'] == $elementid)){
							unset($result[$selement['sysmapid']]);
							unset($selements[$selementid]);
						}
					}
				}

				foreach($restr_host_groups as $elementid){
					foreach($selements as $selementid => $selement){
						if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) && ($selement['elementid'] == $elementid)){
							unset($result[$selement['sysmapid']]);
							unset($selements[$selementid]);
						}
					}
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Elements
		if(!is_null($options['select_selements']) && str_in_array($options['select_selements'], $subselects_allowed_outputs)){
			if(!isset($selements)){
				$selements = array();

				$sql = 'SELECT se.* '.
						' FROM sysmaps_elements se '.
						' WHERE '.DBcondition('se.sysmapid', $sysmapids);
				$db_selements = DBselect($sql);
				while($selement = DBfetch($db_selements)){
					$selement['urls'] = array();
					$selements[$selement['selementid']] = $selement;
				}
			}

			$sql = 'SELECT sysmapelementurlid, selementid, name, url  '.
					' FROM sysmap_element_url '.
					' WHERE '.DBcondition('selementid', array_keys($selements));
			$db_selement_urls = DBselect($sql);
			while($selement_url = DBfetch($db_selement_urls)){
				$selements[$selement_url['selementid']]['urls'][] = $selement_url;
			}

			foreach($selements as $num => $selement){
				if(!isset($result[$selement['sysmapid']]['selements'])){
					$result[$selement['sysmapid']]['selements'] = array();
				}
				$result[$selement['sysmapid']]['selements'][$selement['selementid']] = $selement;
			}
		}

// Adding Links
		if(!is_null($options['select_links']) && str_in_array($options['select_links'], $subselects_allowed_outputs)){
			$linkids = array();
			$map_links = array();

			$sql = 'SELECT sl.* FROM sysmaps_links sl WHERE '.DBcondition('sl.sysmapid', $sysmapids);
			$db_links = DBselect($sql);
			while($link = DBfetch($db_links)){
				$link['linktriggers'] = array();

				$map_links[$link['linkid']] = $link;
				$linkids[$link['linkid']] = $link['linkid'];
			}

			$sql = 'SELECT DISTINCT slt.* FROM sysmaps_link_triggers slt WHERE '.DBcondition('slt.linkid', $linkids);
			$db_link_triggers = DBselect($sql);
			while($link_trigger = DBfetch($db_link_triggers)){
				$map_links[$link_trigger['linkid']]['linktriggers'][] = $link_trigger;
			}

			foreach($map_links as $num => $link){
				if(!isset($result[$link['sysmapid']]['links'])){
					$result[$link['sysmapid']]['links'] = array();
				}

				$result[$link['sysmapid']]['links'][] = $link;
			}
		}

// Adding Urls
		if($options['output'] != API_OUTPUT_SHORTEN){
			$sql = 'SELECT su.* FROM sysmap_url su WHERE '.DBcondition('su.sysmapid', $sysmapids);
			$db_urls = DBselect($sql);
			while($url = DBfetch($db_urls)){
				$sysmapid = $url['sysmapid'];
				unset($url['sysmapid']);
				$result[$sysmapid]['urls'][] = $url;
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Sysmap IDs by Sysmap params
 *
 * @param array $sysmap_data
 * @param array $sysmap_data['name']
 * @param array $sysmap_data['sysmapid']
 * @return string sysmapid
 */
	public static function getObjects($sysmapData){
		$options = array(
			'filter' => $sysmapData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($sysmapData['node']))
			$options['nodeids'] = getNodeIdByNodeName($sysmapData['node']);
		else if(isset($sysmapData['nodeids']))
			$options['nodeids'] = $sysmapData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('sysmapid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Add Map
 *
 * @param _array $maps
 * @param string $maps['name']
 * @param array $maps['width']
 * @param int $maps['height']
 * @param string $maps['backgroundid']
 * @param string $maps['highlight']
 * @param array $maps['label_type']
 * @param int $maps['label_location']
 * @return boolean | array
 */
	public static function create($maps){
		$maps = zbx_toArray($maps);

		try{
			self::BeginTransaction(__METHOD__);

			$newMapNames = zbx_objectValues($maps, 'name');
// Exists
			$options = array(
				'filter' => array('name' => $newMapNames),
				'output' => 'extend',
				'nopermissions' => 1
			);
			$db_maps = self::get($options);
			foreach($db_maps as $dbmnum => $db_map){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_MAP.' [ '.$db_map['name'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
//--

			foreach($maps as $mnum => $map){
				$map_db_fields = array(
					'name' => null,
				);
				if(!check_db_fields($map_db_fields, $map)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for map');
				}

				if(self::exists(array('name' => $map['name']))){
					self::exception(ZBX_API_ERROR_PARAMETERS,'Map [ '.$map['name'].' ] already exists.');
				}
			}
			$sysmapids = DB::insert('sysmaps', $maps);

			$data_elements = array();
			$insert_urls = array();
			foreach($sysmapids as $mnum => $sysmapid){
				if(isset($maps[$mnum]['urls'])){
					foreach($maps[$mnum]['urls'] as $url){
						$url['sysmapid'] = $sysmapid;
						$insert_urls[] = $url;
					}
				}

				if(isset($maps[$mnum]['selements'])){
					foreach($maps[$mnum]['selements'] as $selement){
						$selement['sysmapid'] = $sysmapid;
						$data_elements[] = $selement;
					}
				}
			}
			DB::insert('sysmap_url', $insert_urls);

			self::addElements($data_elements);

			self::EndTransaction(true, __METHOD__);

			return array('sysmapids' => $sysmapids);
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
 * Update Map
 *
 * @param array $maps multidimensional array with Hosts data
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
		$maps = zbx_toArray($maps);
		$sysmapids = zbx_objectValues($maps, 'sysmapid');

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'sysmapids' => $sysmapids,
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
			);
			$db_sysmaps = self::get($options);
			foreach($maps as $mnum => $map){
				if(!isset($db_sysmaps[$map['sysmapid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Map with ID ['.$map['sysmapid'].'] does not exist');
				}
			}

			$update = array();
			$urlidsToDelete = $urlsToUpdate = $urlsToAdd = array();
			foreach($maps as $mnum => $map){
				if(isset($map['name'])){
					$options = array(
						'filter' => array(
							'name' => $map['name']
						),
						'output' => API_OUTPUT_SHORTEN,
						'editable' => 1,
						'nopermissions' => 1
					);
					$map_exists = self::get($options);
					$map_exists = reset($map_exists);

					if($map_exists && ($map_exists['sysmapid'] != $map['sysmapid'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Map [ '.$map['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					}
				}

				$update[] = array(
					'values' => $map,
					'where' => array('sysmapid='.$map['sysmapid']),
				);


				if(isset($map['urls'])){
					foreach($db_sysmaps[$map['sysmapid']]['urls'] as $existing_url){
						$toUpdate = false;
						foreach($map['urls'] as $unum => $new_url){
							if($existing_url['name'] == $new_url['name']){
								$toUpdate = array(
									'values' => $new_url,
									'where' => array('sysmapurlid='.$existing_url['sysmapurlid'])
								);
								unset($map['urls'][$unum]);

								break;
							}
						}

						if($toUpdate){
							$urlsToUpdate[] = $toUpdate;
						}
						else{
							$urlidsToDelete[] = $existing_url['sysmapurlid'];
						}
					}

					foreach($map['urls'] as $newUrl){
						$newUrl['sysmapid'] = $map['sysmapid'];
						$urlsToAdd[] = $newUrl;
					}

				}
			}
			DB::update('sysmaps', $update);

			if(!empty($urlidsToDelete))
				DB::delete('sysmap_url', DBcondition('sysmapurlid', $urlidsToDelete));
			DB::update('sysmap_url', $urlsToUpdate);
			DB::insert('sysmap_url', $urlsToAdd);

			self::EndTransaction(true, __METHOD__);
			return array('sysmapids' => $sysmapids);
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
 * Delete Map
 *
 * @param array $sysmaps
 * @param array $sysmaps['sysmapid']
 * @return boolean
 */
	public static function delete($sysmapids){

		try{
			self::BeginTransaction(__METHOD__);

// Permissions
			$options = array(
				'sysmapids' => $sysmapids,
				'editable' => 1,
				'preservekeys' => 1
			);
			$del_sysmaps = self::get($options);
			foreach($sysmapids as $snum => $sysmapid){
				if(!isset($del_sysmaps[$sysmapid]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//---

// delete maps from selements of other maps
			DB::delete('sysmaps_elements', array(
				DBcondition('elementid', $sysmapids),
				'elementtype='.SYSMAP_ELEMENT_TYPE_MAP
			));
//----
			DB::delete('sysmaps', array(DBcondition('sysmapid', $sysmapids)));

			self::EndTransaction(true, __METHOD__);
			return array('sysmapids' => $sysmapids);
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
 * addLinks Map
 *
 * @param array $links
 * @param array $links[0,...]['sysmapid']
 * @param array $links[0,...]['selementid1']
 * @param array $links[0,...]['selementid2']
 * @param array $links[0,...]['drawtype']
 * @param array $links[0,...]['color']
 * @return boolean
 */
	public static function addLinks($links){
		$result_links = array();
		$links = zbx_toArray($links);

		try{
			self::BeginTransaction(__METHOD__);

			foreach($links as $lnum => $link){
				$link_db_fields = array(
					'sysmapid' => null,
					'label' => '',
					'selementid1' => null,
					'selementid2' => null,
					'drawtype' => 2,
					'color' => 3
				);

				if(!check_db_fields($link_db_fields, $link)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for link');
				}

				$linkid = add_link($link);
				if(!$linkid){
					self::exception();
				}

				$new_link = array('linkid' => $linkid);
				$result_links[] = array_merge($new_link, $link);
			}

			self::EndTransaction(true, __METHOD__);
			return $result_links;
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
 * Add Element to Sysmap
 *
 * @param array $elements[0,...]['sysmapid']
 * @param array $elements[0,...]['elementid']
 * @param array $elements[0,...]['elementtype']
 * @param array $elements[0,...]['label']
 * @param array $elements[0,...]['x']
 * @param array $elements[0,...]['y']
 * @param array $elements[0,...]['iconid_off']
 * @param array $elements[0,...]['iconid_unknown']
 * @param array $elements[0,...]['iconid_on']
 * @param array $elements[0,...]['iconid_disabled']
 * @param array $elements[0,...]['url']
 * @param array $elements[0,...]['label_location']
 */
	public static function addElements($selements){
		$selements = zbx_toArray($selements);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'sysmapids' => zbx_objectValues($selements, 'sysmapid'),
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN,
			);
			$upd_maps = self::get($options);

			foreach($selements as $snumm => $selement){
				if(!isset($upd_maps[$selement['sysmapid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}

			foreach($selements as $snumm => $selement){
				$selement_db_fields = array(
					'sysmapid' => null,
					'elementid' => null,
					'elementtype' => null,
				);
				if(!check_db_fields($selement_db_fields, $selement)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for element');
				}

				if(check_circle_elements_link($selement['sysmapid'],$selement['elementid'],$selement['elementtype'])){
					self::exception(S_CIRCULAR_LINK_CANNOT_BE_CREATED.' "'.$selement['label'].'"');
				}
			}
			$selementids = DB::insert('sysmaps_elements', $selements);

			$insert_urls = array();
			foreach($selementids as $snum => $selementid){
				if(isset($selements[$snum]['urls'])){
					foreach($selements[$snum]['urls'] as $url){
						$url['selementid'] = $selementid;
						$insert_urls[] = $url;
					}
				}

			}
			DB::insert('sysmap_element_url', $insert_urls);

			self::EndTransaction(true, __METHOD__);

			return $selementids;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);

			$errors = $e->getErrors();
			$error = reset($errors);

			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}


/**
 * Update Element to Sysmap
 *
 * @param array $elements[0,...]['selementid']
 * @param array $elements[0,...]['sysmapid']
 * @param array $elements[0,...]['elementid']
 * @param array $elements[0,...]['elementtype']
 * @param array $elements[0,...]['label']
 * @param array $elements[0,...]['x']
 * @param array $elements[0,...]['y']
 * @param array $elements[0,...]['iconid_off']
 * @param array $elements[0,...]['iconid_unknown']
 * @param array $elements[0,...]['iconid_on']
 * @param array $elements[0,...]['iconid_disabled']
 * @param array $elements[0,...]['url']
 * @param array $elements[0,...]['label_location']
 */
	public static function updateElements($selements){
		$selements = zbx_toArray($selements);
		$selementids = array();

		$sysmapids = zbx_objectValues($selements, 'sysmapid');

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'sysmapids' => $sysmapids,
				'editable' => 1,
				'preservekeys' => 1,
				'select_selements' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_SHORTEN,
			);
			$upd_maps = self::get($options);

			foreach($selements as $snumm => $selement){
				if(!isset($upd_maps[$selement['sysmapid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}

			$update = array();
			$urlidsToDelete = $urlsToUpdate = $urlsToAdd = array();
			foreach($selements as $snumm => $selement){
				$selement_db_fields = array(
					'sysmapid' => null,
					'selementid' => null,
					'iconid_off' => null,
				);
				if(!check_db_fields($selement_db_fields, $selement)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for element');
				}

				if(check_circle_elements_link($selement['sysmapid'],$selement['elementid'],$selement['elementtype'])){
					self::exception(S_CIRCULAR_LINK_CANNOT_BE_CREATED.' "'.$selement['label'].'"');
				}

				$update[] = array(
					'values' => $selement,
					'where' => array('selementid='.$selement['selementid']),
				);
				$selementids[] = $selement['selementid'];

				if(isset($selement['urls'])){
					foreach($upd_maps[$selement['sysmapid']]['selements'][$selement['selementid']]['urls'] as $existing_url){
						$toUpdate = false;
						foreach($selement['urls'] as $unum => $new_url){
							if($existing_url['name'] == $new_url['name']){
								$toUpdate = array(
									'values' => $new_url,
									'where' => array('sysmapelementurlid ='.$existing_url['sysmapelementurlid'])
								);
								unset($selement['urls'][$unum]);

								break;
							}
						}

						if($toUpdate){
							$urlsToUpdate[] = $toUpdate;
						}
						else{
							$urlidsToDelete[] = $existing_url['sysmapelementurlid'];
						}
					}

					foreach($selement['urls'] as $newUrl){
						$newUrl['selementid'] = $selement['selementid'];
						$urlsToAdd[] = $newUrl;
					}

				}
			}
			DB::update('sysmaps_elements', $update);

			if(!empty($urlidsToDelete))
				DB::delete('sysmap_element_url', DBcondition('sysmapelementurlid', $urlidsToDelete));
			DB::update('sysmap_element_url', $urlsToUpdate);
			DB::insert('sysmap_element_url', $urlsToAdd);

			self::EndTransaction(true, __METHOD__);
			return $selementids;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);

			$errors = $e->getErrors();
			$error = reset($errors);

			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

/**
 * Delete Element from map
 *
 * @param array $selements multidimensional array with selement objects
 * @param array $selements[0, ...]['selementid'] selementid to delete
 */
    public static function deleteElements($selements){
        $selements = zbx_toArray($selements);
        $selementids = zbx_objectValues($selements, 'selementid');

		$sysmapids = zbx_objectValues($selements, 'sysmapid');

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'sysmapids' => $sysmapids,
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN,
			);
			$upd_maps = self::get($options);

			foreach($selements as $snumm => $selement){
				if(!isset($upd_maps[$selement['sysmapid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}

	        $result = delete_sysmaps_element($selementids);
			if(!$result) self::exception(ZBX_API_ERROR_INTERNAL, 'Map delete elements failed');

			self::EndTransaction(true, __METHOD__);

			return $selementids;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$errors = $e->getErrors();
			$error = reset($errors);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
    }

/**
 * Add link trigger to link (Sysmap)
 *
 * @param array $links[0,...]['linkid']
 * @param array $links[0,...]['triggerid']
 * @param array $links[0,...]['drawtype']
 * @param array $links[0,...]['color']
 */
	public static function addLinkTrigger($linktriggers){
		$errors = array();
		$result_linktriggers = array();
		$result = false;

		$linktriggers = zbx_toArray($linktriggers);

		self::BeginTransaction(__METHOD__);
		foreach($linktriggers as $linktrigger){

			$linktrigger_db_fields = array(
				'linkid' => null,
				'triggerid' => null,
				'drawtype' => 0,
				'color' => 'DD0000'
			);

			if(!check_db_fields($linktrigger_db_fields, $linktrigger)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for linktrigger');
				break;
			}

			$linktriggerid = get_dbid('sysmaps_link_triggers', 'linktriggerid');
			$sql = 'INSERT INTO sysmaps_link_triggers (linktriggerid, linkid, triggerid, drawtype, color) '.
				' VALUES ('.$linktriggerid.','.$linktrigger['linkid'].','.$linktrigger['triggerid'].', '.
					$linktrigger['drawtype'].','.zbx_dbstr($linktrigger['color']).')';
			$result = DBexecute($sql);
			if(!$result){
				$result = false;
				break;
			}

			$new_linktriggerid = array('linktriggerid' => $linktriggerid);
			$result_linktriggers[] = array_merge($new_linktriggerid, $linktriggerid);
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return $result_linktriggers;
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

}

?>
