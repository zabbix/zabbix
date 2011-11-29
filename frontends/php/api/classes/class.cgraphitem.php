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
 * File containing CGraphItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with GraphItems
 */
class CGraphItem extends CZBXAPI {
	/**
	* Get GraphItems data
	*
	* @param array $options
	* @return array|boolean
	*/
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('gitemid');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('gitems' => 'gi.gitemid'),
			'from'		=> array('graphs_items' => 'graphs_items gi'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
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
		$options = zbx_array_merge($def_options, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $user_type || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS ('.
										' SELECT hgg.groupid'.
										' FROM hosts_groups hgg,rights rr,users_groups ugg'.
										' WHERE i.hostid=hgg.hostid'.
											' AND rr.id=hgg.groupid'.
											' AND rr.groupid=ugg.usrgrpid'.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}
			$sql_parts['from']['graphs'] = 'graphs g';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where'][] = DBcondition('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['itemid'] = 'gi.itemid';
			}
			$sql_parts['where'][] = DBcondition('gi.itemid', $options['itemids']);
		}

		// type
		if (!is_null($options['type'] )) {
			$sql_parts['where'][] = 'gi.type='.$options['type'];
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['gitems'] = 'gi.*';
		}

		// expandData
		if (!is_null($options['expandData'])) {
			$sql_parts['select']['key'] = 'i.key_';
			$sql_parts['select']['hostid'] = 'i.hostid';
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['gii'] = 'gi.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT gi.gitemid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'gi');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$gitemids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if (!empty($sql_parts['select'])) {
			$sql_select .= implode(',', $sql_parts['select']);
		}
		if (!empty($sql_parts['from'])) {
			$sql_from .= implode(',', $sql_parts['from']);
		}
		if (!empty($sql_parts['where'])) {
			$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
		}
		if (!empty($sql_parts['order'])) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('gi.gitemid', $nodeids).
					$sql_where.
					$sql_order;
		$db_res = DBselect($sql, $sql_limit);
		while ($gitem = DBfetch($db_res)) {
			if (!is_null($options['countOutput'])){
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
		if (!is_null($options['selectGraphs']) && str_in_array($options['selectGraphs'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectGraphs'],
				'gitemids' => $gitemids,
				'preservekeys' => true
			);
			$graphs = API::Graph()->get($obj_params);
			foreach ($graphs as $graphid => $graph) {
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
	 * @param array $gitem_data
	 * @param array $gitem_data['itemid']
	 * @param array $gitem_data['graphid']
	 * @return string|boolean graphid
	 */
	public function getObjects($gitem_data) {
		$result = array();
		$gitemids = array();

		$db_res = DBselect(
			'SELECT gi.gitemid'.
			' FROM graphs_items gi'.
			' WHERE gi.itemid='.$gitem_data['itemid'].
				' AND gi.graphid='.$gitem_data['graphid']
		);
		while ($gitem = DBfetch($db_res)) {
			$gitemids[$gitem['gitemid']] = $gitem['gitemid'];
		}

		if (!empty($gitemids)) {
			$result = $this->get(array('gitemids' => $gitemids, 'output' => API_OUTPUT_EXTEND));
		}
		return $result;
	}
}
?>
