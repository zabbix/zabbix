<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


/**
 * File containing CScreenItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with ScreenItems
 */
class CTemplateScreenItem extends CZBXAPI {

	protected $tableName = 'screens_items';
	protected $tableAlias = 'si';

	/**
	 * Supported values for the resourcetype column.
	 *
	 * @var array
	 */
	protected static $resourceTypes = array(
		SCREEN_RESOURCE_GRAPH,
		SCREEN_RESOURCE_SIMPLE_GRAPH,
		SCREEN_RESOURCE_MAP,
		SCREEN_RESOURCE_PLAIN_TEXT,
		SCREEN_RESOURCE_HOSTS_INFO,
		SCREEN_RESOURCE_TRIGGERS_INFO,
		SCREEN_RESOURCE_SERVER_INFO,
		SCREEN_RESOURCE_CLOCK,
		SCREEN_RESOURCE_SCREEN,
		SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
		SCREEN_RESOURCE_DATA_OVERVIEW,
		SCREEN_RESOURCE_URL,
		SCREEN_RESOURCE_ACTIONS,
		SCREEN_RESOURCE_EVENTS,
		SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
		SCREEN_RESOURCE_SYSTEM_STATUS,
		SCREEN_RESOURCE_HOST_TRIGGERS
	);

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
	 * Get ScreemItem data
	 *
	 * @param array $options
	 * @param array $options['nodeids']			Node IDs
	 * @param array $options['hostid']			Use hostid to get real resource id
	 * @param array $options['screenitemids']	Search by screen item IDs
	 * @param array $options['screenids']		Search by screen IDs
	 * @param array $options['filter']			Result filter
	 * @param array $options['limit']			The size of the result set
	 *
	 * @return array|boolean Host data as array or false if error
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
		if (!is_null($options['hostids']) && !empty($result)) {
			if (empty($options['screenitemid'])) {
				$options['screenitemid'] = zbx_objectValues($result, 'screenitemid');
			}

			$templateScreens = API::TemplateScreen()->get(array(
				'screenitemids' => $options['screenitemid'],
				'hostids' => $options['hostids'],
				'selectScreenItems' => API_OUTPUT_EXTEND
			));
			if (!empty($templateScreens)) {
				foreach ($result as &$resultScreenitem) {
					foreach ($templateScreens as $templateScreen) {
						foreach ($templateScreen['screenitems'] as $screenitem) {
							if ($resultScreenitem['screenitemid'] == $screenitem['screenitemid'] && !empty($screenitem['real_resourceid'])) {
								$resultScreenitem['real_resourceid'] = $screenitem['real_resourceid'];
							}
						}
					}
				}
				unset($resultScreenitem);
			}
		}

		return $result;
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific screen ids are given
		if ($options['screenids'] === null) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// screen ids
		if ($options['screenids'] !== null) {
			zbx_value2array($options['screenids']);
			$sqlParts = $this->addQuerySelect($this->fieldId('screenid'), $sqlParts);
			$sqlParts['where'][] = DBcondition($this->fieldId('screenid'), $options['screenids']);
		}

		return $sqlParts;
	}
}
