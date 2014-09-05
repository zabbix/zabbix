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


/**
 * Class containing methods for operations with template screen items.
 *
 * @package API
 */
class CTemplateScreenItem extends CApiService {

	protected $tableName = 'screens_items';
	protected $tableAlias = 'si';

	protected $sortColumns = array(
		'screenitemid',
		'screenid'
	);

	public function __construct() {
		parent::__construct();

		$this->getOptions = zbx_array_merge($this->getOptions, array(
			'screenitemids'	=> null,
			'screenids'		=> null,
			'hostids'		=> null,
			'editable'		=> null,
			'sortfield'		=> '',
			'sortorder'		=> '',
			'preservekeys'	=> null,
			'countOutput'	=> null
		));
	}

	/**
	 * Get screen item data.
	 *
	 * @param array $options
	 * @param array $options['hostid']			Use hostid to get real resource id
	 * @param array $options['screenitemids']	Search by screen item IDs
	 * @param array $options['screenids']		Search by screen IDs
	 * @param array $options['filter']			Result filter
	 * @param array $options['limit']			The size of the result set
	 *
	 * @return array
	 */
	public function get(array $options = array()) {
		$options = zbx_array_merge($this->getOptions, $options);

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = array();
		while ($row = DBfetch($res)) {
			// count query, return a single result
			if ($options['countOutput'] !== null) {
				$result = $row['rowscount'];
			}
			// normal select query
			else {
				if ($options['preservekeys'] !== null) {
					$result[$row['screenitemid']] = $row;
				}
				else {
					$result[] = $row;
				}
			}
		}

		// fill result with real resourceid
		if ($options['hostids'] && $result) {
			if (empty($options['screenitemid'])) {
				$options['screenitemid'] = zbx_objectValues($result, 'screenitemid');
			}

			$dbTemplateScreens = API::TemplateScreen()->get(array(
				'output' => array('screenitemid'),
				'screenitemids' => $options['screenitemid'],
				'hostids' => $options['hostids'],
				'selectScreenItems' => API_OUTPUT_EXTEND
			));

			if ($dbTemplateScreens) {
				foreach ($result as &$screenItem) {
					foreach ($dbTemplateScreens as $dbTemplateScreen) {
						foreach ($dbTemplateScreen['screenitems'] as $dbScreenItem) {
							if ($screenItem['screenitemid'] == $dbScreenItem['screenitemid']
									&& isset($dbScreenItem['real_resourceid']) && $dbScreenItem['real_resourceid']) {
								$screenItem['real_resourceid'] = $dbScreenItem['real_resourceid'];
							}
						}
					}
				}
				unset($screenItem);
			}
		}

		return $result;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// screen ids
		if ($options['screenids'] !== null) {
			zbx_value2array($options['screenids']);
			$sqlParts = $this->addQuerySelect($this->fieldId('screenid'), $sqlParts);
			$sqlParts['where'][] = dbConditionInt($this->fieldId('screenid'), $options['screenids']);
		}

		return $sqlParts;
	}
}
