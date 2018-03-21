<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Discovery rules'))
	->addItem(get_header_host_table('discoveries', $data['hostid'],
		array_key_exists('itemid', $data) ? $data['itemid'] : 0
	));

// create form
$itemForm = (new CForm())
	->setName('itemForm')
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid']);

if (!empty($this->data['itemid'])) {
	$itemForm->addVar('itemid', $this->data['itemid']);
}

// create form list
$itemFormList = new CFormList('itemFormList');
if (!empty($this->data['templates'])) {
	$itemFormList->addRow(_('Parent discovery rules'), $this->data['templates']);
}

$itemFormList->addRow(
	(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
	(new CTextBox('name', $this->data['name'], $this->data['limited']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
);

// append type to form list
if ($this->data['limited']) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow((new CLabel(_('Type'), 'typename')),
		(new CTextBox('typename', item_type2str($this->data['type']), true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow((new CLabel(_('Type'), 'type')),
		(new CComboBox('type', $this->data['type']))->addItems($this->data['types'])
	);
}

// append key to form list
$itemFormList->addRow(
	(new CLabel(_('Key'), 'key'))->setAsteriskMark(),
	(new CTextBox('key', $this->data['key'], $this->data['limited']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
);

// ITEM_TYPE_HTTPAGENT URL field.
$itemFormList->addRow(
	(new CLabel(_('URL'), 'url'))->setAsteriskMark(),
	[
		(new CTextBox('url', $data['url'], $data['limited'], DB::getFieldLength('items', 'url')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('httpcheck_parseurl', _('Parse')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited'])
			->setAttribute('data-action', 'parse_url')
	],
	'url_row'
);

// ITEM_TYPE_HTTPAGENT Query fields.
$query_fields_data = [];

if (is_array($data['query_fields']) && $data['query_fields']) {
	foreach ($data['query_fields'] as $pair) {
		$query_fields_data[] = ['key' => key($pair), 'value' => reset($pair)];
	}
}
else if (!$data['limited']) {
	$query_fields_data[] = ['key' => '', 'value' => ''];
}

$query_fields = (new CTag('script', true))->setAttribute('type', 'text/json');
$query_fields->items = [json_encode($query_fields_data, JSON_UNESCAPED_UNICODE)];

$itemFormList->addRow(
	new CLabel(_('Query fields'), 'query_fields_pairs'),
	(new CDiv([
		(new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
			->setFooter(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled(!$data['limited'])
						->setAttribute('data-row-action', 'add_row')
				))->setColSpan(5)
			)),
		(new CTag('script', true))
			->setAttribute('type', 'text/x-jquery-tmpl')
			->addItem(new CRow([
				(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CTextBox('query_fields[key][#{index}]', '#{key}', $data['limited']))
					->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
				'&rArr;',
				(new CTextBox('query_fields[value][#{index}]', '#{value}', $data['limited']))
					->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled(!$data['limited'])
					->setAttribute('data-row-action', 'remove_row')
			])),
		$query_fields
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setId('query_fields_pairs')
		->setAttribute('data-sortable-pairs-table',  $data['limited'] ? '0' : '1')
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
	'query_fields_row'
);

// ITEM_TYPE_HTTPAGENT Request type.
$request_method = $data['limited'] ? [new CVar('request_method', $data['request_method'])] : [];
$request_method[] = (new CComboBox($data['limited'] ? '' : 'request_method', $data['request_method'], null, [
	HTTPCHECK_REQUEST_GET => 'GET',
	HTTPCHECK_REQUEST_POST => 'POST',
	HTTPCHECK_REQUEST_PUT => 'PUT',
	HTTPCHECK_REQUEST_HEAD => 'HEAD'
]))->setEnabled(!$data['limited']);
$itemFormList->addRow(new CLabel(_('Request type'), 'request_method'), $request_method, 'request_method_row');

// ITEM_TYPE_HTTPAGENT Timeout field.
$itemFormList->addRow(
	new CLabel(_('Timeout'), 'timeout'),
	(new CTextBox('timeout', $data['timeout'], $data['limited']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'timeout_row'
);

// ITEM_TYPE_HTTPAGENT Request body type.
$itemFormList->addRow(
	new CLabel(_('Request body type'), 'post_type'),
	(new CRadioButtonList('post_type', (int) $data['post_type']))
		->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
		->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
		->addValue(_('XML data'), ZBX_POSTTYPE_XML)
		->setEnabled(!$data['limited'])
		->setModern(true),
	'post_type_row'
);

// ITEM_TYPE_HTTPAGENT Request body.
$itemFormList->addRow(
	new CLabel(_('Request body'), 'posts'),
	(new CTextArea('posts', $data['posts'], ['readonly' =>  $data['limited']]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'posts_row'
);

// ITEM_TYPE_HTTPAGENT Headers fields.
$headers_data = [];

if (is_array($data['headers']) && $data['headers']) {
	foreach ($data['headers'] as $pair) {
		$headers_data[] = ['key' => key($pair), 'value' => reset($pair)];
	}
}
else if (!$data['limited']) {
	$headers_data[] = ['key' => '', 'value' => ''];
}
$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [json_encode($headers_data, JSON_UNESCAPED_UNICODE)];

$itemFormList->addRow(
	new CLabel(_('Headers'), 'headers_pairs'),
	(new CDiv([
		(new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
			->setFooter(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled(!$data['limited'])
						->setAttribute('data-row-action', 'add_row')
				))->setColSpan(5)
			)),
		(new CTag('script', true))
			->setAttribute('type', 'text/x-jquery-tmpl')
			->addItem(new CRow([
				(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CTextBox('headers[key][#{index}]', '#{key}', $data['limited']))->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
				'&rArr;',
				(new CTextBox('headers[value][#{index}]', '#{value}', $data['limited']))
					->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled(!$data['limited'])
					->setAttribute('data-row-action', 'remove_row')
			])),
		$headers
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setId('headers_pairs')
		->setAttribute('data-sortable-pairs-table', $data['limited'] ? '0' : '1')
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
	'headers_row'
);

// ITEM_TYPE_HTTPAGENT Required status codes.
$itemFormList->addRow(
	new CLabel(_('Required status codes'), 'status_codes'),
	(new CTextBox('status_codes', $data['status_codes'], $data['limited']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'status_codes_row'
);

// ITEM_TYPE_HTTPAGENT Follow redirects.
$itemFormList->addRow(
	new CLabel(_('Follow redirects'), 'follow_redirects'),
	(new CCheckBox('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON))
		->setEnabled(!$data['limited'])
		->setChecked($data['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON),
	'follow_redirects_row'
);

// ITEM_TYPE_HTTPAGENT Retrieve mode.
$itemFormList->addRow(
	new CLabel(_('Retrieve mode'), 'retrieve_mode'),
	(new CRadioButtonList('retrieve_mode', (int) $data['retrieve_mode']))
		->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
		->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
		->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
		->setEnabled(!$data['limited'])
		->setModern(true),
	'retrieve_mode_row'
);

// ITEM_TYPE_HTTPAGENT Enable trapping.
$itemFormList->addRow(
	new CLabel(_('Enable trapping'), 'allow_traps'),
	(new CCheckBox('allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
		->setEnabled(!$data['limited'])
		->setChecked($data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON),
	'allow_traps_row'
);

// ITEM_TYPE_HTTPAGENT HTTP proxy.
$itemFormList->addRow(
	new CLabel(_('HTTP proxy'), 'http_proxy'),
	(new CTextBox('http_proxy', $data['http_proxy'], $data['limited'], DB::getFieldLength('items', 'http_proxy')))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('placeholder', 'http://[user[:password]@]proxy.example.com[:port]'),
	'http_proxy_row'
);

// ITEM_TYPE_HTTPAGENT HTTP authentication.
$itemFormList->addRow(
	new CLabel(_('HTTP authentication'), 'http_authtype'),
	$data['limited']
	? [
		new CVar('http_authtype', $data['http_authtype'], 'http_authtype'),
		(new CTextBox('http_typename', itemHttpAuthType($data['http_authtype']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	]
	: new CComboBox('http_authtype', $data['http_authtype'], null, itemHttpAuthType()),
	'http_authtype_row'
);

// ITEM_TYPE_HTTPAGENT User name.
$itemFormList->addRow(
	(new CLabel(_('User name'), 'http_username'))->setAsteriskMark(),
	(new CTextBox('http_username', $data['http_username'], $data['limited'], DB::getFieldLength('items', 'username')))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'http_username_row'
);

// ITEM_TYPE_HTTPAGENT Password.
$itemFormList->addRow(
	(new CLabel(_('Password'), 'http_password'))->setAsteriskMark(),
	(new CTextBox('http_password', $data['http_password'], $data['limited'], DB::getFieldLength('items', 'password')))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'http_password_row'
);

// ITEM_TYPE_HTTPAGENT SSL verify peer.
$itemFormList->addRow(
	new CLabel(_('SSL verify peer'), 'verify_peer'),
	(new CCheckBox('verify_peer', HTTPTEST_VERIFY_PEER_ON))
		->setEnabled(!$data['limited'])
		->setChecked($data['verify_peer'] == HTTPTEST_VERIFY_PEER_ON),
	'verify_peer_row'
);

// ITEM_TYPE_HTTPAGENT SSL verify host.
$itemFormList->addRow(
	new CLabel(_('SSL verify host'), 'verify_host'),
	(new CCheckBox('verify_host', HTTPTEST_VERIFY_HOST_ON))
		->setEnabled(!$data['limited'])
		->setChecked($data['verify_host'] == HTTPTEST_VERIFY_HOST_ON),
	'verify_host_row'
);

// ITEM_TYPE_HTTPAGENT SSL certificate file.
$itemFormList->addRow(
	new CLabel(_('SSL certificate file'), 'ssl_cert_file'),
	(new CTextBox('ssl_cert_file', $data['ssl_cert_file'], $data['limited'],
		DB::getFieldLength('items', 'ssl_cert_file')
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'ssl_cert_file_row'
);

// ITEM_TYPE_HTTPAGENT SSL key file.
$itemFormList->addRow(
	new CLabel(_('SSL key file'), 'ssl_key_file'),
	(new CTextBox('ssl_key_file', $data['ssl_key_file'], $data['limited'], DB::getFieldLength('items', 'ssl_key_file')))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'ssl_key_file_row'
);

// ITEM_TYPE_HTTPAGENT SSL key password.
$itemFormList->addRow(
	new CLabel(_('SSL key password'), 'ssl_key_password'),
	(new CTextBox('ssl_key_password', $data['ssl_key_password'], $data['limited'],
		DB::getFieldLength('items', 'ssl_key_password')
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'ssl_key_password_row'
);

// append interfaces to form list
if (!empty($this->data['interfaces'])) {
	$interfaces_combobox = (new CComboBox('interfaceid', $data['interfaceid']))->setAriaRequired();

	// Set up interface groups sorted by priority.
	$interface_types = zbx_objectValues($this->data['interfaces'], 'type');
	$interface_groups = [];
	foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $interface_type) {
		if (in_array($interface_type, $interface_types)) {
			$interface_groups[$interface_type] = new COptGroup(interfaceType2str($interface_type));
		}
	}

	// add interfaces to groups
	foreach ($this->data['interfaces'] as $interface) {
		$option = new CComboItem($interface['interfaceid'],
			$interface['useip']
				? $interface['ip'].' : '.$interface['port']
				: $interface['dns'].' : '.$interface['port'],
			($interface['interfaceid'] == $data['interfaceid'])
		);
		$option->setAttribute('data-interfacetype', $interface['type']);
		$interface_groups[$interface['type']]->addItem($option);
	}
	foreach ($interface_groups as $interface_group) {
		$interfaces_combobox->addItem($interface_group);
	}

	$span = (new CSpan(_('No interface found')))
		->addClass(ZBX_STYLE_RED)
		->setId('interface_not_defined')
		->setAttribute('style', 'display: none;');

	$itemFormList->addRow((new CLabel(_('Host interface'), 'interfaceid'))->setAsteriskMark(),
		[$interfaces_combobox, $span], 'interface_row'
	);
	$itemForm->addVar('selectedInterfaceId', $data['interfaceid']);
}
$itemFormList->addRow(
	(new CLabel(_('SNMP OID'), 'snmp_oid'))->setAsteriskMark(),
	(new CTextBox('snmp_oid', $this->data['snmp_oid'], $this->data['limited'], 512))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	(new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_contextname'
);
$itemFormList->addRow(
	(new CLabel(_('SNMP community'), 'snmp_community'))->setAsteriskMark(),
	(new CTextBox('snmp_community', $this->data['snmp_community'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	(new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_securityname'
);

// append snmpv3 security level to form list
$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel'], null, [
	ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
]);
$itemFormList->addRow(_('Security level'), $securityLevelComboBox, 'row_snmpv3_securitylevel');
$itemFormList->addRow((new CLabel(_('Authentication protocol'), 'snmpv3_authprotocol')),
	(new CRadioButtonList('snmpv3_authprotocol', (int) $this->data['snmpv3_authprotocol']))
		->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
		->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
		->setModern(true),
	'row_snmpv3_authprotocol'
);
$itemFormList->addRow(_('Authentication passphrase'),
	(new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_authpassphrase'
);
$itemFormList->addRow((new CLabel(_('Privacy protocol'), 'snmpv3_privprotocol')),
	(new CRadioButtonList('snmpv3_privprotocol', (int) $this->data['snmpv3_privprotocol']))
		->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
		->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
		->setModern(true),
	'row_snmpv3_privprotocol'
);
$itemFormList->addRow(_('Privacy passphrase'),
	(new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	(new CTextBox('port', $this->data['port'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH), 'row_port'
);
$itemFormList->addRow(
	(new CLabel(_('IPMI sensor'), 'ipmi_sensor'))->setAsteriskMark(),
	(new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], $this->data['limited'], 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_ipmi_sensor'
);

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype'], null, [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
]);
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, 'row_authtype');
$itemFormList->addRow((new CLabel(_('JMX endpoint'), 'jmx_endpoint'))->setAsteriskMark(),
	(new CTextBox('jmx_endpoint', $data['jmx_endpoint'], false, 255))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_jmx_endpoint'
);
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $this->data['username'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_username'
);
$itemFormList->addRow(
	(new CLabel(_('Public key file'), 'publickey'))->setAsteriskMark(),
	(new CTextBox('publickey', $this->data['publickey'], false, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_publickey'
);
$itemFormList->addRow(
	(new CLabel(_('Private key file'), 'privatekey'))->setAsteriskMark(),
	(new CTextBox('privatekey', $this->data['privatekey'], false, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	(new CTextBox('password', $this->data['password'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_password'
);
$itemFormList->addRow(
	(new CLabel(_('Executed script'), 'params_es'))->setAsteriskMark(),
	(new CTextArea('params_es', $this->data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'label_executed_script'
);
$itemFormList->addRow(
	(new CLabel(_('SQL query'), 'params_ap'))->setAsteriskMark(),
	(new CTextArea('params_ap', $this->data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'label_params'
);

$itemFormList->addRow((new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
	(new CTextBox('delay', $data['delay']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_delay'
);

// Append delay_flex to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay']))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period']))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', ''))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', ''))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', ''))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule']))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$delayFlexTable->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

$delayFlexTable->addRow([(new CButton('interval_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')
]);

$itemFormList->addRow(_('Custom intervals'),
		(new CDiv($delayFlexTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
		'row_flex_intervals'
	)
	->addRow((new CLabel(_('Keep lost resources period'), 'lifetime'))->setAsteriskMark(),
		(new CTextBox('lifetime', $data['lifetime']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('Allowed hosts'),
		(new CTextBox('trapper_hosts', $this->data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'row_trapper_hosts'
	)
	->addRow(_('Description'),
		(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// status
$enabledCheckBox = (new CCheckBox('status', ITEM_STATUS_ACTIVE))->setChecked($this->data['status'] == ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

/*
 * Condition tab
 */
$conditionFormList = new CFormList();

// type of calculation
$conditionFormList->addRow(_('Type of calculation'),
	[
		new CComboBox('evaltype', $this->data['evaltype'], null, [
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or'),
			CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
		]),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan(''))
			->setId('expression'),
		(new CTextBox('formula', $this->data['formula']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('formula')
			->setAttribute('placeholder', 'A or (B and C) &hellip;')
	],
	'conditionRow'
);

// macros
$conditionTable = (new CTable())
	->setId('conditions')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), _('Action')]);

$conditions = $this->data['conditions'];
if (!$conditions) {
	$conditions = [['macro' => '', 'value' => '', 'formulaid' => num2letter(0)]];
}
else {
	$conditions = CConditionHelper::sortConditionsByFormulaId($conditions);
}

// fields
foreach ($conditions as $i => $condition) {
	// formula id
	$formulaId = [
		new CSpan($condition['formulaid']),
		new CVar('conditions['.$i.'][formulaid]', $condition['formulaid'])
	];

	// macro
	$macro = (new CTextBox('conditions['.$i.'][macro]', $condition['macro'], false, 64))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->addClass('macro')
		->setAttribute('placeholder', '{#MACRO}')
		->setAttribute('data-formulaid', $condition['formulaid']);

	// value
	$value = (new CTextBox('conditions['.$i.'][value]', $condition['value'], false, 255))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('regular expression'));

	// delete button
	$deleteButtonCell = [
		(new CButton('conditions_'.$i.'_remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];

	$row = [$formulaId, $macro, new CSpan(_('matches')), $value,
		(new CCol($deleteButtonCell))->addClass(ZBX_STYLE_NOWRAP)
	];
	$conditionTable->addRow($row, 'form_row');
}

$conditionTable->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$conditionFormList->addRow(_('Filters'),
	(new CDiv($conditionTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append tabs to form
$itemTab = (new CTabView())
	->addTab('itemTab', $this->data['caption'], $itemFormList)
	->addTab('macroTab', _('Filters'), $conditionFormList);
if (!hasRequest('form_refresh')) {
	$itemTab->setSelected(0);
}

// append buttons to form
if (!empty($this->data['itemid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] != HOST_STATUS_TEMPLATE) {
		$buttons[] = (new CSubmit('check_now', _('Check now')))
			->setEnabled(in_array($data['item']['type'], checkNowAllowedTypes())
					&& $data['item']['status'] == ITEM_STATUS_ACTIVE
					&& $data['host']['status'] == HOST_STATUS_MONITORED
			);
	}

	$buttons[] = (new CButtonDelete(_('Delete discovery rule?'), url_params(['form', 'itemid', 'hostid'])))
		->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_param('hostid'));

	$itemTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('hostid'))]
	));
}

$itemForm->addItem($itemTab);
$widget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.host.discovery.edit.js.php';

return $widget;
