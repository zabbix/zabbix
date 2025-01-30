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
 * Get data for LLD rule edit page.
 *
 * @param array $item  LLD rule to take the data from.
 *
 * @return array
 */
function getItemFormData(array $item = []) {
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
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'is_discovery_rule' => true,
		'parent_discoveryid' => getRequest('parent_discoveryid', 0),
		'itemid' => getRequest('itemid'),
		'limited' => false,
		'interfaceid' => getRequest('interfaceid', 0),
		'name' => getRequest('name', ''),
		'description' => getRequest('description', ''),
		'key' => getRequest('key', ''),
		'master_itemid' => getRequest('master_itemid', 0),
		'hostname' => getRequest('hostname'),
		'delay' => getRequest('delay', ZBX_LLD_RULE_DELAY_DEFAULT),
		'history' => getRequest('history', DB::getDefault('items', 'history')),
		'status' => getRequest('status', isset($_REQUEST['form_refresh']) ? 1 : 0),
		'type' => getRequest('type', ITEM_TYPE_ZABBIX),
		'snmp_oid' => getRequest('snmp_oid', ''),
		'value_type' => getRequest('value_type', ITEM_VALUE_TYPE_UINT64),
		'trapper_hosts' => getRequest('trapper_hosts', ''),
		'units' => getRequest('units', ''),
		'valuemapid' => getRequest('valuemapid', 0),
		'params' => getRequest('params', ''),
		'browser_script' => getRequest('browser_script', $browser_script),
		'trends' => getRequest('trends', DB::getDefault('items', 'trends')),
		'delay_flex' => array_values(getRequest('delay_flex', [])),
		'ipmi_sensor' => getRequest('ipmi_sensor', ''),
		'authtype' => getRequest('authtype', 0),
		'username' => getRequest('username', ''),
		'password' => getRequest('password', ''),
		'publickey' => getRequest('publickey', ''),
		'privatekey' => getRequest('privatekey', ''),
		'logtimefmt' => getRequest('logtimefmt', ''),
		'possibleHostInventories' => null,
		'alreadyPopulated' => null,
		'initial_item_type' => null,
		'templates' => [],
		'jmx_endpoint' => getRequest('jmx_endpoint', ZBX_DEFAULT_JMX_ENDPOINT),
		'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
		'url' => getRequest('url'),
		'query_fields' => getRequest('query_fields', []),
		'parameters' => getRequest('parameters', []),
		'posts' => getRequest('posts'),
		'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
		'follow_redirects' => hasRequest('form_refresh')
			? (int) getRequest('follow_redirects')
			: getRequest('follow_redirects', DB::getDefault('items', 'follow_redirects')),
		'post_type' => getRequest('post_type', DB::getDefault('items', 'post_type')),
		'http_proxy' => getRequest('http_proxy'),
		'headers' => getRequest('headers', []),
		'retrieve_mode' => getRequest('retrieve_mode', DB::getDefault('items', 'retrieve_mode')),
		'request_method' => getRequest('request_method', DB::getDefault('items', 'request_method')),
		'output_format' => getRequest('output_format', DB::getDefault('items', 'output_format')),
		'allow_traps' => getRequest('allow_traps', DB::getDefault('items', 'allow_traps')),
		'ssl_cert_file' => getRequest('ssl_cert_file'),
		'ssl_key_file' => getRequest('ssl_key_file'),
		'ssl_key_password' => getRequest('ssl_key_password'),
		'verify_peer' => getRequest('verify_peer', DB::getDefault('items', 'verify_peer')),
		'verify_host' => getRequest('verify_host', DB::getDefault('items', 'verify_host')),
		'http_authtype' => getRequest('http_authtype', ZBX_HTTP_AUTH_NONE),
		'http_username' => getRequest('http_username', ''),
		'http_password' => getRequest('http_password', ''),
		'preprocessing' => getRequest('preprocessing', []),
		'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params'),
		'context' => getRequest('context'),
		'show_inherited_tags' => getRequest('show_inherited_tags', 0),
		'tags' => getRequest('tags', []),
		'backurl' => getRequest('backurl'),
		'lifetime_type' => getRequest('lifetime_type', DB::getDefault('items', 'lifetime_type')),
		'lifetime' => getRequest('lifetime', DB::getDefault('items', 'lifetime')),
		'enabled_lifetime_type' => getRequest('enabled_lifetime_type', DB::getDefault('items', 'enabled_lifetime_type')),
		'enabled_lifetime' => getRequest('enabled_lifetime', ZBX_LLD_RULE_ENABLED_LIFETIME)
	];
	CArrayHelper::sort($data['preprocessing'], ['sortorder']);

	// Unset empty and inherited tags.
	foreach ($data['tags'] as $key => $tag) {
		if ($tag['tag'] === '' && $tag['value'] === '') {
			unset($data['tags'][$key]);
		}
		elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
			unset($data['tags'][$key]);
		}
		else {
			unset($data['tags'][$key]['type']);
		}
	}

	if ($data['parent_discoveryid'] != 0) {
		$data['discover'] = hasRequest('form_refresh')
			? getRequest('discover', DB::getDefault('items', 'discover'))
			: (($item && array_key_exists('discover', $item))
				? $item['discover']
				: DB::getDefault('items', 'discover')
			);
	}

	if ($data['type'] != ITEM_TYPE_HTTPAGENT) {
		$data['headers'] = [];
		$data['query_fields'] = [];
	}

	if (in_array($data['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
		CArrayHelper::sort($data['parameters'], ['name']);
		$data['parameters'] = array_values($data['parameters']);
	}
	else {
		$data['parameters'] = [];
	}

	// Dependent item initialization by master_itemid.
	if (array_key_exists('master_item', $item)) {
		$data['master_itemid'] = $item['master_item']['itemid'];
		$data['master_itemname'] = $item['master_item']['name'];
		// Do not initialize item data if only master_item array was passed.
		unset($item['master_item']);
	}

	// hostid
	if ($data['parent_discoveryid'] != 0) {
		$discoveryRule = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'selectHosts' => ['flags'],
			'itemids' => $data['parent_discoveryid'],
			'editable' => true
		]);
		$discoveryRule = reset($discoveryRule);
		$data['hostid'] = $discoveryRule['hostid'];
		$data['host'] = $discoveryRule['hosts'][0];
	}
	else {
		$data['hostid'] = getRequest('hostid', 0);
	}

	foreach ($data['preprocessing'] as &$step) {
		$step += [
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		];
	}
	unset($step);

	// types, http items only for internal processes
	$data['types'] = item_type2str();
	unset($data['types'][ITEM_TYPE_HTTPTEST]);
	unset($data['types'][ITEM_TYPE_CALCULATED], $data['types'][ITEM_TYPE_SNMPTRAP]);

	// item
	if (array_key_exists('itemid', $item)) {
		$data['item'] = $item;
		$data['hostid'] = !empty($data['hostid']) ? $data['hostid'] : $data['item']['hostid'];
		$data['limited'] = ($data['item']['templateid'] != 0);
		$data['interfaceid'] = $item['interfaceid'];

		// discovery rule
		$flag = ZBX_FLAG_DISCOVERY_RULE;
		$data['templates'] = makeItemTemplatesHtml($item['itemid'], getItemParentTemplates([$item], $flag), $flag,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);
	}

	// caption
	$data['caption'] = _('Discovery rule');

	// fill data from item
	if (!hasRequest('form_refresh') && ($item || $data['limited'])) {
		$data['name'] = $data['item']['name'];
		$data['description'] = $data['item']['description'];
		$data['key'] = $data['item']['key_'];
		$data['interfaceid'] = $data['item']['interfaceid'];
		$data['type'] = $data['item']['type'];
		$data['snmp_oid'] = $data['item']['snmp_oid'];
		$data['value_type'] = $data['item']['value_type'];
		$data['trapper_hosts'] = $data['item']['trapper_hosts'];
		$data['units'] = $data['item']['units'];
		$data['valuemapid'] = $data['item']['valuemapid'];
		$data['hostid'] = $data['item']['hostid'];
		$data['ipmi_sensor'] = $data['item']['ipmi_sensor'];
		$data['authtype'] = $data['item']['authtype'];
		$data['username'] = $data['item']['username'];
		$data['password'] = $data['item']['password'];
		$data['publickey'] = $data['item']['publickey'];
		$data['privatekey'] = $data['item']['privatekey'];
		$data['logtimefmt'] = $data['item']['logtimefmt'];
		$data['jmx_endpoint'] = $data['item']['jmx_endpoint'];
		// ITEM_TYPE_HTTPAGENT
		$data['timeout'] = $data['item']['timeout'];
		$data['url'] = $data['item']['url'];
		$data['query_fields'] = $data['item']['query_fields'];
		$data['parameters'] = $data['item']['parameters'];
		$data['posts'] = $data['item']['posts'];
		$data['status_codes'] = $data['item']['status_codes'];
		$data['follow_redirects'] = $data['item']['follow_redirects'];
		$data['post_type'] = $data['item']['post_type'];
		$data['http_proxy'] = $data['item']['http_proxy'];
		$data['headers'] = $data['item']['headers'];
		$data['retrieve_mode'] = $data['item']['retrieve_mode'];
		$data['request_method'] = $data['item']['request_method'];
		$data['allow_traps'] = $data['item']['allow_traps'];
		$data['ssl_cert_file'] = $data['item']['ssl_cert_file'];
		$data['ssl_key_file'] = $data['item']['ssl_key_file'];
		$data['ssl_key_password'] = $data['item']['ssl_key_password'];
		$data['verify_peer'] = $data['item']['verify_peer'];
		$data['verify_host'] = $data['item']['verify_host'];
		$data['http_authtype'] = $data['item']['authtype'];
		$data['http_username'] = $data['item']['username'];
		$data['http_password'] = $data['item']['password'];

		if ($data['item']['type'] == ITEM_TYPE_BROWSER) {
			$data['browser_script'] = $data['item']['params'];
		}
		else {
			$data['params'] = $data['item']['params'];
		}

		if (in_array($data['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]) && $data['parameters']) {
			CArrayHelper::sort($data['parameters'], ['name']);
			$data['parameters'] = array_values($data['parameters']);
		}

		$data['preprocessing'] = $data['item']['preprocessing'];

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['delay'] = $data['item']['delay'];

			$update_interval_parser = new CUpdateIntervalParser([
				'usermacros' => true,
				'lldmacros' => ($data['parent_discoveryid'] != 0)
			]);

			if ($update_interval_parser->parse($data['delay']) == CParser::PARSE_SUCCESS) {
				$data['delay'] = $update_interval_parser->getDelay();

				if ($data['delay'][0] !== '{') {
					$delay = timeUnitToSeconds($data['delay']);

					if ($delay == 0 && ($data['type'] == ITEM_TYPE_TRAPPER || $data['type'] == ITEM_TYPE_SNMPTRAP
							|| $data['type'] == ITEM_TYPE_DEPENDENT || ($data['type'] == ITEM_TYPE_ZABBIX_ACTIVE
								&& strncmp($data['key'], 'mqtt.get', 8) == 0))) {
						$data['delay'] = ZBX_LLD_RULE_DELAY_DEFAULT;
					}
				}

				foreach ($update_interval_parser->getIntervals() as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
						$data['delay_flex'][] = [
							'delay' => $interval['update_interval'],
							'period' => $interval['time_period'],
							'type' => ITEM_DELAY_FLEXIBLE
						];
					}
					else {
						$data['delay_flex'][] = [
							'schedule' => $interval['interval'],
							'type' => ITEM_DELAY_SCHEDULING
						];
					}
				}
			}
			else {
				$data['delay'] = ZBX_LLD_RULE_DELAY_DEFAULT;
			}

			$data['history'] = $data['item']['history'];
			$data['status'] = $data['item']['status'];
			$data['trends'] = $data['item']['trends'];
		}
	}

	if (!$data['delay_flex']) {
		$data['delay_flex'][] = ['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE];
	}

	// interfaces
	$data['interfaces'] = API::HostInterface()->get([
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND
	]);
	// Sort interfaces to be listed starting with one selected as 'main'.
	CArrayHelper::sort($data['interfaces'], [
		['field' => 'main', 'order' => ZBX_SORT_DOWN],
		['field' => 'interfaceid','order' => ZBX_SORT_UP]
	]);

	unset($data['valuemapid']);

	// unset ssh auth fields
	if ($data['type'] != ITEM_TYPE_SSH) {
		$data['authtype'] = ITEM_AUTHTYPE_PASSWORD;
		$data['publickey'] = '';
		$data['privatekey'] = '';
	}

	if ($data['type'] != ITEM_TYPE_DEPENDENT) {
		$data['master_itemid'] = 0;
	}

	return $data;
}

