<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Class containing methods for operations with auditlog records.
 */
class CAuditLog extends CApiService {

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
	 * @var array Database table with auditlog details supported fields list.
	 */
	protected $details_fields = ['table_name', 'field_name', 'oldvalue', 'newvalue'];

	/**
	 * Method auditlog.get, returns audit log records according filtering criteria.
	 *
	 * @param array          $options                   Array of API request options.
	 * @param int|array      $options['auditids']       Filter by auditids.
	 * @param int|array      $options['userids']        Filter by userids.
	 * @param int            $options['time_from']      Filter by timestamp, range start time, inclusive.
	 * @param int            $options['time_till']      Filter by timestamp, range end time, inclusive.
	 * @param string|array   $options['selectDetails']  Select additional details from auditlog_details.
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
			AUDIT_ACTION_ADD, AUDIT_ACTION_UPDATE, AUDIT_ACTION_DELETE, AUDIT_ACTION_LOGIN, AUDIT_ACTION_LOGOUT,
			AUDIT_ACTION_ENABLE, AUDIT_ACTION_DISABLE, AUDIT_ACTION_EXECUTE
		];
		$resourcetype = [
			AUDIT_RESOURCE_USER, AUDIT_RESOURCE_ZABBIX_CONFIG, AUDIT_RESOURCE_MEDIA_TYPE, AUDIT_RESOURCE_HOST,
			AUDIT_RESOURCE_ACTION, AUDIT_RESOURCE_GRAPH, AUDIT_RESOURCE_GRAPH_ELEMENT, AUDIT_RESOURCE_USER_GROUP,
			AUDIT_RESOURCE_APPLICATION, AUDIT_RESOURCE_TRIGGER, AUDIT_RESOURCE_HOST_GROUP, AUDIT_RESOURCE_ITEM,
			AUDIT_RESOURCE_IMAGE, AUDIT_RESOURCE_VALUE_MAP, AUDIT_RESOURCE_IT_SERVICE, AUDIT_RESOURCE_MAP,
			AUDIT_RESOURCE_SCREEN, AUDIT_RESOURCE_SCENARIO, AUDIT_RESOURCE_DISCOVERY_RULE, AUDIT_RESOURCE_SLIDESHOW,
			AUDIT_RESOURCE_SCRIPT, AUDIT_RESOURCE_PROXY, AUDIT_RESOURCE_MAINTENANCE, AUDIT_RESOURCE_REGEXP,
			AUDIT_RESOURCE_MACRO, AUDIT_RESOURCE_TEMPLATE, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, AUDIT_RESOURCE_ICON_MAP,
			AUDIT_RESOURCE_DASHBOARD, AUDIT_RESOURCE_CORRELATION, AUDIT_RESOURCE_GRAPH_PROTOTYPE,
			AUDIT_RESOURCE_ITEM_PROTOTYPE, AUDIT_RESOURCE_HOST_PROTOTYPE, AUDIT_RESOURCE_AUTOREGISTRATION,
			AUDIT_RESOURCE_MODULE
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'auditids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'auditid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'userid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'clock' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'action' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', $actions)],
				'resourcetype' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', $resourcetype)],
				'note' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'ip' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourceid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourcename' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'table_name' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'field_name' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'note' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'ip' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'resourcename' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'oldvalue' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'newvalue' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'time_from' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'selectDetails' => 			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $this->details_fields), 'default' => null],
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

		if ($result && $options['selectDetails'] !== null) {
			$result = $this->addRelatedObjects($options, $result);
		}

		if (!$options['preservekeys']) {
			$result = array_values($result);
		}

		return $this->unsetExtraFields($result, ['auditid'], $options['output']);
	}

	/**
	 * Add related objects from auditlog_details table if requested.
	 *
	 * @param array $options    Array of API request options.
	 * @param array $result     Associative array of selected auditlog data, key is auditid property.
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$fields = [];

		foreach ($this->details_fields as $field) {
			if ($this->outputIsRequested($field, $options['selectDetails'])) {
				$fields[] = $field;
			}
		};

		foreach ($result as &$row) {
			$row['details'] = [];
		}
		unset($row);

		if ($fields) {
			$relation_fields = ['auditid', 'auditdetailid'];
			$auditlog_details = API::getApiService()->select('auditlog_details', [
				'output' => array_merge($fields, $relation_fields),
				'filter' => ['auditid' => array_keys($result)],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($auditlog_details, 'auditid', 'auditdetailid');
			$auditlog_details = $this->unsetExtraFields($auditlog_details, $relation_fields, []);
			$result = $relation_map->mapMany($result, $auditlog_details, 'details');
		}

		return $result;
	}

	/**
	 * Apply filter and search options to $sql_parts query. Also add auditlog_details alias if filter or search requires
	 * field from auditlog_details table.
	 *
	 * @param string $table        Table name.
	 * @param string $alias        Table alias.
	 * @param array  $options      Request options.
	 * @param array  $sql_parts    Array of SQL query parts to be modified.
	 *
	 * @return array
	 */
	protected function applyQueryFilterOptions($table, $alias, array $options, array $sql_parts): array {
		$filter = ($options['filter'] !== null)
			? array_intersect_key($options['filter'], array_flip($this->details_fields))
			: [];
		$search = ($options['search'] !== null)
			? array_intersect_key($options['search'], array_flip(['oldvalue', 'newvalue']))
			: [];

		if ($filter || $search) {
			$details_options = ['filter' => $filter, 'search' => $search] + $options;
			$sql_parts['where']['aad'] = 'a.auditid=ad.auditid';
			$sql_parts['from']['auditlog_details'] = 'auditlog_details ad';
			$sql_parts = parent::applyQueryFilterOptions('auditlog_details', 'ad', $details_options, $sql_parts);
		}

		return parent::applyQueryFilterOptions($table, $alias, $options, $sql_parts);
	}
}
