<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class to validate input parameters that are passed to jsrpc.php.
 */
class CJsRpcInputValidator {

	/**
	 * Validate input parameters that are passed to jsrpc.php. Validation rules depend on the value of "method"
	 * parameter, which is mandatory.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	public static function validate(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['search',
				'zabbix.status', 'screen.get', 'trigger.get', 'multiselect.get', 'patternselect.get',
				'item_value_type.get', 'get_scripts_by_hosts', 'get_scripts_by_events', 'link_thresholds.validate'
			])]
		]];

		if (!CApiInputValidator::validate($rules, $data, '/', $error)) {
			return false;
		}

		switch ($data['method']) {
			case 'search':
				return self::validateSearch($data);

			case 'zabbix.status':
				return self::validateZabbixStatus($data);

			case 'screen.get':
				return self::validateScreenGet($data);

			case 'trigger.get':
				return self::validateTriggerGet($data);

			case 'multiselect.get':
				return self::validateMultiselectGet($data);

			case 'patternselect.get':
				return self::validatePatternselectGet($data);

			case 'item_value_type.get':
				return self::validateItemValueTypeGet($data);

			case 'get_scripts_by_hosts':
				return self::validateGetScriptsByHosts($data);

			case 'get_scripts_by_events':
				return self::validateGetScriptsByEvents($data);

			case 'link_thresholds.validate':
				return self::validateLinkThresholds($data);
		}

		return false;
	}

	/**
	 * Validate the input parameters for global search box.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateSearch(array $data): bool {
		$error = '';

		// Only "search" parameter is passed to jsrpc.php.
		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'search'],
			'jsonrpc' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'id' => ['type' => API_JSONRPC_ID, 'flags' => API_REQUIRED],
			'params' => ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'search' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for periodical server status check.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateZabbixStatus(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'zabbix.status'],

			// None of these parameters are actually used in jsrpc.php.
			'jsonrpc' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'id' => ['type' => API_JSONRPC_ID, 'flags' => API_REQUIRED],
			'params' => ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'nocache' => ['type' => API_BOOLEAN, 'flags' => API_REQUIRED]
			]]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "flickerfreescreen". Used by several older components like network discovery
	 * rules and history with value (and latest 500) view, and HTTP test and its details. Validation of these parameters
	 * will be become obsolete when DEV-4282 is implemented.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateScreenGet(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'screen.get'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT],
			'timestamp' => ['type' => API_UINT64],
			'mode' => ['type' => API_INT32],
			'resourcetype' => ['type' => API_INT32],
			'profileIdx2' => ['type' => API_INT32],
			'data' => ['type' => API_OBJECT, 'fields' => [
				'sort' => ['type' => API_STRING_UTF8],
				'sortorder' => ['type' => API_STRING_UTF8],
				'filter_druleids' => ['type' => API_IDS, 'flags' => API_NORMALIZE],
				'groupids' => ['type' => API_ANY],
				'hostids' => ['type' => API_ANY],
				'evaltype' => ['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR])],
				'tags' => ['type' => API_OBJECTS, 'fields' => [
					'tag' => ['type' => API_STRING_UTF8],
					'value' => ['type' => API_STRING_UTF8],
					'operator' => ['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL,
						TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS
					])]
				]]
			]],
			'page' => ['type' => API_INT32],
			'pageFile' => ['type' => API_STRING_UTF8],
			'from' => ['type' => API_STRING_UTF8],
			'to' => ['type' => API_STRING_UTF8],
			'itemids' => ['type' => API_IDS, 'flags' => API_NORMALIZE],
			'filter' => ['type' => API_STRING_UTF8],
			'filter_task' => ['type' => API_INT32],
			'mark_color' => ['type' => API_INT32],
			'action' => ['type' => API_STRING_UTF8, 'in' => implode(',', [HISTORY_VALUES, HISTORY_LATEST])]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "trigger.get". Used in maps when adding a trigger.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateTriggerGet(array $data): bool {
		$error = '';

		// Only "triggerids" reaches API in jsrpc.php.
		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'trigger.get'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'triggerids' => ['type' => API_IDS, 'flags' => API_REQUIRED]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "multiselect.get". Used in numerous places in the UI.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateMultiselectGet(array $data): bool {
		$error = '';

		$head_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'multiselect.get'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'object_name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['hostGroup',
				'templateGroup', 'hosts', 'templates', 'items', 'item_prototypes', 'graphs', 'graph_prototypes',
				'triggers', 'users', 'usersGroups', 'drules', 'api_methods', 'valuemap_names', 'valuemaps',
				'template_valuemaps', 'sla', 'proxies', 'proxy_groups', 'roles', 'actions', 'media_types', 'dashboard',
				'services', 'sysmaps', 'host_inventory'
			])],
			'search' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'curtime' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'_' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'limit' => ['type' => API_INT32, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($head_rules, $data, '/', $error)) {
			return false;
		}

		switch ($data['object_name']) {
			case 'hostGroup':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'editable' => ['type' => API_INT32],
					'with_items' => ['type' => API_INT32],
					'with_triggers' => ['type' => API_INT32],
					'with_httptests' => ['type' => API_INT32],
					'with_hosts' => ['type' => API_INT32],
					'with_monitored_triggers' => ['type' => API_INT32],
					'enrich_parent_groups' => ['type' => API_INT32],
					'filter' => ['type' => API_OBJECT, 'fields' => [
						'flags' => ['type' => API_INT32, 'in' => ZBX_FLAG_DISCOVERY_NORMAL]
					]],

					// Options are either not relevant to host group API or simply are not passed.
					'with_graphs' => ['type' => API_INT32],
					'with_monitored_items' => ['type' => API_INT32],
					'with_simple_graph_items' => ['type' => API_INT32],
					'with_graph_prototypes' => ['type' => API_INT32],
					'with_simple_graph_item_prototypes' => ['type' => API_INT32]
				]];
				break;

			case 'templateGroup':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'editable' => ['type' => API_INT32],
					'with_templates' => ['type' => API_INT32],
					'with_triggers' => ['type' => API_INT32],
					'enrich_parent_groups' => ['type' => API_INT32],

					// Option is not relevant to template group API and is not passed.
					'with_monitored_triggers' => ['type' => API_INT32]
				]];
				break;

			case 'hosts':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'editable' => ['type' => API_INT32],
					'with_items' => ['type' => API_INT32],
					'with_triggers' => ['type' => API_INT32],
					'with_httptests' => ['type' => API_INT32],
					'with_monitored_items' => ['type' => API_INT32],
					'with_monitored_triggers' => ['type' => API_INT32],
					'filter' => ['type' => API_OBJECT, 'fields' => [
						'flags' => ['type' => API_INT32, 'in' => ZBX_FLAG_DISCOVERY_NORMAL]
					]],

					// Options are either not relevant to host API or simply are not passed.
					'monitored' => ['type' => API_INT32],
					'real_hosts' => ['type' => API_INT32],
					'with_graphs' => ['type' => API_INT32],
					'with_simple_graph_items' => ['type' => API_INT32],
					'with_graph_prototypes' => ['type' => API_INT32],
					'with_simple_graph_item_prototypes' => ['type' => API_INT32]
				]];
				break;

			case 'templates':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'editable' => ['type' => API_INT32],

					// Options are either not relevant to template API or simply are not passed.
					'with_triggers' => ['type' => API_INT32],
					'with_monitored_triggers' => ['type' => API_INT32]
				]];
				break;

			case 'items':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostid' => ['type' => API_ID],
					'real_hosts' => ['type' => API_INT32],
					'resolve_macros' => ['type' => API_INT32],
					'filter' => ['type' => API_OBJECT, 'fields' => [
						'value_type' => ['type' => API_INTS32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT,
							ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT
						])],
						'flags' => ['type' => API_INT32, 'in' => ZBX_FLAG_DISCOVERY_NORMAL]
					]],

					// Option not relevant to item API and is not passed either.
					'with_simple_graph_items' => ['type' => API_INT32]
				]];
				break;

			case 'item_prototypes':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostid' => ['type' => API_ID],
					'real_hosts' => ['type' => API_INT32],
					'filter' => ['type' => API_OBJECT, 'fields' => [
						'value_type' => ['type' => API_INTS32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT,
							ITEM_VALUE_TYPE_UINT64
						])]
					]],

					// Option not relevant to item prototype API and is not passed either.
					'with_simple_graph_item_prototypes' => ['type' => API_INT32]
				]];
				break;

			case 'graphs':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostid' => ['type' => API_ID],
					'real_hosts' => ['type' => API_INT32],

					// Option not relevant to graph API and is not passed either.
					'with_graphs' => ['type' => API_INT32]
				]];
				break;

			case 'graph_prototypes':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostid' => ['type' => API_ID],
					'real_hosts' => ['type' => API_INT32],

					// Option not relevant to graph prototype API and is not passed either.
					'with_graph_prototypes' => ['type' => API_INT32]
				]];
				break;

			case 'triggers':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'real_hosts' => ['type' => API_INT32],
					'templated' => ['type' => API_INT32],

					// Options not relevant to trigger API and are not passed either.
					'with_triggers' => ['type' => API_INT32],
					'with_monitored_triggers' => ['type' => API_INT32]
				]];
				break;

			case 'users':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'context' => ['type' => API_STRING_UTF8],
					'exclude_provisioned' => ['type' => API_STRING_UTF8]
				]];
				break;

			case 'usersGroups':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'group_status' => ['type' => API_INT32],

					// Option is not passed to API.
					'editable' => ['type' => API_INT32]
				]];
				break;

			case 'drules':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'enabled_only' => ['type' => API_INT32]
				]];
				break;

			case 'api_methods':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'user_type' => ['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER,
						USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN
					])]
				]];
				break;

			case 'valuemap_names':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostids' => ['type' => API_IDS, 'flags' => API_NORMALIZE | API_REQUIRED],
					'context' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['host',
						'template'
					])],
					'with_inherited' => ['type' => API_INT32],

					// Option is not passed to API.
					'editable' => ['type' => API_INT32]
				]];
				break;

			case 'valuemaps':
			case 'template_valuemaps':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostids' => ['type' => API_IDS, 'flags' => API_NORMALIZE | API_REQUIRED],
					'context' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['host',
						'template'
					])],

					// Option is not passed to API.
					'editable' => ['type' => API_INT32]
				]];
				break;

			case 'sla':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'enabled_only' => ['type' => API_INT32]
				]];
				break;

			// "search" parameter has already been validated at this point. Skip further validation for these methods.
			case 'proxies':
			case 'proxy_groups':
			case 'roles':
			case 'actions':
			case 'media_types':
			case 'dashboard':
			case 'services':
			case 'sysmaps':
			case 'host_inventory':
				return true;
		}

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "patternselect.get". Used in modern graph datasets, overrides and problem
	 * filter when selecting hosts or items by pattern.
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validatePatternselectGet(array $data): bool {
		$error = '';

		$head_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'patternselect.get'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'object_name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', ['hosts',
				'items'
			])],
			'search' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'curtime' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'_' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'limit' => ['type' => API_INT32, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($head_rules, $data, '/', $error)) {
			return false;
		}

		switch ($data['object_name']) {
			case 'hosts':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'wildcard_allowed' => ['type' => API_INT32]
				]];
				break;

			case 'items':
				$rules = ['type' => API_OBJECT, 'fields' => $head_rules['fields'] + [
					'hostid' => ['type' => API_ID],
					'real_hosts' => ['type' => API_INT32],
					'resolve_macros' => ['type' => API_INT32],
					'filter' => ['type' => API_OBJECT, 'fields' => [
						'value_type' => ['type' => API_INTS32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT,
							ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT,
							ITEM_VALUE_TYPE_BINARY
						])],
					]],
					'host_pattern' => ['type' => API_STRINGS_UTF8],
					'host_pattern_multiple' => ['type' => API_INT32],
					'host_pattern_wildcard_allowed' => ['type' => API_INT32],
					'wildcard_allowed' => ['type' => API_INT32]
				]];
				break;
		}

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "item_value_type.get".
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateItemValueTypeGet(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'item_value_type.get'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'itemid' => ['type' => API_ID]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "get_scripts_by_hosts".
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateGetScriptsByHosts(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'get_scripts_by_hosts'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'manualinput' => ['type' => API_STRING_UTF8],
			'scriptid' => ['type' => API_ID],
			'hostid' => ['type' => API_ID]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "get_scripts_by_events".
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateGetScriptsByEvents(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'get_scripts_by_events'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'manualinput' => ['type' => API_STRING_UTF8],
			'scriptid' => ['type' => API_ID],
			'eventid' => ['type' => API_ID]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}

	/**
	 * Validate the input parameters for "link_thresholds.validate".
	 *
	 * @param array $data  Input data to validate.
	 *
	 * @return bool
	 */
	private static function validateLinkThresholds(array $data): bool {
		$error = '';

		$rules = ['type' => API_OBJECT, 'fields' => [
			'method' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'link_thresholds.validate'],
			'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => PAGE_TYPE_TEXT_RETURN_JSON],
			'thresholds' => ['type' => API_OBJECTS, 'fields' => [
				'threshold' => ['type' => API_ANY]
			]]
		]];

		return CApiInputValidator::validate($rules, $data, '/', $error);
	}
}
