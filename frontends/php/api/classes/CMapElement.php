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
/**
 * File containing CMapElement class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Maps Elements
 */
abstract class CMapElement extends CZBXAPI {

	protected $tableName = 'sysmaps_elements';

	protected $tableAlias = 'se';

/**
 * Get Map data
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['countoutput'] count Hosts, returned column name is rowscount
 * @param string $options['search'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['sortorder']
 * @param string $options['sortfield']
 * @return array|boolean Host data as array or false if error
 */
	protected function getSelements($options = array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('selementid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('sysmaps_elements' => 'se.selementid'),
			'from'		=> array('sysmaps_elements' => 'sysmaps_elements se'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'sysmapids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectUrls'				=> null,
			'selectLinks'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['sysmaps_elements']);

			$dbTable = DB::getSchema('sysmaps_elements');
			$sqlParts['select']['selementid'] = 'se.selementid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'se.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// selementids
		if (!is_null($options['selementids'])) {
			zbx_value2array($options['selementids']);
			$sqlParts['where']['selementid'] = DBcondition('se.selementid', $options['selementids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('se.selementid', $nodeids);
			}
		}

// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['sysmapid'] = 'se.sysmapid';
			}

			$sqlParts['where']['sysmapid'] = DBcondition('se.sysmapid', $options['sysmapids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['sysmapid'] = 'se.sysmapid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('se.sysmapid', $nodeids);
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('se.selementid', $nodeids);
		}

// search
		if (!is_null($options['search'])) {
			zbx_db_search('sysmaps_elements se', $options, $sqlParts);
		}

// filter
		if (!is_null($options['filter'])) {
			zbx_db_filter('sysmaps_elements se', $options, $sqlParts);
		}

// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['sysmaps'] = 'se.*';
		}

// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sqlParts['select'] = array('count(DISTINCT s.sysmapid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'se');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$selementids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select']))	$sqlSelect.= implode(',', $sqlParts['select']);
		if (!empty($sqlParts['from']))		$sqlFrom.= implode(',', $sqlParts['from']);
		if (!empty($sqlParts['where']))		$sqlWhere.= implode(' AND ', $sqlParts['where']);
		if (!empty($sqlParts['group']))		$sqlWhere.= ' GROUP BY '.implode(',', $sqlParts['group']);
		if (!empty($sqlParts['order']))		$sqlOrder.= ' ORDER BY '.implode(',', $sqlParts['order']);
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.$sqlWhere.
				$sqlGroup.
				$sqlOrder;
//SDI($sql);
		$res = DBselect($sql, $sqlLimit);
		while ($selement = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $selement['rowscount'];
			}
			else{
				$selementids[$selement['selementid']] = $selement['selementid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$selement['selementid']] = array('selementid' => $selement['selementid']);
				}
				else{
					if (!isset($result[$selement['selementid']])) $result[$selement['selementid']]= array();

					if (!is_null($options['selectLinks']) && !isset($result[$selement['selementid']]['links'])) {
						$result[$selement['selementid']]['links'] = array();
					}

					if (!is_null($options['selectUrls']) && !isset($result[$selement['selementid']]['urls'])) {
						$result[$selement['selementid']]['urls'] = array();
					}

					$result[$selement['selementid']] += $selement;
				}
			}
		}


// sysmapids
		$sysmapids = array();
		$sql = 'SELECT se.sysmapid '.
			' FROM sysmaps_elements se'.
			' WHERE '.DBcondition('se.selementid', $selementids);
		$dbSysmaps = DBselect($sql);
		while ($dbSysmap = DBfetch($dbSysmaps)) {
			$sysmapids[$dbSysmap['sysmapid']] = $dbSysmap['sysmapid'];
		}
// ---

		if (!is_null($options['countOutput'])) {
			return $result;
		}

// Adding URLS
		if (!is_null($options['selectUrls']) && str_in_array($options['selectUrls'], $subselectsAllowedOutputs)) {
			$sql = 'SELECT sysmapelementurlid, selementid, name, url  '.
					' FROM sysmap_element_url '.
					' WHERE '.DBcondition('selementid', $selementids);
			$dbSelementUrls = DBselect($sql);
			while ($selementUrl = DBfetch($dbSelementUrls)) {
				$result[$selementUrl['selementid']]['urls'][] = $selementUrl;
			}
		}
// Adding Links
		if (!is_null($options['selectLinks']) && str_in_array($options['selectLinks'], $subselectsAllowedOutputs)) {
			$linkids = array();
			$mapLinks = array();

			$sql = 'SELECT sl.* FROM sysmaps_links sl WHERE '.DBcondition('sl.sysmapid', $sysmapids);
			$dbLinks = DBselect($sql);
			while ($link = DBfetch($dbLinks)) {
				$link['linktriggers'] = array();

				$mapLinks[$link['linkid']] = $link;
				$linkids[$link['linkid']] = $link['linkid'];
			}

			$sql = 'SELECT DISTINCT slt.* FROM sysmaps_link_triggers slt WHERE '.DBcondition('slt.linkid', $linkids);
			$dbLinkTriggers = DBselect($sql);
			while ($linkTrigger = DBfetch($dbLinkTriggers)) {
				$mapLinks[$linkTrigger['linkid']]['linktriggers'][] = $linkTrigger;
			}

			foreach ($mapLinks as $num => $link) {
				if (!isset($result[$link['selementid1']]['links']))
					$result[$link['selementid1']]['links'] = array();

				if (!isset($result[$link['selementid2']]['links']))
					$result[$link['selementid2']]['links'] = array();

				if (!is_null($options['preservekeys'])) {
					$result[$link['selementid1']]['links'][$link['linkid']] = $link;
					$result[$link['selementid2']]['links'][$link['linkid']] = $link;
				}
				else{
					$result[$link['selementid1']]['links'][] = $link;
					$result[$link['selementid2']]['links'][] = $link;
				}
			}
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	protected function getLinks($options=array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		$sortColumns = array('linkid'); // allowed columns for sorting
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sqlParts = array(
			'select' => array('sysmaps_links' => 'sl.linkid'),
			'from' => array('sysmaps_links' => 'sysmaps_links sl'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$defOptions = array(
			'nodeids'					=> null,
			'sysmapids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($defOptions, $options);


		if (is_array($options['output'])) {
			unset($sqlParts['select']['sysmaps_links']);

			$dbTable = DB::getSchema('sysmaps_links');
			$sqlParts['select']['linkid'] = 'sl.linkid';
			foreach ($options['output'] as $key => $field) {
				if (isset($dbTable['fields'][$field]))
					$sqlParts['select'][$field] = 'sl.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// linkids
		if (!is_null($options['linkids'])) {
			zbx_value2array($options['linkids']);
			$sqlParts['where']['linkid'] = DBcondition('sl.linkid', $options['linkids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('sl.linkid', $nodeids);
			}
		}

// sysmapids
		if (!is_null($options['sysmapids'])) {
			zbx_value2array($options['sysmapids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['sysmapid'] = 'sl.sysmapid';
			}

			$sqlParts['where']['sysmapid'] = DBcondition('sl.sysmapid', $options['sysmapids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['sysmapid'] = 'sl.sysmapid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('sl.sysmapid', $nodeids);
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if (!$nodeCheck) {
			$nodeCheck = true;
			$sqlParts['where'][] = DBin_node('sl.linkid', $nodeids);
		}

// search
		if (!is_null($options['search'])) {
			zbx_db_search('sysmaps_links sl', $options, $sqlParts);
		}

// filter
		if (!is_null($options['filter'])) {
			zbx_db_filter('sysmaps_links sl', $options, $sqlParts);
		}

// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['sysmaps'] = 'sl.*';
		}

// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sqlParts['select'] = array('count(DISTINCT s.sysmapid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'sl');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$linkids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select']))	$sqlSelect.= implode(',', $sqlParts['select']);
		if (!empty($sqlParts['from']))		$sqlFrom.= implode(',', $sqlParts['from']);
		if (!empty($sqlParts['where']))		$sqlWhere.= implode(' AND ', $sqlParts['where']);
		if (!empty($sqlParts['group']))		$sqlWhere.= ' GROUP BY '.implode(',', $sqlParts['group']);
		if (!empty($sqlParts['order']))		$sqlOrder.= ' ORDER BY '.implode(',', $sqlParts['order']);
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.$sqlWhere.
				$sqlGroup.
				$sqlOrder;
//SDI($sql);
		$res = DBselect($sql, $sqlLimit);
		while ($link = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $link['rowscount'];
			}
			else{
				$linkids[$link['linkid']] = $link['linkid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$link['linkid']] = array('linkid' => $link['linkid']);
				}
				else{
					$result[$link['linkid']] = $link;
				}
			}
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	protected function checkSelementInput(&$selements, $method) {
		$create = ($method == 'createSelements');
		$update = ($method == 'updateSelements');
		$delete = ($method == 'deleteSelements');

// permissions
		if ($update || $delete) {
			$selementDbFields = array(
				'selementid' => null,
			);

			$dbSelements = $this->getSelements(array(
				'selementids' => zbx_objectValues($selements, 'selementid'),
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => true,
				'preservekeys' => true,
				'selectUrls' => API_OUTPUT_EXTEND
			));
		}
		else{
			$selementDbFields = array(
				'sysmapid' => null,
				'elementid' => null,
				'elementtype' => null,
				'iconid_off' => null,
				'urls' => array()
			);
		}

		foreach ($selements as &$selement) {
			if (!check_db_fields($selementDbFields, $selement))
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for element.'));

			if ($update || $delete) {
				if (!isset($dbSelements[$selement['selementid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				$dbSelement = array_merge($dbSelements[$selement['selementid']], $selement);
			}
			else{
				$dbSelement = $selement;
			}

			if (isset($selement['iconid_off']) && ($selement['iconid_off'] == 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No icon for map element "%s".', $selement['label']));
			}

			if ($this->checkCircleSelementsLink($dbSelement['sysmapid'], $dbSelement['elementid'], $dbSelement['elementtype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Circular link cannot be created for map element "%s".', $dbSelement['label']));
			}
		}
		unset($selement);

		return ($update || $delete) ? $dbSelements : true;
	}


	protected function checkLinkInput($links, $method) {
		$create = ($method == 'createLink');
		$update = ($method == 'updateLink');
		$delete = ($method == 'deleteLink');

// permissions
		if ($update || $delete) {
			$linkDbFields = array(
				'linkid' => null,
			);

			$dbLinks = $this->getLinks(array(
				'selementids' => zbx_objectValues($links, 'linkid'),
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => true,
				'preservekeys' => true,
			));
		}
		else{
			$linkDbFields = array(
				'sysmapid' => null,
				'selementid1' => null,
				'selementid2' => null,
			);
		}

		foreach ($links as $link) {
			if (!check_db_fields($linkDbFields, $link)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for map link'));
			}

			if ($update || $delete) {
				if (!isset($dbLinks[$link['linkid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		return true;
	}

	public function checkCircleSelementsLink($sysmapid, $elementid, $elementtype) {
		if ($elementtype != SYSMAP_ELEMENT_TYPE_MAP) return false;

		if (bccomp($sysmapid, $elementid) == 0) return true;

		$sql = 'SELECT elementid, elementtype '.
				' FROM sysmaps_elements '.
				' WHERE sysmapid='.$elementid .
					' AND elementtype='.SYSMAP_ELEMENT_TYPE_MAP;
		$dbElements = DBselect($sql);

		while ($element = DBfetch($dbElements)) {
			if ($this->checkCircleSelementsLink($sysmapid, $element['elementid'], $element['elementtype']))
				return true;
		}
		return false;
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
 * @param array $elements[0,...]['iconid_on']
 * @param array $elements[0,...]['iconid_disabled']
 * @param array $elements[0,...]['urls'][0,...]
 * @param array $elements[0,...]['label_location']
 */
	protected function createSelements($selements) {
		$selements = zbx_toArray($selements);

		$this->checkSelementInput($selements, __FUNCTION__);

		$selementids = DB::insert('sysmaps_elements', $selements);

		$insertUrls = array();
		foreach ($selementids as $snum => $selementid) {
			foreach ($selements[$snum]['urls'] as $url) {
				$url['selementid'] = $selementid;
				$insertUrls[] = $url;
			}
		}

		DB::insert('sysmap_element_url', $insertUrls);

	return array('selementids' => $selementids);
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
 * @param array $elements[0,...]['iconid_on']
 * @param array $elements[0,...]['iconid_disabled']
 * @param array $elements[0,...]['url']
 * @param array $elements[0,...]['label_location']
 */
	protected function updateSelements($selements) {
		$selements = zbx_toArray($selements);
		$selementids = array();

		$dbSelements = $this->checkSelementInput($selements, __FUNCTION__);

		$update = array();
		$urlsToDelete = $urlsToUpdate = $urlsToAdd = array();
		foreach ($selements as $selement) {
			$update[] = array(
				'values' => $selement,
				'where' => array('selementid'=>$selement['selementid']),
			);
			$selementids[] = $selement['selementid'];

			if (!isset($selement['urls'])) continue;

			$diffUrls = zbx_array_diff($selement['urls'], $dbSelements[$selement['selementid']]['urls'], 'name');

// Add
			foreach ($diffUrls['first'] as $newUrl) {
				$newUrl['selementid'] = $selement['selementid'];
				$urlsToAdd[] = $newUrl;
			}

// update url
			foreach ($diffUrls['both'] as $unum => $updUrl)
				$urlsToUpdate[] = array(
					'values' => $updUrl,
					'where' => array('selementid'=>$selement['selementid'],'name'=>$updUrl['name'])
				);

// delete url
			$urlsToDelete = array_merge($urlsToDelete, zbx_objectValues($diffUrls['second'], 'sysmapelementurlid'));
		}

		DB::update('sysmaps_elements', $update);

		if (!empty($urlsToDelete))
			DB::delete('sysmap_element_url', array('sysmapelementurlid' => $urlsToDelete));

		if (!empty($urlsToUpdate))
			DB::update('sysmap_element_url', $urlsToUpdate);

		if (!empty($urlsToAdd))
			DB::insert('sysmap_element_url', $urlsToAdd);

	return array('selementids' => $selementids);
	}

/**
 * Delete Element from map
 *
 * @param array $selements multidimensional array with selement objects
 * @param array $selements[0, ...]['selementid'] selementid to delete
 */
	protected function deleteSelements($selements) {
		$selements = zbx_toArray($selements);
		$selementids = zbx_objectValues($selements, 'selementid');

		$this->checkSelementInput($selements, __FUNCTION__);

		DB::delete('sysmaps_elements', array('selementid' => $selementids));

	return $selementids;
	}

/**
 * createLink Link
 *
 * @param array $links
 * @param array $links[0,...]['sysmapid']
 * @param array $links[0,...]['selementid1']
 * @param array $links[0,...]['selementid2']
 * @param array $links[0,...]['drawtype']
 * @param array $links[0,...]['color']
 * @return boolean
 */
	protected function createLinks($links) {
		$links = zbx_toArray($links);

		$this->checkLinkInput($links, __FUNCTION__);

		$linkids = DB::insert('sysmaps_links', $links);

		return array('linkids' => $linkids);
	}


	protected function updateLinks($links) {
		$links = zbx_toArray($links);

		$this->checkLinkInput($links, __FUNCTION__);

		$udpateLinks = array();
		foreach ($links as $lnum => $link)
			$udpateLinks[] = array('values' => $link, 'where' => array('linkid'=>$link['linkid']));

		DB::update('sysmaps_links', $udpateLinks);

	return array('linkids' => zbx_objectValues($links, 'linkid'));
	}

/**
 * Delete Link from map
 *
 * @param array $links multidimensional array with link objects
 * @param array $links[0, ...]['linkid'] link ID to delete
 */
	protected function deleteLinks($links) {
		zbx_value2array($links);
		$linkids = zbx_objectValues($links, 'linkid');

		$this->checkLinkInput($links, __FUNCTION__);

		DB::delete('sysmaps_links', array('linkid' => $linkids));

	return array('linkids' => $linkids);
	}

/**
 * Add link trigger to link (Sysmap)
 *
 * @param array $links[0,...]['linkid']
 * @param array $links[0,...]['triggerid']
 * @param array $links[0,...]['drawtype']
 * @param array $links[0,...]['color']
 */
	protected function createLinkTriggers($linktriggers) {
		$linktriggers = zbx_toArray($linktriggers);

		$linktriggerDbFields = array(
			'linkid' => null,
			'triggerid' => null,
			'drawtype' => 0,
			'color' => 'DD0000'
		);

		foreach ($linktriggers as $linktrigger) {
			if (!check_db_fields($linktriggerDbFields, $linktrigger))
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger'));
		}

		$linktriggerids = DB::insert('sysmaps_link_triggers', $linktriggers);

		return array('linktriggerids' => $linktriggerids);
	}


	protected function updateLinkTriggers($linktriggers) {
		$linktriggers = zbx_toArray($linktriggers);
		$linktriggerids = zbx_objectValues($linktriggers, 'linktriggerid');

		$linktriggerDbFields = array(
			'linktriggerid' => null
		);

		$updateLinkTriggers = array();
		foreach ($linktriggers as $linktrigger) {
			if (!check_db_fields($linktriggerDbFields, $linktrigger))
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger update'));

			$updateLinkTriggers[] = array(
				'values' => $linktrigger,
				'where' => array('linktriggerid'=>$linktrigger['linktriggerid'])
			);
		}

		DB::update('sysmaps_link_triggers', $updateLinkTriggers);

		return array('linktriggerids' => $linktriggerids);
	}

	protected function deleteLinkTriggers($linktriggers) {
		$linktriggers = zbx_toArray($linktriggers);
		$linktriggerids = zbx_objectValues($linktriggers, 'linktriggerid');

		$linktriggerDbFields = array(
			'linktriggerid' => null
		);

		foreach ($linktriggers as $linktrigger) {
			if (!check_db_fields($linktriggerDbFields, $linktrigger))
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger delete'));
		}

		DB::delete('sysmaps_link_triggers', array('linktriggerid' => $linktriggerids));

		return array('linktriggerids' => $linktriggerids);
	}

}

?>
