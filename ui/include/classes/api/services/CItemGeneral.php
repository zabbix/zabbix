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
	 * @var array
	 */
	protected $field_rules;

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

		// template - if templated item, value is taken from template item, cannot be changed on host
		// system - values should not be updated
		// host - value should be null for template items
		$this->field_rules = [
			'type'					=> ['template' => 1],
			'snmp_oid'				=> ['template' => 1],
			'hostid'				=> [],
			'name'					=> ['template' => 1],
			'description'			=> [],
			'key_'					=> ['template' => 1],
			'master_itemid'			=> ['template' => 1],
			'delay'					=> [],
			'history'				=> [],
			'trends'				=> [],
			'status'				=> [],
			'discover'				=> [],
			'value_type'			=> ['template' => 1],
			'trapper_hosts'			=> [],
			'units'					=> ['template' => 1],
			'formula'				=> ['template' => 1],
			'error'					=> ['system' => 1],
			'lastlogsize'			=> ['system' => 1],
			'logtimefmt'			=> [],
			'templateid'			=> ['system' => 1],
			'valuemapid'			=> ['template' => 1],
			'params'				=> [],
			'ipmi_sensor'			=> ['template' => 1],
			'authtype'				=> [],
			'username'				=> [],
			'password'				=> [],
			'publickey'				=> [],
			'privatekey'			=> [],
			'mtime'					=> ['system' => 1],
			'flags'					=> [],
			'filter'				=> [],
			'interfaceid'			=> ['host' => 1],
			'inventory_link'		=> [],
			'lifetime'				=> [],
			'preprocessing'			=> ['template' => 1],
			'overrides'				=> ['template' => 1],
			'jmx_endpoint'			=> [],
			'url'					=> ['template' => 1],
			'timeout'				=> ['template' => 1],
			'query_fields'			=> ['template' => 1],
			'parameters'			=> ['template' => 1],
			'posts'					=> ['template' => 1],
			'status_codes'			=> ['template' => 1],
			'follow_redirects'		=> ['template' => 1],
			'post_type'				=> ['template' => 1],
			'http_proxy'			=> ['template' => 1],
			'headers'				=> ['template' => 1],
			'retrieve_mode'			=> ['template' => 1],
			'request_method'		=> ['template' => 1],
			'output_format'			=> ['template' => 1],
			'allow_traps'			=> [],
			'ssl_cert_file'			=> ['template' => 1],
			'ssl_key_file'			=> ['template' => 1],
			'ssl_key_password'		=> ['template' => 1],
			'verify_peer'			=> ['template' => 1],
			'verify_host'			=> ['template' => 1]
		];

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_NO_INTERFACE => _('Cannot find host interface on "%1$s" for item key "%2$s".')
		]);
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected function validateCreate(array &$items): void {
		self::checkItemKey($items);

		$hostids = array_unique(array_column($items, 'hostid'));

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => $hostids,
			'templated_hosts' => true,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($hostids) != count($db_hosts)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkAndAddUuid($items, $db_hosts);
		self::checkDuplicates($items);
		self::checkHostInterface($items, $db_hosts);
		$this->checkSpecificFields($items);
		$this->validatePreprocessing($items);
		$this->validateDependentItems($items);
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
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$items, ?array &$db_items): void {
		static::addAffectedObjects($items, $db_items);

		self::checkItemKey($items, $db_items);

		$hostids = array_unique(array_column($items, 'hostid'));

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => $hostids,
			'templated_hosts' => true,
			'editable' => true,
			'preservekeys' => true
		]);

		self::checkDuplicates($items, $db_items);
		self::checkHostInterface($items, $db_hosts, $db_items);
		$this->checkSpecificFields($items);
		$this->validatePreprocessing($items);
		$this->validateDependentItems($items);
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
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkItemKey(array $items, array $db_items = null): void {
		$item_key_parser = new CItemKey();

		foreach ($items as $i => $item) {
			if ($db_items !== null && $item['type'] == $db_items[$item['itemid']]['type']
					&& $item['key_'] === $db_items[$item['itemid']]['key_']) {
				continue;
			}

			if ($item_key_parser->parse($item['key_']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/key_',
					$item_key_parser->getError()
				));
			}

			if ($item['type'] == ITEM_TYPE_SNMPTRAP && $item['key_'] !== 'snmptrap.fallback'
					&& $item_key_parser->getKey() !== 'snmptrap') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/key_',
					_('invalid SNMP trap key')
				));
			}
		}
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
	 * Check that only items on templates have UUID. Add UUID to all host prototypes on templates,
	 *   if it doesn't exist.
	 *
	 * @param array $items
	 * @param array $db_hosts
	 *
	 * @throws APIException
	 */
	protected static function checkAndAddUuid(array &$items, array $db_hosts): void {
		foreach ($items as $i => &$item) {
			if ($db_hosts[$item['hostid']]['status'] != HOST_STATUS_TEMPLATE && array_key_exists('uuid', $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			if ($db_hosts[$item['hostid']]['status'] == HOST_STATUS_TEMPLATE && !array_key_exists('uuid', $item)) {
				$item['uuid'] = generateUuidV4();
			}
		}
		unset($item);

		$db_uuid = DB::select('items', [
			'output' => ['uuid'],
			'filter' => ['uuid' => array_column($items, 'uuid')],
			'limit' => 1
		]);

		if ($db_uuid) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $db_uuid[0]['uuid'])
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
	 * @param array      $tpl_items  An array of items to inherit.
	 * @param array|null $hostids    An array of hosts to inherit to; if set to null, the items will be inherited to all
	 *                               linked hosts or templates.
	 */
	protected function inherit(array $tpl_items, array $hostids = null) {
		$tpl_items = zbx_toHash($tpl_items, 'itemid');

		// Inherit starting from common items and finishing up dependent.
		while ($tpl_items) {
			$_tpl_items = [];

			foreach ($tpl_items as $tpl_item) {
				if ($tpl_item['type'] != ITEM_TYPE_DEPENDENT
						|| !array_key_exists($tpl_item['master_itemid'], $tpl_items)) {
					$_tpl_items[$tpl_item['itemid']] = $tpl_item;
				}
			}

			foreach ($_tpl_items as $itemid => $_tpl_item) {
				unset($tpl_items[$itemid]);
			}

			$this->_inherit($_tpl_items, $hostids);
		}
	}

	/**
	 * Auxiliary method for item inheritance. See full description in inherit() method.
	 */
	private function _inherit(array $tpl_items, array $hostids = null) {
		// Prepare the child items.
		$new_items = $this->prepareInheritedItems($tpl_items, $hostids);
		if (!$new_items) {
			return;
		}

		$ins_items = [];
		$upd_items = [];

		foreach ($new_items as $new_item) {
			if (array_key_exists('itemid', $new_item)) {
				if ($this instanceof CItemPrototype) {
					unset($new_item['ruleid']);
				}
				$upd_items[$new_item['itemid']] = $new_item;
			}
			else {
				$ins_items[] = $new_item;
			}
		}

		$this->validateDependentItems($new_items);

		// Save the new items.
		if ($ins_items) {
			if ($this instanceof CItem) {
				static::validateInventoryLinks($ins_items);
			}

			$this->createForce($ins_items);
		}

		if ($upd_items) {
			if ($this instanceof CItem) {
				static::validateInventoryLinks($upd_items, true);
			}

			$db_items = $this->getDbObjects($upd_items);

			$this->updateForce($upd_items, $db_items);
		}

		$new_items = array_merge($upd_items, $ins_items);

		// Inheriting items from the templates.
		$db_items = DBselect(
			'SELECT i.itemid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', zbx_objectValues($new_items, 'itemid')).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		$tpl_itemids = [];
		while ($db_item = DBfetch($db_items)) {
			$tpl_itemids[$db_item['itemid']] = true;
		}

		foreach ($new_items as $index => $new_item) {
			if (!array_key_exists($new_item['itemid'], $tpl_itemids)) {
				unset($new_items[$index]);
			}
		}

		$this->inherit($new_items);
	}

	/**
	 * Prepares and returns an array of child items, inherited from items $tpl_items on the given hosts.
	 *
	 * @param array      $tpl_items
	 * @param string     $tpl_items[<itemid>]['itemid']
	 * @param string     $tpl_items[<itemid>]['hostid']
	 * @param string     $tpl_items[<itemid>]['key_']
	 * @param int        $tpl_items[<itemid>]['type']
	 * @param array      $tpl_items[<itemid>]['preprocessing']                    (optional)
	 * @param int        $tpl_items[<itemid>]['preprocessing'][]['type']
	 * @param string     $tpl_items[<itemid>]['preprocessing'][]['params']
	 * @param int        $tpl_items[<itemid>]['flags']
	 * @param string     $tpl_items[<itemid>]['master_itemid']                    (optional)
	 * @param mixed      $tpl_items[<itemid>][<field_name>]                       (optional)
	 * @param array|null $hostids
	 *
	 * @throws APIException
	 *
	 * @return array an array of unsaved child items
	 */
	private function prepareInheritedItems(array $tpl_items, array $hostids = null): array {
		$itemids_by_templateid = [];
		foreach ($tpl_items as $tpl_item) {
			$itemids_by_templateid[$tpl_item['hostid']][] = $tpl_item['itemid'];
		}

		// Fetch all child hosts.
		$chd_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'selectParentTemplates' => ['templateid'],
			'selectInterfaces' => ['interfaceid', 'main', 'type'],
			'templateids' => array_keys($itemids_by_templateid),
			'hostids' => $hostids,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		]);
		if (!$chd_hosts) {
			return [];
		}

		$chd_items_tpl = [];
		$chd_items_key = [];

		// Preparing list of items by item templateid.
		$sql = 'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid'.
			' FROM items i'.
			' WHERE '.dbConditionInt('i.templateid', zbx_objectValues($tpl_items, 'itemid'));
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionInt('i.hostid', $hostids);
		}
		$db_items = DBselect($sql);

		while ($db_item = DBfetch($db_items)) {
			$hostid = $db_item['hostid'];
			unset($db_item['hostid']);

			$chd_items_tpl[$hostid][$db_item['templateid']] = $db_item;
		}

		$hostids_by_key = [];

		// Preparing list of items by item key.
		foreach ($chd_hosts as $chd_host) {
			$tpl_itemids = [];

			foreach ($chd_host['parentTemplates'] as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
					$tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
				}
			}

			foreach ($tpl_itemids as $tpl_itemid) {
				if (!array_key_exists($chd_host['hostid'], $chd_items_tpl)
						|| !array_key_exists($tpl_itemid, $chd_items_tpl[$chd_host['hostid']])) {
					$hostids_by_key[$tpl_items[$tpl_itemid]['key_']][] = $chd_host['hostid'];
				}
			}
		}

		foreach ($hostids_by_key as $key_ => $key_hostids) {
			$sql_select = ($this instanceof CItemPrototype) ? ',id.parent_itemid AS ruleid' : '';
			// "LEFT JOIN" is needed to check flags on inherited and existing item, item prototype or lld rule.
			// For example, when linking an item prototype with same key as in an item on target host or template.
			$sql_join = ($this instanceof CItemPrototype) ? ' LEFT JOIN item_discovery id ON i.itemid=id.itemid' : '';
			$db_items = DBselect(
				'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid'.$sql_select.
					' FROM items i'.$sql_join.
					' WHERE '.dbConditionInt('i.hostid', $key_hostids).
						' AND '.dbConditionString('i.key_', [$key_])
			);

			while ($db_item = DBfetch($db_items)) {
				$hostid = $db_item['hostid'];
				unset($db_item['hostid']);

				$chd_items_key[$hostid][$db_item['key_']] = $db_item;
			}
		}

		// List of the discovery rules.
		if ($this instanceof CItemPrototype) {
			// List of itemids without 'ruleid' property.
			$tpl_itemids = [];
			$tpl_ruleids = [];
			foreach ($tpl_items as $tpl_item) {
				if (!array_key_exists('ruleid', $tpl_item)) {
					$tpl_itemids[] = $tpl_item['itemid'];
				}
				else {
					$tpl_ruleids[$tpl_item['ruleid']] = true;
				}
			}

			if ($tpl_itemids) {
				$db_rules = DBselect(
					'SELECT id.parent_itemid,id.itemid'.
						' FROM item_discovery id'.
						' WHERE '.dbConditionInt('id.itemid', $tpl_itemids)
				);

				while ($db_rule = DBfetch($db_rules)) {
					$tpl_items[$db_rule['itemid']]['ruleid'] = $db_rule['parent_itemid'];
					$tpl_ruleids[$db_rule['parent_itemid']] = true;
				}
			}

			$sql = 'SELECT i.hostid,i.templateid,i.itemid'.
					' FROM items i'.
					' WHERE '.dbConditionInt('i.templateid', array_keys($tpl_ruleids));
			if ($hostids !== null) {
				$sql .= ' AND '.dbConditionInt('i.hostid', $hostids);
			}
			$db_rules = DBselect($sql);

			// List of child lld ruleids by child hostid and parent lld ruleid.
			$chd_ruleids = [];
			while ($db_rule = DBfetch($db_rules)) {
				$chd_ruleids[$db_rule['hostid']][$db_rule['templateid']] = $db_rule['itemid'];
			}
		}

		$new_items = [];
		// List of the updated item keys by hostid.
		$upd_hostids_by_key = [];

		foreach ($chd_hosts as $chd_host) {
			$tpl_itemids = [];

			foreach ($chd_host['parentTemplates'] as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
					$tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
				}
			}

			foreach ($tpl_itemids as $tpl_itemid) {
				$tpl_item = $tpl_items[$tpl_itemid];

				$chd_item = null;

				// Update by templateid.
				if (array_key_exists($chd_host['hostid'], $chd_items_tpl)
						&& array_key_exists($tpl_item['itemid'], $chd_items_tpl[$chd_host['hostid']])) {
					$chd_item = $chd_items_tpl[$chd_host['hostid']][$tpl_item['itemid']];

					if ($tpl_item['key_'] !== $chd_item['key_']) {
						$upd_hostids_by_key[$tpl_item['key_']][] = $chd_host['hostid'];
					}
				}
				// Update by key.
				elseif (array_key_exists($chd_host['hostid'], $chd_items_key)
						&& array_key_exists($tpl_item['key_'], $chd_items_key[$chd_host['hostid']])) {
					$chd_item = $chd_items_key[$chd_host['hostid']][$tpl_item['key_']];

					// Check if an item of a different type with the same key exists.
					if ($tpl_item['flags'] != $chd_item['flags']) {
						$this->errorInheritFlags($chd_item['flags'], $chd_item['key_'], $chd_host['host']);
					}

					// Check if item already linked to another template.
					if ($chd_item['templateid'] != 0 && bccomp($chd_item['templateid'], $tpl_item['itemid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _params(
							$this->getErrorMsg(self::ERROR_EXISTS_TEMPLATE), [$tpl_item['key_'], $chd_host['host']]
						));
					}

					if ($this instanceof CItemPrototype) {
						$chd_ruleid = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
						if (bccomp($chd_item['ruleid'], $chd_ruleid) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Item prototype "%1$s" already exists on "%2$s", linked to another rule.',
									$chd_item['key_'], $chd_host['host']
								)
							);
						}
					}
				}

				// copying item
				$new_item = $tpl_item;
				$new_item['uuid'] = '';

				if ($chd_item !== null) {
					$new_item['itemid'] = $chd_item['itemid'];
				}
				else {
					unset($new_item['itemid']);
					if ($this instanceof CItemPrototype) {
						$new_item['ruleid'] = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
					}
				}
				$new_item['hostid'] = $chd_host['hostid'];
				$new_item['templateid'] = $tpl_item['itemid'];

				if ($chd_host['status'] != HOST_STATUS_TEMPLATE) {
					if ($chd_item === null || $new_item['type'] != $chd_item['type']) {
						$interface = self::findInterfaceForItem($new_item['type'], $chd_host['interfaces']);

						if ($interface) {
							$new_item['interfaceid'] = $interface['interfaceid'];
						}
						elseif ($interface !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _params(
								$this->getErrorMsg(self::ERROR_NO_INTERFACE), [$chd_host['host'], $new_item['key_']]
							));
						}
					}

					if ($this instanceof CItem || $this instanceof CDiscoveryRule) {
						if (!array_key_exists('itemid', $new_item)) {
							$new_item['rtdata'] = true;
						}
					}
				}

				if (array_key_exists('preprocessing', $new_item)) {
					foreach ($new_item['preprocessing'] as $preprocessing) {
						if ($chd_item) {
							$preprocessing['itemid'] = $chd_item['itemid'];
						}
						else {
							unset($preprocessing['itemid']);
						}
					}
				}

				$new_items[] = $new_item;
			}
		}

		// Check if item with a new key already exists on the child host.
		if ($upd_hostids_by_key) {
			$sql_where = [];
			foreach ($upd_hostids_by_key as $key => $hostids) {
				$sql_where[] = dbConditionInt('i.hostid', $hostids).' AND i.key_='.zbx_dbstr($key);
			}

			$sql = 'SELECT i.hostid,i.key_'.
				' FROM items i'.
				' WHERE ('.implode(') OR (', $sql_where).')';
			$db_items = DBselect($sql, 1);

			if ($db_item = DBfetch($db_items)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _params($this->getErrorMsg(self::ERROR_EXISTS),
					[$db_item['key_'], $chd_hosts[$db_item['hostid']]['host']]
				));
			}
		}

		return $this->prepareDependentItems($tpl_items, $new_items, $hostids);
	}

	/**
	 * Update relations for inherited dependent items to master items.
	 *
	 * @param array      $tpl_items
	 * @param int        $tpl_items[<itemid>]['type']
	 * @param string     $tpl_items[<itemid>]['master_itemid']
	 * @param array      $new_items
	 * @param string     $new_items[<itemid>]['hostid']
	 * @param int        $new_items[<itemid>]['type']
	 * @param string     $new_items[<itemid>]['templateid']
	 * @param array|null $hostids
	 *
	 * @return array an array of synchronized inherited items.
	 */
	private function prepareDependentItems(array $tpl_items, array $new_items, array $hostids = null) {
		$tpl_master_itemids = [];

		foreach ($tpl_items as $tpl_item) {
			if ($tpl_item['type'] == ITEM_TYPE_DEPENDENT) {
				$tpl_master_itemids[$tpl_item['master_itemid']] = true;
			}
		}

		if ($tpl_master_itemids) {
			$sql = 'SELECT i.itemid,i.hostid,i.templateid'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.templateid', array_keys($tpl_master_itemids));
			if ($hostids !== null) {
				$sql .= ' AND '.dbConditionId('i.hostid', $hostids);
			}
			$db_items = DBselect($sql);

			$master_links = [];

			while ($db_item = DBfetch($db_items)) {
				$master_links[$db_item['templateid']][$db_item['hostid']] = $db_item['itemid'];
			}

			foreach ($new_items as &$new_item) {
				if ($new_item['type'] == ITEM_TYPE_DEPENDENT) {
					$tpl_item = $tpl_items[$new_item['templateid']];

					if (array_key_exists('master_itemid', $tpl_item)) {
						$new_item['master_itemid'] = $master_links[$tpl_item['master_itemid']][$new_item['hostid']];
					}
				}
			}
			unset($new_item);
		}

		return $new_items;
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
	 * @param array      $items
	 * @param array|null $db_items
	 */
	protected static function updateParameters(array &$items, array $db_items = null): void {
		$ins_item_parameters = [];
		$upd_item_parameters = [];
		$del_item_parameterids = [];

		foreach ($items as &$item) {
			if (!array_key_exists('parameters', $item)) {
				continue;
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
	 * Check if any item from list already exists.
	 * If items have item ids it will check for existing item with different itemid.
	 *
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkDuplicates(array $items, array $db_items = null): void {
		$itemKeysByHostId = [];
		$itemIds = [];
		foreach ($items as $item) {
			if ($db_items !== null && $item['key_'] === $db_items[$item['itemid']]['key_']) {
				continue;
			}

			if (!isset($itemKeysByHostId[$item['hostid']])) {
				$itemKeysByHostId[$item['hostid']] = [];
			}
			$itemKeysByHostId[$item['hostid']][] = $item['key_'];

			if (isset($item['itemid'])) {
				$itemIds[] = $item['itemid'];
			}
		}

		$sqlWhere = [];
		foreach ($itemKeysByHostId as $hostId => $keys) {
			$sqlWhere[] = '(i.hostid='.zbx_dbstr($hostId).' AND '.dbConditionString('i.key_', $keys).')';
		}

		if ($sqlWhere) {
			$sql = 'SELECT i.key_,h.host'.
					' FROM items i,hosts h'.
					' WHERE i.hostid=h.hostid AND ('.implode(' OR ', $sqlWhere).')';

			// if we update existing items we need to exclude them from result.
			if ($itemIds) {
				$sql .= ' AND '.dbConditionInt('i.itemid', $itemIds, true);
			}
			$dbItems = DBselect($sql, 1);
			while ($dbItem = DBfetch($dbItems)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item with key "%1$s" already exists on "%2$s".', $dbItem['key_'], $dbItem['host']));
			}
		}
	}

	/**
	 * @param array      $items
	 * @param array      $db_hosts
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkHostInterface(array $items, array $db_hosts, array $db_items = null): void {
		$db_interfaces = DBfetchArrayAssoc(DBselect(
			'SELECT i.interfaceid,i.hostid,i.type'.
				' FROM interface i'.
				' WHERE '.dbConditionInt('i.hostid', array_column($db_hosts, 'hostid'))
		), 'interfaceid');

		$item_types_with_interfaces = [
			ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
			ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_SNMP
		];

		foreach ($items as $i => $item) {
			if ($db_items !== null && !array_key_exists('interfaceid', $item)
					&& ($item['type'] == $db_items[$item['itemid']]['type']
						|| in_array($item['type'], $item_types_with_interfaces))) {
				continue;
			}

			$host = $db_hosts[$item['hostid']];

			if ($host['status'] == HOST_STATUS_TEMPLATE) {
				if (array_key_exists('interfaceid', $item)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('unexpected parameter "%1$s"', 'interfaceid')
					));
				}
			}
			else {
				$interface_type = itemTypeInterface($item['type']);

				if ($interface_type !== false) {
					if (!array_key_exists('interfaceid', $item) || !$item['interfaceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No interface found.'));
					}
					elseif (!array_key_exists($item['interfaceid'], $db_interfaces)
							|| bccomp($db_interfaces[$item['interfaceid']]['hostid'], $item['hostid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses host interface from non-parent host.'));
					}
					elseif ($interface_type !== INTERFACE_TYPE_ANY
							&& $db_interfaces[$item['interfaceid']]['type'] != $interface_type) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses incorrect interface type.'));
					}
				}
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
	 * Validate items with type ITEM_TYPE_DEPENDENT for create or update operation.
	 *
	 * @param array  $items
	 * @param string $items[]['itemid']         (mandatory for updated items and item prototypes)
	 * @param string $items[]['hostid']
	 * @param int    $items[]['type']
	 * @param string $items[]['master_itemid']  (mandatory for ITEM_TYPE_DEPENDENT)
	 * @param int    $items[]['flags']          (mandatory for items)
	 *
	 * @throws APIException for invalid data.
	 */
	protected function validateDependentItems(array $items) {
		$dep_items = [];
		$upd_itemids = [];

		foreach ($items as $item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if ($this instanceof CDiscoveryRule || $this instanceof CItemPrototype
						|| (array_key_exists('flags', $item) && $item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)) {
					$dep_items[] = $item;
				}

				if (array_key_exists('itemid', $item)) {
					$upd_itemids[] = $item['itemid'];
				}
			}
		}

		if (!$dep_items) {
			return;
		}

		if ($this instanceof CItemPrototype && $upd_itemids) {
			$db_links = DBselect(
				'SELECT id.itemid,id.parent_itemid AS ruleid'.
				' FROM item_discovery id'.
				' WHERE '.dbConditionId('id.itemid', $upd_itemids)
			);

			$links = [];

			while ($db_link = DBfetch($db_links)) {
				$links[$db_link['itemid']] = $db_link['ruleid'];
			}

			foreach ($dep_items as &$dep_item) {
				if (array_key_exists('itemid', $dep_item)) {
					$dep_item['ruleid'] = $links[$dep_item['itemid']];
				}
			}
			unset($dep_item);
		}

		$master_itemids = [];

		foreach ($dep_items as $dep_item) {
			$master_itemids[$dep_item['master_itemid']] = true;
		}

		$master_items = [];

		// Fill relations array by master items (item prototypes). Discovery rule should not be master item.
		do {
			if ($this instanceof CItemPrototype) {
				$db_master_items = DBselect(
					'SELECT i.itemid,i.hostid,i.master_itemid,i.flags,id.parent_itemid AS ruleid'.
					' FROM items i'.
						' LEFT JOIN item_discovery id'.
							' ON i.itemid=id.itemid'.
					' WHERE '.dbConditionId('i.itemid', array_keys($master_itemids)).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE])
				);
			}
			// CDiscoveryRule, CItem
			else {
				$db_master_items = DBselect(
					'SELECT i.itemid,i.hostid,i.master_itemid'.
					' FROM items i'.
					' WHERE '.dbConditionId('i.itemid', array_keys($master_itemids)).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL])
				);
			}

			while ($db_master_item = DBfetch($db_master_items)) {
				$master_items[$db_master_item['itemid']] = $db_master_item;

				unset($master_itemids[$db_master_item['itemid']]);
			}

			if ($master_itemids) {
				reset($master_itemids);

				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
						_s('Item "%1$s" does not exist or you have no access to this item', key($master_itemids))
					)
				);
			}

			$master_itemids = [];

			foreach ($master_items as $master_item) {
				if ($master_item['master_itemid'] != 0
						&& !array_key_exists($master_item['master_itemid'], $master_items)) {
					$master_itemids[$master_item['master_itemid']] = true;
				}
			}
		} while ($master_itemids);

		foreach ($dep_items as $dep_item) {
			$master_item = $master_items[$dep_item['master_itemid']];

			if ($dep_item['hostid'] != $master_item['hostid']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('"hostid" of dependent item and master item should match')
				));
			}

			if ($this instanceof CItemPrototype && $master_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
					&& $dep_item['ruleid'] != $master_item['ruleid']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('"ruleid" of dependent item and master item should match')
				));
			}

			if (array_key_exists('itemid', $dep_item)) {
				$master_itemid = $dep_item['master_itemid'];

				while ($master_itemid != 0) {
					if ($master_itemid == $dep_item['itemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('circular item dependency is not allowed')
						));
					}

					$master_itemid = $master_items[$master_itemid]['master_itemid'];
				}
			}
		}

		// Fill relations array by dependent items (item prototypes).
		$root_itemids = [];

		foreach ($master_items as $master_item) {
			if ($master_item['master_itemid'] == 0) {
				$root_itemids[] = $master_item['itemid'];
			}
		}

		$dependent_items = [];

		foreach ($dep_items as $dep_item) {
			if (array_key_exists('itemid', $dep_item)) {
				$dependent_items[$dep_item['master_itemid']][] = $dep_item['itemid'];
			}
		}

		$master_itemids = $root_itemids;

		do {
			$sql = 'SELECT i.master_itemid,i.itemid'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.master_itemid', $master_itemids);
			if ($upd_itemids) {
				$sql .= ' AND '.dbConditionId('i.itemid', $upd_itemids, true); // Exclude updated items.
			}

			$db_items = DBselect($sql);

			while ($db_item = DBfetch($db_items)) {
				$dependent_items[$db_item['master_itemid']][] = $db_item['itemid'];
			}

			$_master_itemids = $master_itemids;
			$master_itemids = [];

			foreach ($_master_itemids as $master_itemid) {
				if (array_key_exists($master_itemid, $dependent_items)) {
					$master_itemids = array_merge($master_itemids, $dependent_items[$master_itemid]);
				}
			}
		} while ($master_itemids);

		foreach ($dep_items as $dep_item) {
			if (!array_key_exists('itemid', $dep_item)) {
				$dependent_items[$dep_item['master_itemid']][] = false;
			}
		}

		foreach ($root_itemids as $root_itemid) {
			self::checkDependencyDepth($dependent_items, $root_itemid);
		}
	}

	/**
	 * Validate depth and amount of elements in the tree of the dependent items.
	 *
	 * @param array  $dependent_items
	 * @param string $dependent_items[<master_itemid>][]  List if the dependent item IDs ("false" for new items)
	 *                                                    by master_itemid.
	 * @param string $root_itemid                         ID of the item being checked.
	 * @param int    $level                               Current dependency level.
	 *
	 * @throws APIException for invalid data.
	 */
	private static function checkDependencyDepth(array $dependent_items, $root_itemid, $level = 0) {
		$count = 0;

		if (array_key_exists($root_itemid, $dependent_items)) {
			if (++$level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('maximum number of dependency levels reached')
				));
			}

			foreach ($dependent_items[$root_itemid] as $master_itemid) {
				$count++;

				if ($master_itemid !== false) {
					$count += self::checkDependencyDepth($dependent_items, $master_itemid, $level);
				}
			}

			if ($count > ZBX_DEPENDENT_ITEM_MAX_COUNT) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('maximum dependent items count reached')
				));
			}
		}

		return $count;
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
	 * @param array $items
	 *
	 * @throws APIException
	 */
	protected static function checkValueMaps(array $items): void {
		$valuemapids_by_hostid = [];

		foreach ($items as $item) {
			if (array_key_exists('valuemapid', $item) && $item['valuemapid'] != 0) {
				$valuemapids_by_hostid[$item['hostid']][$item['valuemapid']] = true;
			}
		}

		$sql_where = [];
		foreach ($valuemapids_by_hostid as $hostid => $valuemapids) {
			$sql_where[] = '(vm.hostid='.zbx_dbstr($hostid).' AND '.
				dbConditionId('vm.valuemapid', array_keys($valuemapids)).')';
		}

		if ($sql_where) {
			$result = DBselect(
				'SELECT vm.valuemapid,vm.hostid'.
				' FROM valuemap vm'.
				' WHERE '.implode(' OR ', $sql_where)
			);
			while ($row = DBfetch($result)) {
				unset($valuemapids_by_hostid[$row['hostid']][$row['valuemapid']]);

				if (!$valuemapids_by_hostid[$row['hostid']]) {
					unset($valuemapids_by_hostid[$row['hostid']]);
				}
			}

			if ($valuemapids_by_hostid) {
				$hostid = key($valuemapids_by_hostid);
				$valuemapid = key($valuemapids_by_hostid[$hostid]);

				$host_row = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.zbx_dbstr($hostid)));
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Valuemap with ID "%1$s" is not available on "%2$s".',
					$valuemapid, $host_row['host']
				));
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
	 * @param array $items
	 *
	 * @return array
	 */
	protected function getDbObjects(array $items): array {
		$output = ['itemid', 'templateid'];

		foreach ($this->field_rules as $field => $rules) {
			if (!array_key_exists('system', $rules)) {
				$output[] = $field;
			}
		}

		$options = [
			'output' => $output,
			'itemids' => array_column($items, 'itemid'),
			'editable' => true,
			'nopermissions' => true,
			'preservekeys' => true
		];

		if ($this instanceof CItemPrototype) {
			$options['selectDiscoveryRule'] = ['itemid'];
		}

		$db_items = $this->get($options);

		foreach ($db_items as &$db_item) {
			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$db_item['ruleid'] = $db_item['discoveryRule']['itemid'];
				unset($db_item['discoveryRule']);
			}

			// Fix for audit log, because field type in object is different from field type in DB object.
			$db_item['query_fields'] = $db_item['query_fields'] ? json_encode($db_item['query_fields']) : '';
			$db_item['headers'] = self::headersArrayToString($db_item['headers']);
		}
		unset($db_item);

		return $db_items;
	}

	/**
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
			if (array_key_exists('parameters', $item)) {
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
