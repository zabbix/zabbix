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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Class containing methods for operations with auditlog records.
 */
class CAuditLog extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	/**
	 * @var string Database table name.
	 */
	protected $tableName = 'auditlog';

	/**
	 * @var string Database table name alias.
	 */
	protected $tableAlias = 'a';

	/**
	 * @var array Database fields list allowed for sort operation.
	 */
	protected $sortColumns = ['auditid', 'userid', 'clock'];

	/**
	 * Method auditlog.get, returns audit log records according filtering criteria.
	 *
	 * @param array          $options                   Array of API request options.
	 * @param int|array      $options['auditids']       Filter by auditids.
	 * @param int|array      $options['userids']        Filter by userids.
	 * @param int            $options['time_from']      Filter by timestamp, range start time, inclusive.
	 * @param int            $options['time_till']      Filter by timestamp, range end time, inclusive.
	 * @param string         $options['sortfield']      Sorting field: auditid, userid, clock.
	 * @param string         $options['sortorder']      Sorting direction.
	 * @param array          $options['filter']         Filter by fields value, exact match.
	 * @param array          $options['search']         Filter by fields value, case insensitive search of substring.
	 * @param bool           $options['countOutput']
	 * @param bool           $options['excludeSearch']
	 * @param int            $options['limit']
	 * @param string|array   $options['output']
	 * @param bool           $options['preservekeys']
	 * @param bool           $options['searchByAny']
	 * @param bool           $options['searchWildcardsEnabled']
	 * @param bool           $options['startSearch']
	 *
	 * @throws APIException
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$result = [];
		$fields = array_keys($this->getTableSchema($this->tableName())['fields']);
		$actions = [
			CAudit::ACTION_ADD, CAudit::ACTION_UPDATE, CAudit::ACTION_DELETE, CAudit::ACTION_LOGOUT,
			CAudit::ACTION_EXECUTE, CAudit::ACTION_LOGIN_SUCCESS, CAudit::ACTION_LOGIN_FAILED,
			CAudit::ACTION_HISTORY_CLEAR
		];
		$resourcetype = [
			CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTHENTICATION, CAudit::RESOURCE_AUTH_TOKEN,
			CAudit::RESOURCE_AUTOREGISTRATION, CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD,
			CAudit::RESOURCE_DISCOVERY_RULE, CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE,
			CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE,
			CAudit::RESOURCE_HOUSEKEEPING, CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE,
			CAudit::RESOURCE_ITEM, CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_IT_SERVICE,
			CAudit::RESOURCE_MACRO, CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP, CAudit::RESOURCE_MEDIA_TYPE,
			CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
			CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SETTINGS, CAudit::RESOURCE_SLA,
			CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TRIGGER,
			CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER, CAudit::RESOURCE_USER_GROUP,
			CAudit::RESOURCE_USER_ROLE, CAudit::RESOURCE_VALUE_MAP, CAudit::RESOURCE_HA_NODE
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'auditids' =>				['type' => API_CUIDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'auditid' =>				['type' => API_CUIDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'userid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'clock' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'action' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', $actions)],
				'resourcetype' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', $resourcetype)],
				'ip' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourceid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourcename' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'username' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'recordsetid' =>			['type' => API_CUIDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'username' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'ip' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourcename' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'details' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'time_from' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $fields), 'default' => $fields],
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

		$sql_parts = [
			'select'	=> ['auditlog' => 'a.auditid'],
			'from'		=> ['auditlog' => 'auditlog a'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $fields;
		}

		if ($options['userids'] !== null) {
			$sql_parts['where']['userid'] = dbConditionId('a.userid', $options['userids']);
		}

		if ($options['time_from'] !== null) {
			$sql_parts['where'][] = 'a.clock>='.zbx_dbstr($options['time_from']);
		}

		if ($options['time_till'] !== null) {
			$sql_parts['where'][] = 'a.clock<='.zbx_dbstr($options['time_till']);
		}

		$sql_parts = $this->applyQueryFilterOptions($this->tableName, $this->tableAlias, $options, $sql_parts);
		$sql_parts = $this->applyQueryOutputOptions($this->tableName, $this->tableAlias, $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName, $this->tableAlias, $options, $sql_parts);
		$res = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($audit = DBfetch($res)) {
			if (!$options['countOutput']) {
				$result[$audit['auditid']] = $audit;
				continue;
			}

			$result = $audit['rowscount'];
		}

		if ($options['countOutput']) {
			return $result;
		}

		if (!$options['preservekeys']) {
			$result = array_values($result);
		}

		return $this->unsetExtraFields($result, ['auditid'], $options['output']);
	}
}
