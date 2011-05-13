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
class CMap extends CMapElement{
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
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['sortorder']
 * @param string $options['sortfield']
 * @return array|boolean Host data as array or false if error
 */
	public function get($options=array()){

		$result = array();
		$user_type = self::$userData['type'];

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
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'selectSelements'			=> null,
			'selectLinks'				=> null,
			'countOutput'				=> null,
			'expandUrls' 				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(is_array($options['output'])){
			unset($sql_parts['select']['sysmaps']);

			$dbTable = DB::getSchema('sysmaps');
			$sql_parts['select']['sysmapid'] = 's.sysmapid';
			foreach($options['output'] as $field){
				if(isset($dbTable['fields'][$field]))
					$sql_parts['select'][$field] = 's.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
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
			zbx_db_filter('sysmaps s', $options, $sql_parts);
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

					// originally we intended not to pass those parameters if advanced labels are off, but they might be useful
					// leaving this block commented
					// if(isset($sysmap['label_format']) && ($sysmap['label_format'] == SYSMAP_LABEL_ADVANCED_OFF)){
					// 	unset($sysmap['label_string_hostgroup'],$sysmap['label_string_host'],$sysmap['label_string_trigger'],$sysmap['label_string_map'],$sysmap['label_string_image']);
					// }
					if(!is_null($options['selectSelements']) && !isset($result[$sysmap['sysmapid']]['selements'])){
						$result[$sysmap['sysmapid']]['selements'] = array();
					}
					if(!is_null($options['selectLinks']) && !isset($result[$sysmap['sysmapid']]['links'])){
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

				if(!empty($link_triggers)){
					$trig_options = array(
						'triggerids' => $link_triggers,
						'editable' => $options['editable'],
						'output' => API_OUTPUT_SHORTEN,
						'preservekeys' => 1,
					);
					$all_triggers = API::Trigger()->get($trig_options);
					foreach($link_triggers as $id => $triggerid){
						if(!isset($all_triggers[$triggerid])){
							unset($result[$id], $sysmapids[$id]);
						}
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
							$hosts_to_check[$selement['elementid']] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$maps_to_check[$selement['elementid']] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$triggers_to_check[$selement['elementid']] = $selement['elementid'];
						break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$host_groups_to_check[$selement['elementid']] = $selement['elementid'];
						break;
					}
				}

// sdi($hosts_to_check);
// sdi($maps_to_check);
// sdi($triggers_to_check);
// sdi($host_groups_to_check);

				$nodeids = get_current_nodeid(true);

				if(!empty($hosts_to_check)){
					$host_options = array(
						'hostids' => $hosts_to_check,
						'nodeids' => $nodeids,
						'editable' => $options['editable'],
						'preservekeys' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$allowed_hosts = API::Host()->get($host_options);

					foreach($hosts_to_check as $elementid){
						if(!isset($allowed_hosts[$elementid])){
							foreach($selements as $selementid => $selement){
								if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) && (bccomp($selement['elementid'],$elementid) == 0)){
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if(!empty($maps_to_check)){
					$map_options = array(
						'sysmapids' => $maps_to_check,
						'nodeids' => $nodeids,
						'editable' => $options['editable'],
						'preservekeys' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$allowed_maps = $this->get($map_options);

					foreach($maps_to_check as $elementid){
						if(!isset($allowed_maps[$elementid])){
							foreach($selements as $selementid => $selement){
								if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) && (bccomp($selement['elementid'],$elementid) == 0)){
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if(!empty($triggers_to_check)){
					$trigger_options = array(
						'triggerids' => $triggers_to_check,
						'nodeids' => $nodeids,
						'editable' => $options['editable'],
						'preservekeys' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$allowed_triggers = API::Trigger()->get($trigger_options);

					foreach($triggers_to_check as $elementid){
						if(!isset($allowed_triggers[$elementid])){
							foreach($selements as $selementid => $selement){
								if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) && (bccomp($selement['elementid'],$elementid) == 0)){
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

				if(!empty($host_groups_to_check)){
					$hostgroup_options = array(
						'groupids' => $host_groups_to_check,
						'nodeids' => $nodeids,
						'editable' => $options['editable'],
						'preservekeys' => 1,
						'output' => API_OUTPUT_SHORTEN,
					);
					$allowed_host_groups = API::HostGroup()->get($hostgroup_options);

					foreach($host_groups_to_check as $elementid){
						if(!isset($allowed_host_groups[$elementid])){
							foreach($selements as $selementid => $selement){
								if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP) && (bccomp($selement['elementid'],$elementid) == 0)){
									unset($result[$selement['sysmapid']], $selements[$selementid]);
								}
							}
						}
					}
				}

			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Elements
		if(!is_null($options['selectSelements']) && str_in_array($options['selectSelements'], $subselects_allowed_outputs)){
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

			if(!is_null($options['expandUrls'])){
				$sql = 'SELECT sysmapid, name, url, elementtype'.
						' FROM sysmap_url'.
						' WHERE '.DBcondition('sysmapid', $sysmapids);
				$db_map_urls = DBselect($sql);
				while($map_url = DBfetch($db_map_urls)){
					foreach($selements as $snum => $selement){
						if((bccomp($selement['sysmapid'],$map_url['sysmapid']) == 0) && ($selement['elementtype'] == $map_url['elementtype'])){
							$selements[$snum]['urls'][] = $this->expandUrlMacro($map_url, $selement);
						}
					}
				}
			}

			$sql = 'SELECT sysmapelementurlid, selementid, name, url  '.
					' FROM sysmap_element_url '.
					' WHERE '.DBcondition('selementid', array_keys($selements));
			$db_selement_urls = DBselect($sql);
			while($selement_url = DBfetch($db_selement_urls)){
				if(is_null($options['expandUrls']))
					$selements[$selement_url['selementid']]['urls'][] = $selement_url;
				else
				$selements[$selement_url['selementid']]['urls'][] = $this->expandUrlMacro($selement_url, $selements[$selement_url['selementid']]);
			}

			foreach($selements as $selement){
				if(!isset($result[$selement['sysmapid']]['selements'])){
					$result[$selement['sysmapid']]['selements'] = array();
				}
				if(!is_null($options['preservekeys']))
					$result[$selement['sysmapid']]['selements'][$selement['selementid']] = $selement;
				else
					$result[$selement['sysmapid']]['selements'][] = $selement;
			}
		}

// Adding Links
		if(!is_null($options['selectLinks']) && str_in_array($options['selectLinks'], $subselects_allowed_outputs)){
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

			foreach($map_links as $link){
				if(!isset($result[$link['sysmapid']]['links'])){
					$result[$link['sysmapid']]['links'] = array();
				}

				if(!is_null($options['preservekeys']))
					$result[$link['sysmapid']]['links'][$link['linkid']] = $link;
				else
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
	public function getObjects($sysmapData){
		$options = array(
			'filter' => $sysmapData,
			'output' => API_OUTPUT_EXTEND
		);

		if(isset($sysmapData['node']))
			$options['nodeids'] = getNodeIdByNodeName($sysmapData['node']);
		else if(isset($sysmapData['nodeids']))
			$options['nodeids'] = $sysmapData['nodeids'];

		$result = $this->get($options);

	return $result;
	}

	public function exists($object){
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

		$objs = $this->get($options);

	return !empty($objs);
	}

	public function checkInput(&$maps, $method){
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

// permissions
		if($update || $delete){
			$mapDbFields = array('sysmapid' => null);
			$dbMaps = $this->get(array(
				'sysmapids' => zbx_objectValues($maps, 'sysmapid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true,
				'selectLinks' => API_OUTPUT_EXTEND,
				'selectSelements' => API_OUTPUT_EXTEND,
			));
		}
		else{
			$mapDbFields = array(
				'name' => null,
				'width' => null,
				'height' => null,
				'urls' => array(),
				'selements' => array(),
				'links' => array()
			);
		}

		$mapNames = array();
		foreach($maps as &$map){
			if(!check_db_fields($mapDbFields, $map)){
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect fields for sysmap'));
			}

			if($update || $delete){
				if(!isset($dbMaps[$map['sysmapid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				$dbMap = array_merge($dbMaps[$map['sysmapid']], $map);
			}
			else{
				$dbMap = $map;
			}

			if(isset($map['name'])){
				if(isset($mapNames[$map['name']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate map name for map "%s".', $dbMap['name']));
				else
					$mapNames[$map['name']] = $update ? $map['sysmapid'] : 1;
			}

			if(isset($map['width']) && (($map['width'] > 65535) || ($map['width'] < 1)))
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect map width value for map "%s".', $dbMap['name']));

			if(isset($map['height']) && (($map['height'] > 65535) || ($map['height'] < 1)))
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect map height value for map "%s".', $dbMap['name']));

// LABELS
			$mapLabels = array('label_type' => array('typeName' => _('icon')));
			if($dbMap['label_format'] == SYSMAP_LABEL_ADVANCED_ON){
				$mapLabels['label_type_hostgroup'] = array('string' => 'label_string_hostgroup', 'typeName' => _('host group'));
				$mapLabels['label_type_host'] = array('string' => 'label_string_host', 'typeName' => _('host'));
				$mapLabels['label_type_trigger'] = array('string' => 'label_string_trigger', 'typeName' => _('trigger'));
				$mapLabels['label_type_map'] = array('string' => 'label_string_map', 'typeName' => _('map'));
				$mapLabels['label_type_image'] = array('string' => 'label_string_image', 'typeName' => _('image'));
			}

			foreach($mapLabels as $labelName => $labelData){
				if(!isset($map[$labelName])) continue;

				if(sysmapElementLabel($map[$labelName]) === false)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));

				if(MAP_LABEL_TYPE_CUSTOM == $map[$labelName]){
					if(!isset($labelData['string']))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));

					if(!isset($map[$labelData['string']]) || zbx_empty($map[$labelData['string']]))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Custom label for map "%2$s" elements of type "%1$s" may not be empty.', $labelData['typeName'], $dbMap['name']));
				}

				if(($labelName == 'label_type_image') && (MAP_LABEL_TYPE_STATUS == $map[$labelName]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));

				if($labelName == 'label_type' || $labelName == 'label_type_host') continue;

				if(MAP_LABEL_TYPE_IP == $map[$labelName])
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect %1$s label type value for map "%2$s".', $labelData['typeName'], $dbMap['name']));
			}
//---

// GRID OPTIONS
			// validating grid options
			$possibleGridSizes = array(20, 40, 50, 75, 100);
			if($update || $create){
				// grid size
				if(isset($map['grid_size']) && !in_array($map['grid_size'], $possibleGridSizes)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s"', $map['grid_size'], implode('", "', $possibleGridSizes)));
				}
				// grid auto align
				if(isset($map['grid_align']) && $map['grid_align'] != SYSMAP_GRID_ALIGN_ON &&  $map['grid_align'] != SYSMAP_GRID_ALIGN_OFF){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_align". Choices are: "%2$s" and "%3$s"', $map['grid_align'], SYSMAP_GRID_ALIGN_ON, SYSMAP_GRID_ALIGN_OFF));
				}
				// grid show
				if(isset($map['grid_show']) && $map['grid_show'] != SYSMAP_GRID_SHOW_ON &&  $map['grid_show'] != SYSMAP_GRID_SHOW_OFF){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Value "%1$s" is invalid for parameter "grid_show". Choices are: "%2$s" and "%3$s"', $map['grid_show'], SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_SHOW_OFF));
				}
			}

// URLS
			if(isset($map['urls']) && !empty($map['urls'])){
				$urlNames = zbx_toHash($map['urls'], 'name');
				foreach($map['urls'] as $url){
					if($url['name'] === '' || $url['url'] === '')
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link should have both "name" and "url" fields for map "%s".', $dbMap['name']));

					if(!isset($urlNames[$url['name']]))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link name should be unique for map "%s".', $dbMap['name']));

					unset($urlNames[$url['name']]);
				}
			}

// Map selement links
			if(!empty($map['links'])){
				$mapSelements = zbx_toHash($map['selements'],'selementid');

				foreach($map['links'] as $link){
					if(!isset($mapSelements[$link['selementid1']]))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link selementid1 field is pointing to non existant map selement ID "%1$s" for map "%2$s" .', $link['selementid1'], $dbMap['name']));

					if(!isset($mapSelements[$link['selementid2']]))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Link selementid2 field is pointing to non existant map selement ID "%1$s" for map "%2$s" .', $link['selementid2'], $dbMap['name']));
				}
			}
		}
		unset($map);

// Exists
		if(($create || $update) && !empty($mapNames)){
			$options = array(
				'filter' => array('name' => array_keys($mapNames)),
				'output' => array('sysmapid', 'name'),
				'nopermissions' => true
			);
			$existDbMaps = $this->get($options);
			foreach($existDbMaps as $dbMap){
				if($create || (bccomp($mapNames[$dbMap['name']], $dbMap['sysmapid']) != 0))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Map with name "%s" already exists', $dbMap['name']));
			}
		}
//--

		return ($update || $delete) ? $dbMaps : true;
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
 * @param int $maps['grid_size'] size of a one grid cell. 100 refers to 100x100 and so on.
 * @param int $maps['grid_show'] does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
 * @param int $maps['grid_align'] does elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
 * @return boolean | array
 */
	public function create($maps){
		$maps = zbx_toArray($maps);

		$this->checkInput($maps, __FUNCTION__);

		$sysmapids = DB::insert('sysmaps', $maps);

		$newUrls = array();
		$newSelements = array();
		$newLinks = array();

		foreach($sysmapids as $mnum => $sysmapid){
			foreach($maps[$mnum]['urls'] as $url){
				$url['sysmapid'] = $sysmapid;
				$newUrls[] = $url;
			}

			foreach($maps[$mnum]['selements'] as $snum => $selement)
				$maps[$mnum]['selements'][$snum]['sysmapid'] = $sysmapid;

			$newSelements = array_merge($newSelements, $maps[$mnum]['selements']);

			foreach($maps[$mnum]['links'] as $lnum => $link)
				$maps[$mnum]['links'][$lnum]['sysmapid'] = $sysmapid;

			$newLinks = array_merge($newLinks, $maps[$mnum]['links']);
		}

		DB::insert('sysmap_url', $newUrls);

		if(!empty($newSelements)){
			$selementids = $this->createSelements($newSelements);

			if(!empty($newLinks)){
// Links
				$mapVirtSelements = array();
				foreach($selementids['selementids'] as $snum => $selementid)
					$mapVirtSelements[$newSelements[$snum]['selementid']] = $selementid;

				foreach($newLinks as $lnum => $link){
					$newLinks[$lnum]['selementid1'] = $mapVirtSelements[$link['selementid1']];
					$newLinks[$lnum]['selementid2'] = $mapVirtSelements[$link['selementid2']];
				}
				unset($mapVirtSelements);

				$linkids = $this->createLinks($newLinks);

// linkTriggers
				$newLinkTriggers = array();
				foreach($linkids['linkids'] as $lnum => $linkid){
					if(!isset($newLinks[$lnum]['linktriggers'])) continue;

					foreach($newLinks[$lnum]['linktriggers'] as $linktrigger){
						$linktrigger['linkid'] = $linkid;
						$newLinkTriggers[] = $linktrigger;
					}
				}

				if(!empty($newLinkTriggers))
					$this->createLinkTriggers($newLinkTriggers);
			}
		}

		return array('sysmapids' => $sysmapids);
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
 * @param int $maps['grid_size'] size of a one grid cell. 100 refers to 100x100 and so on.
 * @param int $maps['grid_show'] does grid need to be shown. Constants: SYSMAP_GRID_SHOW_ON / SYSMAP_GRID_SHOW_OFF
 * @param int $maps['grid_align'] does elements need to be aligned to the grid. Constants: SYSMAP_GRID_ALIGN_ON / SYSMAP_GRID_ALIGN_OFF
 * @return boolean
 */
	public function update($maps){
		$maps = zbx_toArray($maps);
		$sysmapids = zbx_objectValues($maps, 'sysmapid');

		$dbMaps = $this->checkInput($maps, __FUNCTION__);

		$updateMaps = array();
		$urlidsToDelete = $urlsToUpdate = $urlsToAdd = array();
		$selementsToDelete = $selementsToUpdate = $selementsToAdd = array();
		$linksToDelete = $linksToUpdate = $linksToAdd = array();

		foreach($maps as $map){
			$updateMaps[] = array(
				'values' => $map,
				'where' => array('sysmapid' => $map['sysmapid']),
			);

			$dbMap = $dbMaps[$map['sysmapid']];

// URLS
			if(isset($map['urls'])){
				$urlDiff = zbx_array_diff($map['urls'], $dbMap['urls'], 'name');

				foreach($urlDiff['both'] as $updUrl){
					$urlsToUpdate[] = array(
						'values' => $updUrl,
						'where' => array('name' => $updUrl['name'], 'sysmapid' => $map['sysmapid'])
					);
				}

				foreach($urlDiff['first'] as $newUrl){
					$newUrl['sysmapid'] = $map['sysmapid'];
					$urlsToAdd[] = $newUrl;
				}

				$urlidsToDelete = array_merge($urlidsToDelete, zbx_objectValues($urlDiff['second'], 'sysmapurlid'));
			}

// Elements
			if(isset($map['selements'])){
				$selementDiff = zbx_array_diff($map['selements'], $dbMap['selements'], 'selementid');
				// We need sysmapid for add operations
				foreach($selementDiff['first'] as $newSelement){
					$newSelement['sysmapid'] = $map['sysmapid'];
					$selementsToAdd[] = $newSelement;
				}

				$selementsToUpdate = array_merge($selementsToUpdate, $selementDiff['both']);
				$selementsToDelete = array_merge($selementsToDelete, $selementDiff['second']);
			}

// Links
			if(isset($map['links'])){
				$linkDiff = zbx_array_diff($map['links'], $dbMap['links'], 'linkid');
				// We need sysmapid for add operations
				foreach($linkDiff['first'] as $newLink){
					$newLink['sysmapid'] = $map['sysmapid'];
					$linksToAdd[] = $newLink;
				}

				$linksToUpdate = array_merge($linksToUpdate, $linkDiff['both']);
				$linksToDelete = array_merge($linksToDelete, $linkDiff['second']);
			}
		}

		DB::update('sysmaps', $updateMaps);

// Urls
		DB::insert('sysmap_url', $urlsToAdd);
		DB::update('sysmap_url', $urlsToUpdate);

		if(!empty($urlidsToDelete))
			DB::delete('sysmap_url', array('sysmapurlid' => $urlidsToDelete));

// Selements
		$newSelementIds = array('selementids' => array());
		if(!empty($selementsToAdd))
			$newSelementIds = $this->createSelements($selementsToAdd);

		if(!empty($selementsToUpdate))
			$this->updateSelements($selementsToUpdate);

		if(!empty($selementsToDelete))
			$this->deleteSelements($selementsToDelete);

// Links
		if(!empty($linksToAdd)){

			$mapVirtSelements = array();
			foreach($newSelementIds['selementids'] as $snum => $selementid)
				$mapVirtSelements[$selementsToAdd[$snum]['selementid']] = $selementid;

			foreach($selementsToUpdate as $selement)
				$mapVirtSelements[$selement['selementid']] = $selement['selementid'];

			foreach($linksToAdd as $lnum => $link){
				$linksToAdd[$lnum]['selementid1'] = $mapVirtSelements[$linksToAdd[$lnum]['selementid1']];
				$linksToAdd[$lnum]['selementid2'] = $mapVirtSelements[$linksToAdd[$lnum]['selementid2']];
			}

			unset($mapVirtSelements);
		}

		$newLinkIds = $updLinkIds = array('linkids' => array());
		if(!empty($linksToAdd))
			$newLinkIds = $this->createLinks($linksToAdd);

		if(!empty($linksToUpdate))
			$updLinkIds = $this->updateLinks($linksToUpdate);

		if(!empty($linksToDelete))
			$this->deleteLinks($linksToDelete);

// linkTriggers
		$linkTriggersToDelete = $linkTriggersToUpdate = $linkTriggersToAdd = array();
		foreach($newLinkIds['linkids'] as $lnum => $linkid){
			if(!isset($linksToAdd[$lnum]['linktriggers'])) continue;

			foreach($linksToAdd[$lnum]['linktriggers'] as $linktrigger){
				$linktrigger['linkid'] = $linkid;
				$linkTriggersToAdd[] = $linktrigger;
			}
		}

		$dbLinks = array();

		$linkTriggerResource = DBselect('SELECT * FROM sysmaps_link_triggers WHERE '.DBcondition('linkid',$updLinkIds['linkids']));
		while($dbLinkTrigger = DBfetch($linkTriggerResource))
			zbx_subarray_push($dbLinks, $dbLinkTrigger['linkid'], $dbLinkTrigger);

		foreach($updLinkIds['linkids'] as $lnum => $linkid){
			if(!isset($linksToUpdate[$lnum]['linktriggers'])) continue;

			$dbLinkTriggers = isset($dbLinks[$linkid]) ? $dbLinks[$linkid] : array();
			$dbLinkTriggersDiff = zbx_array_diff($linksToUpdate[$lnum]['linktriggers'], $dbLinkTriggers, 'linktriggerid');

			foreach($dbLinkTriggersDiff['first'] as $newLinkTrigger){
				$newLinkTrigger['linkid'] = $linkid;
				$linkTriggersToAdd[] = $newLinkTrigger;
			}

			$linkTriggersToUpdate = array_merge($linkTriggersToUpdate, $dbLinkTriggersDiff['both']);
			$linkTriggersToDelete = array_merge($linkTriggersToDelete, $dbLinkTriggersDiff['second']);
		}

		if(!empty($linkTriggersToAdd))
			$this->createLinkTriggers($linkTriggersToAdd);

		if(!empty($linkTriggersToUpdate))
			$this->updateLinkTriggers($linkTriggersToUpdate);

		if(!empty($linkTriggersToDelete))
			$this->deleteLinkTriggers($linkTriggersToDelete);


	return array('sysmapids' => $sysmapids);
	}


/**
 * Delete Map
 *
 * @param array $sysmaps
 * @param array $sysmaps['sysmapid']
 * @return boolean
 */
	public function delete($sysmapids){

		$maps = zbx_toObject($sysmapids, 'sysmapid');
		$this->checkInput($maps, __FUNCTION__);

	// delete maps from selements of other maps
		DB::delete('sysmaps_elements', array(
			'elementid' => $sysmapids,
			'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
		));

		DB::delete('screens_items', array(
			'resourceid' => $sysmapids,
			'resourcetype' => SCREEN_RESOURCE_MAP
		));

		DB::delete('profiles', array(
			'idx' => 'web.maps.sysmapid',
			'value_id' => $sysmapids
		));
		//----
		DB::delete('sysmaps', array('sysmapid' => $sysmapids));

		return array('sysmapids' => $sysmapids);
	}

	private function expandUrlMacro($url, $selement){

		switch($selement['elementtype']){
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP: $macro = '{HOSTGROUP.ID}' ; break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER: $macro = '{TRIGGER.ID}' ; break;
			case SYSMAP_ELEMENT_TYPE_MAP: $macro = '{MAP.ID}' ; break;
			case SYSMAP_ELEMENT_TYPE_HOST: $macro = '{HOST.ID}' ; break;
			default: $macro = false;
		}

		if($macro)
			$url['url'] = str_replace($macro, $selement['elementid'], $url['url']);
		return $url;
	}

}

?>
