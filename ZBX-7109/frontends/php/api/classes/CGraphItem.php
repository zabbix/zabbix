<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * File containing CGraphItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with GraphItems
 */
class CGraphItem extends CZBXAPI {

	protected $tableName = 'graphs_items';
	protected $tableAlias = 'gi';

	/**
	 * Get GraphItems data
	 *
	 * @param array $options
	 * @return array|boolean
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('gitemid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('gitems' => 'gi.gitemid'),
			'from'		=> array('graphs_items' => 'graphs_items gi'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'		=> null,
			'graphids'		=> null,
			'itemids'		=> null,
			'type'			=> null,
			'editable'		=> null,
			'nopermissions'	=> null,
			// output
			'selectGraphs'	=> null,
			'output'		=> API_OUTPUT_REFER,
			'expandData'	=> null,
			'countOutput'	=> null,
			'preservekeys'	=> null,
			'sortfield'		=> '',
			'sortorder'		=> '',
			'limit'			=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE gi.itemid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
					' GROUP BY i.itemid'.
					' HAVING MIN(r.permission)>='.$permission.
					')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['graphid'] = 'gi.graphid';
			}
			$sqlParts['from']['graphs'] = 'graphs g';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['itemid'] = 'gi.itemid';
			}
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);
		}

		// type
		if (!is_null($options['type'] )) {
			$sqlParts['where'][] = 'gi.type='.zbx_dbstr($options['type']);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['gitems'] = 'gi.*';
		}

		// expandData
		if (!is_null($options['expandData'])) {
			$sqlParts['select']['key'] = 'i.key_';
			$sqlParts['select']['hostid'] = 'i.hostid';
			$sqlParts['select']['flags'] = 'i.flags';
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['gii'] = 'gi.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT gi.gitemid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'gi');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$gitemids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('gi.gitemid', $nodeids).
					$sqlWhere.
					$sqlOrder;
		$dbRes = DBselect($sql, $sqlLimit);
		while ($gitem = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				$result = $gitem['rowscount'];
			}
			else {
				$gitemids[$gitem['gitemid']] = $gitem['gitemid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$gitem['gitemid']] = array('gitemid' => $gitem['gitemid']);
				}
				else {
					if (!isset($result[$gitem['gitemid']])) {
						$result[$gitem['gitemid']] = array();
					}

					// graphids
					if (isset($gitem['graphid']) && is_null($options['selectGraphs'])) {
						if (!isset($result[$gitem['gitemid']]['graphs'])) {
							$result[$gitem['gitemid']]['graphs'] = array();
						}
						$result[$gitem['gitemid']]['graphs'][] = array('graphid' => $gitem['graphid']);
					}
					$result[$gitem['gitemid']] += $gitem;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding graphs
		if (!is_null($options['selectGraphs']) && str_in_array($options['selectGraphs'], $subselectsAllowedOutputs)) {
			$graphs = API::Graph()->get(array(
				'nodeids' => $nodeids,
				'output' => $options['selectGraphs'],
				'gitemids' => $gitemids,
				'preservekeys' => true
			));
			foreach ($graphs as $graph) {
				$gitems = $graph['gitems'];
				unset($graph['gitems']);
				foreach ($gitems as $item) {
					$result[$gitem['gitemid']]['graphs'][] = $graph;
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get graph items by graph id and graph item id
	 *
	 * @param array $gitemData
	 * @param array $gitemData['itemid']
	 * @param array $gitemData['graphid']
	 * @return string|boolean graphid
	 */
	public function getObjects($gitemData) {
		$result = array();
		$gitemids = array();

		$dbRes = DBselect(
			'SELECT gi.gitemid'.
			' FROM graphs_items gi'.
			' WHERE gi.itemid='.zbx_dbstr($gitemData['itemid']).
				' AND gi.graphid='.zbx_dbstr($gitemData['graphid'])
		);
		while ($gitem = DBfetch($dbRes)) {
			$gitemids[$gitem['gitemid']] = $gitem['gitemid'];
		}

		if (!empty($gitemids)) {
			$result = $this->get(array('gitemids' => $gitemids, 'output' => API_OUTPUT_EXTEND));
		}
		return $result;
	}
}
?>
