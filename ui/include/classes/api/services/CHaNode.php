<?php declare(strict_types = 0);
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
 * High availability Node API implementation.
 */
class CHaNode extends CApiService {

	public const ACCESS_RULES = [
		// Actual check for USER_TYPE_SUPER_ADMIN performed inside.
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'ha_node';
	protected $sortColumns = ['name', 'lastaccess', 'status'];
	protected $tableAlias = 'han';

	/**
	 * @param array $options
	 *
	 * @return array|string
	 *
	 * @throws APIException
	 */
	public function get(array $options = [], bool $api_call = true) {
		if ($api_call && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'ha_nodeids' =>				['type' => API_CUIDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'address' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_NODE_STATUS_STANDBY, ZBX_NODE_STATUS_STOPPED, ZBX_NODE_STATUS_UNAVAILABLE, ZBX_NODE_STATUS_ACTIVE])]
			]],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['ha_nodeid', 'name', 'address', 'port', 'lastaccess', 'status']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_nodes = [];

		$sql = $this->createSelectQuery($this->tableName, $options);
		$resource = DBselect($sql, $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_nodes[$row['ha_nodeid']] = $row;
		}

		if ($db_nodes) {
			$db_nodes = $this->unsetExtraFields($db_nodes, ['ha_nodeid'], $options['output']);
			$db_nodes = $this->unsetExtraFields($db_nodes, ['ha_sessionid']);

			if (!$options['preservekeys']) {
				$db_nodes = array_values($db_nodes);
			}
		}

		return $db_nodes;
	}
}