/**
 * Get list of item pre-processing data and return a prepared HTML object.
 *
 * @param array  $preprocessing                            Array of item pre-processing steps.
 * @param string $preprocessing[]['type']                  Pre-processing step type.
 * @param array  $preprocessing[]['params']                Additional parameters used by pre-processing.
 * @param string $preprocessing[]['error_handler']         Action type used in case of pre-processing step failure.
 * @param string $preprocessing[]['error_handler_params']  Error handler parameters.
 * @param bool   $readonly                                 True if fields should be read only.
 * @param array  $types                                    Supported pre-processing types.
 *
 * @return CList
 */
function getItemPreprocessing(array $preprocessing, $readonly, array $types) {
	$script_maxlength = DB::getFieldLength('item_preproc', 'params');
	$preprocessing_list = (new CList())
		->setId('preprocessing')
		->addClass('preprocessing-list')
		->addClass(ZBX_STYLE_LIST_NUMBERED)
		->setAttribute('data-readonly', $readonly)
		->addItem(
			(new CListItem([
				(new CDiv(_('Name')))->addClass('step-name'),
				(new CDiv(_('Parameters')))->addClass('step-parameters'),
				(new CDiv(_('Custom on fail')))->addClass('step-on-fail'),
				(new CDiv(_('Actions')))->addClass('step-action')
			]))
				->addClass('preprocessing-list-head')
				->addStyle(!$preprocessing ? 'display: none;' : null)
		);

	$i = 0;

	foreach ($preprocessing as $step) {
		// Create a select with preprocessing types.
		$preproc_types_select = (new CSelect('preprocessing['.$i.'][type]'))
			->setId('preprocessing_'.$i.'_type')
			->setValue($step['type'])
			->setReadonly($readonly)
			->setWidthAuto();

		foreach (get_preprocessing_types(null, true, $types) as $group) {
			$opt_group = new CSelectOptionGroup($group['label']);

			foreach ($group['types'] as $type => $label) {
				$opt_group->addOption((new CSelectOption($type, $label))->setDisabled(
					$step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED && $type == $step['type']
				));
			}

			$preproc_types_select->addOptionGroup($opt_group);
		}

		// Depending on preprocessing type, display corresponding params field and placeholders.
		$params = '';

		if ($step['type'] != ZBX_PREPROC_SNMP_WALK_TO_JSON) {
			// Create a primary param text box, so it can be hidden if necessary.
			$step_param_0_value = array_key_exists('params', $step) ? $step['params'][0] : '';
			$step_param_0 = (new CTextBox('preprocessing['.$i.'][params][0]', $step_param_0_value))
				->setReadonly($readonly);

			// Create a secondary param text box, so it can be hidden if necessary.
			$step_param_1_value = (array_key_exists('params', $step) && array_key_exists(1, $step['params']))
				? $step['params'][1]
				: '';
			$step_param_1 = (new CTextBox('preprocessing['.$i.'][params][1]', $step_param_1_value))
				->setReadonly($readonly);
		}

		// Add corresponding placeholders and show or hide text boxes.
		switch ($step['type']) {
			case ZBX_PREPROC_MULTIPLIER:
				$params = $step_param_0
					->setAttribute('placeholder', _('number'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
				break;

			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
				$params = $step_param_0
					->setAttribute('placeholder', _('list of characters'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
				break;

			case ZBX_PREPROC_XPATH:
			case ZBX_PREPROC_ERROR_FIELD_XML:
				$params = $step_param_0->setAttribute('placeholder', _('XPath'));
				break;

			case ZBX_PREPROC_JSONPATH:
			case ZBX_PREPROC_ERROR_FIELD_JSON:
				$params = $step_param_0->setAttribute('placeholder', _('$.path.to.node'));
				break;

			case ZBX_PREPROC_REGSUB:
			case ZBX_PREPROC_ERROR_FIELD_REGEX:
				$params = [
					$step_param_0->setAttribute('placeholder', _('pattern')),
					$step_param_1->setAttribute('placeholder', _('output'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_RANGE:
				$params = [
					$step_param_0->setAttribute('placeholder', _('min')),
					$step_param_1->setAttribute('placeholder', _('max'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_REGEX:
			case ZBX_PREPROC_VALIDATE_NOT_REGEX:
				$params = $step_param_0->setAttribute('placeholder', _('pattern'));
				break;

			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
				$params = $step_param_0
					->setAttribute('placeholder', _('seconds'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
				break;

			case ZBX_PREPROC_SCRIPT:
				$params = new CMultilineInput($step_param_0->getName(), $step_param_0_value, [
					'title' => _('JavaScript'),
					'placeholder' => _('script'),
					'placeholder_textarea' => 'return value',
					'label_before' => 'function (value) {',
					'label_after' => '}',
					'grow' => 'auto',
					'rows' => 0,
					'maxlength' => $script_maxlength,
					'readonly' => $readonly
				]);
				break;

			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: '';

				if ($step_param_1_value === ZBX_PREPROC_PROMETHEUS_FUNCTION) {
					$step_param_1_value = $step_param_2_value;
					$step_param_2_value = '';
				}

				$params = [
					$step_param_0->setAttribute('placeholder',
						_('<metric name>{<label name>="<label value>", ...} == <value>')
					),
					(new CSelect('preprocessing['.$i.'][params][1]'))
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_PREPROC_PROMETHEUS_VALUE => _('value'),
							ZBX_PREPROC_PROMETHEUS_LABEL => _('label'),
							ZBX_PREPROC_PROMETHEUS_SUM => 'sum',
							ZBX_PREPROC_PROMETHEUS_MIN => 'min',
							ZBX_PREPROC_PROMETHEUS_MAX => 'max',
							ZBX_PREPROC_PROMETHEUS_AVG => 'avg',
							ZBX_PREPROC_PROMETHEUS_COUNT => 'count'
						]))
						->addClass('js-preproc-param-prometheus-pattern-function')
						->setValue($step_param_1_value)
						->setReadonly($readonly),
					(new CTextBox('preprocessing['.$i.'][params][2]', $step_param_2_value))
						->setTitle($step_param_2_value)
						->setAttribute('placeholder', _('<label name>'))
						->setEnabled($step_param_1_value === ZBX_PREPROC_PROMETHEUS_LABEL)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_PROMETHEUS_TO_JSON:
				$params = $step_param_0->setAttribute('placeholder',
					_('<metric name>{<label name>="<label value>", ...} == <value>')
				);
				break;

			case ZBX_PREPROC_CSV_TO_JSON:
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: ZBX_PREPROC_CSV_NO_HEADER;

				$params = [
					$step_param_0
						->setAttribute('placeholder', _('delimiter'))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					$step_param_1
						->setAttribute('placeholder', _('qualifier'))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					(new CCheckBox('preprocessing['.$i.'][params][2]', ZBX_PREPROC_CSV_HEADER))
						->setLabel(_('With header row'))
						->setChecked($step_param_2_value == ZBX_PREPROC_CSV_HEADER)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_STR_REPLACE:
				$params = [
					$step_param_0->setAttribute('placeholder', _('search string')),
					$step_param_1->setAttribute('placeholder', _('replacement'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				if ($step_param_0_value == '') {
					$step_param_0_value = ZBX_PREPROC_MATCH_ERROR_ANY;
				}

				$params = [
					(new CSelect('preprocessing['.$i.'][params][0]'))
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_PREPROC_MATCH_ERROR_ANY => _('any error'),
							ZBX_PREPROC_MATCH_ERROR_REGEX => _('error matches'),
							ZBX_PREPROC_MATCH_ERROR_NOT_REGEX => _('error does not match')
						]))
							->setAttribute('placeholder', _('error-matching'))
							->addClass('js-preproc-param-error-matching')
							->setValue($step_param_0_value)
							->setReadonly($readonly),
					$step_param_1
						->setAttribute('placeholder', _('pattern'))
						->setReadonly($readonly)
						->addClass(
							$step_param_0_value == ZBX_PREPROC_MATCH_ERROR_ANY ? ZBX_STYLE_VISIBILITY_HIDDEN : null
						)
				];
				break;


			case ZBX_PREPROC_SNMP_WALK_VALUE:
				$params = [
					$step_param_0->setAttribute('placeholder', _('OID')),
					(new CSelect('preprocessing['.$i.'][params][1]'))
						->setValue($step_param_1_value)
						->setAdaptiveWidth(202)
						->addOptions([
							new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
							new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
							new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
							new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
						])
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_SNMP_WALK_TO_JSON:
				$mapping_rows = [];
				$count = count($step['params']);

				for ($j = 0; $j < $count; $j += 3) {
					$mapping_rows[] = [
						(new CRow([
							new CCol(
								(new CTextBox('preprocessing['.$i.'][params][]', $step['params'][$j]))
									->setReadonly($readonly)
									->removeId()
									->setAttribute('placeholder', _('Field name'))
							),
							new CCol(
								(new CTextBox('preprocessing['.$i.'][params][]', $step['params'][$j + 1]))
									->setReadonly($readonly)
									->removeId()
									->setAttribute('placeholder', _('OID prefix'))
							),
							new CCol(
								(new CSelect('preprocessing['.$i.'][params][]'))
									->setValue($step['params'][$j + 2])
									->setWidth(ZBX_TEXTAREA_PREPROC_TREAT_SELECT)
									->addOptions([
										new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
										new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
									])
									->setReadonly($readonly)
							),
							(new CCol(
								(new CButtonLink(_('Remove')))
									->addClass('js-group-json-action-delete')
									->setEnabled(!$readonly && $count > 3)
							))->addClass(ZBX_STYLE_NOWRAP)
						]))->addClass('group-json-row')
					];
				}

				$params = (new CDiv())
					->addItem([
						(new CTable())
							->addClass('group-json-mapping')
							->setHeader(
								(new CRowHeader([
									new CColHeader(_('Field name')),
									new CColHeader(_('OID prefix')),
									new CColHeader(_('Format')),
									(new CColHeader(''))->addClass(ZBX_STYLE_NOWRAP)
								]))->addClass(ZBX_STYLE_GREY)
							)
							->addItem($mapping_rows)
							->addItem(
								(new CTag('tfoot', true))
									->addItem(
										(new CCol(
											(new CButtonLink(_('Add')))
												->addClass('js-group-json-action-add')
												->setEnabled(!$readonly)
										))->setColSpan(4)
									)
							)
							->setAttribute('data-index', $i)
					])->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR);
				break;

			case ZBX_PREPROC_SNMP_GET_VALUE:
				$params = (new CSelect('preprocessing['.$i.'][params][0]'))
					->setValue($step_param_0_value)
					->setAdaptiveWidth(202)
					->addOptions([
						new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
					])
					->setReadonly($readonly);
				break;
		}

		// Create checkbox "Custom on fail" and enable or disable depending on preprocessing type.
		$on_fail = new CCheckBox('preprocessing['.$i.'][on_fail]');

		switch ($step['type']) {
			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
			case ZBX_PREPROC_THROTTLE_VALUE:
			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			case ZBX_PREPROC_SCRIPT:
			case ZBX_PREPROC_STR_REPLACE:
				$on_fail->setEnabled(false);
				break;

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				$on_fail
					->setReadonly(true)
					->setChecked(true);
				break;

			default:
				$on_fail->setReadonly($readonly);

				if ($step['error_handler'] != ZBX_PREPROC_FAIL_DEFAULT) {
					$on_fail->setChecked(true);
				}
				break;
		}

		$error_handler = (new CRadioButtonList('preprocessing['.$i.'][error_handler]',
			($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT)
				? ZBX_PREPROC_FAIL_DISCARD_VALUE
				: (int) $step['error_handler']
		))
			->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
			->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
			->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
			->setModern(true);

		$error_handler_params = (new CTextBox('preprocessing['.$i.'][error_handler_params]',
			$step['error_handler_params'])
		)->setTitle($step['error_handler_params']);

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT) {
			$error_handler->setEnabled(false);
		}

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT
				|| $step['error_handler'] == ZBX_PREPROC_FAIL_DISCARD_VALUE) {
			$error_handler_params
				->setEnabled(false)
				->addStyle('display: none;');
		}

		$on_fail_options = (new CDiv([
			new CLabel(_('Custom on fail')),
			$error_handler->setReadonly($readonly),
			$error_handler_params->setReadonly($readonly)
		]))->addClass('on-fail-options');

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT) {
			$on_fail_options->addStyle('display: none;');
		}

		$preprocessing_list->addItem(
			(new CListItem([
				(new CDiv([
					(new CDiv(new CVar('preprocessing['.$i.'][sortorder]', $step['sortorder'])))
						->addClass(ZBX_STYLE_DRAG_ICON),
					(new CDiv($preproc_types_select))
						->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)
						->addClass('step-name'),
					(new CDiv($params))->addClass('step-parameters'),
					(new CDiv($on_fail))->addClass('step-on-fail'),
					(new CDiv([
						(new CButton('preprocessing['.$i.'][test]', _('Test')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('preprocessing-step-test')
							->removeId(),
						(new CButton('preprocessing['.$i.'][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
							->setEnabled(!$readonly)
							->removeId()
					]))->addClass('step-action')
				]))->addClass('preprocessing-step'),
				$on_fail_options
			]))
				->addClass('preprocessing-list-item')
				->setAttribute('data-step', $i)
		);

		$i++;
	}

	$preprocessing_list->addItem(
		(new CListItem([
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$readonly)
			))->addClass('step-action'),
			(new CDiv(
				(new CButton('preproc_test_all', _('Test all steps')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addStyle(($i > 0) ? null : 'display: none')
			))->addClass('step-action')
		]))->addClass('preprocessing-list-foot')
	);

	return $preprocessing_list;
}

/**
 * Prepares data to copy items/triggers/graphs.
 *
 * @param string      $elements_field
 * @param null|string $title
 *
 * @return array
 */
function getCopyElementsFormData($elements_field, $title = null) {
	$data = [
		'title' => $title,
		'elements_field' => $elements_field,
		'elements' => getRequest($elements_field, []),
		'copy_type' => getRequest('copy_type', COPY_TYPE_TO_TEMPLATE_GROUP),
		'copy_targetids' => getRequest('copy_targetids', []),
		'hostid' => 0
	];

	$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';
	$filter_hostids = getRequest('filter_hostids', CProfile::getArray($prefix.'triggers.filter_hostids', []));

	if (count($filter_hostids) == 1) {
		$data['hostid'] = reset($filter_hostids);
	}

	if (!$data['elements'] || !is_array($data['elements'])) {
		show_error_message(_('Incorrect list of items.'));

		return $data;
	}

	if ($data['copy_targetids']) {
		switch ($data['copy_type']) {
			case COPY_TYPE_TO_HOST_GROUP:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $data['copy_targetids'],
					'editable' => true
				]), ['groupid' => 'id']);
				break;

			case COPY_TYPE_TO_HOST:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $data['copy_targetids'],
					'editable' => true
				]), ['hostid' => 'id']);
				break;

			case COPY_TYPE_TO_TEMPLATE:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $data['copy_targetids'],
					'editable' => true
				]), ['templateid' => 'id']);
				break;

			case COPY_TYPE_TO_TEMPLATE_GROUP:
				$data['copy_targetids'] = CArrayHelper::renameObjectsKeys(API::TemplateGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $data['copy_targetids'],
					'editable' => true
				]), ['groupid' => 'id']);
				break;
		}
	}

	return $data;
}

