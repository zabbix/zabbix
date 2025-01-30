<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'items i';
			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where'][] = 'gi.itemid=i.itemid';
			$sqlParts['where'][] = 'i.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM graphs_items gi1'.
				' JOIN items i1 ON gi1.itemid=i1.itemid'.
				' JOIN host_hgset hh1 ON i1.hostid=hh1.hostid'.
				' LEFT JOIN permission p1 ON hh1.hgsetid=p1.hgsetid'.
					' AND p1.ugsetid=p.ugsetid'.
				' WHERE gi.graphid=gi1.graphid'.
					' AND p1.permission IS NULL'.
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

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
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
