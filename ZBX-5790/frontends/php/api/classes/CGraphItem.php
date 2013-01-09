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
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

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
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['select']['graphid'] = 'gi.graphid';
			$sqlParts['from']['graphs'] = 'graphs g';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'gi.itemid';
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);
		}

		// type
		if (!is_null($options['type'] )) {
			$sqlParts['where'][] = 'gi.type='.$options['type'];
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'gi');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($gitem = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				$result = $gitem['rowscount'];
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('graphid'), $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		// expandData
		if ($options['expandData'] !== null) {
			$sqlParts['select'][] = 'i.key_';
			$sqlParts['select'][] = 'i.hostid';
			$sqlParts['select'][] = 'i.flags';
			$sqlParts['select'][] = 'h.host';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['gii'] = 'gi.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		if ($options['selectGraphs'] !== null) {
			$sqlParts = $this->addQuerySelect('graphid', $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding graphs
		if ($options['selectGraphs'] !== null) {
			$relationMap = $this->createRelationMap($result, 'gitemid', 'graphid');
			$graphs = API::Graph()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectGraphs'],
				'gitemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $graphs, 'graphs');
		}

		return $result;
	}
}
?>