function getTriggerMassupdateFormData() {
	$data = [
		'visible' => getRequest('visible', []),
		'dependencies' => getRequest('dependencies', []),
		'tags' => getRequest('tags', []),
		'mass_update_tags' => getRequest('mass_update_tags', ZBX_ACTION_ADD),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
		'massupdate' => getRequest('massupdate', 1),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'g_triggerid' => getRequest('g_triggerid', []),
		'priority' => getRequest('priority', 0),
		'hostid' => getRequest('hostid', 0),
		'context' => getRequest('context')
	];

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		]);

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			]);
			$data['dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
		}
		else {
			$data['dependencies'] = $dependencyTriggers;
		}
	}

	foreach ($data['dependencies'] as &$dependency) {
		order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
	}
	unset($dependency);

	order_result($data['dependencies'], 'description', ZBX_SORT_UP);

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	return $data;
}

/**
 * Renders tag table row.
 *
 * @param int|string $index
 * @param array	     $tag
 * @param string     $tag['tag']                      Tag name.
 * @param string     $tag['value']                    Tag value.
 * @param int        $tag['type']                     (optional) Tag ownership type.
 * @param int        $tag['automatic']                (optional) Tag automatic flag.
 * @param array      $tag['parent_templates']         (optional) List of templates that tags are inherited from.
 * @param array      $options
 * @param bool       $options['add_post_js']          (optional) Parameter passed to CTextAreaFlexible.
 * @param bool       $options['show_inherited_tags']  (optional) Render row in inherited tag mode. This enables usage of $tag['type'].
 * @param bool       $options['with_automatic']       (optional) Render row with 'automatic' input. This enables usage of $tag['automatic'].
 * @param string     $options['field_name']           (optional) Re-define default field name.
 * @param bool       $options['readonly']             (optional) Render row in read-only mode.
 * @param string     $options['source']               (optional) The origin of tag.
 *
 * @return CRow
 */
