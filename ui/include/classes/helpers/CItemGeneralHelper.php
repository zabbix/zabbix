<?php declare(strict_types = 0);
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


class CItemGeneralHelper {

	/**
	 * Get item fields default values.
	 */
	public static function getDefaults(): array {
		// Default script value for browser type items.
		$browser_script = <<<'JAVASCRIPT'
var browser = new Browser(Browser.chromeOptions());

try {
	browser.navigate("https://example.com");
	browser.collectPerfEntries();
}
finally {
	return JSON.stringify(browser.getResult());
}
JAVASCRIPT;

		return [
			'allow_traps' => DB::getDefault('items', 'allow_traps'),
			'authtype' => DB::getDefault('items', 'authtype'),
			'browser_script' => $browser_script,
			'custom_timeout' => ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED,
			'delay_flex' => [],
			'delay' => ZBX_ITEM_DELAY_DEFAULT,
			'description' => DB::getDefault('items', 'description'),
			'discovered' => false,
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'headers' => [],
			'history_mode' => ITEM_STORAGE_CUSTOM,
			'history' => DB::getDefault('items', 'history'),
			'hostid' => 0,
			'http_authtype' => ZBX_HTTP_AUTH_NONE,
			'http_password' => '',
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'http_username' => '',
			'interfaceid' => 0,
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),
			'itemid' => 0,
			'jmx_endpoint' => ZBX_DEFAULT_JMX_ENDPOINT,
			'key' => '',
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'master_itemid' => 0,
			'master_item' => [],
			'name' => '',
			'output_format' => DB::getDefault('items', 'output_format'),
			'parameters' => [],
			'params_ap' => DB::getDefault('items', 'params'),
			'params_es' => DB::getDefault('items', 'params'),
			'params_f' => DB::getDefault('items', 'params'),
			'parent_items' => [],
			'passphrase' => '',
			'password' => DB::getDefault('items', 'password'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'preprocessing' => [],
			'privatekey' => DB::getDefault('items', 'privatekey'),
			'publickey' => DB::getDefault('items', 'publickey'),
			'query_fields' => [],
			'request_method' => DB::getDefault('items', 'request_method'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'script' => DB::getDefault('items', 'params'),
			'show_inherited_tags' => 0,
			'snmp_oid' => DB::getDefault('items', 'snmp_oid'),
			'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
			'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
			'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'status' => DB::getDefault('items', 'status'),
			'tags' => [],
			'templated' => false,
			'templateid' => 0,
			'timeout' => DB::getDefault('items', 'timeout'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),
			'trends_mode' => ITEM_STORAGE_CUSTOM,
			'trends' => DB::getDefault('items', 'trends'),
			'type' => DB::getDefault('items', 'type'),
			'units' => DB::getDefault('items', 'units'),
			'url' => '',
			'username' => DB::getDefault('items', 'username'),
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'valuemapid' => 0,
			'valuemap' => [],
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'verify_peer' => DB::getDefault('items', 'verify_peer')
		];
	}
	/**
	 * Add tags inherited from host and template to item tags. An additional property 'type' is set for each returned
	 * tag.
	 *
	 * @param string $item['itemid']
	 * @param string $item['hostid']
	 * @param string $item['templateid']
	 * @param array  $item['parent_lld'] 				(optional) Parent LLD for discovered item prototype.
	 * @param string $item['parent_lld']['itemid']
	 * @param string $item['parent_lld']['templateid']
	 * @param int    $item['parent_lld']['flags']
	 * @param array  $item_tags
	 *
	 * @return array
	 */
	public static function addInheritedTags(array $item, array $item_tags): array {
		self::findParentLldTemplateid($item);

		$tags = [];

		$parent_templates = array_key_exists('parent_lld', $item)
			? getItemParentTemplates([$item['parent_lld']], $item['parent_lld']['flags'])['templates']
			: getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL)['templates'];
		unset($parent_templates[0]);

		$db_templates = $parent_templates
			? API::Template()->get([
				'output' => ['templateid'],
				'selectTags' => ['tag', 'value'],
				'templateids' => array_keys($parent_templates),
				'preservekeys' => true
			])
			: [];

		$inherited_tags = [];

		// Make list of template tags.
		foreach ($parent_templates as $templateid => $template) {
			if (!array_key_exists($templateid, $db_templates)) {
				continue;
			}

			foreach ($db_templates[$templateid]['tags'] as $tag) {
				if (array_key_exists($tag['tag'], $inherited_tags)
						&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
					$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
						$templateid => $template
					];
				}
				else {
					$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
						'parent_templates' => [$templateid => $template],
						'type' => ZBX_PROPERTY_INHERITED
					];
				}
			}
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectTags' => ['tag', 'value'],
			'hostids' => $item['hostid'],
			'templated_hosts' => true
		]);

		// Overwrite and attach host level tags.
		if ($db_hosts) {
			foreach ($db_hosts[0]['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_INHERITED];
			}
		}

		$item_tags_uniqe = [];

		// Overwrite and attach item's own tags.
		foreach ($item_tags as $tag) {
			if (array_key_exists($tag['tag'], $inherited_tags)
					&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
			}
			else {
				$item_tags_uniqe[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
			}
		}

		foreach ($inherited_tags as $tag) {
			$tags = array_merge($tags, array_values($tag));
		}

		foreach ($item_tags_uniqe as $tag) {
			$tags = array_merge($tags, array_values($tag));
		}

		if ($tags) {
			CArrayHelper::sort($tags, ['tag', 'value']);
		}

		return $tags;
	}

	/**
	 * Convert API data to be ready to use for edit or create form.
	 *
	 * @param array $item  Array of API fields data.
	 */
	public static function convertApiInputForForm(array $item): array {
		$i = 0;
		foreach ($item['preprocessing'] as &$step) {
			$step['params'] = $step['type'] == ZBX_PREPROC_SCRIPT
				? [$step['params'], ''] : explode("\n", $step['params']);
			$step['sortorder'] = $i++;
		}
		unset($step);

		$item += [
			'valuemap' => [],
			'master_item' => [],
			'templated' => (bool) $item['templateid'],
			'discovered' => $item['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
			'http_authtype' => ZBX_HTTP_AUTH_NONE,
			'http_username' => '',
			'http_password' => '',
			'history_mode' => ITEM_STORAGE_CUSTOM,
			'trends_mode' => ITEM_STORAGE_CUSTOM,
			'show_inherited_tags' => 0,
			'custom_timeout' => ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED,
			'key' => $item['key_']
		];
		unset($item['key_']);
		$history_seconds = timeUnitToSeconds($item['history']);
		$trends_seconds = timeUnitToSeconds($item['trends']);

		if ($history_seconds !== null && $history_seconds == ITEM_NO_STORAGE_VALUE) {
			$item['history_mode'] = ITEM_STORAGE_OFF;
			$item['history'] = DB::getDefault('items', 'history');
		}

		if ($trends_seconds !== null && $trends_seconds == ITEM_NO_STORAGE_VALUE) {
			$item['trends_mode'] = ITEM_STORAGE_OFF;
			$item['trends'] = DB::getDefault('items', 'trends');
		}

		if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
			$item['http_authtype'] = $item['authtype'];
			$item['http_username'] = $item['username'];
			$item['http_password'] = $item['password'];
			$item['authtype'] = DB::getDefault('items', 'authtype');
			$item['username'] = '';
			$item['password'] = '';
		}

		if ($item['type'] != ITEM_TYPE_JMX) {
			$item['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if ($item['timeout'] !== DB::getDefault('items', 'timeout')) {
			$item['custom_timeout'] = ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED;
		}

		if ($item['parameters']) {
			CArrayHelper::sort($item['parameters'], ['name']);
			$item['parameters'] = array_values($item['parameters']);
		}

		if ($item['tags']) {
			CArrayHelper::sort($item['tags'], ['tag', 'value']);
		}

		if ($item['valuemapid']) {
			$valuemap = API::ValueMap()->get([
				'output' => ['valuemapid', 'name', 'hostid'],
				'valuemapids' => [$item['valuemapid']]
			]);
			$item['valuemap'] = $valuemap ? reset($valuemap) : [];
		}

		$params_field = [
			ITEM_TYPE_SCRIPT => 'script',
			ITEM_TYPE_BROWSER => 'browser_script',
			ITEM_TYPE_SSH => 'params_es',
			ITEM_TYPE_TELNET => 'params_es',
			ITEM_TYPE_DB_MONITOR => 'params_ap',
			ITEM_TYPE_CALCULATED => 'params_f'
		];

		if (array_key_exists($item['type'], $params_field)) {
			$field = $params_field[$item['type']];
			$item[$field] = $item['params'];
			$item['params'] = '';
		}

		$item += static::getDefaults();

		return $item;
	}

	/**
	 * Set item delay and delay_flex properties.
	 *
	 * @param CUpdateIntervalParser $parser
	 * @param array                 $item
	 */
	public static function addDelayWithFlexibleIntervals(CUpdateIntervalParser $parser, array $item): array {
		$item['delay_flex'] = [];
		$item['delay'] = $parser->getDelay();

		if ($item['delay'][0] !== '{') {
			$delay = timeUnitToSeconds($item['delay']);

			if ($delay == 0 && ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
					|| $item['type'] == ITEM_TYPE_DEPENDENT || ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE
						&& strncmp($item['key'], 'mqtt.get', 8) == 0))) {
				$item['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			}
		}

		foreach ($parser->getIntervals() as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
				$item['delay_flex'][] = [
					'delay' => $interval['update_interval'],
					'period' => $interval['time_period'],
					'type' => ITEM_DELAY_FLEXIBLE
				];
			}
			else {
				$item['delay_flex'][] = [
					'schedule' => $interval['interval'],
					'type' => ITEM_DELAY_SCHEDULING
				];
			}
		}

		return $item;
	}

	public static function getPreprocessingValidationRules(bool $allow_lld_macro): array {
		return ['objects', 'fields' => [
			// Control parameters.
			'on_fail' => ['integer', 'in' => ['1']],
			'sortorder' => ['integer'],

			// Fields.
			'type' => ['integer', 'required', 'in' => CItem::SUPPORTED_PREPROCESSING_TYPES],
			'params_1' => [
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_REGSUB]]
				],
				['db item_preproc.params',
					'when' => ['type', 'in' => [ZBX_PREPROC_STR_REPLACE]]
				],
				['db item_preproc.params', 'required',
					'use' => [CNumberParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('A floating point value is expected.')],
					'when' => ['type', 'in' => [ZBX_PREPROC_VALIDATE_RANGE]]
				],
				['db item_preproc.params', 'required', 'not_empty', 'when' => ['type', 'in' => [
					ZBX_PREPROC_ERROR_FIELD_REGEX
				]]],
				['db item_preproc.params', 'required', 'not_empty',
					'in' => [ZBX_PREPROC_PROMETHEUS_VALUE, ZBX_PREPROC_PROMETHEUS_LABEL, ZBX_PREPROC_PROMETHEUS_SUM,
						ZBX_PREPROC_PROMETHEUS_MIN, ZBX_PREPROC_PROMETHEUS_MAX, ZBX_PREPROC_PROMETHEUS_AVG,
						ZBX_PREPROC_PROMETHEUS_COUNT
					],
					'when' => ['type', 'in' => [ZBX_PREPROC_PROMETHEUS_PATTERN]]
				]
			],
			'params_0' => [
				['db item_preproc.params', 'required', 'not_empty', 'allow_macro',
					'use' => [CRegexValidator::class, []],
					'when' => ['type', 'in' => [ZBX_PREPROC_REGSUB]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_STR_REPLACE]]
				],
				['db item_preproc.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ZBX_PREPROC_TRIM]]],
				['db item_preproc.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ZBX_PREPROC_LTRIM]]],
				['db item_preproc.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ZBX_PREPROC_RTRIM]]],
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_XPATH]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_JSONPATH]]
				],
				['db item_preproc.params', 'when' => ['type', 'in' => [ZBX_PREPROC_CSV_TO_JSON]]],
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_SNMP_WALK_VALUE]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'in' => [(string) ZBX_PREPROC_SNMP_UTF8_FROM_HEX, (string) ZBX_PREPROC_SNMP_MAC_FROM_HEX,
						(string) ZBX_PREPROC_SNMP_INT_FROM_BITS
					],
					'when' => ['type', 'in' => [ZBX_PREPROC_SNMP_GET_VALUE]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'use' => [CNumberParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('A floating point value is expected.')],
					'when' => ['type', 'in' => [ZBX_PREPROC_MULTIPLIER]]
				],
				['db item_preproc.params','required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_SCRIPT]]
				],
				['db item_preproc.params',
					'use' => [CNumberParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('A floating point value is expected.')],
					'when' => ['type', 'in' => [ZBX_PREPROC_VALIDATE_RANGE]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'messages' => ['not_empty' => _('At least one of "min" or "max" parameters should be specified.')],
					'when' => [
						['type', 'in' => [ZBX_PREPROC_VALIDATE_RANGE]],
						['params_1', 'in' => ['']]
					]
				],
				['db item_preproc.params', 'required', 'not_empty', 'allow_macro',
					'use' => [CRegexValidator::class, []],
					'when' => ['type', 'in' => [ZBX_PREPROC_VALIDATE_REGEX]]
				],
				['db item_preproc.params', 'required', 'not_empty', 'allow_macro', 'use' => [
						CRegexValidator::class, []
					], 'when' => ['type', 'in' => [ZBX_PREPROC_VALIDATE_NOT_REGEX]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_PREPROC_ERROR_FIELD_JSON]]
				],
				['db item_preproc.params', 'required', 'not_empty', 'when' => ['type', 'in' => [
							ZBX_PREPROC_ERROR_FIELD_XML
				]]],
				['db item_preproc.params', 'required', 'not_empty', 'allow_macro', 'use' => [
						CRegexValidator::class, []
					], 'when' => ['type', 'in' => [ZBX_PREPROC_ERROR_FIELD_REGEX]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 25 * SEC_PER_YEAR, 'usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'when' => ['type', 'in' => [ZBX_PREPROC_THROTTLE_TIMED_VALUE]]
				],
				['db item_preproc.params', 'required', 'not_empty',
					'use' => [CPrometheusPatternParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('Invalid Prometheus pattern.')],
					'when' => ['type', 'in' => [ZBX_PREPROC_PROMETHEUS_PATTERN]]
				],
				['db item_preproc.params',
					'use' => [CPrometheusPatternParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('Invalid Prometheus pattern.')],
					'when' => ['type', 'in' => [ZBX_PREPROC_PROMETHEUS_TO_JSON]]
				]
			],
			'params_2' => [
				['integer', 'in' => [ZBX_PREPROC_CSV_NO_HEADER, ZBX_PREPROC_CSV_HEADER],
					'when' => ['type', 'in' => [ZBX_PREPROC_CSV_TO_JSON]]
				],
				['string', 'required', 'not_empty',
					'use' => [CPrometheusOutputParser::class, ['usermacros' => true, 'lldmacros' => $allow_lld_macro]],
					'messages' => ['use' => _('Invalid Prometheus label.')],
					'when' => [
						['type', 'in' => [ZBX_PREPROC_PROMETHEUS_PATTERN]],
						['params_1', 'in' => [ZBX_PREPROC_PROMETHEUS_LABEL]]
					]
				]
			],
			'params_set_snmp' => ['objects', 'fields' => [
					'name' => ['string', 'required', 'not_empty'],
					'oid_prefix' => ['string', 'required', 'not_empty'],
					'format' => ['integer', 'in' => [
						ZBX_PREPROC_SNMP_UNCHANGED, ZBX_PREPROC_SNMP_UTF8_FROM_HEX, ZBX_PREPROC_SNMP_MAC_FROM_HEX,
						ZBX_PREPROC_SNMP_INT_FROM_BITS
					]]
				], 'when' => ['type', 'in' => [ZBX_PREPROC_SNMP_WALK_TO_JSON]]
			],
			'params_0_not_supported' => ['integer', 'required',
				'in' => [ZBX_PREPROC_MATCH_ERROR_ANY, ZBX_PREPROC_MATCH_ERROR_REGEX, ZBX_PREPROC_MATCH_ERROR_NOT_REGEX],
				'when' => ['type', 'in' => [ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]]
			],
			'params_1_not_supported' => ['db item_preproc.params', 'required', 'not_empty', 'allow_macro',
				'use' => [CRegexValidator::class, []],
				'when' => [
					['type', 'in' => [ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
					['params_0_not_supported', 'in' => [ZBX_PREPROC_MATCH_ERROR_REGEX, ZBX_PREPROC_MATCH_ERROR_NOT_REGEX]]
				]
			],
			'error_handler' => ['integer',
				'in' => [ZBX_PREPROC_FAIL_DEFAULT, ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE,
					ZBX_PREPROC_FAIL_SET_ERROR
				]
			],
			'error_handler_params' => [
				['string', 'required', 'not_empty', 'when' => [
					['error_handler', 'in' => [ZBX_PREPROC_FAIL_SET_ERROR]],
					['on_fail', 'in' => ['1']]
				]]
			]
		]];
	}

	/**
	 * Convert form submitted data to be ready to send to API for update or create operation.
	 *
	 * @param array $input  Array of form input fields.
	 */
	public static function convertFormInputForApi(array $input): array {
		$field_map = ['key' => 'key_'];

		if ($input['history_mode'] == ITEM_STORAGE_OFF) {
			$input['history'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($input['trends_mode'] == ITEM_STORAGE_OFF) {
			$input['trends'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($input['type'] == ITEM_TYPE_HTTPAGENT) {
			$field_map['http_authtype'] = 'authtype';
			$field_map['http_username'] = 'username';
			$field_map['http_password'] = 'password';

			$input['query_fields'] = prepareItemQueryFields($input['query_fields']);
			$input['headers'] = prepareItemHeaders($input['headers']);
		}
		else {
			$input['query_fields'] = [];
			$input['headers'] = [];
		}

		if ($input['type'] == ITEM_TYPE_SSH && $input['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
			$field_map['passphrase'] = 'password';
		}

		if ($input['type'] != ITEM_TYPE_JMX) {
			$input['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if ($input['request_method'] == HTTPCHECK_REQUEST_HEAD) {
			$input['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
		}

		if ($input['custom_timeout'] == ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED) {
			$input['timeout'] = DB::getDefault('items', 'timeout');
		}

		if ($input['preprocessing']) {
			$input['preprocessing'] = normalizeItemPreprocessingSteps($input['preprocessing']);
		}

		if ($input['delay_flex']) {
			$input['delay'] = getDelayWithCustomIntervals($input['delay'], $input['delay_flex']);
		}

		$params_field = [
			ITEM_TYPE_SCRIPT => 'script',
			ITEM_TYPE_BROWSER => 'browser_script',
			ITEM_TYPE_SSH => 'params_es',
			ITEM_TYPE_TELNET => 'params_es',
			ITEM_TYPE_DB_MONITOR => 'params_ap',
			ITEM_TYPE_CALCULATED => 'params_f'
		];
		$input['params'] = '';

		if (array_key_exists($input['type'], $params_field)) {
			$field = $params_field[$input['type']];
			$input['params'] = $input[$field];
		}

		return CArrayHelper::renameKeys($input, $field_map);
	}

	/**
	 * Normalize and clean form data.
	 *
	 * @param array $input  Form data.
	 *
	 * @return array normalized form data.
	 */
	public static function normalizeFormData(array $input): array {
		foreach ($input['preprocessing'] as &$step) {
			$step = self::normalizeFormDataPreprocessingStep($step);
		}
		unset($step);

		$tags = [];

		foreach ($input['tags'] as $tag) {
			if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
				// Skip inherited tags.
				continue;
			}

			if ($tag['tag'] !== '' || $tag['value'] !== '') {
				$tags[] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
			}
		}

		$input['tags'] = $tags;
		$custom_intervals = [];

		foreach ($input['delay_flex'] as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE
					&& $interval['delay'] === '' && $interval['period'] === '') {
				continue;
			}

			if ($interval['type'] == ITEM_DELAY_SCHEDULING && $interval['schedule'] === '') {
				continue;
			}

			$custom_intervals[] = $interval;
		};

		$input['delay_flex'] = $custom_intervals;
		$query_fields = [];
		$headers = [];

		if ($input['type'] == ITEM_TYPE_HTTPAGENT) {
			foreach ($input['query_fields'] as $query_field) {
				if ($query_field['name'] !== '' || $query_field['value'] !== '') {
					$query_fields[] = $query_field;
				}
			}

			foreach ($input['headers'] as $header) {
				if ($header['name'] !== '' || $header['value'] !== '') {
					$headers[] = $header;
				}
			}

			CArrayHelper::sort($query_fields, ['sortorder']);
			CArrayHelper::sort($headers, ['sortorder']);
		}
		elseif (in_array($input['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
			$parameters = [];

			foreach ($input['parameters'] as $parameter) {
				if ($parameter['name'] !== '' || $parameter['value'] !== '') {
					$parameters[] = $parameter;
				}
			}

			$input['parameters'] = $parameters;
		}

		$input['query_fields'] = array_values($query_fields);
		$input['headers'] = array_values($headers);
		CArrayHelper::sort($input['preprocessing'], ['sortorder']);
		$input['preprocessing'] = array_values($input['preprocessing']);

		return $input;
	}

	public static function normalizeFormDataPreprocessingStep(array $step): array {
		if (array_key_exists('params', $step)) {
			return $step;
		}

		$step['params'] = [];

		if (array_key_exists('params_set_snmp', $step)) {
			foreach ($step['params_set_snmp'] as $param) {
				$step['params'][] = $param['name'];
				$step['params'][] = $param['oid_prefix'];
				$step['params'][] = $param['format'];
			}

			unset($step['params_set_snmp']);
		}

		foreach (['params_0', 'params_1', 'params_2'] as $key) {
			if (array_key_exists($key, $step)) {
				if ($step[$key] === null) {
					$step[$key] = '';
				}

				$step['params'][] = $step[$key];
				unset($step[$key]);
			}
		}

		foreach (['params_0_not_supported', 'params_1_not_supported'] as $key) {
			if (array_key_exists($key, $step)) {
				if ($step[$key] === null) {
					$step[$key] = '';
				}

				$step['params'][] = $step[$key];
				unset($step[$key]);
			}
		}

		return $step;
	}

	/**
	 * Sort steps and prioritize ZBX_PREPROC_VALIDATE_NOT_SUPPORTED checks, with "match any error" being the last of them.
	 *
	 * @param array $steps
	 *
	 * @return array
	 */
	public static function sortPreprocessingSteps(array $steps): array {
		CArrayHelper::sort($steps, ['sortorder']);
		$ns_regex = [];
		$ns_any = [];
		$other = [];

		foreach ($steps as $step) {
			if ($step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
				$other[] = $step;
				continue;
			}

			if ($step['params'][0] == ZBX_PREPROC_MATCH_ERROR_ANY) {
				$ns_any[] = $step;
			}
			else {
				$ns_regex[] = $step;
			}
		}

		return array_merge($ns_regex, $ns_any, $other);
	}

	/**
	 * @param array  $src_items
	 * @param array  $dst_hosts
	 *
	 * @return array
	 */
	protected static function getDestinationValueMaps(array $src_items, array $dst_hosts): array {
		$item_indexes = [];
		$dst_valuemapids = [];

		$dst_hostids = array_keys($dst_hosts);

		foreach ($src_items as $src_item) {
			if ($src_item['valuemapid'] != 0) {
				$item_indexes[$src_item['valuemapid']][] = $src_item['itemid'];

				$dst_valuemapids[$src_item['itemid']] = array_fill_keys($dst_hostids, 0);
			}
		}

		if (!$item_indexes) {
			return [];
		}

		$src_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'valuemapids' => array_keys($item_indexes)
		]);

		$dst_valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'hostid', 'name'],
			'hostids' => $dst_hostids,
			'filter' => ['name' => array_unique(array_column($src_valuemaps, 'name'))]
		]);

		$_dst_valuemapids = [];

		foreach ($dst_valuemaps as $dst_valuemap) {
			$_dst_valuemapids[$dst_valuemap['name']][$dst_valuemap['hostid']] = $dst_valuemap['valuemapid'];
		}

		foreach ($src_valuemaps as $src_valuemap) {
			if (array_key_exists($src_valuemap['name'], $_dst_valuemapids)) {
				foreach ($_dst_valuemapids[$src_valuemap['name']] as $dst_hostid => $dst_valuemapid) {
					foreach ($item_indexes[$src_valuemap['valuemapid']] as $src_itemid) {
						$dst_valuemapids[$src_itemid][$dst_hostid] = $dst_valuemapid;
					}
				}
			}
		}

		return $dst_valuemapids;
	}

	/**
	 * @param array  $src_items
	 * @param array  $dst_hosts
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationHostInterfaces(array $src_items, array $dst_hosts): array {
		$dst_hostids = array_keys($dst_hosts);

		if (reset($dst_hosts)['status'] == HOST_STATUS_TEMPLATE) {
			$dst_interfaceids = [];

			if (in_array(reset($src_items)['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				foreach ($src_items as $src_item) {
					if ($src_item['interfaceid'] != 0) {
						$dst_interfaceids[$src_item['itemid']] = array_fill_keys($dst_hostids, 0);
					}
				}
			}

			return $dst_interfaceids;
		}

		$item_indexes = [];
		$dst_interfaceids = [];

		foreach ($src_items as $src_item) {
			if (itemTypeInterface($src_item['type']) !== false) {
				$dst_interfaceids[$src_item['itemid']] = array_fill_keys($dst_hostids, 0);
			}

			if ($src_item['interfaceid'] != 0) {
				$item_indexes[$src_item['interfaceid']][] = $src_item['itemid'];
			}
		}

		if (!$dst_interfaceids) {
			return [];
		}

		$src_interfaces = [];

		if ($item_indexes) {
			$src_interfaces = API::HostInterface()->get([
				'output' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
				'interfaceids' => array_keys($item_indexes),
				'preservekeys' => true
			]);

			foreach ($src_interfaces as &$src_interface) {
				unset($src_interface['interfaceid']);
			}
			unset($src_interface);
		}

		$dst_interfaces = API::HostInterface()->get([
			'output' => ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => $dst_hostids
		]);

		$main_interfaceids = [];

		foreach ($dst_interfaces as $dst_interface) {
			$dst_interfaceid = $dst_interface['interfaceid'];
			$dst_hostid = $dst_interface['hostid'];
			unset($dst_interface['interfaceid'], $dst_interface['hostid']);

			foreach ($src_interfaces as $src_interfaceid => $src_interface) {
				if ($src_interface == $dst_interface) {
					foreach ($item_indexes[$src_interfaceid] as $src_itemid) {
						$dst_interfaceids[$src_itemid][$dst_hostid] = $dst_interfaceid;
					}

					break;
				}
			}

			if ($dst_interface['main'] == INTERFACE_PRIMARY) {
				$main_interfaceids[$dst_hostid][$dst_interface['type']] = $dst_interfaceid;
			}
		}

		$interfaces_by_priority = array_flip(CItemGeneral::INTERFACE_TYPES_BY_PRIORITY);

		foreach ($dst_interfaceids as $src_itemid => &$dst_host_interfaceids) {
			foreach ($dst_host_interfaceids as $dst_hostid => &$dst_interfaceid) {
				if ($dst_interfaceid != 0) {
					continue;
				}

				$dst_interface_type = itemTypeInterface($src_items[$src_itemid]['type']);

				if ($dst_interface_type == INTERFACE_TYPE_OPT) {
					$src_item = $src_items[$src_itemid];

					if (in_array($src_item['hosts'][0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
							&& $src_item['interfaceid'] == 0) {
						continue;
					}

					$dst_interface_type = array_key_exists($dst_hostid, $main_interfaceids)
						? key(array_intersect_key($interfaces_by_priority, $main_interfaceids[$dst_hostid]))
						: null;

					if ($dst_interface_type !== null) {
						$dst_interfaceid = $main_interfaceids[$dst_hostid][$dst_interface_type];
					}
				}
				else {
					if (array_key_exists($dst_hostid, $main_interfaceids)
							&& array_key_exists($dst_interface_type, $main_interfaceids[$dst_hostid])) {
						$dst_interfaceid = $main_interfaceids[$dst_hostid][$dst_interface_type];
					}
					else {
						$hosts = API::Host()->get([
							'output' => ['host'],
							'hostids' => $dst_hostid
						]);

						error(_s('Cannot find host interface on "%1$s" for item with key "%2$s".',
							$hosts[0]['host'], $src_items[$src_itemid]['key_']
						));

						throw new Exception();
					}
				}
			}
			unset($dst_interfaceid);
		}
		unset($dst_host_interfaceids);

		return $dst_interfaceids;
	}

	/**
	 * @param array  $src_items
	 * @param array  $dst_hosts
	 * @param int    $flags
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationMasterItems(array $src_items, array $dst_hosts, int $flags): array {
		$dst_hostids = array_keys($dst_hosts);
		$item_indexes = [];
		$dst_master_itemids = [];

		foreach ($src_items as $src_item) {
			if ($src_item['master_itemid'] != 0) {
				$item_indexes[$src_item['master_itemid']][] = $src_item['itemid'];
				$dst_master_itemids[$src_item['itemid']] = array_fill_keys($dst_hostids, 0);
			}
		}

		if (!$item_indexes) {
			return [];
		}

		$src_master_items = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'itemids' => array_keys($item_indexes),
			'webitems' => true,
			'preservekeys' => true
		]);
		$host_filter = reset($dst_hosts)['status'] == HOST_STATUS_TEMPLATE
			? ['templateids' => $dst_hostids]
			: ['hostids' => $dst_hostids];
		$dst_master_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'filter' => ['key_' => array_unique(array_column($src_master_items, 'key_'))],
			'webitems' => true
		] + $host_filter);
		$_dst_master_itemids = [];

		foreach ($dst_master_items as $dst_master_item) {
			$_dst_master_itemids[$dst_master_item['key_']][$dst_master_item['hostid']] = $dst_master_item['itemid'];
		}

		foreach ($src_master_items as $src_master_item) {
			if (array_key_exists($src_master_item['key_'], $_dst_master_itemids)) {
				foreach ($_dst_master_itemids[$src_master_item['key_']] as $dst_hostid => $dst_master_itemid) {
					foreach ($item_indexes[$src_master_item['itemid']] as $src_itemid) {
						$dst_master_itemids[$src_itemid][$dst_hostid] = $dst_master_itemid;
					}
				}
			}
		}

		$src_master_itemprototypes = [];
		$dst_master_itemprototypes = [];

		if ($flags == ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE) {
			$src_master_itemprototypes = API::ItemPrototype()->get([
				'output' => ['itemid', 'key_'],
				'itemids' => array_keys($item_indexes),
				'preservekeys' => true
			]);
			$dst_master_itemprototypes = API::ItemPrototype()->get([
				'output' => ['itemid', 'hostid', 'key_'],
				'filter' => ['key_' => array_unique(array_column($src_master_itemprototypes, 'key_'))]
			] + $host_filter);

			foreach ($dst_master_itemprototypes as $dst_master_item) {
				$_dst_master_itemids[$dst_master_item['key_']][$dst_master_item['hostid']] = $dst_master_item['itemid'];
			}

			foreach ($src_master_itemprototypes as $src_master_item) {
				if (array_key_exists($src_master_item['key_'], $_dst_master_itemids)) {
					foreach ($_dst_master_itemids[$src_master_item['key_']] as $dst_hostid => $dst_master_itemid) {
						foreach ($item_indexes[$src_master_item['itemid']] as $src_itemid) {
							$dst_master_itemids[$src_itemid][$dst_hostid] = $dst_master_itemid;
						}
					}
				}
			}
		}

		$missing_master_item = [
			ZBX_FLAG_DISCOVERY_NORMAL => _('Cannot copy item with key "%1$s" without its master item with key "%2$s".'),
			ZBX_FLAG_DISCOVERY_PROTOTYPE => _('Cannot copy item prototype with key "%1$s" without its master item with key "%2$s".'),
			ZBX_FLAG_DISCOVERY_RULE => _('Cannot copy LLD rule with key "%1$s" without its master item with key "%2$s".'),
			ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE => _('Cannot copy LLD rule prototype with key "%1$s" without its master item with key "%2$s".')
		];

		foreach ($dst_master_itemids as $src_itemid => $dst_host_master_itemids) {
			foreach ($dst_host_master_itemids as $dst_hostid => $dst_master_itemid) {
				if ($dst_master_itemid == 0) {
					$src_item = $src_items[$src_itemid];
					$error = array_key_exists($src_item['master_itemid'], $src_master_items)
						? sprintf($missing_master_item[$src_item['flags']],
							$src_item['key_'],
							$src_master_items[$src_item['master_itemid']]['key_']
						)
						: _s('Cannot copy LLD rule prototype with key "%1$s" without its master item prototype with key "%2$s".',
							$src_item['key_'],
							$src_master_itemprototypes[$src_item['master_itemid']]['key_']
						);

					error($error);

					throw new Exception();
				}
			}
		}

		return $dst_master_itemids;
	}

	public static function findParentLldTemplateid(array &$data): void {
		if (!array_key_exists('parent_lld', $data)) {
			return;
		}

		$lldruleid = $data['parent_lld']['itemid'];

		while ($data['parent_lld']['templateid'] == 0) {
			$options = [
				'output' => ['itemid', 'templateid', 'flags'],
				'selectDiscoveryRule' => ['itemid'],
				'itemids' => $lldruleid,
				'nopermissions' => true
			];

			$db_source = API::DiscoveryRule()->get($options)
				?: API::DiscoveryRulePrototype()->get($options + ['selectDiscoveryRulePrototype' => ['itemid']]);

			$data['parent_lld'] = reset($db_source) + ['discoveryRulePrototype' => []];

			$parent_lld = $data['parent_lld']['discoveryRule'] ?: $data['parent_lld']['discoveryRulePrototype'];

			if (!$parent_lld) {
				break;
			}

			$lldruleid = $parent_lld['itemid'];
		}
	}
}
