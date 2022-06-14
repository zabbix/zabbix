<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Class containing methods for operations with graph items.
 */
class CGraphItem extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'graphs_items';
	protected $tableAlias = 'gi';
	protected $sortColumns = ['gitemid'];

	/**
	 * Get GraphItems data
	 *
	 * @param array $options
	 * @return array|boolean
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['gitems' => 'gi.gitemid'],
			'from'		=> ['graphs_items' => 'graphs_items gi'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'graphids'		=> null,
			'itemids'		=> null,
			'type'			=> null,
			'editable'		=> false,
			'nopermissions'	=> null,
			// output
			'selectGraphs'	=> null,
			'output'		=> API_OUTPUT_EXTEND,
			'countOutput'	=> false,
			'preservekeys'	=> false,
			'sortfield'		=> '',
			'sortorder'		=> '',
			'limit'			=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

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
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs'] = 'graphs g';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);
		}

		// type
		if (!is_null($options['type'] )) {
			$sqlParts['where'][] = 'gi.type='.zbx_dbstr($options['type']);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($gitem = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $gitem['rowscount'];
			}
			else {
				$result[$gitem['gitemid']] = $gitem;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['graphid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['selectGraphs'] !== null) {
			$sqlParts = $this->addQuerySelect('graphid', $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding graphs
		if ($options['selectGraphs'] !== null && $options['selectGraphs'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'gitemid', 'graphid');
			$graphs = API::Graph()->get([
				'output' => $options['selectGraphs'],
				'gitemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $graphs, 'graphs');
		}

		return $result;
	}
}
