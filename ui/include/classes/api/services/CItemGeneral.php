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
 * Class containing methods for operations with item general.
 */
abstract class CItemGeneral extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	public const INTERFACE_TYPES_BY_PRIORITY = [
		INTERFACE_TYPE_AGENT,
		INTERFACE_TYPE_SNMP,
		INTERFACE_TYPE_JMX,
		INTERFACE_TYPE_IPMI
	];

	/**
	 * A list of supported preprocessing types.
	 *
	 * @var array
	 */
	public const SUPPORTED_PREPROCESSING_TYPES = [];

	/**
	 * A list of preprocessing types that supports the "params" field.
	 *
	 * @var array
	 */
	protected const PREPROC_TYPES_WITH_PARAMS = [
		ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_TRIM, ZBX_PREPROC_REGSUB,
		ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX,
		ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML,
		ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
		ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON,
		ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_SNMP_WALK_VALUE,
		ZBX_PREPROC_SNMP_WALK_TO_JSON, ZBX_PREPROC_SNMP_GET_VALUE
	];

	/**
	 * A list of preprocessing types that supports the error handling.
	 *
	 * @var array
	 */
	protected const PREPROC_TYPES_WITH_ERR_HANDLING = [
		ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_REGSUB, ZBX_PREPROC_BOOL2DEC, ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC,
		ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
		ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX,
		ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX,
		ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON,
		ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_XML_TO_JSON, ZBX_PREPROC_SNMP_WALK_VALUE,
		ZBX_PREPROC_SNMP_WALK_TO_JSON, ZBX_PREPROC_SNMP_GET_VALUE
	];

	/**
	 * A list of supported item types.
	 *
	 * @var array
	 */
	protected const SUPPORTED_ITEM_TYPES = [];

	/**
	 * A list of field names for each of value types.
	 *
	 * @var array
	 */
	protected const VALUE_TYPE_FIELD_NAMES = [];

	/**
	 * Maximum number of items per iteration.
	 *
	 * @var int
	 */
	protected const CHUNK_SIZE = 1000;

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get($options = []);

	/**
	 * @param array      $field_names
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function validateByType(array $field_names, array &$items, array $db_items = null): void {
		$checked_fields = array_fill_keys($field_names, ['type' => API_ANY]);

		foreach ($items as $i => &$item) {
			$api_input_rules = ['type' => API_OBJECT, 'fields' => $checked_fields];
			$db_item = ($db_items === null) ? null : $db_items[$item['itemid']];
			$item_type = CItemTypeFactory::getObject($item['type']);

			if ($db_item === null) {
				$api_input_rules['fields'] += $item_type::getCreateValidationRules($item);
			}
			elseif ($db_item['templateid'] != 0) {
				if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
					$item += array_intersect_key($db_item, array_flip(['allow_traps']));
				}
				elseif ($item['type'] == ITEM_TYPE_SSH) {
					$item += array_intersect_key($db_item, array_flip(['authtype']));
				}

				if ($item['type'] === ITEM_TYPE_SSH && $item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY
						&& $db_item['authtype'] != ITEM_AUTHTYPE_PUBLICKEY) {
					$item += array_intersect_key($db_item, array_flip(['publickey', 'privatekey']));
				}

				$api_input_rules['fields'] += $item_type::getUpdateValidationRulesInherited($db_item);
			}
			elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$api_input_rules['fields'] += $item_type::getUpdateValidationRulesDiscovered();
			}
			else {
				if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
					$item += array_intersect_key($db_item, array_flip(
						['request_method', 'post_type', 'authtype', 'allow_traps']
					));
				}
				elseif ($item['type'] == ITEM_TYPE_SSH) {
					$item += array_intersect_key($db_item, array_flip(['authtype']));
				}

				$interfaceid_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI,
					ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_HTTPAGENT,
					ITEM_TYPE_SNMP
				];

				if (in_array($item['type'], $interfaceid_types)) {
					$opt_interface_types = [ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
						ITEM_TYPE_HTTPAGENT
					];

					if (in_array($db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
							&& (!in_array($db_item['type'], $interfaceid_types)
								|| (in_array($item['type'], array_diff($interfaceid_types, $opt_interface_types))
									&& in_array($db_item['type'], $opt_interface_types)
									&& $db_item['interfaceid'] == 0))) {
						$item += array_intersect_key($db_item, array_flip(['interfaceid']));
					}
				}

				$username_types = [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
					ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT
				];

				if (in_array($item['type'], [ITEM_TYPE_SSH, ITEM_TYPE_TELNET])) {
					$opt_username_types = array_diff($username_types, [ITEM_TYPE_SSH, ITEM_TYPE_TELNET]);

					if (!in_array($db_item['type'], $username_types)
							|| (in_array($db_item['type'], $opt_username_types) && $db_item['username'] === '')) {
						$item += array_intersect_key($db_item, array_flip(['username']));
					}
				}

				$params_types = [ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
					ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
				];

				if (in_array($item['type'], $params_types) && !in_array($db_item['type'], $params_types)) {
					$item += array_intersect_key($db_item, array_flip(['params']));
				}

				$delay_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
					ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
					ITEM_TYPE_BROWSER
				];

				if (in_array($item['type'], $delay_types)) {
					if (!in_array($db_item['type'], $delay_types)
							|| ($db_item['type'] == ITEM_TYPE_ZABBIX_ACTIVE
								&& strncmp($db_item['key_'], 'mqtt.get', 8) == 0)) {
						$item += array_intersect_key($db_item, array_flip(['delay']));
					}
				}

				if ($item['type'] == ITEM_TYPE_DEPENDENT && $db_item['type'] != ITEM_TYPE_DEPENDENT) {
					$item += array_intersect_key($db_item, array_flip(['master_itemid']));
				}

				if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
					if ($db_item['type'] != ITEM_TYPE_HTTPAGENT) {
						$item += array_intersect_key($db_item, array_flip(['url']));
					}

					$post_types = [ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML];

					if (in_array($item['post_type'], $post_types) && !in_array($db_item['post_type'], $post_types)) {
						$item += array_intersect_key($db_item, array_flip(['posts']));
					}
				}

				if ($item['type'] == ITEM_TYPE_IPMI
						&& ($db_item['type'] != ITEM_TYPE_IPMI
							|| ($item['key_'] !== $db_item['key_'] && $db_item['key_'] === 'ipmi.get'))) {
					$item += array_intersect_key($db_item, array_flip(['ipmi_sensor']));
				}

				if ($item['type'] == ITEM_TYPE_JMX && $db_item['type'] != ITEM_TYPE_JMX) {
					$item += array_intersect_key($db_item, array_flip(['jmx_endpoint']));
				}

				if ($item['type'] == ITEM_TYPE_SNMP) {
					$item += array_intersect_key($db_item, array_flip(['snmp_oid']));
				}

				if ($item['type'] === ITEM_TYPE_SSH && $item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY
						&& $db_item['authtype'] != ITEM_AUTHTYPE_PUBLICKEY) {
					$item += array_intersect_key($db_item, array_flip(['publickey', 'privatekey']));
				}

				$api_input_rules['fields'] += $item_type::getUpdateValidationRules($db_item);
			}

			$api_input_rules['fields'] += CItemType::getDefaultValidationRules();

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if ($item['type'] == ITEM_TYPE_JMX) {
				if (array_key_exists('username', $item) || array_key_exists('password', $item)
						|| ($db_item !== null && $db_item['type'] != ITEM_TYPE_JMX)) {
					$_item = array_intersect_key($item, array_flip(['username', 'password']));

					if ($db_item === null) {
						$_item += array_fill_keys(['username', 'password'], '');
					}
					else {
						$_item += array_intersect_key($db_item, array_flip(['username', 'password']));
					}

					if (($_item['username'] === '') !== ($_item['password'] === '')) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
							_('both username and password should be either present or empty')
						));
					}
				}
			}

			if (array_key_exists('query_fields', $item)) {
				if (strlen(self::prepareQueryFieldsForDb($item['query_fields']))
						> DB::getFieldLength('items', 'query_fields')) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/query_fields', _('value is too long')
					));
				}

				$sortorder = 0;
				$fields = [];

				foreach ($item['query_fields'] as $field) {
					$fields[++$sortorder] = ['sortorder' => $sortorder] + $field;
				}

				$item['query_fields'] = $fields;
			}

			if (array_key_exists('headers', $item)) {
				if (strlen(self::prepareHeadersForDb($item['headers'])) > DB::getFieldLength('items', 'headers')) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/headers', _('value is too long')
					));
				}

				$sortorder = 0;
				$fields = [];

				foreach ($item['headers'] as $field) {
					$fields[++$sortorder] = ['sortorder' => $sortorder] + $field;
				}

				$item['headers'] = $fields;
			}
		}
		unset($item);
	}

	/**
	 * Check preprocessing steps do not have duplicates with "Check for not supported item" with "error any".
	 *
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected static function checkPreprocessingStepsDuplicates(array $items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'fields' => [
			'preprocessing' => ['type' => API_OBJECTS, 'uniq' => [['type', 'params']], 'fields' => [
				'type' =>	['type' => API_ANY],
				'params' =>	['type' => API_ANY]
			]]
		]];
		$items_steps = [];

		foreach ($items as $i1 => $item) {
			if (!array_key_exists('preprocessing', $item)) {
				continue;
			}

			foreach ($item['preprocessing'] as $i2 => $step) {
				if ($step['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
					[$match_type] = explode("\n", $step['params']);

					if ($match_type == ZBX_PREPROC_MATCH_ERROR_ANY) {
						$items_steps[$i1]['preprocessing'][$i2] = [
							'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
							'params' => ZBX_PREPROC_MATCH_ERROR_ANY
						];
					}
				}
			}
		}

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $items_steps, '', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array $items
	 */
	protected static function validateUniqueness(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'uuid' =>	['type' => API_ANY],
			'hostid' =>	['type' => API_ANY],
			'key_' =>	['type' => API_ANY]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @return array
	 */
	protected static function getTagsValidationRules(): array {
		return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
			'tag' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_tag', 'tag')],
			'value' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('item_tag', 'value')]
		]];
	}

	/**
	 * @param int $flags
	 *
	 * @return array
	 */
	public static function getPreprocessingValidationRules(int $flags = 0x00): array {
		return [
			'type' => API_OBJECTS,
			'flags' => API_NORMALIZE,
			'uniq_by_values' => [
				['type' => [ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED]],
				['type' => [ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				['type' => [ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON]]
			],
			'fields' => [
				'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', static::SUPPORTED_PREPROCESSING_TYPES)],
				'params' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', static::PREPROC_TYPES_WITH_PARAMS)], 'type' => API_PREPROC_PARAMS, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO | ($flags & API_ALLOW_LLD_MACRO), 'preproc_type' => ['field' => 'type'], 'length' => DB::getFieldLength('item_preproc', 'params')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('item_preproc', 'params')]
				]],
				'error_handler' =>			['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', array_diff(static::PREPROC_TYPES_WITH_ERR_HANDLING, [ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]))], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PREPROC_FAIL_DEFAULT, ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE, ZBX_PREPROC_FAIL_SET_ERROR])],
												['if' => ['field' => 'type', 'in' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE, ZBX_PREPROC_FAIL_SET_ERROR])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('item_preproc', 'error_handler')]
				]],
				'error_handler_params' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => static function (array $data): bool {
													return array_key_exists('error_handler', $data) && $data['error_handler'] == ZBX_PREPROC_FAIL_SET_VALUE;
												},  'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_preproc', 'error_handler_params')],
												['if' => static function (array $data): bool {
													return array_key_exists('error_handler', $data) && $data['error_handler'] == ZBX_PREPROC_FAIL_SET_ERROR;
												},  'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_preproc', 'error_handler_params')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('item_preproc', 'error_handler_params')]
				]]
			]
		];
	}

	/**
	 * Check that host IDs of given items are valid.
	 * If host IDs are valid, $db_hosts and $db_templates parameters will be filled with found hosts and templates.
	 *
	 * @param array      $items
	 * @param array|null $db_hosts
	 * @param array|null $db_templates
	 *
	 * @throws APIException
	 */
	protected static function checkHostsAndTemplates(array $items, array &$db_hosts = null,
			array &$db_templates = null): void {
		$hostids = array_unique(array_column($items, 'hostid'));

		$db_templates = API::Template()->get([
			'output' => [],
			'templateids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		$_hostids = array_diff($hostids, array_keys($db_templates));

		$db_hosts = $_hostids
			? API::Host()->get([
				'output' => ['status'],
				'hostids' => $_hostids,
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		if (count($db_templates) + count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Add host_status property to given items in accordance to statuses of given hosts and templates.
	 *
	 * @param array $items
	 * @param array $db_hosts
	 * @param array $db_templates
	 */
	protected static function addHostStatus(array &$items, array $db_hosts, array $db_templates): void {
		foreach ($items as &$item) {
			$item['host_status'] = array_key_exists($item['hostid'], $db_templates)
				? HOST_STATUS_TEMPLATE
				: $db_hosts[$item['hostid']]['status'];
		}
		unset($item);
	}

	/**
	 * Add flags property to given items with the given flags value.
	 *
	 * @param array $items
	 * @param int   $flags
	 */
	protected static function addFlags(array &$items, int $flags): void {
		foreach ($items as &$item) {
			$item['flags'] = $flags;
		}
		unset($item);
	}

	/**
	 * Add the UUID to those of the given items that belong to a template and don't have the 'uuid' parameter set.
	 *
	 * @param array $items
	 */
	protected static function addUuid(array &$items): void {
		foreach ($items as &$item) {
			if ($item['host_status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $item)) {
				$item['uuid'] = generateUuidV4();
			}
		}
		unset($item);
	}

	/**
	 * Verify host prototype UUIDs are not repeated.
	 *
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkUuidDuplicates(array $items, array $db_items = null): void {
		$item_indexes = [];

		foreach ($items as $i => $item) {
			if (!array_key_exists('uuid', $item)) {
				continue;
			}

			if ($db_items === null || $item['uuid'] !== $db_items[$item['itemid']]['uuid']) {
				$item_indexes[$item['uuid']] = $i;
			}
		}

		if (!$item_indexes) {
			return;
		}

		$flags = $items[reset($item_indexes)]['flags'];

		$duplicates = DB::select('items', [
			'output' => ['uuid'],
			'filter' => [
				'flags' => $flags,
				'uuid' => array_keys($item_indexes)
			],
			'limit' => 1
		]);

		if ($duplicates) {
			switch ($flags) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$error = _s('Invalid parameter "%1$s": %2$s.', '/'.($item_indexes[$duplicates[0]['uuid']] + 1),
						_('item with the same UUID already exists')
					);
					break;

				case ZBX_FLAG_DISCOVERY_RULE:
					$error = _s('Invalid parameter "%1$s": %2$s.', '/'.($item_indexes[$duplicates[0]['uuid']] + 1),
						_('LLD rule with the same UUID already exists')
					);
					break;

				case ZBX_FLAG_DISCOVERY_PROTOTYPE:
					$error = _s('Invalid parameter "%1$s": %2$s.', '/'.($item_indexes[$duplicates[0]['uuid']] + 1),
						_('item prototype with the same UUID already exists')
					);
					break;
			}

			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array      $items
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	protected static function getTemplateLinks(array $items, ?array $hostids): array {
		if ($hostids !== null) {
			$db_hosts = DB::select('hosts', [
				'output' => ['hostid', 'status'],
				'hostids' => $hostids,
				'preservekeys' => true
			]);

			$tpl_links = [];

			foreach ($items as $item) {
				$tpl_links[$item['hostid']] = $db_hosts;
			}
		}
		else {
			$templateids = [];

			foreach ($items as $item) {
				if ($item['host_status'] == HOST_STATUS_TEMPLATE) {
					$templateids[$item['hostid']] = true;
				}
			}

			if (!$templateids) {
				return [];
			}

			$result = DBselect(
				'SELECT ht.templateid,ht.hostid,h.status'.
				' FROM hosts_templates ht,hosts h'.
				' WHERE ht.hostid=h.hostid'.
					' AND '.dbConditionId('ht.templateid', array_keys($templateids)).
					' AND '.dbConditionInt('h.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED])
			);

			$tpl_links = [];

			while ($row = DBfetch($result)) {
				$tpl_links[$row['templateid']][$row['hostid']] = [
					'hostid' => $row['hostid'],
					'status' => $row['status']
				];
			}
		}

		return $tpl_links;
	}

	/**
	 * Filter out inheritable items from the given items.
	 *
	 * @param array $items
	 * @param array $db_items
	 * @param array $tpl_links
	 */
	protected static function filterObjectsToInherit(array &$items, array &$db_items, array $tpl_links): void {
		foreach ($items as $i => $item) {
			if (!array_key_exists($item['hostid'], $tpl_links)) {
				unset($items[$i]);

				if (array_key_exists($item['itemid'], $db_items)) {
					unset($db_items[$item['itemid']]);
				}
			}
		}
	}

	/**
	 * Check that no items with repeating keys would be inherited to a single host or template.
	 *
	 * @param array $items
	 * @param array $db_items
	 * @param array $tpl_links
	 *
	 * @throws APIException
	 */
	protected static function checkDoubleInheritedNames(array $items, array $db_items, array $tpl_links): void {
		$item_indexes = [];

		foreach ($items as $i => $item) {
			if (array_key_exists($item['itemid'], $db_items) && $item['key_'] === $db_items[$item['itemid']]['key_']) {
				continue;
			}

			$item_indexes[$item['key_']][] = $i;
		}

		foreach ($item_indexes as $key => $indexes) {
			if (count($indexes) == 1) {
				continue;
			}

			$hostids = [];

			foreach ($indexes as $i) {
				$templateid = $items[$i]['hostid'];
				$same_hosts = array_intersect_key($tpl_links[$templateid], $hostids);

				if ($same_hosts) {
					$same_host = reset($same_hosts);

					$templateid_first = $hostids[$same_host['hostid']];
					$templateid_second = $templateid;

					$hosts = DB::select('hosts', [
						'output' => ['hostid', 'host'],
						'hostids' => [$templateid_first, $templateid_second, $same_host['hostid']],
						'preservekeys' => true
					]);

					$target_is_host = in_array($same_host['status'],
						[HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]
					);

					switch ($items[$i]['flags']) {
						case ZBX_FLAG_DISCOVERY_NORMAL:
								$error = $target_is_host
								? _('Cannot inherit items with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on host "%4$s".')
								: _('Cannot inherit items with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on template "%4$s".');
							break;

						case ZBX_FLAG_DISCOVERY_PROTOTYPE:
							$error = $target_is_host
								? _('Cannot inherit item prototypes with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on host "%4$s".')
								: _('Cannot inherit item prototypes with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on template "%4$s".');
							break;

						case ZBX_FLAG_DISCOVERY_RULE:
							$error = $target_is_host
								? _('Cannot inherit LLD rules with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on host "%4$s".')
								: _('Cannot inherit LLD rules with key "%1$s" of both "%2$s" and "%3$s" templates, because the key must be unique on template "%4$s".');
							break;
					}

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $key,
						$hosts[$templateid_first]['host'], $hosts[$templateid_second]['host'],
						$hosts[$same_host['hostid']]['host']
					));
				}

				$hostids += array_fill_keys(array_keys($tpl_links[$templateid]), $templateid);
			}
		}
	}

	/**
	 * Get item chunks to inherit.
	 *
	 * @param array $items
	 * @param array $tpl_links
	 *
	 * @return array
	 */
	protected static function getInheritChunks(array $items, array $tpl_links): array {
		$chunks = [
			[
				'item_indexes' => [],
				'hosts' => [],
				'size' => 0
			]
		];
		$last = 0;

		foreach ($items as $i => $item) {
			$hosts_chunks = array_chunk($tpl_links[$item['hostid']], self::CHUNK_SIZE, true);

			foreach ($hosts_chunks as $hosts) {
				if ($chunks[$last]['size'] < self::CHUNK_SIZE) {
					$_hosts = array_slice($hosts, 0, self::CHUNK_SIZE - $chunks[$last]['size'], true);

					$can_add_hosts = !array_intersect_key($chunks[$last]['hosts'],
						array_diff_key($tpl_links[$item['hostid']], $_hosts)
					);

					if ($can_add_hosts) {
						foreach ($chunks[$last]['item_indexes'] as $_i) {
							$new_hosts = array_diff_key($_hosts, $chunks[$last]['hosts']);

							if (array_intersect_key($tpl_links[$items[$_i]['hostid']], $new_hosts)) {
								$can_add_hosts = false;
								break;
							}
						}
					}

					if ($can_add_hosts) {
						$chunks[$last]['item_indexes'][] = $i;
						$chunks[$last]['hosts'] += $_hosts;
						$chunks[$last]['size'] += count($_hosts);

						$hosts = array_diff_key($hosts, $_hosts);
					}
				}

				if ($hosts) {
					$chunks[++$last] = [
						'item_indexes' => [$i],
						'hosts' => $hosts,
						'size' => count($hosts)
					];
				}
			}
		}

		return $chunks;
	}

	/**
	 * @param array $item
	 * @param array $upd_db_item
	 *
	 * @throws APIException
	 */
	protected static function showObjectMismatchError(array $item, array $upd_db_item): void {
		$target_is_host = in_array($upd_db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

		$hosts = DB::select('hosts', [
			'output' => ['host'],
			'hostids' => [$item['hostid'], $upd_db_item['hostid']],
			'preservekeys' => true
		]);

		$error = '';

		switch ($item['flags']) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				switch ($upd_db_item['flags']) {
					case ZBX_FLAG_DISCOVERY_RULE:
						$error = $target_is_host
							? _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because an LLD rule with the same key already exists.')
							: _('Cannot inherit item with key "%1$s" of template "%2$s" to template "%3$s", because an LLD rule with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_PROTOTYPE:
						$error = $target_is_host
							? _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because an item prototype with the same key already exists.')
							: _('Cannot inherit item with key "%1$s" of template "%2$s" to template "%3$s", because an item prototype with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_CREATED:
						$error = $target_is_host
							? _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because a discovered item with the same key already exists.')
							: _('Cannot inherit item with key "%1$s" of template "%2$s" to template "%3$s", because a discovered item with the same key already exists.');
						break 2;
				}
				break;

			case ZBX_FLAG_DISCOVERY_RULE:
				switch ($upd_db_item['flags']) {
					case ZBX_FLAG_DISCOVERY_NORMAL:
						$error = $target_is_host
							? _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to host "%3$s", because an item with the same key already exists.')
							: _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to template "%3$s", because an item with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_PROTOTYPE:
						$error = $target_is_host
							? _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to host "%3$s", because an item prototype with the same key already exists.')
							: _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to template "%3$s", because an item prototype with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_CREATED:
						$error = $target_is_host
							? _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to host "%3$s", because a discovered item with the same key already exists.')
							: _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to template "%3$s", because a discovered item with the same key already exists.');
						break 2;
				}
				break;

			case ZBX_FLAG_DISCOVERY_PROTOTYPE:
				switch ($upd_db_item['flags']) {
					case ZBX_FLAG_DISCOVERY_NORMAL:
						$error = $target_is_host
							? _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to host "%3$s", because an item with the same key already exists.')
							: _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to template "%3$s", because an item with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_RULE:
						$error = $target_is_host
							? _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to host "%3$s", because an LLD rule with the same key already exists.')
							: _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to template "%3$s", because an LLD rule with the same key already exists.');
						break 2;

					case ZBX_FLAG_DISCOVERY_CREATED:
						$error = $target_is_host
							? _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to host "%3$s", because a discovered item with the same key already exists.')
							: _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to template "%3$s", because a discovered item with the same key already exists.');
						break 2;
				}
				break;
		}

		if ($error) {
			self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $upd_db_item['key_'],
				$hosts[$item['hostid']]['host'], $hosts[$upd_db_item['hostid']]['host']
			));
		}

		if ($upd_db_item['templateid'] == 0) {
			return;
		}

		$template = DBfetch(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', [$upd_db_item['templateid']])
		));

		switch ($item['flags']) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				$error = $target_is_host
					? _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because an item with the same key is already inherited from template "%4$s".')
					: _('Cannot inherit item with key "%1$s" of template "%2$s" to template "%3$s", because an item with the same key is already inherited from template "%4$s".');
				break;

			case ZBX_FLAG_DISCOVERY_RULE:
				$error = $target_is_host
					? _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to host "%3$s", because an LLD rule with the same key is already inherited from template "%4$s".')
					: _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to template "%3$s", because an LLD rule with the same key is already inherited from template "%4$s".');
				break;

			case ZBX_FLAG_DISCOVERY_PROTOTYPE:
				$error = $target_is_host
					? _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to host "%3$s", because an item prototype with the same key is already inherited from template "%4$s".')
					: _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to template "%3$s", because an item prototype with the same key is already inherited from template "%4$s".');
				break;
		}

		self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $upd_db_item['key_'], $hosts[$item['hostid']]['host'],
			$hosts[$upd_db_item['hostid']]['host'], $template['host']
		));
	}

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	protected static function unsetNestedObjectIds(array $item): array {
		if (array_key_exists('tags', $item)) {
			foreach ($item['tags'] as &$tag) {
				unset($tag['itemtagid']);
			}
			unset($tag);
		}

		if (array_key_exists('preprocessing', $item)) {
			foreach ($item['preprocessing'] as &$preprocessing) {
				unset($preprocessing['item_preprocid']);
			}
			unset($preprocessing);
		}

		if (array_key_exists('parameters', $item)) {
			foreach ($item['parameters'] as &$parameter) {
				unset($parameter['item_parameterid']);
			}
			unset($parameter);
		}

		return $item;
	}

	/**
	 * Update relation to master item for inherited dependent items.
	 *
	 * @param array $upd_items
	 * @param array $ins_items
	 * @param array $hostids
	 */
	protected static function setChildMasterItemIds(array &$upd_items, array &$ins_items, array $hostids): void {
		$upd_item_indexes = [];
		$ins_item_indexes = [];

		foreach ($upd_items as $i => $upd_item) {
			if ($upd_item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists('master_itemid', $upd_item)) {
				$upd_item_indexes[$upd_item['master_itemid']][$upd_item['hostid']][] = $i;
			}
		}

		foreach ($ins_items as $i => $ins_item) {
			if ($ins_item['type'] == ITEM_TYPE_DEPENDENT) {
				$ins_item_indexes[$ins_item['master_itemid']][$ins_item['hostid']][] = $i;
			}
		}

		if (!$upd_item_indexes && !$ins_item_indexes) {
			return;
		}

		$options = [
			'output' => ['itemid', 'hostid', 'templateid'],
			'filter' => [
				'templateid' => array_keys($ins_item_indexes + $upd_item_indexes),
				'hostid' => $hostids
			]
		];
		$result = DBselect(DB::makeSql('items', $options));

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['templateid'], $upd_item_indexes)
					&& array_key_exists($row['hostid'], $upd_item_indexes[$row['templateid']])) {
				foreach ($upd_item_indexes[$row['templateid']][$row['hostid']] as $i) {
					$upd_items[$i]['master_itemid'] = $row['itemid'];
				}
			}

			if (array_key_exists($row['templateid'], $ins_item_indexes)
					&& array_key_exists($row['hostid'], $ins_item_indexes[$row['templateid']])) {
				foreach ($ins_item_indexes[$row['templateid']][$row['hostid']] as $i) {
					$ins_items[$i]['master_itemid'] = $row['itemid'];
				}
			}
		}
	}

	/**
	 * @param array $upd_items
	 * @param array $upd_db_items
	 * @param array $ins_items
	 *
	 * @throws APIException
	 */
	protected static function addInterfaceIds(array &$upd_items, array $upd_db_items, array &$ins_items): void {
		$upd_item_indexes = [];
		$ins_item_indexes = [];
		$interface_types = [];

		$upd_item_indexes_by_interfaceid = [];

		foreach ($upd_items as $i => $upd_item) {
			if (!in_array($upd_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				continue;
			}

			$interface_type = itemTypeInterface($upd_item['type']);

			if ($interface_type === false) {
				continue;
			}

			if ($upd_db_items[$upd_item['itemid']]['interfaceid'] != 0) {
				$db_interface_type = itemTypeInterface($upd_db_items[$upd_item['itemid']]['type']);

				if ($interface_type != $db_interface_type) {
					if ($db_interface_type == INTERFACE_TYPE_OPT) {
						$upd_item_indexes_by_interfaceid[$upd_db_items[$upd_item['itemid']]['interfaceid']][] = $i;
					}
					elseif ($interface_type != INTERFACE_TYPE_OPT) {
						$upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

						if ($interface_types !== null) {
							$interface_types[$interface_type] = true;
						}
					}
				}
			}
			else {
				$upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

				if ($interface_types !== null) {
					if ($interface_type == INTERFACE_TYPE_OPT) {
						$interface_types = null;
					}
					else {
						$interface_types[$interface_type] = true;
					}
				}
			}
		}

		if ($upd_item_indexes_by_interfaceid) {
			$options = [
				'output' => ['interfaceid', 'type'],
				'interfaceids' => array_keys($upd_item_indexes_by_interfaceid)
			];
			$result = DBselect(DB::makeSql('interface', $options));

			while ($row = DBfetch($result)) {
				foreach ($upd_item_indexes_by_interfaceid[$row['interfaceid']] as $i) {
					$upd_item = $upd_items[$i];
					$interface_type = itemTypeInterface($upd_item['type']);

					if ($interface_type != $row['type']) {
						$upd_item_indexes[$upd_item['hostid']][$interface_type][] = $i;

						if ($interface_types !== null) {
							$interface_types[$interface_type] = true;
						}
					}
				}
			}
		}

		foreach ($ins_items as $i => $ins_item) {
			if (!in_array($ins_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				continue;
			}

			$interface_type = itemTypeInterface($ins_item['type']);

			if ($interface_type === false) {
				continue;
			}

			$ins_item_indexes[$ins_item['hostid']][$interface_type][] = $i;

			if ($interface_types !== null) {
				if ($interface_type == INTERFACE_TYPE_OPT) {
					$interface_types = null;
				}
				else {
					$interface_types[$interface_type] = true;
				}
			}
		}

		if (!$upd_item_indexes && !$ins_item_indexes) {
			return;
		}

		$options = [
			'output' => ['interfaceid', 'hostid', 'type'],
			'filter' => [
				'hostid' => array_keys($upd_item_indexes + $ins_item_indexes),
				'main' => INTERFACE_PRIMARY
			]
		];

		if ($interface_types !== null) {
			$options['filter']['type'] = array_keys($interface_types);
		}

		$result = DBselect(DB::makeSql('interface', $options));

		$priority_interfaces = [];

		while ($row = DBfetch($result)) {
			$has_opt_type_items = false;

			if (array_key_exists($row['hostid'], $upd_item_indexes)) {
				if (array_key_exists(INTERFACE_TYPE_OPT, $upd_item_indexes[$row['hostid']])) {
					$has_opt_type_items = true;
				}

				if (array_key_exists($row['type'], $upd_item_indexes[$row['hostid']])) {
					foreach ($upd_item_indexes[$row['hostid']][$row['type']] as $_i => $i) {
						$upd_items[$i]['interfaceid'] = $row['interfaceid'];

						unset($upd_item_indexes[$row['hostid']][$row['type']][$_i]);
					}

					if (!$upd_item_indexes[$row['hostid']][$row['type']]) {
						unset($upd_item_indexes[$row['hostid']][$row['type']]);
					}

					if (!$upd_item_indexes[$row['hostid']]) {
						unset($upd_item_indexes[$row['hostid']]);
					}
				}
			}

			if (array_key_exists($row['hostid'], $ins_item_indexes)) {
				if (array_key_exists(INTERFACE_TYPE_OPT, $ins_item_indexes[$row['hostid']])) {
					$has_opt_type_items = true;
				}

				if (array_key_exists($row['type'], $ins_item_indexes[$row['hostid']])) {
					foreach ($ins_item_indexes[$row['hostid']][$row['type']] as $_i => $i) {
						$ins_items[$i]['interfaceid'] = $row['interfaceid'];

						unset($ins_item_indexes[$row['hostid']][$row['type']][$_i]);
					}

					if (!$ins_item_indexes[$row['hostid']][$row['type']]) {
						unset($ins_item_indexes[$row['hostid']][$row['type']]);
					}

					if (!$ins_item_indexes[$row['hostid']]) {
						unset($ins_item_indexes[$row['hostid']]);
					}
				}
			}

			if ($has_opt_type_items) {
				$priority_index = array_search($row['type'], self::INTERFACE_TYPES_BY_PRIORITY);

				if (!array_key_exists($row['hostid'], $priority_interfaces)
						|| $priority_index < $priority_interfaces[$row['hostid']]['priority_index']) {
					$priority_interfaces[$row['hostid']] = [
						'interfaceid' => $row['interfaceid'],
						'type' => $row['type'],
						'priority_index' => $priority_index
					];
				}
			}
		}

		foreach ($upd_item_indexes as $hostid => $item_indexes) {
			if (!array_key_exists(INTERFACE_TYPE_OPT, $item_indexes)) {
				continue;
			}

			foreach ($item_indexes[INTERFACE_TYPE_OPT] as $i) {
				if (array_key_exists($hostid, $priority_interfaces)) {
					$upd_items[$i]['interfaceid'] = $priority_interfaces[$hostid]['interfaceid'];
				}
			}

			unset($upd_item_indexes[$hostid][INTERFACE_TYPE_OPT]);

			if (!$upd_item_indexes[$hostid]) {
				unset($upd_item_indexes[$hostid]);
			}
		}

		foreach ($ins_item_indexes as $hostid => $item_indexes) {
			if (!array_key_exists(INTERFACE_TYPE_OPT, $item_indexes)) {
				continue;
			}

			foreach ($item_indexes[INTERFACE_TYPE_OPT] as $i) {
				if (array_key_exists($hostid, $priority_interfaces)) {
					$ins_items[$i]['interfaceid'] = $priority_interfaces[$hostid]['interfaceid'];
				}
			}

			unset($ins_item_indexes[$hostid][INTERFACE_TYPE_OPT]);

			if (!$ins_item_indexes[$hostid]) {
				unset($ins_item_indexes[$hostid]);
			}
		}

		$item = null;

		if ($upd_item_indexes) {
			$hostid = key($upd_item_indexes);
			$interface_type = key($upd_item_indexes[$hostid]);
			$i = reset($upd_item_indexes[$hostid][$interface_type]);

			$item = $upd_items[$i];
		}
		elseif ($ins_item_indexes) {
			$hostid = key($ins_item_indexes);
			$interface_type = key($ins_item_indexes[$hostid]);
			$i = reset($ins_item_indexes[$hostid][$interface_type]);

			$item = $ins_items[$i];
		}

		if ($item === null) {
			return;
		}

		$templates = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', [$item['templateid']])
		));

		$hosts = DB::select('hosts', [
			'output' => ['host'],
			'hostids' => $item['hostid']
		]);

		switch ($item['flags']) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				$error = _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because a host interface of type "%4$s" is required.');
				break;

			case ZBX_FLAG_DISCOVERY_RULE:
				$error = _('Cannot inherit LLD rule with key "%1$s" of template "%2$s" to host "%3$s", because a host interface of type "%4$s" is required.');
				break;

			case ZBX_FLAG_DISCOVERY_PROTOTYPE:
				$error = _('Cannot inherit item prototype with key "%1$s" of template "%2$s" to host "%3$s", because a host interface of type "%4$s" is required.');
				break;
		}

		self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $item['key_'], $templates[0]['host'],
			$hosts[0]['host'], interfaceType2str($interface_type)
		));
	}

	/**
	 * Inherit dependent items in nesting order.
	 *
	 * @param array $dep_items_to_link[<master item index>][<dependent item index>]
	 * @param array $items_to_link
	 * @param array $hostids
	 */
	protected static function inheritDependentItems(array $dep_items_to_link, array $items_to_link,
			array $hostids): void {
		while ($dep_items_to_link) {
			$items = [];

			foreach ($dep_items_to_link as $i => $_items) {
				if (array_key_exists($i, $items_to_link)) {
					$items += $_items;
					unset($dep_items_to_link[$i]);
				}
			}

			static::inherit(array_values($items), [], $hostids, true);

			$items_to_link = $items;
		}
	}

	/**
	 * @param array      $items
	 * @param array      $db_items
	 * @param array|null $hostids
	 * @param bool       $is_dep_items  Inherit called for dependent items.
	 */
	abstract protected static function inherit(array $items, array $db_items = [], array $hostids = null,
			bool $is_dep_items = false): void;

	/**
	 * Add default values for fields that became unnecessary as the result of the change of the type fields.
	 *
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addFieldDefaultsByType(array &$items, array $db_items): void {
		$type_field_defaults = [
			// The fields used for multiple item types.
			'interfaceid' => 0,
			'authtype' => DB::getDefault('items', 'authtype'),
			'username' => DB::getDefault('items', 'username'),
			'password' => DB::getDefault('items', 'password'),
			'params' => DB::getDefault('items', 'params'),
			'timeout' => DB::getDefault('items', 'timeout'),
			'delay' => DB::getDefault('items', 'delay'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),

			// Dependent item type specific fields.
			'master_itemid' => 0,

			// HTTP Agent item type specific fields.
			'url' => DB::getDefault('items', 'url'),
			'query_fields' => [],
			'request_method' => DB::getDefault('items', 'request_method'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'headers' => [],
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'output_format' => DB::getDefault('items', 'output_format'),
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'verify_peer' => DB::getDefault('items', 'verify_peer'),
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
			'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
			'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
			'allow_traps' => DB::getDefault('items', 'allow_traps'),

			// IPMI item type specific fields.
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),

			// JMX item type specific fields.
			'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),

			// Script item type specific fields.
			'parameters' => [],

			// SNMP item type specific fields.
			'snmp_oid' => DB::getDefault('items', 'snmp_oid'),

			// SSH item type specific fields.
			'publickey' => DB::getDefault('items', 'publickey'),
			'privatekey' => DB::getDefault('items', 'privatekey')
		];

		$value_type_field_defaults = [
			'units' => DB::getDefault('items', 'units'),
			'trends' => DB::getDefault('items', 'trends'),
			'valuemapid' => 0,
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'inventory_link' => DB::getDefault('items', 'inventory_link')
		];

		foreach ($items as &$item) {
			if (!array_key_exists('type', $db_items[$item['itemid']])) {
				continue;
			}

			$db_item = $db_items[$item['itemid']];

			if ($item['type'] != $db_item['type']) {
				$type_field_names = CItemTypeFactory::getObject($item['type'])::FIELD_NAMES;
				$db_type_field_names = CItemTypeFactory::getObject($db_item['type'])::FIELD_NAMES;

				$field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

				if ($item['host_status'] == HOST_STATUS_TEMPLATE && array_key_exists('interfaceid', $field_names)) {
					unset($field_names['interfaceid']);
				}

				$item += array_intersect_key($type_field_defaults, $field_names);
			}

			switch ($item['type']) {
				case ITEM_TYPE_SIMPLE:
					if (($item['type'] != $db_item['type'] || $item['key_'] !== $db_item['key_'])
							&& (strncmp($item['key_'], 'icmpping', 8) == 0
								|| strncmp($item['key_'], 'vmware.', 7) == 0)) {
						$item += array_intersect_key($type_field_defaults, array_flip(['timeout']));
					}
					break;

				case ITEM_TYPE_ZABBIX_ACTIVE:
					if (($item['type'] != $db_item['type'] || $item['key_'] !== $db_item['key_'])
							&& strncmp($item['key_'], 'mqtt.get', 8) == 0) {
						$item += array_intersect_key($type_field_defaults, array_flip(['delay']));
					}
					break;

				case ITEM_TYPE_SSH:
					if ($item['type'] != $db_item['type']) {
						if ($db_item['type'] == ITEM_TYPE_HTTPAGENT) {
							$item += array_intersect_key($type_field_defaults, array_flip(['authtype']));
						}
					}
					elseif (array_key_exists('authtype', $item) && $item['authtype'] !== $db_item['authtype']
							&& $item['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
						$item += array_intersect_key($type_field_defaults, array_flip(['publickey', 'privatekey']));
					}
					break;

				case ITEM_TYPE_HTTPAGENT:
					if ($item['type'] != $db_item['type']) {
						if (!array_key_exists('authtype', $item)) {
							$item += array_intersect_key($type_field_defaults, array_flip(['authtype']));
						}

						if ($item['authtype'] == ZBX_HTTP_AUTH_NONE) {
							$item += array_intersect_key($type_field_defaults, array_flip(['username', 'password']));
						}

						if (!array_key_exists('allow_traps', $item)
								|| $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF) {
							$item += array_intersect_key($type_field_defaults, array_flip(['trapper_hosts']));
						}
					}
					else {
						if (array_key_exists('request_method', $item)
								&& $item['request_method'] != $db_item['request_method']
								&& $item['request_method'] == HTTPCHECK_REQUEST_HEAD) {
							$item += ['retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_HEADERS];
						}

						if (array_key_exists('authtype', $item) && $item['authtype'] != $db_item['authtype']
								&& $item['authtype'] == ZBX_HTTP_AUTH_NONE) {
							$item += array_intersect_key($type_field_defaults, array_flip(['username', 'password']));
						}

						if (array_key_exists('allow_traps', $item) && $item['allow_traps'] != $db_item['allow_traps']
								&& $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF) {
							$item += array_intersect_key($type_field_defaults, array_flip(['trapper_hosts']));
						}
					}
					break;

				case ITEM_TYPE_SNMP:
					if (array_key_exists('snmp_oid', $item)
							&& ($item['type'] != $db_item['type'] || $item['snmp_oid'] !== $db_item['snmp_oid'])
							&& strncmp($item['snmp_oid'], 'get[', 4) != 0
							&& strncmp($item['snmp_oid'], 'walk[', 5) != 0) {
						$item += array_intersect_key($type_field_defaults, array_flip(['timeout']));
					}
					break;
			}

			if (array_key_exists('value_type', $item) && $item['value_type'] != $db_item['value_type']) {
				$type_field_names = static::VALUE_TYPE_FIELD_NAMES[$item['value_type']];
				$db_type_field_names = static::VALUE_TYPE_FIELD_NAMES[$db_item['value_type']];

				$field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

				if (array_key_exists('trends', $field_names)) {
					$item += ['trends' => 0];
				}

				$item += array_intersect_key($value_type_field_defaults, $field_names);
			}
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	protected static function updateParameters(array &$items, array &$db_items = null,
			array &$upd_itemids = null): void {
		$ins_item_parameters = [];
		$upd_item_parameters = [];
		$del_item_parameterids = [];

		foreach ($items as $i => &$item) {
			$update = false;

			if ($db_items === null) {
				if (in_array($item['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])
						&& array_key_exists('parameters', $item) && $item['parameters']) {
					$update = true;
				}
			}
			else {
				if (!array_key_exists('type', $db_items[$item['itemid']])) {
					continue;
				}

				if (in_array($item['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
					$update = array_key_exists('parameters', $item);
				}
				elseif (in_array($db_items[$item['itemid']]['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])
						&& $db_items[$item['itemid']]['parameters']) {
					$update = true;
				}
			}

			if (!$update) {
				continue;
			}

			$changed = false;
			$db_item_parameters = ($db_items !== null)
				? array_column($db_items[$item['itemid']]['parameters'], null, 'name')
				: [];

			foreach ($item['parameters'] as &$item_parameter) {
				if (array_key_exists($item_parameter['name'], $db_item_parameters)) {
					$db_item_parameter = $db_item_parameters[$item_parameter['name']];
					$item_parameter['item_parameterid'] = $db_item_parameter['item_parameterid'];
					unset($db_item_parameters[$db_item_parameter['name']]);

					$upd_item_parameter = DB::getUpdatedValues('item_parameter', $item_parameter, $db_item_parameter);

					if ($upd_item_parameter) {
						$upd_item_parameters[] = [
							'values' => $upd_item_parameter,
							'where' => ['item_parameterid' => $db_item_parameter['item_parameterid']]
						];
						$changed = true;
					}
				}
				else {
					$ins_item_parameters[] = ['itemid' => $item['itemid']] + $item_parameter;
					$changed = true;
				}
			}
			unset($item_parameter);

			if ($db_item_parameters) {
				$del_item_parameterids =
					array_merge($del_item_parameterids, array_column($db_item_parameters, 'item_parameterid'));
				$changed = true;
			}

			if ($db_items !== null) {
				if ($changed) {
					$upd_itemids[$i] = $item['itemid'];
				}
				else {
					unset($item['parameters'], $db_items[$item['itemid']]['parameters']);
				}
			}
		}
		unset($item);

		if ($del_item_parameterids) {
			DB::delete('item_parameter', ['item_parameterid' => $del_item_parameterids]);
		}

		if ($upd_item_parameters) {
			DB::update('item_parameter', $upd_item_parameters);
		}

		if ($ins_item_parameters) {
			$item_parameterids = DB::insert('item_parameter', $ins_item_parameters);
		}

		foreach ($items as &$item) {
			if (!array_key_exists('parameters', $item)) {
				continue;
			}

			foreach ($item['parameters'] as &$item_parameter) {
				if (!array_key_exists('item_parameterid', $item_parameter)) {
					$item_parameter['item_parameterid'] = array_shift($item_parameterids);
				}
			}
			unset($item_parameter);
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	protected static function updatePreprocessing(array &$items, array &$db_items = null,
			array &$upd_itemids = null): void {
		$ins_item_preprocs = [];
		$upd_item_preprocs = [];
		$del_item_preprocids = [];

		foreach ($items as $i => &$item) {
			if (!array_key_exists('preprocessing', $item)) {
				continue;
			}

			$changed = false;
			$db_item_preprocs = ($db_items !== null)
				? array_column($db_items[$item['itemid']]['preprocessing'], null, 'step')
				: [];

			$step = 1;
			$item['preprocessing'] = self::sortPreprocessingSteps($item['preprocessing']);

			foreach ($item['preprocessing'] as &$item_preproc) {
				$item_preproc['step'] = $step++;

				if (array_key_exists($item_preproc['step'], $db_item_preprocs)) {
					$db_item_preproc = $db_item_preprocs[$item_preproc['step']];
					$item_preproc['item_preprocid'] = $db_item_preproc['item_preprocid'];
					unset($db_item_preprocs[$db_item_preproc['step']]);

					$upd_item_preproc = DB::getUpdatedValues('item_preproc', $item_preproc, $db_item_preproc);

					if ($upd_item_preproc) {
						$upd_item_preprocs[] = [
							'values' => $upd_item_preproc,
							'where' => ['item_preprocid' => $db_item_preproc['item_preprocid']]
						];
						$changed = true;
					}
				}
				else {
					$ins_item_preprocs[] = ['itemid' => $item['itemid']] + $item_preproc;
					$changed = true;
				}
			}
			unset($item_preproc);

			if ($db_item_preprocs) {
				$del_item_preprocids =
					array_merge($del_item_preprocids, array_column($db_item_preprocs, 'item_preprocid'));
				$changed = true;
			}

			if ($db_items !== null) {
				if ($changed) {
					$upd_itemids[$i] = $item['itemid'];
				}
				else {
					unset($item['preprocessing'], $db_items[$item['itemid']]['preprocessing']);
				}
			}
		}
		unset($item);

		if ($del_item_preprocids) {
			DB::delete('item_preproc', ['item_preprocid' => $del_item_preprocids]);
		}

		if ($upd_item_preprocs) {
			DB::update('item_preproc', $upd_item_preprocs);
		}

		if ($ins_item_preprocs) {
			$item_preprocids = DB::insert('item_preproc', $ins_item_preprocs);
		}

		foreach ($items as &$item) {
			if (!array_key_exists('preprocessing', $item)) {
				continue;
			}

			foreach ($item['preprocessing'] as &$item_preproc) {
				if (!array_key_exists('item_preprocid', $item_preproc)) {
					$item_preproc['item_preprocid'] = array_shift($item_preprocids);
				}
			}
			unset($item_preproc);
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	protected static function updateTags(array &$items, array &$db_items = null, array &$upd_itemids = null): void {
		$ins_tags = [];
		$del_itemtagids = [];

		foreach ($items as $i => &$item) {
			if (!array_key_exists('tags', $item)) {
				continue;
			}

			$changed = false;
			$db_tags = ($db_items !== null) ? $db_items[$item['itemid']]['tags'] : [];

			foreach ($item['tags'] as &$tag) {
				$db_itemtagid = key(array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
					return $tag['tag'] === $db_tag['tag']
						&& (!array_key_exists('value', $tag) || $tag['value'] === $db_tag['value']);
				}));

				if ($db_itemtagid !== null) {
					$tag['itemtagid'] = $db_itemtagid;
					unset($db_tags[$db_itemtagid]);
				}
				else {
					$ins_tags[] = ['itemid' => $item['itemid']] + $tag;
					$changed = true;
				}
			}
			unset($tag);

			if ($db_tags) {
				$del_itemtagids = array_merge($del_itemtagids, array_keys($db_tags));
				$changed = true;
			}

			if ($db_items !== null) {
				if ($changed) {
					$upd_itemids[$i] = $item['itemid'];
				}
				else {
					unset($item['tags'], $db_items[$item['itemid']]['tags']);
				}
			}
		}
		unset($item);

		if ($del_itemtagids) {
			DB::delete('item_tag', ['itemtagid' => $del_itemtagids]);
		}

		if ($ins_tags) {
			$itemtagids = DB::insert('item_tag', $ins_tags);
		}

		foreach ($items as &$item) {
			if (!array_key_exists('tags', $item)) {
				continue;
			}

			foreach ($item['tags'] as &$tag) {
				if (!array_key_exists('itemtagid', $tag)) {
					$tag['itemtagid'] = array_shift($itemtagids);
				}
			}
			unset($tag);
		}
		unset($item);
	}

	/**
	 * Check for unique item keys.
	 *
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException if item keys are not unique.
	 */
	protected static function checkDuplicates(array $items, array $db_items = null): void {
		$host_keys = [];

		foreach ($items as $item) {
			if ($db_items === null || $item['key_'] !== $db_items[$item['itemid']]['key_']) {
				$host_keys[$item['hostid']][] = $item['key_'];
			}
		}

		if (!$host_keys) {
			return;
		}

		$where = [];
		foreach ($host_keys as $hostid => $keys) {
			$where[] = '('.dbConditionId('i.hostid', [$hostid]).' AND '.dbConditionString('i.key_', $keys).')';
		}

		$duplicates = DBfetchArray(DBselect(
			'SELECT i.key_,i.flags,h.host,h.status'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND ('.implode(' OR ', $where).')',
			1
		));

		if ($duplicates) {
			$target_is_template = ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE);

			switch ($duplicates[0]['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
				case ZBX_FLAG_DISCOVERY_CREATED:
					$error = $target_is_template
						? _('An item with key "%1$s" already exists on the template "%2$s".')
						: _('An item with key "%1$s" already exists on the host "%2$s".');
					break;

				case ZBX_FLAG_DISCOVERY_PROTOTYPE:
					$error = $target_is_template
						? _('An item prototype with key "%1$s" already exists on the template "%2$s".')
						: _('An item prototype with key "%1$s" already exists on the host "%2$s".');
					break;

				case ZBX_FLAG_DISCOVERY_RULE:
					$error = $target_is_template
						? _('An LLD rule with key "%1$s" already exists on the template "%2$s".')
						: _('An LLD rule with key "%1$s" already exists on the host "%2$s".');
					break;
			}

			self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $duplicates[0]['key_'], $duplicates[0]['host']));
		}
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkHostInterfaces(array $items, array $db_items = null): void {
		foreach ($items as $i => &$item) {
			$interface_type = itemTypeInterface($item['type']);

			if (!in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
					|| $interface_type === false) {
				unset($items[$i]);
				continue;
			}

			$check = false;

			if ($db_items === null) {
				if (array_key_exists('interfaceid', $item)) {
					if ($item['interfaceid'] != 0) {
						$check = true;
					}
					elseif ($interface_type != INTERFACE_TYPE_OPT) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($i + 1).'/interfaceid', _('the host interface ID is expected')
						));
					}
				}
			}
			else {
				$db_item = $db_items[$item['itemid']];

				if ($item['type'] == $db_item['type']) {
					if (array_key_exists('interfaceid', $item)) {
						if ($item['interfaceid'] != 0) {
							if (bccomp($item['interfaceid'], $db_item['interfaceid']) != 0) {
								$check = true;
							}
						}
						elseif ($interface_type != INTERFACE_TYPE_OPT) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1).'/interfaceid', _('the host interface ID is expected')
							));
						}
					}
				}
				else {
					$db_interface_type = itemTypeInterface($db_item['type']);

					if (array_key_exists('interfaceid', $item)) {
						if ($item['interfaceid'] != 0) {
							if (bccomp($item['interfaceid'], $db_item['interfaceid']) != 0
									|| ($interface_type != INTERFACE_TYPE_OPT
										&& $interface_type != $db_interface_type)) {
								$check = true;
							}
						}
						elseif ($interface_type != INTERFACE_TYPE_OPT) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1).'/interfaceid', _('the host interface ID is expected')
							));
						}
					}
					else {
						if ($db_item['interfaceid'] != 0) {
							if ($interface_type != INTERFACE_TYPE_OPT && $interface_type != $db_interface_type) {
								$item += ['interfaceid' => $db_item['interfaceid']];

								$check = true;
							}
						}
						elseif ($interface_type != INTERFACE_TYPE_OPT) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1), _s('the parameter "%1$s" is missing', 'interfaceid')
							));
						}
					}
				}
			}

			if (!$check) {
				unset($items[$i]);
			}
		}
		unset($item);

		if (!$items) {
			return;
		}

		$db_interfaces = DB::select('interface', [
			'output' => ['interfaceid', 'hostid', 'type'],
			'interfaceids' => array_unique(array_column($items, 'interfaceid')),
			'preservekeys' => true
		]);

		foreach ($items as $i => $item) {
			if (!array_key_exists($item['interfaceid'], $db_interfaces)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/interfaceid', _('the host interface ID is expected')
				));
			}

			if (bccomp($db_interfaces[$item['interfaceid']]['hostid'], $item['hostid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/interfaceid', _('cannot be the host interface ID from another host')
				));
			}

			$interface_type = itemTypeInterface($item['type']);

			if ($interface_type != INTERFACE_TYPE_OPT
					&& $db_interfaces[$item['interfaceid']]['type'] != $interface_type) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/interfaceid',
					_s('the host interface ID of type "%1$s" is expected', interfaceType2str($interface_type))
				));
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'hostid');
			$hosts = API::Host()->get([
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'output' => $options['selectHosts'],
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding preprocessing
		if ($options['selectPreprocessing'] !== null && $options['selectPreprocessing'] != API_OUTPUT_COUNT) {
			$db_item_preproc = API::getApiService()->select('item_preproc', [
				'output' => $this->outputExtend($options['selectPreprocessing'], ['itemid', 'step']),
				'filter' => ['itemid' => array_keys($result)]
			]);

			CArrayHelper::sort($db_item_preproc, ['step']);

			foreach ($result as &$item) {
				$item['preprocessing'] = [];
			}
			unset($item);

			foreach ($db_item_preproc as $step) {
				$itemid = $step['itemid'];
				unset($step['item_preprocid'], $step['itemid'], $step['step']);

				if (array_key_exists($itemid, $result)) {
					$result[$itemid]['preprocessing'][] = $step;
				}
			}
		}

		// Add value mapping.
		if (($this instanceof CItemPrototype || $this instanceof CItem) && $options['selectValueMap'] !== null) {
			if ($options['selectValueMap'] === API_OUTPUT_EXTEND) {
				$options['selectValueMap'] = ['valuemapid', 'name', 'mappings'];
			}

			foreach ($result as &$item) {
				$item['valuemap'] = [];
			}
			unset($item);

			$valuemaps = DB::select('valuemap', [
				'output' => array_diff($this->outputExtend($options['selectValueMap'], ['valuemapid', 'hostid']),
					['mappings']
				),
				'filter' => ['valuemapid' => array_keys(array_flip(array_column($result, 'valuemapid')))],
				'preservekeys' => true
			]);

			if ($this->outputIsRequested('mappings', $options['selectValueMap']) && $valuemaps) {
				$params = [
					'output' => ['valuemapid', 'type', 'value', 'newvalue'],
					'filter' => ['valuemapid' => array_keys($valuemaps)],
					'sortfield' => ['sortorder']
				];
				$query = DBselect(DB::makeSql('valuemap_mapping', $params));

				while ($mapping = DBfetch($query)) {
					$valuemaps[$mapping['valuemapid']]['mappings'][] = [
						'type' => $mapping['type'],
						'value' => $mapping['value'],
						'newvalue' => $mapping['newvalue']
					];
				}
			}

			foreach ($result as &$item) {
				if (array_key_exists('valuemapid', $item) && array_key_exists($item['valuemapid'], $valuemaps)) {
					$item['valuemap'] = array_intersect_key($valuemaps[$item['valuemapid']],
						array_flip($options['selectValueMap'])
					);
				}
			}
			unset($item);
		}

		if (!$options['countOutput'] && $this->outputIsRequested('parameters', $options['output'])) {
			$item_parameters = DBselect(
				'SELECT ip.itemid,ip.name,ip.value'.
				' FROM item_parameter ip'.
				' WHERE '.dbConditionInt('ip.itemid', array_keys($result))
			);

			foreach ($result as &$item) {
				$item['parameters'] = [];
			}
			unset($item);

			while ($row = DBfetch($item_parameters)) {
				$result[$row['itemid']]['parameters'][] = [
					'name' =>  $row['name'],
					'value' =>  $row['value']
				];
			}
		}

		return $result;
	}

	/**
	 * Check that dependent items of given items are valid.
	 *
	 * @param array $items
	 * @param array $db_items
	 * @param bool  $inherited
	 *
	 * @throws APIException
	 */
	protected static function checkDependentItems(array $items, array $db_items = [], bool $inherited = false): void {
		$del_links = [];

		foreach ($items as $i => $item) {
			$check = false;

			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if (!array_key_exists('itemid', $item)) {
					if ($item['master_itemid'] != 0) {
						$check = true;
					}
					else {
						$error = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
							? _('an item/item prototype ID is expected')
							: _('an item ID is expected');

						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($i + 1).'/master_itemid', $error
						));
					}
				}
				else {
					if (array_key_exists('master_itemid', $item)) {
						if ($item['master_itemid'] != 0) {
							if (bccomp($item['master_itemid'], $db_items[$item['itemid']]['master_itemid']) != 0) {
								$check = true;

								if ($db_items[$item['itemid']]['master_itemid'] != 0) {
									$del_links[$item['itemid']] = $db_items[$item['itemid']]['master_itemid'];
								}
							}
						}
						else {
							$error = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
								? _('an item/item prototype ID is expected')
								: _('an item ID is expected');

							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1).'/master_itemid', $error
							));
						}
					}
				}
			}
			elseif (array_key_exists('itemid', $item) && $db_items[$item['itemid']]['type'] == ITEM_TYPE_DEPENDENT) {
				$del_links[$item['itemid']] = $db_items[$item['itemid']]['master_itemid'];
			}

			if (!$check) {
				unset($items[$i]);
			}
		}

		if (!$items) {
			return;
		}

		if (!$inherited) {
			self::checkMasterItems($items, $db_items);
		}

		$dep_item_links = self::getDependentItemLinks($items, $del_links);

		if (!$inherited && $db_items) {
			self::checkCircularDependencies($items, $dep_item_links);
		}
	}

	/**
	 * Check that master item IDs of given dependent items are valid.
	 *
	 * @param array $items
	 * @param array $db_items
	 *
	 * @throws APIException
	 */
	private static function checkMasterItems(array $items, array $db_items): void {
		$master_itemids = array_unique(array_column($items, 'master_itemid'));
		$flags = $items[key($items)]['flags'];

		if ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$db_master_items = DBfetchArrayAssoc(DBselect(
				'SELECT i.itemid,i.hostid,i.master_itemid,i.flags,id.parent_itemid AS ruleid'.
				' FROM items i'.
				' LEFT JOIN item_discovery id ON i.itemid=id.itemid'.
				' WHERE '.dbConditionId('i.itemid', $master_itemids).
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE])
			), 'itemid');
		}
		else {
			$db_master_items = DB::select('items', [
				'output' => ['itemid', 'hostid', 'master_itemid'],
				'itemids' => $master_itemids,
				'filter' => [
					'flags' => ZBX_FLAG_DISCOVERY_NORMAL
				],
				'preservekeys' => true
			]);
		}

		foreach ($items as $i => $item) {
			if (!array_key_exists($item['master_itemid'], $db_master_items)) {
				$error = $flags == ZBX_FLAG_DISCOVERY_PROTOTYPE
					? _('an item/item prototype ID is expected')
					: _('an item ID is expected');

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/master_itemid', $error
				));
			}

			$db_master_item = $db_master_items[$item['master_itemid']];

			if (bccomp($db_master_item['hostid'], $item['hostid']) != 0) {
				$error = $flags == ZBX_FLAG_DISCOVERY_PROTOTYPE
					? _('cannot be an item/item prototype ID from another host or template')
					: _('cannot be an item ID from another host or template');

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/master_itemid', $error
				));
			}

			if ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE && $db_master_item['ruleid'] != 0) {
				$item_ruleid = array_key_exists('itemid', $item)
					? $db_items[$item['itemid']]['ruleid']
					: $item['ruleid'];

				if (bccomp($db_master_item['ruleid'], $item_ruleid) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/master_itemid', _('cannot be an item prototype ID from another LLD rule')
					));
				}
			}
		}
	}

	/**
	 * Get dependent item links starting from the given dependent items and till the highest dependency level.
	 *
	 * @param  array $items
	 * @param  array $del_links
	 *
	 * @return array  Array of the links where each key contain the ID of dependent item and value contain the
	 *                appropriate ID of the master item.
	 */
	private static function getDependentItemLinks(array $items, array $del_links): array {
		$links = array_column($items, 'master_itemid', 'itemid');
		$master_itemids = array_flip(array_column($items, 'master_itemid'));

		while ($master_itemids) {
			$options = [
				'output' => ['itemid', 'hostid', 'master_itemid'],
				'itemids' => array_keys($master_itemids)
			];
			$db_master_items = DBselect(DB::makeSql('items', $options));

			$master_itemids = [];

			while ($db_master_item = DBfetch($db_master_items)) {
				if (array_key_exists($db_master_item['itemid'], $del_links)
						&& bccomp($db_master_item['master_itemid'], $del_links[$db_master_item['itemid']]) == 0) {
					$links[$db_master_item['itemid']] = 0;
					continue;
				}

				$links[$db_master_item['itemid']] = $db_master_item['master_itemid'];

				if ($db_master_item['master_itemid'] != 0) {
					$master_itemids[$db_master_item['master_itemid']] = true;
				}
			}
		}

		return $links;
	}

	/**
	 * Check that the changed master item IDs of dependent items do not create a circular dependencies.
	 *
	 * @param array $items
	 * @param array $dep_item_links
	 *
	 * @throws APIException
	 */
	private static function checkCircularDependencies(array $items, array $dep_item_links): void {
		foreach ($items as $i => $item) {
			if ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE) {
				continue;
			}

			$master_itemid = $item['master_itemid'];

			while ($master_itemid != 0) {
				if (bccomp($master_itemid, $item['itemid']) == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/master_itemid', _('circular item dependency is not allowed')
					));
				}

				$master_itemid = $dep_item_links[$master_itemid];
			}
		}
	}

	/**
	 * Check that valuemap belong to same host as item.
	 *
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkValueMaps(array $items, array $db_items = null): void {
		$item_indexes = [];

		foreach ($items as $i => $item) {
			if (array_key_exists('valuemapid', $item) && $item['valuemapid'] != 0
					&& ($db_items === null
						|| bccomp($item['valuemapid'], $db_items[$item['itemid']]['valuemapid']) != 0)) {
				$item_indexes[$item['valuemapid']][] = $i;
			}
		}

		if (!$item_indexes) {
			return;
		}

		$options = [
			'output' => ['valuemapid', 'hostid'],
			'valuemapids' => array_keys($item_indexes)
		];
		$db_valuemaps = DBselect(DB::makeSql('valuemap', $options));

		while ($db_valuemap = DBfetch($db_valuemaps)) {
			foreach ($item_indexes[$db_valuemap['valuemapid']] as $i) {
				if (bccomp($db_valuemap['hostid'], $items[$i]['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/valuemapid', _('cannot be a value map ID from another host or template')
					));
				}
			}
		}
	}

	/**
	 * Add the internally used fields to the given $db_items.
	 *
	 * @param array $db_items
	 */
	protected static function addInternalFields(array &$db_items): void {
		$result = DBselect(
			'SELECT i.itemid,i.hostid,i.templateid,i.flags,h.status AS host_status'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', array_keys($db_items))
		);

		while ($row = DBfetch($result)) {
			$db_items[$row['itemid']] += $row;
		}
	}

	/**
	 * Note: instances may override this to add e.g. tags.
	 *
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedObjects(array $items, array &$db_items): void {
		self::addAffectedTags($items, $db_items);
		self::addAffectedPreprocessing($items, $db_items);
		self::addAffectedParameters($items, $db_items);
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedTags(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			if (array_key_exists('tags', $item)) {
				$itemids[] = $item['itemid'];
				$db_items[$item['itemid']]['tags'] = [];
			}
		}

		if (!$itemids) {
			return;
		}

		$options = [
			'output' => ['itemtagid', 'itemid', 'tag', 'value'],
			'filter' => ['itemid' => $itemids]
		];
		$db_item_tags = DBselect(DB::makeSql('item_tag', $options));

		while ($db_item_tag = DBfetch($db_item_tags)) {
			$db_items[$db_item_tag['itemid']]['tags'][$db_item_tag['itemtagid']] =
				array_diff_key($db_item_tag, array_flip(['itemid']));
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedPreprocessing(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			if (array_key_exists('preprocessing', $item)) {
				$itemids[] = $item['itemid'];
				$db_items[$item['itemid']]['preprocessing'] = [];
			}
		}

		if (!$itemids) {
			return;
		}

		$options = [
			'output' => [
				'item_preprocid', 'itemid', 'step', 'type', 'params', 'error_handler', 'error_handler_params'
			],
			'filter' => ['itemid' => $itemids],
			'sortfield' => ['itemid', 'step']
		];
		$db_item_preprocs = DBselect(DB::makeSql('item_preproc', $options));

		while ($db_item_preproc = DBfetch($db_item_preprocs)) {
			$db_items[$db_item_preproc['itemid']]['preprocessing'][$db_item_preproc['item_preprocid']] =
				array_diff_key($db_item_preproc, array_flip(['itemid']));
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedParameters(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			$type = $item['type'];
			$db_type = $db_items[$item['itemid']]['type'];

			if ((array_key_exists('parameters', $item) && in_array($type, [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]))
					|| ($type != $db_type && in_array($db_type, [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]))) {
				$itemids[] = $item['itemid'];
				$db_items[$item['itemid']]['parameters'] = [];
			}
			elseif (array_key_exists('parameters', $item)) {
				$db_items[$item['itemid']]['parameters'] = [];
			}
		}

		if (!$itemids) {
			return;
		}

		$options = [
			'output' => ['item_parameterid', 'itemid', 'name', 'value'],
			'filter' => ['itemid' => $itemids]
		];
		$db_item_parameters = DBselect(DB::makeSql('item_parameter', $options));

		while ($db_item_parameter = DBfetch($db_item_parameters)) {
			$db_items[$db_item_parameter['itemid']]['parameters'][$db_item_parameter['item_parameterid']] =
				array_diff_key($db_item_parameter, array_flip(['itemid']));
		}
	}

	/**
	 * Add the inherited items of the given items to the given item array.
	 *
	 * @param array $db_items
	 */
	public static function addInheritedItems(array &$db_items): void {
		$templateids = array_keys($db_items);

		do {
			$options = [
				'output' => ['itemid', 'name'],
				'filter' => ['templateid' => $templateids]
			];
			$result = DBselect(DB::makeSql('items', $options));

			$templateids = [];

			while ($row = DBfetch($result)) {
				if (!array_key_exists($row['itemid'], $db_items)) {
					$templateids[] = $row['itemid'];

					$db_items[$row['itemid']] = $row;
				}
			}
		} while ($templateids);
	}

	/**
	 * Reset the MIN and MAX values of Y axis in the graphs, if such are calculated using the given items.
	 *
	 * @param array $del_itemids
	 */
	protected static function resetGraphsYAxis(array $del_itemids): void {
		DB::update('graphs', [
			'values' => [
				'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymin_itemid' => null
			],
			'where' => ['ymin_itemid' => $del_itemids]
		]);

		DB::update('graphs', [
			'values' => [
				'ymax_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymax_itemid' => null
			],
			'where' => ['ymax_itemid' => $del_itemids]
		]);
	}

	/**
	 * Delete triggers and trigger prototypes, which contain the given items in the expression.
	 *
	 * @param array $del_itemids
	 */
	protected static function deleteAffectedTriggers(array $del_itemids): void {
		$result = DBselect(
			'SELECT DISTINCT f.triggerid,t.flags'.
			' FROM functions f,triggers t'.
			' WHERE f.triggerid=t.triggerid'.
				' AND '.dbConditionInt('f.itemid', $del_itemids)
		);

		$del_trigger_prototypeids = [];
		$del_triggerids = [];

		while ($row = DBfetch($result)) {
			if ($row['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$del_trigger_prototypeids[] = $row['triggerid'];
			}
			else {
				$del_triggerids[] = $row['triggerid'];
			}
		}

		if ($del_triggerids) {
			CTriggerManager::delete($del_triggerids);
		}

		if ($del_trigger_prototypeids) {
			CTriggerPrototypeManager::delete($del_trigger_prototypeids);
		}
	}

	/**
	 * Prioritize ZBX_PREPROC_VALIDATE_NOT_SUPPORTED checks, with "match any error" being the last of them.
	 *
	 * @param array $steps
	 *
	 * @return array
	 */
	private static function sortPreprocessingSteps(array $steps): array {
		$ns_regex = [];
		$ns_any = [];
		$other = [];

		foreach ($steps as $step) {
			if ($step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
				$other[] = $step;
				continue;
			}

			[$match_type] = explode("\n", $step['params']);

			if ($match_type == ZBX_PREPROC_MATCH_ERROR_ANY) {
				$ns_any[] = $step;
			}
			else {
				$ns_regex[] = $step;
			}
		}

		return array_merge($ns_regex, $ns_any, $other);
	}

	protected static function prepareItemsForApi(array &$items, bool $sortorder = true): void {
		foreach ($items as &$item) {
			self::prepareItemForApi($item, $sortorder);
		}
		unset($item);
	}

	protected static function prepareItemForApi(array &$item, bool $sortorder = true): void {
		if (array_key_exists('query_fields', $item)) {
			$item['query_fields'] = self::prepareQueryFieldsForApi($item['query_fields'], $sortorder);
		}

		if (array_key_exists('headers', $item)) {
			$item['headers'] = self::prepareHeadersForApi($item['headers'], $sortorder);
		}
	}

	private static function prepareQueryFieldsForApi(string $query_fields, bool $sortorder): array {
		if ($query_fields === '') {
			return [];
		}

		$_query_fields = json_decode($query_fields, true);
		$query_fields = [];

		if (json_last_error() == JSON_ERROR_NONE) {
			foreach ($_query_fields as $i => $field) {
				$query_fields[] = $sortorder
					? ['sortorder' => $i + 1, 'name' => (string) key($field), 'value' => reset($field)]
					: ['name' => (string) key($field), 'value' => reset($field)];
			}
		}

		return $query_fields;
	}

	private static function prepareHeadersForApi(string $headers, bool $sortorder): array {
		if ($headers === '') {
			return [];
		}

		$_headers = explode("\r\n", $headers);
		$headers = [];

		foreach ($_headers as $i => $header) {
			[$name, $value] = explode(': ', $header, 2) + [1 => ''];

			$headers[] = $sortorder
				? ['sortorder' => $i + 1, 'name' => $name, 'value' => $value]
				: ['name' => $name, 'value' => $value];
		}

		return $headers;
	}

	protected static function prepareItemsForDb(array &$items): void {
		foreach ($items as &$item) {
			self::prepareItemForDb($item);
		}
		unset($item);
	}

	private static function prepareItemForDb(array &$item): void {
		if (array_key_exists('query_fields', $item)) {
			$item['query_fields'] = self::prepareQueryFieldsForDb($item['query_fields']);
		}

		if (array_key_exists('headers', $item)) {
			$item['headers'] = self::prepareHeadersForDb($item['headers']);
		}
	}

	private static function prepareQueryFieldsForDb(array $query_fields): string {
		foreach ($query_fields as &$query_field) {
			$query_field = [$query_field['name'] => $query_field['value']];
		}
		unset($query_field);

		return $query_fields ? json_encode(array_values($query_fields), JSON_UNESCAPED_UNICODE) : '';
	}

	private static function prepareHeadersForDb(array $headers): string {
		foreach ($headers as &$header) {
			$header = $header['name'].': '.$header['value'];
		}
		unset($header);

		return $headers ? implode("\r\n", $headers) : '';
	}
}
