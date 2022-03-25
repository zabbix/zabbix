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
 * Class containing methods for operations with item general.
 */
abstract class CItemGeneral extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected const ERROR_EXISTS_TEMPLATE = 'existsTemplate';
	protected const ERROR_EXISTS = 'exists';
	protected const ERROR_NO_INTERFACE = 'noInterface';

	/**
	 * A list of supported pre-processing types.
	 *
	 * @var array
	 */
	public const SUPPORTED_PREPROCESSING_TYPES = [];

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
	 * Value of audit log resource.
	 *
	 * @var int
	 */
	protected const AUDIT_RESOURCE = -1;

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get($options = []);

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_NO_INTERFACE => _('Cannot find host interface on "%1$s" for item key "%2$s".')
		]);
	}

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
				$api_input_rules['fields'] += $item_type::getUpdateValidationRulesInherited($item, $db_item);
			}
			elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$api_input_rules['fields'] += $item_type::getUpdateValidationRulesDiscovered($item, $db_item);
			}
			else {
				$api_input_rules['fields'] += $item_type::getUpdateValidationRules($item, $db_item);
			}

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($item);
	}

	/**
	 * @param array $items
	 */
	protected static function validateUniqueness(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['hostid', 'key_']], 'fields' => [
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
		return ['type' => API_OBJECTS, 'uniq' => [['tag', 'value']], 'fields' => [
			'tag' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_tag', 'tag')],
			'value' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('item_tag', 'value'), 'default' => DB::getDefault('item_tag', 'value')]
		]];
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected function validatePreprocessing(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'fields' => [
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', static::SUPPORTED_PREPROCESSING_TYPES)],
			'params' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => ZBX_PREPROC_MULTIPLIER], 'type' => API_MULTIPLIER, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | $this instanceof CItemPrototype ? API_ALLOW_LLD_MACRO : 0, 'length' => DB::getFieldLength('item_preproc', 'params')],
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_TRIM, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_SCRIPT, ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_preproc', 'params')],
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_REGSUB, ZBX_PREPROC_ERROR_FIELD_REGEX])], 'type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_preproc', 'params')],
											['if' => ['field' => 'type', 'in' => ZBX_PREPROC_THROTTLE_TIMED_VALUE], 'type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | ($this instanceof CItemPrototype || $this instanceof CDiscoveryRule) ? API_ALLOW_LLD_MACRO : 0, 'in' => '1:'.ZBX_MAX_TIMESHIFT, 'length' => DB::getFieldLength('item_preproc', 'params')],
											['if' => ['field' => 'type', 'in' => ZBX_PREPROC_PROMETHEUS_TO_JSON], 'type' => API_PROMETHEUS_PATTERN, 'flags' => API_REQUIRED | $this instanceof CItemPrototype ? API_ALLOW_LLD_MACRO : 0, 'length' => DB::getFieldLength('item_preproc', 'params')],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'error_handler' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_REGSUB, ZBX_PREPROC_BOOL2DEC, ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC, ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_XML_TO_JSON])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PREPROC_FAIL_DEFAULT, ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE, ZBX_PREPROC_FAIL_SET_ERROR])],
											['if' => ['field' => 'type', 'in' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE, ZBX_PREPROC_FAIL_SET_ERROR])],
											['else' => true, 'type' => API_INT32, 'in' => ZBX_PREPROC_FAIL_DEFAULT, 'default' => ZBX_PREPROC_FAIL_DEFAULT]
			]],
			'error_handler_params' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'error_handler', 'in' => ZBX_PREPROC_FAIL_DEFAULT.','.ZBX_PREPROC_FAIL_DISCARD_VALUE], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => ''],
											['if' => ['field' => 'error_handler', 'in' => ZBX_PREPROC_FAIL_SET_VALUE], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('item_preproc', 'error_handler_params')],
											['if' => ['field' => 'error_handler', 'in' => ZBX_PREPROC_FAIL_SET_ERROR], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_preproc', 'error_handler_params')]
			]]
		]];

		$prometheus_pattern_parser = new CPrometheusPatternParser([
			'usermacros' => true,
			'lldmacros' => ($this instanceof CItemPrototype)
		]);
		$prometheus_output_parser = new CPrometheusOutputParser([
			'usermacros' => true,
			'lldmacros' => ($this instanceof CItemPrototype)
		]);
		$with_header_row_validator = new CLimitedSetValidator([
			'values' => [ZBX_PREPROC_CSV_NO_HEADER, ZBX_PREPROC_CSV_HEADER]
		]);

		foreach ($items as $i => &$item) {
			if (!array_key_exists('preprocessing', $item) || !$item['preprocessing']) {
				continue;
			}

			$item['preprocessing'] = self::normalizeItemPreprocessingSteps($item['preprocessing']);

			if (!CApiInputValidator::validate($api_input_rules, $item['preprocessing'], '/'.($i + 1).'/preprocessing',
					$error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$delta = false;
			$throttling = false;
			$prometheus = false;
			$not_supported = false;

			foreach ($item['preprocessing'] as $preprocessing) {
				switch ($preprocessing['type']) {
					case ZBX_PREPROC_REGSUB:
					case ZBX_PREPROC_ERROR_FIELD_REGEX:
					case ZBX_PREPROC_STR_REPLACE:
						$params = explode("\n", $preprocessing['params']);

						if ($params[0] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('first parameter is expected')
							));
						}

						if (($preprocessing['type'] == ZBX_PREPROC_REGSUB
								|| $preprocessing['type'] == ZBX_PREPROC_ERROR_FIELD_REGEX)
									&& (!array_key_exists(1, $params) || $params[1] === '')) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('second parameter is expected')
							));
						}
						break;

					case ZBX_PREPROC_DELTA_VALUE:
					case ZBX_PREPROC_DELTA_SPEED:
						if ($delta) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one change step is allowed.'));
						}

						$delta = true;
						break;

					case ZBX_PREPROC_VALIDATE_RANGE:
						$params = explode("\n", $preprocessing['params']);

						if ($params[0] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('first parameter is expected')
							));
						}

						if (!is_numeric($params[0])
								&& (new CUserMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS
								&& (!$this instanceof CItemPrototype
									|| ((new CLLDMacroFunctionParser())->parse($params[0]) != CParser::PARSE_SUCCESS
										&& (new CLLDMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('a numeric value is expected')
							));
						}

						if (!array_key_exists(1, $params) || $params[1] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('second parameter is expected')
							));
						}

						if (!is_numeric($params[1])
								&& (new CUserMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS
								&& (!$this instanceof CItemPrototype
									|| ((new CLLDMacroFunctionParser())->parse($params[1]) != CParser::PARSE_SUCCESS
										&& (new CLLDMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('a numeric value is expected')
							));
						}

						if (is_numeric($params[0]) && is_numeric($params[1]) && $params[0] > $params[1]) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value for field "%1$s": %2$s.',
								'params',
								_s('"%1$s" value must be less than or equal to "%2$s" value', _('min'), _('max'))
							));
						}
						break;

					case ZBX_PREPROC_THROTTLE_VALUE:
					case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
						if ($throttling) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one throttling step is allowed.'));
						}

						$throttling = true;
						break;

					case ZBX_PREPROC_PROMETHEUS_PATTERN:
					case ZBX_PREPROC_PROMETHEUS_TO_JSON:
						if ($prometheus) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one Prometheus step is allowed.'));
						}

						$prometheus = true;

						if ($preprocessing['type'] == ZBX_PREPROC_PROMETHEUS_PATTERN) {
							$params = explode("\n", $preprocessing['params']);

							if ($params[0] === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('first parameter is expected')
								));
							}
							elseif (!array_key_exists(1, $params)) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('second parameter is expected')
								));
							}
							elseif ($params[2] === ''
									&& ($params[1] === ZBX_PREPROC_PROMETHEUS_LABEL
										|| $params[1] === ZBX_PREPROC_PROMETHEUS_FUNCTION)) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('third parameter is expected')
								));
							}

							if ($prometheus_pattern_parser->parse($params[0]) != CParser::PARSE_SUCCESS) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('invalid Prometheus pattern')
								));
							}

							if (!in_array($params[1], [ZBX_PREPROC_PROMETHEUS_VALUE, ZBX_PREPROC_PROMETHEUS_LABEL,
									ZBX_PREPROC_PROMETHEUS_FUNCTION])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('invalid aggregation method')
								));
							}

							switch ($params[1]) {
								case ZBX_PREPROC_PROMETHEUS_VALUE:
									if ($params[2] !== '') {
										self::exception(ZBX_API_ERROR_PARAMETERS,
											_s('Incorrect value for field "%1$s": %2$s.', 'params',
												_('invalid Prometheus output')
											)
										);
									}
									break;

								case ZBX_PREPROC_PROMETHEUS_LABEL:
									if ($prometheus_output_parser->parse($params[2]) != CParser::PARSE_SUCCESS) {
										self::exception(ZBX_API_ERROR_PARAMETERS,
											_s('Incorrect value for field "%1$s": %2$s.', 'params',
												_('invalid Prometheus output')
											)
										);
									}
									break;

								case ZBX_PREPROC_PROMETHEUS_FUNCTION:
									if (!in_array($params[2], [ZBX_PREPROC_PROMETHEUS_SUM, ZBX_PREPROC_PROMETHEUS_MIN,
											ZBX_PREPROC_PROMETHEUS_MAX, ZBX_PREPROC_PROMETHEUS_AVG,
											ZBX_PREPROC_PROMETHEUS_COUNT])) {
										self::exception(ZBX_API_ERROR_PARAMETERS,
											_s('Incorrect value for field "%1$s": %2$s.', 'params',
												_('unsupported Prometheus function')
											)
										);
									}
									break;
							}
						}
						break;

					case ZBX_PREPROC_CSV_TO_JSON:
						$params = explode("\n", $preprocessing['params']);
						$params_cnt = count($params);

						if ($params_cnt > 3) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($params_cnt == 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('second parameter is expected')
							));
						}
						elseif ($params_cnt == 2) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('third parameter is expected')
							));
						}
						else {
							// Correct amount of parameters, but check if they are valid.

							if (mb_strlen($params[0]) > 1) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('value of first parameter is too long')
								));
							}

							if (mb_strlen($params[1]) > 1) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('value of second parameter is too long')
								));
							}

							if (!$with_header_row_validator->validate($params[2])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect value for field "%1$s": %2$s.', 'params',
										_s('value of third parameter must be one of %1$s',
											implode(', ', [ZBX_PREPROC_CSV_NO_HEADER, ZBX_PREPROC_CSV_HEADER])
										)
									)
								);
							}
						}
						break;

					case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
						if ($not_supported) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('Only one not supported value check is allowed.')
							);
						}

						$not_supported = true;
						break;
				}
			}
		}
		unset($item);
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected function checkSpecificFields(array &$items): void {
		foreach ($items as $i => &$item) {
			switch ($item['type']) {
				case ITEM_TYPE_JMX:
					if ((array_key_exists('username', $item) && !array_key_exists('password', $item))
							|| (!array_key_exists('username', $item) && array_key_exists('password', $item))
							|| (array_key_exists('username', $item) && array_key_exists('password', $item)
								&& ($item['username'] === '') !== ($item['password'] === ''))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'username', _('both username and password should be either present or empty')
						));
					}
					break;

				case ITEM_TYPE_HTTPAGENT:
					if (array_key_exists('query_fields', $item)) {
						if ($item['query_fields']) {
							foreach ($item['query_fields'] as $v) {
								if (!is_array($v) || count($v) > 1 || key($v) === '') {
									self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
										'/'.($i + 1).'/query_fields', _('nonempty key and value pair expected'))
									);
								}
							}

							$item['query_fields'] = json_encode($item['query_fields']);

							if (strlen($item['query_fields']) > DB::getFieldLength('items', 'query_fields')) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
									'query_fields', _('cannot convert to JSON, result value too long')
								));
							}
						}
						else {
							$item['query_fields'] = '';
						}
					}

					if (array_key_exists('headers', $item)) {
						foreach ($item['headers'] as $k => $v) {
							if (trim($k) === '' || !is_string($v) || $v === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
									'headers', _('nonempty key and value pair expected'))
								);
							}
						}

						$item['headers'] = self::headersArrayToString($item['headers']);
					}
					break;
			}
		}
		unset($item);
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
	 * Add host_status property to given items in accordance of given hosts and templates statuses.
	 *
	 * @param array $items
	 * @param array $db_hosts
	 * @param array $db_templates
	 */
	protected static function addHostStatus(array &$items, array $db_hosts, array $db_templates): void {
		foreach ($items as &$item) {
			if (array_key_exists($item['hostid'], $db_templates)) {
				$item['host_status'] = HOST_STATUS_TEMPLATE;
			}
			else {
				$item['host_status'] = $db_hosts[$item['hostid']]['status'];
			}
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
	 * Check and add UUID to all item prototypes on templates, if it doesn't exist.
	 *
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected static function checkAndAddUuid(array &$items): void {
		foreach ($items as &$item) {
			if ($item['host_status'] != HOST_STATUS_TEMPLATE) {
				continue;
			}

			if (!array_key_exists('uuid', $item)) {
				$item['uuid'] = generateUuidV4();
			}
		}
		unset($item);

		$uuids = array_column($items, 'uuid');

		if (!$uuids) {
			return;
		}

		$duplicates = DB::select('items', [
			'output' => ['uuid'],
			'filter' => ['uuid' => $uuids],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $duplicates[0]['uuid'])
			);
		}
	}

	protected function errorInheritFlags($flag, $key, $host) {
		switch ($flag) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_RULE:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as a discovery rule.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_PROTOTYPE:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item prototype.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CREATED:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item created from item prototype.', $key, $host));
				break;
			default:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as unknown item element.', $key, $host));
		}
	}

	/**
	 * Returns the interface that best matches the given item.
	 *
	 * @param array $item_type  An item type
	 * @param array $interfaces An array of interfaces to choose from
	 *
	 * @return array|boolean    The best matching interface;
	 *							an empty array of no matching interface was found;
	 *							false, if the item does not need an interface
	 */
	public static function findInterfaceForItem($item_type, array $interfaces) {
		$interface_by_type = [];
		foreach ($interfaces as $interface) {
			if ($interface['main'] == 1) {
				$interface_by_type[$interface['type']] = $interface;
			}
		}

		// find item interface type
		$type = itemTypeInterface($item_type);

		// the item can use any interface
		if ($type == INTERFACE_TYPE_ANY) {
			$interface_types = [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI];
			foreach ($interface_types as $interface_type) {
				if (array_key_exists($interface_type, $interface_by_type)) {
					return $interface_by_type[$interface_type];
				}
			}
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			return array_key_exists($type, $interface_by_type) ? $interface_by_type[$type] : [];
		}
		// the item does not need an interface
		else {
			return false;
		}
	}

	/**
	 * Updates the children of the item on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $items     An array of newly created/updated template-items to inherit changes from.
	 * @param array      $db_items  Their corresponding records (for updates).
	 * @param array|null $hostids   A list of hosts IDs to limit inheritance to.
	 *								If NULL, the items will be inherited to all linked hosts or templates.
	 */
	protected function inherit(array $items, array $db_items = [], array $hostids = null) {
		$dep_items = [];
		$db_dep_items = [];

		// Inherit starting from common items and finishing with dependent ones.
		foreach ($items as $i => $item) {
			// Item is (or switched type) to dependent; is changed along with its master-item.
			if ($item['type'] == ITEM_TYPE_DEPENDENT
					|| (array_key_exists('master_itemid', $item) && $item['master_itemid'] !=0)) {
				$dep_items[$i] = $item;
				unset($items[$i]);

				if (array_key_exists($item['itemid'], $db_items)) {
					$db_dep_items[$item['itemid']] = $db_items[$item['itemid']];
					unset($db_items[$item['itemid']]);
				}
			}
		}

		$this->_inherit($items, $db_items, $hostids);
		$this->_inherit($dep_items, $db_dep_items, $hostids);
	}

	/**
	 * Auxiliary method for item inheritance.
	 *
	 * @see CItemGeneral::inherit()  For full description.
	 */

	private function _inherit(array $items, array $db_items = [], array $hostids = null): void {
		$ins_items = [];
		$upd_items = [];
		$upd_db_items = [];

		if ($db_items) {
			$_upd_db_items = $this->getChildObjectsUsingTemplateid($items, $db_items);

			if ($_upd_db_items) {
				$_upd_items = self::getUpdChildObjectsUsingTemplateid($items, $db_items, $_upd_db_items);

				self::checkDuplicates($_upd_items, $_upd_db_items);

				$upd_items = array_merge($upd_items, $_upd_items);
				$upd_db_items += $_upd_db_items;
			}
		}

		if (count($items) != count($db_items)) {
			$_upd_db_items = $this->getChildObjectsUsingName($items, $hostids);

			if ($_upd_db_items) {
				$_upd_items = self::getUpdChildObjectsUsingName($items, $db_items, $_upd_db_items);

				$upd_items = array_merge($upd_items, $_upd_items);
				$upd_db_items += $_upd_db_items;
			}

			$ins_items = $this->getInsChildObjects($items, $_upd_db_items, $hostids);

			self::checkKeyPresenceOnHosts($ins_items);
		}

		if ($upd_items) {
			$this->updateForce($upd_items, $upd_db_items);
		}

		if ($ins_items) {
			$ins_items = self::syncMasterItemLinks($ins_items, array_column($items, null, 'itemid'));

			$this->createForce($ins_items);
		}

		[$tpl_items, $tpl_db_items] = $this->getTemplatedObjects(
			array_merge($upd_items, $ins_items), $upd_db_items
		);

		self::checkDependentItems($tpl_items, $tpl_db_items);

		if ($tpl_items) {
			$this->inherit($tpl_items, $tpl_db_items);
		}
	}

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	private static function unsetNestedObjectIds(array $item): array {
		if (array_key_exists('preprocessing', $item)) {
			foreach ($item['preprocessing'] as &$preprocessing) {
				unset($preprocessing['item_preprocid']);
			}
			unset($preprocessing);
		}

		if (array_key_exists('tags', $item)) {
			foreach ($item['tags'] as &$tag) {
				unset($tag['itemtagid']);
			}
			unset($tag);
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
	 * Collect all fields used by items of various types to select for their DB counterparts.
	 *
	 * @param array $items          Items and derivatives to inherit from.
	 *								various combinations of fields depending on type/whether it was changed.
	 * @param string $alias_prefix  Table alias prefix, 'i' by default.
	 *
	 * @return array
	 */
	protected static function getCombinedFields(array $items, $alias_prefix = 'i'): array {
		$schema = DB::getSchema('items');
		$item_fields = [];

		foreach ($items as $item) {
			$item_fields += array_fill_keys(array_keys($item), true);
		}

		if ($alias_prefix !== '') {
			$alias_prefix = $alias_prefix.'.';
		}

		foreach (array_keys($item_fields) as $field) {
			if (array_key_exists($field, $schema['fields'])) {
				$item_fields[$field] = $alias_prefix.$field;
			}
			else {
				unset($item_fields[$field]);
			}
		}

		return $item_fields;
	}

	/**
	 * Template items overide ones on host with the same key.
	 * Load host.items that should be overriden with template.item if matched by name (key).
	 *
	 * @param array      $items
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private function getChildObjectsUsingName(array $items, ?array $hostids): array {
		$upd_db_items = [];
		$parent_indexes = [];

		$template_hostids = [];
		$hostids = [];
		$hostids_condition = ($hostids !== null) ? ' AND '.dbConditionId('ht.hostid', $hostids) : '';
		$template_links = DBselect(
			'SELECT ht.templateid,ht.hostid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionId('ht.templateid', array_column($items, 'hostid')).
				$hostids_condition
		);

		while ($row = DBfetch($template_links)) {
			$template_hostids[$row['templateid']][] = $row['hostid'];
			$hostids[] = $row['hostid'];
		}

		$sql = 'SELECT '.implode(',', self::getCombinedFields($items));
		$sql .= ($this instanceof CItemPrototype) ? ',id.parent_itemid AS ruleid' : '';
		$sql .=	' FROM items i';
		// "LEFT JOIN" is needed to check flags on inherited and existing item, item prototype or LLD rule.
		// For example, when linking an item prototype with same key as in an item on target host or template.
		$sql .= ($this instanceof CItemPrototype) ? ' LEFT JOIN item_discovery id ON i.itemid=id.itemid' : '';
		$sql .= ' WHERE '.dbConditionId('i.hostid', $hostids).
			' AND '.dbConditionString('i.key_', array_column($items, 'key_', 'key_'));

		$db_items = DBselect($sql);

		while ($row = DBfetch($db_items)) {
			foreach ($items as $i => $item) {
				if (array_key_exists($item['hostid'], $template_hostids)
						&& in_array($row['hostid'], $template_hostids[$item['hostid']])
						&& $row['key_'] === $item['key_']) {
					$row['parent_host'] = $item['hostid'];

					$upd_db_items[$row['itemid']] = $row;
					$parent_indexes[$row['itemid']] = $i;
				}
			}
		}

		if ($upd_db_items) {
			$upd_items = [];

			foreach ($upd_db_items as $upd_db_item) {
				$item = $items[$parent_indexes[$upd_db_item['itemid']]];

				$upd_item = array_intersect_key($upd_db_item, array_flip(['itemid', 'hostid', 'type']));
				$upd_item += [
					'templateid' => $item['itemid'],
					'preprocessing' => [],
					'parameters' => [],
					'tags' => [],
				];

				if ($this instanceof CItemPrototype) {
					$upd_item['ruleid'] = $upd_db_item['ruleid'];
				}

				$upd_items[] = $upd_item;
			}

			static::addAffectedObjects($upd_items, $upd_db_items);
		}

		return $upd_db_items;
	}

	/**
	 * Transfer input changes from parent items to child items.
	 *
	 * @param array $items         Items.
	 * @param array $db_items
	 * @param array $upd_db_items  Parent versions.
	 *
	 * @return array Items to update.
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $items, array $db_items, array $upd_db_items) {
		$upd_items = [];

		foreach ($items as $item) {
			if (!array_key_exists($item['itemid'], $db_items)) {
				continue;
			}

			$item = self::unsetNestedObjectIds($item);

			// Find children of template-item, add input changes.
			foreach ($upd_db_items as $upd_db_item) {
				if (bccomp($item['itemid'], $upd_db_item['templateid']) != 0) {
					continue;
				}

				$upd_item = array_intersect_key($upd_db_item,
					array_flip(['itemid', 'hostid', 'templateid', 'ruleid'])
				) + $item;

				$upd_items[] = $upd_item;
			}
		}

		return $upd_items;
	}

	/**
	 * Get updates/overrides to same-key item DB records derived from template-item having that key.
	 *
	 * @param array $items
	 * @param array $db_items
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingName(array $items, array $db_items, array $upd_db_items): array {
		$upd_items = [];

		foreach ($items as $item) {
			if (array_key_exists($item['itemid'], $db_items)) {
				continue;
			}

			$item = self::unsetNestedObjectIds($item);

			foreach ($upd_db_items as $upd_db_item) {
				if (bccomp($item['hostid'], $upd_db_item['parent_host']) != 0
						|| $item['key_'] !== $upd_db_item['key_']) {
					continue;
				}

				$upd_item = array_intersect_key($upd_db_item, array_flip(['itemid', 'hostid', 'templateid']));
				$upd_item += $item;
				$upd_item['templateid'] = $item['itemid'];

				$upd_item += $upd_db_item['preprocessing'] ? ['preprocessing' => []] : [];
				$upd_item += $upd_db_item['parameters'] ? ['parameters' => []] : [];
				$upd_item += $upd_db_item['tags'] ? ['tags' => []] : [];

				$upd_items[] = $upd_item;
			}
		}

		return $upd_items;
	}

	/**
	 * @param array      $items
	 * @param array      $upd_db_items
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private function getInsChildObjects(array $items, array $upd_db_items, ?array $hostids): array {
		$ins_items = [];

		$template_hostids = [];
		$host_statuses = [];
		$hostids_condition = ($hostids !== null) ? ' AND '.dbConditionId('ht.hostid', $hostids) : '';
		$template_links = DBselect(
			'SELECT ht.templateid,ht.hostid,h.status AS host_status'.
			' FROM hosts_templates ht,hosts h'.
			' WHERE '.dbConditionId('ht.templateid', array_column($items, 'hostid')).
				' AND ht.hostid=h.hostid'.
				$hostids_condition
		);

		while ($row = DBfetch($template_links)) {
			$template_hostids[$row['templateid']][] = $row['hostid'];
			$host_statuses[$row['hostid']] = $row['host_status'];
		}

		if (!$template_hostids) {
			return $ins_items;
		}

		foreach ($items as $item) {
			if (!array_key_exists($item['hostid'], $template_hostids)) {
				continue;
			}

			$item['uuid'] = '';
			$item = self::unsetNestedObjectIds($item);

			if (__CLASS__ === 'CItem' || __CLASS__ === 'CDiscoveryRule') {
				$item['rtdata'] = true;
			}

			foreach ($template_hostids[$item['hostid']] as $hostid) {
				foreach ($upd_db_items as $upd_db_item) {
					if (bccomp($hostid, $upd_db_item['hostid']) == 0
							&& $item['key_'] == $upd_db_item['key_']) {
						continue 2;
					}
				}

				$ins_item = [
					'hostid' => $hostid,
					'templateid' => $item['itemid'],
					'host_status' => $host_statuses[$hostid],
				] + array_diff_key($item, array_flip(['itemid']));

				$ins_items[] = $ins_item;
			}
		}

		return $ins_items;
	}

	/**
	 * @param array $items     Recently created/updated items that may need to be inherited.
	 * @param array $db_items  Their current DB records.
	 *
	 * @return array           Templated items with child-object info filled in, where not being replaced.
	 */
	private function getChildObjectsUsingTemplateid(array $items, array $db_items): array {
		$sql = 'SELECT i.itemid,i.hostid,i.key_,i.templateid,i.type,i.value_type,i.name,i.master_itemid,i.interfaceid,'.
				'i.history,h.status as host_status';
		$sql .= ($this instanceof CItemPrototype) ? ',id.parent_itemid AS ruleid' : '';
		$sql .= ' FROM hosts h,items i';
		$sql .= ($this instanceof CItemPrototype) ? ' LEFT JOIN item_discovery id ON id.itemid=i.itemid' : '';
		$sql .= ' WHERE '.dbConditionInt('i.templateid', array_keys($db_items)).
			' AND h.hostid=i.hostid';
		$upd_db_items = DBfetchArrayAssoc(DBselect($sql), 'itemid');

		if ($upd_db_items) {
			$upd_items = [];

			foreach ($upd_db_items as $upd_db_item) {
				$db_item = $db_items[$upd_db_item['templateid']];

				$upd_item = array_intersect_key($upd_db_item, array_flip(['itemid', 'hostid', 'templateid', 'type']));
				$upd_item += array_intersect_key([
					'preprocessing' => [],
					'parameters' => [],
					'tags' => []
				], $db_item);

				$upd_items[] = $upd_item;
			}

			static::addAffectedObjects($upd_items, $upd_db_items);
		}

		return $upd_db_items;
	}

	/**
	 * Filters out $items that don't belong to a template, optionally ones limited via $hostids.
	 *
	 * @param array       $items      Recently created/updated items and derivatives.
	 * @param string      $items[<itemid>]['itemid']
	 * @param string      $items[<itemid>]['hostid']
	 * @param string      $items[<itemid>]['key_']
	 * @param int         $items[<itemid>]['type']
	 * @param array       $items[<itemid>]['preprocessing']                    (optional)
	 * @param int         $items[<itemid>]['preprocessing'][]['type']
	 * @param string      $items[<itemid>]['preprocessing'][]['params']
	 * @param int         $items[<itemid>]['flags']
	 * @param string      $items[<itemid>]['master_itemid']                    (optional)
	 * @param mixed       $items[<itemid>][<field_name>]                       (optional)
	 * @param array|null  $db_items   Accompanying DB records.
	 * @param array|null  $hostids    To filter for.
	 *
	 * @throws APIException
	 *
	 * @return array     Consisting of [0] parent items and (on update) [1] their current DB records.
	 */
	protected function getTemplatedObjects(array $items, array $db_items = null, array $hostids = null): array {
		$hostids_condition = ($hostids === null)
			? dbConditionId('ht.templateid', array_column($items, 'hostid', 'hostid'))
			: dbConditionId('h.hostid', $hostids);

		$sql = 'SELECT DISTINCT ht.templateid'.
		' FROM hosts_templates ht'.
		' LEFT JOIN hosts h ON ht.hostid=h.hostid AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE]).
		' WHERE '.$hostids_condition;
		$templateids = DBfetchColumn(DBselect($sql), 'templateid');

		foreach ($items as $i => $item) {
			if (!in_array($item['hostid'], $templateids)) {
				unset($items[$i]);

				if ($db_items !== null && array_key_exists($item['itemid'], $db_items)) {
					unset($db_items[$item['itemid']]);
				}
			}
		}

		// Reindex sequentially for possible inserts.
		$items = array_values($items);

		return ($db_items === null) ? [$items] : [$items, $db_items];
	}


	/**
	 * Throw exception if item with a new key already exists on the child host.
	 *
	 * @param array $items  To be inserted.
	 *
	 * @throws APIException
	 * @return void
	 */
	protected function checkKeyPresenceOnHosts(array $items): void {
		if (!$items) {
			return;
		}

		$key_hostids = [];
		$sql_where = [];

		foreach ($items as $item) {
			$key_hostids[$item['key_']][] = $item['hostid'];
		}

		foreach ($key_hostids as $key => $hostids) {
			$sql_where[] = dbConditionId('i.hostid', $hostids).' AND '.dbConditionString('i.key_', [$key]);
		}

		$sql = 'SELECT i.hostid,i.key_,h.host'.
			' FROM items i, hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND ('.implode(') OR (', $sql_where).')';

		if ($db_item = DBfetch(DBselect($sql, 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _params($this->getErrorMsg(self::ERROR_EXISTS),
				[$db_item['key_'], $db_item['host']]
			));
		}
	}

	/**
	 * Update relation to master item for inherited dependent items.
	 *
	 * @param array       $tpl_items                             Recently created/updated items.
	 * @param int         $tpl_items[<itemid>]['type']
	 * @param string      $tpl_items[<itemid>]['master_itemid']
	 * @param array|null  $hostids                               To limit propagation to, otherwise any.
	 * @param array       $items                                 Items prepared to be inherited.
	 * @param string      $items[<itemid>]['hostid']
	 * @param int         $items[<itemid>]['type']
	 * @param string      $items[<itemid>]['templateid']
	 *
	 * @return array      Inherited items with master_itemid updated for dependent ones.
	 */
	private static function syncMasterItemLinks(array $items, array $tpl_items): array {
		$master_itemids = [];

		foreach ($tpl_items as $tpl_item) {
			if ($tpl_item['type'] == ITEM_TYPE_DEPENDENT) {
				$master_itemids[$tpl_item['master_itemid']] = $tpl_item['master_itemid'];
			}
		}

		if (!$master_itemids) {
			return $items;
		}

		$sql = 'SELECT i.itemid,i.hostid,i.templateid'.
			' FROM items i'.
			' WHERE '.dbConditionId('i.templateid', $master_itemids);

		$master_items = DBselect($sql);
		$master_itemids = [];

		while ($master_item = DBfetch($master_items)) {
			$master_itemids[$master_item['templateid']][$master_item['hostid']] = $master_item['itemid'];
		}

		foreach ($items as &$item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				$tpl_item = $tpl_items[$item['templateid']];

				if (array_key_exists('master_itemid', $tpl_item)) {
					$item['master_itemid'] = $master_itemids[$tpl_item['master_itemid']][$item['hostid']];
				}
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Method validates preprocessing steps independently of other item properties.
	 *
	 * @param array  $preprocessing_steps  An array of item preprocessing step details.
	 *                                     See self::validatePreprocessing for details.
	 *
	 * @return bool|string
	 */
	public function validateItemPreprocessingSteps(array $preprocessing_steps) {
		$items = [['preprocessing' => $preprocessing_steps]];

		try {
			$this->validatePreprocessing($items);

			return true;
		}
		catch (APIException $error) {
			return $error->getMessage();
		}
	}

	/**
	 * Common create handler for Items and derivatives.
	 *
	 * @param array $items Item or derived entity (prototype, discovery rule).
	 */
	protected static function createForce(array &$items): void {
		$itemids = DB::insert('items', $items);

		$ins_items_rtdata = [];
		$ins_items_discovery = [];
		$host_statuses = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
					&& in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				$ins_items_rtdata[] = ['itemid' => $item['itemid']];
			}

			$host_statuses[] = $item['host_status'];
			unset($item['host_status']);

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$ins_items_discovery[] = [
					'itemid' => $item['itemid'],
					'parent_itemid' => $item['ruleid']
				];
			}

			if ($item['type'] != ITEM_TYPE_DEPENDENT) {
				$item['master_itemid'] = null;
			}
		}
		unset($item);

		if ($ins_items_rtdata) {
			DB::insertBatch('item_rtdata', $ins_items_rtdata, false);
		}

		if ($ins_items_discovery) {
			DB::insertBatch('item_discovery', $ins_items_discovery);
		}

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateTags($items);

		self::addAuditLog(CAudit::ACTION_ADD, static::AUDIT_RESOURCE, $items);

		foreach ($items as &$item) {
			$item['host_status'] = array_shift($host_statuses);
		}
		unset($item);
	}

	/**
	 * Common update handler for Items and derivatives.
	 *
	 * @param array $items     Updates to apply.
	 * @param array $db_items  Current versions of items, indexed by itemid.
	 */
	protected function updateForce(array &$items, array $db_items): void {
		// Helps to avoid deadlocks.
		CArrayHelper::sort($items, ['itemid'], ZBX_SORT_DOWN);

		self::addDefaultFields($items, $db_items);

		$upd_items = [];

		foreach ($items as &$item) {
			$upd_item = DB::getUpdatedValues('items', $item, $db_items[$item['itemid']]);

			if ($upd_item) {
				$upd_items[] = [
					'values' => $upd_item,
					'where' => ['itemid' => $item['itemid']]
				];
			}
		}
		unset($item);

		if ($upd_items) {
			DB::update('items', $upd_items);
		}

		self::updateParameters($items, $db_items);
		self::updatePreprocessing($items, $db_items);
		self::updateTags($items, $db_items);

		self::addAuditLog(CAudit::ACTION_UPDATE, static::AUDIT_RESOURCE, $items, $db_items);
	}

	/**
	 * Add default values for a fields that became unnecessary as the result of the change of the type fields.
	 *
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addDefaultFields(array &$items, array $db_items): void {
		$defaults = DB::getDefaults('items') + array_fill_keys(['valuemapid', 'interfaceid', 'master_itemid'], 0);

		foreach ($items as &$item) {
			$db_item = $db_items[$item['itemid']];

			if ($item['type'] != $db_item['type']) {
				$type_field_names = CItemTypeFactory::getObject($item['type'])::FIELD_NAMES;
				$db_type_field_names = CItemTypeFactory::getObject($db_item['type'])::FIELD_NAMES;

				$field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

				if (array_intersect([$item['type'], $db_item['type']], [ITEM_TYPE_SSH, ITEM_TYPE_HTTPAGENT])) {
					$field_names += array_flip(['authtype']);
				}

				if ($item['host_status'] == HOST_STATUS_TEMPLATE && array_key_exists('interfaceid', $field_names)) {
					unset($field_names['interfaceid']);
				}

				$item += array_intersect_key($defaults, $field_names);
			}
			elseif ($item['type'] == ITEM_TYPE_SSH) {
				if (array_key_exists('authtype', $item) && $item['authtype'] !== $db_item['authtype']
						&& $item['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
					$item += array_intersect_key($defaults, array_flip(['publickey', 'privatekey']));
				}
			}
			elseif ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('request_method', $item) && $item['request_method'] != $db_item['request_method']
						&& $item['request_method'] == HTTPCHECK_REQUEST_HEAD) {
					$item += ['retrieve_mode' => HTTPCHECK_REQUEST_HEAD];
				}

				if (array_key_exists('authtype', $item) && $item['authtype'] != $db_item['authtype']
						&& $item['authtype'] == HTTPTEST_AUTH_NONE) {
					$item += array_intersect_key($defaults, array_flip(['username', 'password']));
				}

				if (array_key_exists('allow_traps', $item) && $item['allow_traps'] != $db_item['allow_traps']
						&& $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF) {
					$item += array_intersect_key($defaults, array_flip(['trapper_hosts']));
				}
			}

			if (array_key_exists('value_type', $item) && $item['value_type'] != $db_item['value_type']) {
				$type_field_names = static::VALUE_TYPE_FIELD_NAMES[$item['value_type']];
				$db_type_field_names = static::VALUE_TYPE_FIELD_NAMES[$db_item['value_type']];

				$field_names = array_flip(array_diff($db_type_field_names, $type_field_names));

				$item += array_intersect_key($defaults, $field_names);
			}
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 */
	protected static function updateParameters(array &$items, array $db_items = null): void {
		$ins_item_parameters = [];
		$upd_item_parameters = [];
		$del_item_parameterids = [];

		foreach ($items as &$item) {
			if (($db_items === null && !array_key_exists('parameters', $item))
					|| ($db_items !== null && !array_key_exists('parameters', $db_items[$item['itemid']]))) {
				continue;
			}

			if ($db_items !== null && !array_key_exists('parameters', $item)) {
				$item['parameters'] = [];
			}

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
					}
				}
				else {
					$ins_item_parameters[] = ['itemid' => $item['itemid']] + $item_parameter;
				}
			}
			unset($item_parameter);

			$del_item_parameterids = array_merge($del_item_parameterids,
				array_column($db_item_parameters, 'item_parameterid')
			);
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
	 */
	protected static function updatePreprocessing(array &$items, array $db_items = null): void {
		$ins_item_preprocs = [];
		$upd_item_preprocs = [];
		$del_item_preprocids = [];

		foreach ($items as &$item) {
			if (!array_key_exists('preprocessing', $item)) {
				continue;
			}

			$db_item_preprocs = ($db_items !== null)
				? array_column($db_items[$item['itemid']]['preprocessing'], null, 'step')
				: [];

			$step = 1;

			foreach ($item['preprocessing'] as &$item_preproc) {
				$item_preproc['step'] = ($item_preproc['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) ? 0 : $step++;

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
					}
				}
				else {
					$ins_item_preprocs[] = ['itemid' => $item['itemid']] + $item_preproc;
				}
			}
			unset($item_preproc);

			$del_item_preprocids = array_merge($del_item_preprocids, array_column($db_item_preprocs, 'item_preprocid'));
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
	 */
	protected static function updateTags(array &$items, array $db_items = null): void {
		$ins_tags = [];
		$del_tags = [];

		foreach ($items as &$item) {
			if (!array_key_exists('tags', $item)) {
				continue;
			}

			$db_tags = [];

			if ($db_items !== null) {
				foreach ($db_items[$item['itemid']]['tags'] as $db_tag) {
					$db_tags[$db_tag['tag']][$db_tag['value']] = $db_tag['itemtagid'];
					$del_tags[$db_tag['itemtagid']] = true;
				}
			}

			foreach ($item['tags'] as &$tag) {
				if (array_key_exists($tag['tag'], $db_tags) && array_key_exists($tag['value'], $db_tags[$tag['tag']])) {
					$tag['itemtagid'] = $db_tags[$tag['tag']][$tag['value']];
					unset($del_tags[$tag['itemtagid']]);
				}
				else {
					$ins_tags[] = ['itemid' => $item['itemid']] + $tag;
				}
			}
			unset($tag);
		}
		unset($item);

		if ($del_tags) {
			DB::delete('item_tag', ['itemtagid' => array_keys($del_tags)]);
		}

		if ($ins_tags) {
			$itemtagids = DB::insert('item_tag', $ins_tags);

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
			switch ($duplicates[0]['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					if ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Item key "%1$s" already exists on template "%2$s".');
					}
					else {
						$error = _('Item key "%1$s" already exists on host "%2$s".');
					}
					break;

				case ZBX_FLAG_DISCOVERY_PROTOTYPE:
					if ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('Item prototype key "%1$s" already exists on template "%2$s".');
					}
					else {
						$error = _('Item prototype key "%1$s" already exists on host "%2$s".');
					}
					break;

				case ZBX_FLAG_DISCOVERY_RULE:
					if ($duplicates[0]['status'] == HOST_STATUS_TEMPLATE) {
						$error = _('LLD rule key "%1$s" already exists on template "%2$s".');
					}
					else {
						$error = _('LLD rule key "%1$s" already exists on host "%2$s".');
					}
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
		foreach ($items as $i => $item) {
			if ($db_items === null) {
				if (!array_key_exists('interfaceid', $item)) {
					unset($items[$i]);
					continue;
				}
			}
			else {
				$db_item = $db_items[$item['itemid']];

				if (!array_key_exists('interfaceid', $db_items[$item['itemid']])) {
					unset($items[$i]);
					continue;
				}
				else {
					if ($item['type'] == $db_item['type']) {
						if (!array_key_exists('interfaceid', $item)
								|| bccomp($item['interfaceid'], $db_item['interfaceid']) == 0) {
							unset($items[$i]);
							continue;
						}
					}
					else {
						$interface_type = itemTypeInterface($item['type']);
						$db_interface_type = itemTypeInterface($db_item['type']);

						if ($interface_type === false
								|| ($db_interface_type !== false
									&& ($interface_type == INTERFACE_TYPE_ANY || $interface_type == $db_interface_type)
									&& (!array_key_exists('interfaceid', $item)
										|| bccomp($item['interfaceid'], $db_item['interfaceid']) == 0))) {
							unset($items[$i]);
							continue;
						}

						$item += ['interfaceid' => $db_item['interfaceid']];
					}
				}
			}
		}

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

			if ($interface_type != INTERFACE_TYPE_ANY
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
			if (!array_key_exists('master_itemid', $item) || $item['master_itemid'] == 0
					|| (array_key_exists('itemid', $item)
						&& bccomp($item['master_itemid'], $db_items[$item['itemid']]['master_itemid']) == 0)) {
				unset($items[$i]);
			}
			elseif (array_key_exists('itemid', $item)) {
				if ($db_items[$item['itemid']]['master_itemid'] != 0) {
					$del_links[$item['itemid']] = $db_items[$item['itemid']]['master_itemid'];
				}
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

		$root_itemids = [];

		foreach ($dep_item_links as $itemid => $master_itemid) {
			if ($master_itemid == 0) {
				$root_itemids[] = $itemid;
			}
		}

		$master_item_links = self::getMasterItemLinks($items, $root_itemids, $del_links);

		foreach ($root_itemids as $root_itemid) {
			if (self::maxDependencyLevelExceeded($master_item_links, $root_itemid, $links_path)) {
				[$is_update, $flags, $key, $master_flags, $master_key, $is_template, $host] =
					self::getProblemCausedItemData($links_path, $items, $db_items);

				$error = self::getDependentItemError($is_update, $flags, $master_flags, $is_template);

				self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $key, $master_key, $host,
					_('allowed count of dependency levels will be exceeded')
				));
			}

			if (self::maxDependentItemCountExceeded($master_item_links, $root_itemid, $links_path)) {
				[$is_update, $flags, $key, $master_flags, $master_key, $is_template, $host] =
					self::getProblemCausedItemData($links_path, $items, $db_items);

				$error = self::getDependentItemError($is_update, $flags, $master_flags, $is_template);

				self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $key, $master_key, $host,
					_('allowed count of dependent items will be exceeded')
				));
			}
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
				if ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$error = _('an item/item prototype ID is expected');
				}
				else {
					$error = _('an item ID is expected');
				}

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/master_itemid', $error
				));
			}

			$db_master_item = $db_master_items[$item['master_itemid']];

			if (bccomp($db_master_item['hostid'], $item['hostid']) != 0) {
				if ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$error = _('cannot be an item/item prototype ID from another host or template');
				}
				else {
					$error = _('cannot be an item ID from another host or template');
				}

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/master_itemid', $error
				));
			}

			if ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE && $db_master_item['ruleid'] != 0) {
				$item_ruleid = array_key_exists('itemid', $item)
					? $item['ruleid']
					: $db_items[$item['itemid']]['ruleid'];

				if (bccomp($db_master_item['ruleid'], $item_ruleid) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/master_itemid', _('cannot be an item prototype ID from another LLD rule')
					));
				}
			}
		}
	}

	/**
	 * Get dependendent item links starting from the given dependent items and till the highest dependency level.
	 *
	 * @param  array $items
	 * @param  array $del_links
	 *
	 * @return array  Array of the links where each key contain the ID of dependent item and value contain the
	 *                appropriate ID of the master item.
	 */
	private static function getDependentItemLinks(array $items, array $del_links): array {
		$links = array_column($items, 'master_itemid', 'itemid');
		$master_itemids = array_unique(array_column($items, 'master_itemid'));

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
			$master_itemid = $item['master_itemid'];

			while ($master_itemid != 0) {
				if ($master_itemid == $item['itemid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/master_itemid', _('circular item dependency is not allowed')
					));
				}

				$master_itemid = $dep_item_links[$master_itemid];
			}
		}
	}

	/**
	 * Get master item links starting from the given master items and till the lowest level master items.
	 *
	 * @param  array $items
	 * @param  array $master_itemids
	 * @param  array $del_links
	 *
	 * @return array  Array of the links where each key contain the ID of master item and value contain the array of
	 *                appropriate dependent item IDs.
	 */
	private static function getMasterItemLinks(array $items, array $master_itemids, array $del_links): array {
		$ins_links = [];
		$upd_item_links = [];

		foreach ($items as $item) {
			if (array_key_exists('itemid', $item)) {
				$upd_item_links[$item['master_itemid']][] = $item['itemid'];
			}
			else {
				$ins_links[$item['master_itemid']][] = 0;
			}
		}

		$links = [];

		do {
			$options = [
				'output' => ['master_itemid', 'itemid'],
				'filter' => [
					'master_itemid' => $master_itemids
				]
			];
			$db_items = DBselect(DB::makeSql('items', $options));

			$_master_itemids = [];

			while ($db_item = DBfetch($db_items)) {
				if (array_key_exists($db_item['itemid'], $del_links)
						&& bccomp($db_item['master_itemid'], $del_links[$db_item['itemid']]) == 0) {
					continue;
				}

				$links[$db_item['master_itemid']][] = $db_item['itemid'];
				$_master_itemids[] = $db_item['itemid'];
			}

			foreach ($master_itemids as $master_itemid) {
				if (array_key_exists($master_itemid, $upd_item_links)) {
					foreach ($upd_item_links[$master_itemid] as $itemid) {
						$_master_itemids[] = $itemid;
						$links[$master_itemid][] = $itemid;
					}
				}
			}

			$master_itemids = $_master_itemids;
		} while ($master_itemids);

		foreach ($ins_links as $master_itemid => $ins_items) {
			$links[$master_itemid] = array_merge($links[$master_itemid], $ins_items);
		}

		return $links;
	}

	/**
	 * Check whether maximum number of dependency levels is exceeded.
	 *
	 * @param array      $master_item_links
	 * @param string     $master_itemid
	 * @param array|null $links_path
	 * @param int        $level
	 *
	 * @return bool
	 */
	private static function maxDependencyLevelExceeded(array $master_item_links, string $master_itemid,
			array &$links_path = null, int $level = 0): bool {
		if (!array_key_exists($master_itemid, $master_item_links)) {
			return false;
		}

		if ($links_path === null) {
			$links_path = [];
		}

		$links_path[] = $master_itemid;
		$level++;

		if ($level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
			return true;
		}

		foreach ($master_item_links[$master_itemid] as $itemid) {
			$_links_path = $links_path;

			if (self::maxDependencyLevelExceeded($master_item_links, $itemid, $_links_path, $level)) {
				$links_path = $_links_path;

				return true;
			}
		}

		return false;
	}


	/**
	 * Check whether maximum count of dependent items is exceeded.
	 *
	 * @param array      $master_item_links
	 * @param string     $master_itemid
	 * @param array|null $links_path
	 * @param int        $count
	 *
	 * @return bool
	 */
	private static function maxDependentItemCountExceeded(array $master_item_links, string $master_itemid,
			array &$links_path = null, int &$count = 0): bool {
		if (!array_key_exists($master_itemid, $master_item_links)) {
			return false;
		}

		if ($links_path === null) {
			$links_path = [];
		}

		$links_path[] = $master_itemid;
		$count += count($master_item_links[$master_itemid]);

		if ($count > ZBX_DEPENDENT_ITEM_MAX_COUNT) {
			return true;
		}

		foreach ($master_item_links[$master_itemid] as $itemid) {
			$_links_path = $links_path;

			if (self::maxDependentItemCountExceeded($master_item_links, $itemid, $_links_path, $count)) {
				$links_path = $_links_path;

				return true;
			}
		}

		return false;
	}

	/**
	 * Get the data of dependent item that caused the problem relying on the given path where the problem was detected.
	 *
	 * @param array $links_path
	 * @param array $items
	 * @param array $db_items
	 *
	 * @return array
	 *
	 * @return int   Cumulative count of items under the root item.
	 */
	private static function getProblemCausedItemData(array $links_path, array $items, array $db_items): array {
		$items_by_masterid = [];

		foreach ($items as $i => $item) {
			unset($items[$i]);
			$items_by_masterid[$item['master_itemid']][] = $item;
		}

		foreach ($links_path as $master_itemid) {
			if (array_key_exists($master_itemid, $items_by_masterid)) {
				$item = $items_by_masterid[$master_itemid][0];
				break;
			}
		}

		$master_item_data = DBfetch(DBselect(
			'SELECT i.flags,i.key_,h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', [$item['master_itemid']])
		));

		$is_update = array_key_exists('itemid', $item);
		$flags = $item['flags'];
		$key = $item['key_'];
		$master_flags = $master_item_data['flags'];
		$master_key = $master_item_data['key'];
		$is_template = $item['host_status'] == HOST_STATUS_TEMPLATE;
		$host = $master_item_data['host'];

		return [$is_update, $flags, $key, $master_flags, $master_key, $is_template, $host];
	}

	/**
	 * Get the error message about problem with dependent item according to given data.
	 *
	 * @param bool $is_update
	 * @param int  $flags
	 * @param int  $master_flags
	 * @param bool $is_template
	 *
	 * @return string
	 */
	private static function getDependentItemError(bool $is_update, int $flags, int $master_flags,
			bool $is_template): string {
		if ($flags == ZBX_FLAG_DISCOVERY_NORMAL) {
			if ($is_update) {
				if ($is_template) {
					return _('Cannot update the dependent item "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
				}
				else {
					return _('Cannot update the dependent item "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
				}
			}
			else {
				if ($is_template) {
					return _('Cannot create the dependent item "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
				}
				else {
					return _('Cannot create the dependent item "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
				}
			}
		}
		elseif ($flags == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			if ($is_update) {
				if ($master_flags == ZBX_FLAG_DISCOVERY_NORMAL) {
					if ($is_template) {
						return _('Cannot update the dependent item prototype "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
					}
					else {
						return _('Cannot update the dependent item prototype "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
					}
				}
				else {
					if ($is_template) {
						return _('Cannot update the dependent item prototype "%1$s" with reference to the master item prototype "%2$s" on the template "%3$s": %4$s.');
					}
					else {
						return _('Cannot update the dependent item prototype "%1$s" with reference to the master item prototype "%2$s" on the host "%3$s": %4$s.');
					}
				}
			}
			else {
				if ($master_flags == ZBX_FLAG_DISCOVERY_NORMAL) {
					if ($is_template) {
						return _('Cannot create the dependent item prototype "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
					}
					else {
						return _('Cannot create the dependent item prototype "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
					}
				}
				else {
					if ($is_template) {
						return _('Cannot create the dependent item prototype "%1$s" with reference to the master item prototype "%2$s" on the template "%3$s": %4$s.');
					}
					else {
						return _('Cannot create the dependent item prototype "%1$s" with reference to the master item prototype "%2$s" on the host "%3$s": %4$s.');
					}
				}
			}
		}
		elseif ($flags == ZBX_FLAG_DISCOVERY_RULE) {
			if ($is_update) {
				if ($is_template) {
					return _('Cannot update the dependent LLD rule "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
				}
				else {
					return _('Cannot update the dependent LLD rule "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
				}
			}
			else {
				if ($is_template) {
					return _('Cannot create the dependent LLD rule "%1$s" with reference to the master item "%2$s" on the template "%3$s": %4$s.');
				}
				else {
					return _('Cannot create the dependent LLD rule "%1$s" with reference to the master item "%2$s" on the host "%3$s": %4$s.');
				}
			}
		}
	}

	/**
	 * Converts headers field text to hash with header name as key.
	 *
	 * @param string $headers  Headers string, one header per line, line delimiter "\r\n".
	 *
	 * @return array
	 */
	protected static function headersStringToArray(string $headers): array {
		$result = [];

		foreach (explode("\r\n", $headers) as $header) {
			$header = explode(': ', $header, 2);

			if (count($header) == 2) {
				$result[$header[0]] = $header[1];
			}
		}

		return $result;
	}

	/**
	 * Converts headers fields hash to string.
	 *
	 * @param array $headers  Array of headers where key is header name.
	 *
	 * @return string
	 */
	protected static function headersArrayToString(array $headers): string {
		$result = [];

		foreach ($headers as $k => $v) {
			$result[] = $k.': '.$v;
		}

		return implode("\r\n", $result);
	}

	/**
	 * Remove NCLOB value type fields from resulting query SELECT part if DISTINCT will be used.
	 *
	 * @param string $table_name     Table name.
	 * @param string $table_alias    Table alias value.
	 * @param array  $options        Array of query options.
	 * @param array  $sql_parts      Array of query parts already initialized from $options.
	 *
	 * @return array    The resulting SQL parts array.
	 */
	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts) {
		if (!$options['countOutput'] && self::dbDistinct($sql_parts)) {
			$schema = $this->getTableSchema();
			$nclob_fields = [];

			foreach ($schema['fields'] as $field_name => $field) {
				if ($field['type'] == DB::FIELD_TYPE_NCLOB
						&& $this->outputIsRequested($field_name, $options['output'])) {
					$nclob_fields[] = $field_name;
				}
			}

			if ($nclob_fields) {
				$output = ($options['output'] === API_OUTPUT_EXTEND)
					? array_keys($schema['fields'])
					: $options['output'];

				$options['output'] = array_diff($output, $nclob_fields);
			}
		}

		return parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);
	}

	/**
	 * Add NCLOB type fields if there was DISTINCT in query.
	 *
	 * @param array $options    Array of query options.
	 * @param array $result     Query results.
	 *
	 * @return array    The result array with added NCLOB fields.
	 */
	protected function addNclobFieldValues(array $options, array $result): array {
		$schema = $this->getTableSchema();
		$nclob_fields = [];

		foreach ($schema['fields'] as $field_name => $field) {
			if ($field['type'] == DB::FIELD_TYPE_NCLOB && $this->outputIsRequested($field_name, $options['output'])) {
				$nclob_fields[] = $field_name;
			}
		}

		if (!$nclob_fields) {
			return $result;
		}

		$pk = $schema['key'];
		$options = [
			'output' => $nclob_fields,
			'filter' => [$pk => array_keys($result)]
		];

		$db_items = DBselect(DB::makeSql($this->tableName, $options));

		while ($db_item = DBfetch($db_items)) {
			$result[$db_item[$pk]] += $db_item;
		}

		return $result;
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
	 * Normalize preprocessing step parameters.
	 *
	 * @param array  $preprocessing                   Preprocessing steps.
	 * @param string $preprocessing[<num>]['params']  Preprocessing step parameters.
	 * @param int    $preprocessing[<num>]['type']    Preprocessing step type.
	 *
	 * @return array
	 */
	protected static function normalizeItemPreprocessingSteps(array $preprocessing): array {
		foreach ($preprocessing as &$step) {
			if (!array_key_exists('params', $step)) {
				continue;
			}

			$step['params'] = str_replace("\r\n", "\n", $step['params']);
			$params = explode("\n", $step['params']);

			switch ($step['type']) {
				case ZBX_PREPROC_PROMETHEUS_PATTERN:
					if (!array_key_exists(2, $params)) {
						$params[2] = '';
					}
					break;
			}

			$step['params'] = implode("\n", $params);
		}
		unset($step);

		return $preprocessing;
	}

	/**
	 * Add the specific fields of item types with its values into $db_items.
	 * In case when item type is changed, fields of existing type and new type are added.
	 *
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addDbFieldsByType(array $items, array &$db_items): void {
		$types = [];
		$item_indexes = [];
		$only_template_items = true;

		foreach ($items as $i => $item) {
			$db_item = $db_items[$item['itemid']];

			$types += [$db_item['type'] => true];

			if ($item['type'] != $db_item['type']) {
				$types += [$item['type'] => true];
				$item_indexes[$item['itemid']] = $i;
			}

			if ($db_item['host_status'] != HOST_STATUS_TEMPLATE) {
				$only_template_items = false;
			}
		}

		$output = array_flip(['itemid', 'key_']);
		$type_field_names = [];

		foreach ($types as $type => $foo) {
			$field_names = array_flip(CItemTypeFactory::getObject($type)::FIELD_NAMES);

			if ($only_template_items && array_key_exists('interfaceid', $field_names)) {
				unset($field_names['interfaceid']);
			}

			if ($type == ITEM_TYPE_SCRIPT) {
				unset($field_names['parameters']);
			}

			$output += $field_names;
			$type_field_names[$type] = $field_names;
		}

		$options = [
			'output' => array_keys($output),
			'itemids' => array_keys($db_items)
		];
		$_db_items = DBselect(DB::makeSql('items', $options));

		while ($_db_item = DBfetch($_db_items)) {
			$item = array_key_exists($_db_item['itemid'], $item_indexes)
				? $items[$item_indexes[$_db_item['itemid']]]
				: null;
			$field_names = ($item !== null) ? $type_field_names[$item['type']] : [];

			$db_item = $db_items[$_db_item['itemid']];
			$field_names += $type_field_names[$db_item['type']];

			if ($db_item['host_status'] == HOST_STATUS_TEMPLATE && array_key_exists('interfaceid', $field_names)) {
				unset($field_names['interfaceid']);
			}

			$db_items[$_db_item['itemid']] += array_intersect_key($_db_item, $field_names);
		}
	}

	/**
	 * Note: instances may override this to add e.g. tags.
	 *
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedObjects(array $items, array &$db_items): void {
		self::addAffectedParameters($items, $db_items);
		self::addAffectedPreprocessing($items, $db_items);
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedParameters(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			$db_type = $db_items[$item['itemid']]['type'];

			if (array_key_exists('parameters', $item) || ($item['type'] != $db_type && $db_type == ITEM_TYPE_SCRIPT)) {
				$itemids[] = $item['itemid'];
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
			'filter' => ['itemid' => $itemids]
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
}