function renderTagTableRow($index, array $tag, array $options = []) {
	$options += [
		'readonly' => false,
		'field_name' => 'tags',
		'with_automatic' => false,
		'show_inherited_tags' => false,
		'source' => null
	];

	if ($options['with_automatic'] && !array_key_exists('automatic', $tag)) {
		$tag['automatic'] = ZBX_TAG_MANUAL;
	}

	$textarea_options = array_intersect_key($options, array_flip(['readonly', 'add_post_js']));

	$tag += [
		'type' => ZBX_PROPERTY_OWN,
		'parent_templates' => []
	];

	$tag_field = (new CTextAreaFlexible($options['field_name'].'['.$index.'][tag]', $tag['tag'], $textarea_options))
		->setAdaptiveWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setAttribute('placeholder', _('tag'));

	$type_field = $options['show_inherited_tags']
		? new CVar($options['field_name'].'['.$index.'][type]', $tag['type'])
		: null;

	$automatic_field = $options['with_automatic']
		? new CVar($options['field_name'].'['.$index.'][automatic]', $tag['automatic'])
		: null;

	$value_field = (new CTextAreaFlexible($options['field_name'].'['.$index.'][value]', $tag['value'],
			$textarea_options
		))
		->setAdaptiveWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
		->setAttribute('placeholder', _('value'));

	if ($options['with_automatic'] && $tag['automatic'] == ZBX_TAG_AUTOMATIC) {
		switch ($options['source']) {
			case 'host':
				$actions = (new CSpan(_('(created by host discovery)')))->addClass(ZBX_STYLE_GREY);
				break;

			default:
				$actions = null;
				break;
		}
	}
	else {
		$actions = $options['show_inherited_tags'] && ($tag['type'] & ZBX_PROPERTY_INHERITED) != 0
			? (new CButton($options['field_name'].'['.$index.'][disable]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-disable')
				->setEnabled(!$options['readonly'])
			: (new CButton($options['field_name'].'['.$index.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$options['readonly']);
	}

	return (new CRow([
		(new CCol([$tag_field, $type_field, $automatic_field]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($value_field))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($actions))
			->addClass(ZBX_STYLE_NOWRAP)
			->addClass(ZBX_STYLE_TOP),
		$options['show_inherited_tags']
			? new CCol(makeParentTemplatesList($tag['parent_templates']))
			: null
	]))->addClass('form_row');
}

/**
 * Function to render templates as HTML links or span tags, based on user permissions to edit each particular template.
 */
function makeParentTemplatesList(array $parent_templates): array {
	if (!$parent_templates) {
		return [];
	}

	CArrayHelper::sort($parent_templates, ['name']);

	$allowed_ui_conf_templates = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	$template_list = [];

	foreach ($parent_templates as $templateid => $template) {
		if ($allowed_ui_conf_templates && $template['permission'] == PERM_READ_WRITE) {
			$template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $templateid)
				->getUrl();

			$template_list[] = new CLink($template['name'], $template_url);
		}
		else {
			$template_list[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}

		$template_list[] = ', ';
	}

	array_pop($template_list);

	return $template_list;
}

/**
 * Renders tag table.
 *
 * @param array  $tags
 * @param array  $tags[]['tag']
 * @param array  $tags[]['value']
 * @param bool   $readonly         (optional)
 *
 * @return CTable
 */
function renderTagTable(array $tags, $readonly = false, array $options = []) {
	$table = (new CTable())
		->addStyle('width: 100%; max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER);

	$with_automatic = array_key_exists('with_automatic', $options) && $options['with_automatic'];

	$row_options = [
		'readonly' => $readonly,
		'with_automatic' => $with_automatic
	];

	if (array_key_exists('field_name', $options)) {
		$row_options['field_name'] = $options['field_name'];
	}

	foreach ($tags as $index => $tag) {
		$tag = ['automatic' => $with_automatic ? $tag['automatic'] : ZBX_TAG_MANUAL] + $tag;

		$table->addRow(renderTagTableRow($index, $tag, $row_options));
	}

	return $table->setFooter(new CCol(
		(new CButton('tag_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled(!$readonly)
	));
}
