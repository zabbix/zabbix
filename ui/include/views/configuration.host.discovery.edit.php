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
 * @var CView $this
 * @var array $data
 */

$widget = (new CWidget())
	->setTitle(_('Discovery rules'))
	->setNavigation(getHostNavigation('discoveries', $data['hostid'],
		array_key_exists('itemid', $data) ? $data['itemid'] : 0
	));

$url = (new CUrl('host_discovery.php'))
	->setArgument('context', $data['context'])
	->getUrl();

$form = (new CForm('post', $url))
	->setId('host-discovery-form')
	->setName('itemForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid'])
	->addVar('backurl', $data['backurl']);

if (!empty($data['itemid'])) {
	$form->addVar('itemid', $data['itemid']);
}

$item_tab = (new CFormGrid())->setId('itemFormList');

if (!empty($data['templates'])) {
	$item_tab->addItem([
		new CLabel(_('Parent discovery rules')),
		new CFormField($data['templates'])
	]);
}

$item_tab
	// Append name field to form list.
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField((new CTextBox('name', $data['name'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)
	])
	// Append type select to form list.
	->addItem([
		new CLabel(_('Type'), 'label-type'),
		new CFormField((new CSelect('type'))
			->setValue($data['type'])
			->setId('type')
			->setFocusableElementId('label-type')
			->addOptions(CSelect::createOptionsFromArray($data['types']))
			->setReadonly($data['limited'])
		)
	])
	// Append key to form list.
	->addItem([
		(new CLabel(_('Key'), 'key'))->setAsteriskMark(),
		new CFormField((new CTextBox('key', $data['key'], $data['limited'],
				DB::getFieldLength('item_discovery', 'key_')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		)
	])
	// Append ITEM_TYPE_HTTPAGENT URL field to form list.
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setAsteriskMark()
			->setId('js-item-url-label'),
		(new CFormField([
			(new CTextBox('url', $data['url'], $data['limited'], DB::getFieldLength('items', 'url')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('httpcheck_parseurl', _('Parse')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->setEnabled(!$data['limited'])
				->setAttribute('data-action', 'parse_url')
		]))->setId('js-item-url-field')
	]);

// Prepare ITEM_TYPE_HTTPAGENT query fields.
$query_fields_data = [];

if (is_array($data['query_fields']) && $data['query_fields']) {
	$i = 0;
	foreach ($data['query_fields'] as $pair) {
		$query_fields_data[] = [
			'name' => key($pair),
			'value' => reset($pair),
			'sortorder' => $i++
		];
	}
}
elseif (!$data['limited']) {
	$query_fields_data[] = [
		'name' => '',
		'value' => '',
		'sortorder' => 0
	];
}

$query_fields = (new CTag('script', true))->setAttribute('type', 'text/json');
$query_fields->items = [json_encode($query_fields_data)];

// Prepare ITEM_TYPE_SCRIPT parameters.
$parameters_data = [];

if ($data['parameters']) {
	$parameters_data = $data['parameters'];
}
elseif (!$data['limited']) {
	$parameters_data[] = ['name' => '', 'value' => ''];
}

$parameters_table = (new CTable())
	->setId('parameters_table')
	->setHeader([
		(new CColHeader(_('Name')))->setWidth('50%'),
		(new CColHeader(_('Value')))->setWidth('50%'),
		_('Action')
	])
	->setAttribute('style', 'width: 100%;');

if ($parameters_data) {
	foreach ($parameters_data as $parameter) {
		$parameters_table->addRow([
			(new CTextBox('parameters[name][]', $parameter['name'], $data['limited'],
				DB::getFieldLength('item_parameter', 'name'))
			)
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CTextBox('parameters[value][]', $parameter['value'], $data['limited'],
				DB::getFieldLength('item_parameter', 'value'))
			)
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CButton('', _('Remove')))
				->removeId()
				->onClick('jQuery(this).closest("tr").remove()')
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$data['limited'])
		]);
	}
}

$parameters_table->addRow([
	(new CButton('parameter_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$data['limited'])
]);

$item_tab
	// Append ITEM_TYPE_HTTPAGENT Query fields to form list.
	->addItem([
		(new CLabel(_('Query fields'), 'query_fields_pairs'))->setId('js-item-query-fields-label'),
		(new CFormField((new CDiv([
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
						(new CCol(
							(new CDiv(
								new CVar('query_fields[sortorder][#{index}]', '#{sortorder}')
							))->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextBox('query_fields[name][#{index}]', '#{name}', $data['limited']))
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						'&rArr;',
						(new CTextBox('query_fields[value][#{index}]', '#{value}', $data['limited']))
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
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
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
		))->setId('js-item-query-fields-field')
	])
	// Append ITEM_TYPE_SCRIPT parameters to form list.
	->addItem(
		(new CTag('script', true))
			->setId('parameters_table_row')
			->setAttribute('type', 'text/x-jquery-tmpl')
			->addItem(
				(new CRow([
					(new CTextBox('parameters[name][]', '', false, DB::getFieldLength('item_parameter', 'name')))
						->setAttribute('style', 'width: 100%;')
						->removeId(),
					(new CTextBox('parameters[value][]', '', false, DB::getFieldLength('item_parameter', 'value')))
						->setAttribute('style', 'width: 100%;')
						->removeId(),
					(new CButton('', _('Remove')))
						->removeId()
						->onClick('jQuery(this).closest("tr").remove()')
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				]))
			)
	)
	->addItem([
		(new CLabel(_('Parameters'), $parameters_table->getId()))->setId('js-item-parameters-label'),
		(new CFormField((new CDiv($parameters_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('js-item-parameters-field')
	])
	->addItem([
		(new CLabel(_('Script'), 'script'))
			->setAsteriskMark()
			->setId('js-item-script-label'),
		(new CFormField((new CMultilineInput('script', $data['params'], [
				'title' => _('JavaScript'),
				'placeholder' => _('script'),
				'placeholder_textarea' => 'return value',
				'grow' => 'auto',
				'rows' => 0,
				'maxlength' => DB::getFieldLength('items', 'params'),
				'readonly' => $data['limited']
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-script-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request type to form list.
	->addItem([
		(new CLabel(_('Request type'), 'label-request-method'))->setId('js-item-request-method-label'),
		(new CFormField((new CSelect('request_method'))
			->addOptions(CSelect::createOptionsFromArray([
				HTTPCHECK_REQUEST_GET => 'GET',
				HTTPCHECK_REQUEST_POST => 'POST',
				HTTPCHECK_REQUEST_PUT => 'PUT',
				HTTPCHECK_REQUEST_HEAD => 'HEAD'
			]))
			->setReadonly($data['limited'])
			->setFocusableElementId('label-request-method')
			->setId('request_method')
			->setValue($data['request_method'])
		))->setId('js-item-request-method-field')
	])
	// Append ITEM_TYPE_HTTPAGENT and ITEM_TYPE_SCRIPT timeout field to form list.
	->addItem([
		(new CLabel(_('Timeout'), 'timeout'))
			->setAsteriskMark()
			->setId('js-item-timeout-label'),
		(new CFormField((new CTextBox('timeout', $data['timeout'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-timeout-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request body type to form list.
	->addItem([
		(new CLabel(_('Request body type'), 'post_type'))->setId('js-item-post-type-label'),
		(new CFormField((new CRadioButtonList('post_type', (int) $data['post_type']))
			->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
			->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
			->addValue(_('XML data'), ZBX_POSTTYPE_XML)
			->setEnabled(!$data['limited'])
			->setModern(true)
		))->setId('js-item-post-type-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request body to form list.
	->addItem([
		(new CLabel(_('Request body'), 'posts'))->setId('js-item-posts-label'),
		(new CFormField((new CTextArea('posts', $data['posts'], ['readonly' =>  $data['limited']]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-posts-field')
	]);

// Append ITEM_TYPE_HTTPAGENT Headers fields to form list.
$headers_data = [];

if (is_array($data['headers']) && $data['headers']) {
	$i = 0;
	foreach ($data['headers'] as $pair) {
		$headers_data[] = [
			'name' => key($pair),
			'value' => reset($pair),
			'sortorder' => $i++
		];
	}
}
elseif (!$data['limited']) {
	$headers_data[] = [
		'name' => '',
		'value' => '',
		'sortorder' => 0
	];
}
$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [json_encode($headers_data)];

$item_tab
	->additem([
		(new CLabel(_('Headers'), 'headers_pairs'))->setId('js-item-headers-label'),
		(new CFormField((new CDiv([
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
						(new CCol(
							(new CDiv(
								new CVar('headers[sortorder][#{index}]', '#{sortorder}')
							))->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextBox('headers[name][#{index}]', '#{name}', $data['limited']))
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						'&rArr;',
						(new CTextBox('headers[value][#{index}]', '#{value}', $data['limited'], 2000))
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
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
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
		))->setId('js-item-headers-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Required status codes to form list.
	->addItem([
		(new CLabel(_('Required status codes'), 'status_codes'))->setId('js-item-status-codes-label'),
		(new CFormField((new CTextBox('status_codes', $data['status_codes'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-status-codes-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Follow redirects to form list.
	->addItem([
		(new CLabel(_('Follow redirects'), 'follow_redirects'))->setId('js-item-follow-redirects-label'),
		(new CFormField((new CCheckBox('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON))
			->setEnabled(!$data['limited'])
			->setChecked($data['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON)
		))->setId('js-item-follow-redirects-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Retrieve mode to form list.
	->addItem([
		(new CLabel(_('Retrieve mode'), 'retrieve_mode'))->setId('js-item-retrieve-mode-label'),
		(new CFormField((new CRadioButtonList('retrieve_mode', (int) $data['retrieve_mode']))
			->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
			->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
			->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
			->setEnabled(!($data['limited'] || $data['request_method'] == HTTPCHECK_REQUEST_HEAD))
			->setModern(true)
		))->setId('js-item-retrieve-mode-field')
	])
	// Append ITEM_TYPE_HTTPAGENT HTTP proxy to form list.
	->addItem([
		(new CLabel(_('HTTP proxy'), 'http_proxy'))->setId('js-item-http-proxy-label'),
		(new CFormField((new CTextBox('http_proxy', $data['http_proxy'], $data['limited'],
				DB::getFieldLength('items', 'http_proxy')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', '[protocol://][user[:password]@]proxy.example.com[:port]')
			->disableAutocomplete()
		))->setId('js-item-http-proxy-field')
	])
	// Append ITEM_TYPE_HTTPAGENT HTTP authentication to form list.
	->addItem([
		(new CLabel(_('HTTP authentication'), 'label-http-authtype'))->setId('js-item-http-authtype-label'),
		(new CFormField((new CSelect('http_authtype'))
			->setValue($data['http_authtype'])
			->setId('http_authtype')
			->setFocusableElementId('label-http-authtype')
			->addOptions(CSelect::createOptionsFromArray(httptest_authentications()))
			->setReadonly($data['limited'])
		))->setId('js-item-http-authtype-field')
	])
	// Append ITEM_TYPE_HTTPAGENT User name to form list.
	->addItem([
		(new CLabel(_('User name'), 'http_username'))->setId('js-item-http-username-label'),
		(new CFormField(
			(new CTextBox('http_username', $data['http_username'], $data['limited'],
				DB::getFieldLength('items', 'username')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-http-username-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Password to form list.
	->addItem([
		(new CLabel(_('Password'), 'http_password'))->setId('js-item-http-password-label'),
		(new CFormField(
			(new CTextBox('http_password', $data['http_password'], $data['limited'],
					DB::getFieldLength('items', 'password')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-http-password-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL verify peer to form list.
	->addItem([
		(new CLabel(_('SSL verify peer'), 'verify_peer'))->setId('js-item-verify-peer-label'),
		(new CFormField((new CCheckBox('verify_peer', HTTPTEST_VERIFY_PEER_ON))
			->setEnabled(!$data['limited'])
			->setChecked($data['verify_peer'] == HTTPTEST_VERIFY_PEER_ON)
		))->setId('js-item-verify-peer-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL verify host to form list.
	->addItem([
		(new CLabel(_('SSL verify host'), 'verify_host'))->setId('js-item-verify-host-label'),
		(new CFormField(
			(new CCheckBox('verify_host', HTTPTEST_VERIFY_HOST_ON))
				->setEnabled(!$data['limited'])
				->setChecked($data['verify_host'] == HTTPTEST_VERIFY_HOST_ON)
		))->setId('js-item-verify-host-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL certificate file to form list.
	->addItem([
		(new CLabel(_('SSL certificate file'), 'ssl_cert_file'))->setId('js-item-ssl-cert-file-label'),
		(new CFormField(
			(new CTextBox('ssl_cert_file', $data['ssl_cert_file'], $data['limited'],
				DB::getFieldLength('items', 'ssl_cert_file')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-cert-file-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL key file to form list.
	->addItem([
		(new CLabel(_('SSL key file'), 'ssl_key_file'))->setId('js-item-ssl-key-file-label'),
		(new CFormField(
			(new CTextBox('ssl_key_file', $data['ssl_key_file'], $data['limited'],
				DB::getFieldLength('items', 'ssl_key_file')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-key-file-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL key password to form list.
	->addItem([
		(new CLabel(_('SSL key password'), 'ssl_key_password'))->setId('js-item-ssl-key-password-label'),
		(new CFormField(
			(new CTextBox('ssl_key_password', $data['ssl_key_password'], $data['limited'],
				DB::getFieldLength('items', 'ssl_key_password')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-ssl-key-password-field')
	])
	// Append master item select to form list.
	->addItem([
		(new CLabel(_('Master item'), 'master_itemid_ms'))
			->setAsteriskMark()
			->setId('js-item-master-item-label'),
		(new CFormField((new CMultiSelect([
				'name' => 'master_itemid',
				'object_name' => 'items',
				'multiple' => false,
				'disabled' => $data['limited'],
				'data' => ($data['master_itemid'] > 0)
					? [
						[
							'id' => $data['master_itemid'],
							'prefix' => $data['host']['name'].NAME_DELIMITER,
							'name' => $data['master_itemname']
						]
					]
					: [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'master_itemid',
						'hostid' => $data['hostid'],
						'webitems' => true,
						'normal_only' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-master-item-field')
	]);

// Append interfaces to form list.
$select_interface = getInterfaceSelect($data['interfaces'])
	->setId('interface-select')
	->setValue($data['interfaceid'])
	->addClass(ZBX_STYLE_ZSELECT_HOST_INTERFACE)
	->setFocusableElementId('interfaceid')
	->setAriaRequired();

if ($data['display_interfaces']) {
	$item_tab->addItem([
		(new CLabel(_('Host interface'), $select_interface->getFocusableElementId()))
			->setAsteriskMark()
			->setId('js-item-interface-label'),
		(new CFormField([
			$select_interface,
			(new CSpan(_('No interface found')))
				->addClass(ZBX_STYLE_RED)
				->setId('interface_not_defined')
				->setAttribute('style', 'display: none;')
		]))->setId('js-item-interface-field')
	]);
	$form->addVar('selectedInterfaceId', $data['interfaceid']);
}

$item_tab
	->addItem([
		(new CLabel(_('SNMP OID'), 'snmp_oid'))
			->setAsteriskMark()
			->setId('js-item-snmp-oid-label'),
		(new CFormField((new CTextBox('snmp_oid', $data['snmp_oid'], $data['limited'], 512))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', '[IF-MIB::]ifInOctets.1')
			->setAriaRequired()
		))->setId('js-item-snmp-oid-field')
	]);

$item_tab
	->addItem([
		(new CLabel(_('IPMI sensor'), 'ipmi_sensor'))->setId('js-item-impi-sensor-label'),
		(new CFormField((new CTextBox('ipmi_sensor', $data['ipmi_sensor'], $data['limited'], 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-impi-sensor-field')
	])
	// Append authentication method to form list.
	->addItem([
		(new CLabel(_('Authentication method'), 'label-authtype'))->setId('js-item-authtype-label'),
		(new CFormField((new CSelect('authtype'))
			->setId('authtype')
			->setFocusableElementId('label-authtype')
			->setValue($data['authtype'])
			->addOption(new CSelectOption(ITEM_AUTHTYPE_PASSWORD, _('Password')))
			->addOption(new CSelectOption(ITEM_AUTHTYPE_PUBLICKEY, _('Public key')))
		))->setId('js-item-authtype-field')
	])
	->addItem([
		(new CLabel(_('JMX endpoint'), 'jmx_endpoint'))
			->setAsteriskMark()
			->setId('js-item-jmx-endpoint-label'),
		(new CFormField((new CTextBox('jmx_endpoint', $data['jmx_endpoint'], false, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		))->setId('js-item-jmx-endpoint-field')
	])
	->addItem([
		(new CLabel(_('User name'), 'username'))->setId('js-item-username-label'),
		(new CFormField((new CTextBox('username', $data['username'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete()
		))->setId('js-item-username-field')
	])
	->addItem([
		(new CLabel(_('Public key file'), 'publickey'))
			->setAsteriskMark()
			->setId('js-item-public-key-label'),
		(new CFormField((new CTextBox('publickey', $data['publickey'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-public-key-field')
	])
	->addItem([
		(new CLabel(_('Private key file'), 'privatekey'))
			->setAsteriskMark()
			->setId('js-item-private-key-label'),
		(new CFormField((new CTextBox('privatekey', $data['privatekey'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-private-key-field')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))->setId('js-item-password-label'),
		(new CFormField((new CTextBox('password', $data['password'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete()
		))->setId('js-item-password-field')
	])
	->addItem([
		(new CLabel(_('Executed script'), 'params_es'))
			->setAsteriskMark()
			->setId('js-item-executed-script-label'),
		(new CFormField((new CTextArea('params_es', $data['params']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		))->setId('js-item-executed-script-field')
	])
	->addItem([
		(new CLabel(_('SQL query'), 'params_ap'))
			->setAsteriskMark()
			->setId('js-item-sql-query-label'),
		(new CFormField((new CTextArea('params_ap', $data['params']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		))->setId('js-item-sql-query-field')
	])
	->addItem([
		(new CLabel(_('Update interval'), 'delay'))
			->setAsteriskMark()
			->setId('js-item-delay-label'),
		(new CFormField((new CTextBox('delay', $data['delay']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-delay-field')
	]);

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

$item_tab
	->addItem([
		(new CLabel(_('Custom intervals')))->setId('js-item-flex-intervals-label'),
		(new CFormField((new CDiv($delayFlexTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('js-item-flex-intervals-field')
	])
	->addItem([
		(new CLabel(_('Keep lost resources period'), 'lifetime'))->setAsteriskMark(),
		new CFormField((new CTextBox('lifetime', $data['lifetime']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Enable trapping'), 'allow_traps'))->setId('js-item-allow-traps-label'),
		(new CFormField((new CCheckBox('allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
			->setChecked($data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON)
		))->setId('js-item-allow-traps-field')
	])
	->addItem([
		(new CLabel(_('Allowed hosts'), 'trapper_hosts'))->setId('js-item-trapper-hosts-label'),
		(new CFormField((new CTextBox('trapper_hosts', $data['trapper_hosts']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-trapper-hosts-field')
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField((new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField((new CCheckBox('status', ITEM_STATUS_ACTIVE))->setChecked($data['status'] == ITEM_STATUS_ACTIVE))
	]);

/*
 * Condition tab.
 */
$condition_tab = new CFormGrid();

// type of calculation
$condition_tab->addItem([
	(new CLabel(_('Type of calculation'), 'label-evaltype'))->setId('js-item-condition-label'),
	(new CFormField([
		(new CSelect('evaltype'))
			->setFocusableElementId('label-evaltype')
			->setId('evaltype')
			->setValue($data['evaltype'])
			->addOptions(CSelect::createOptionsFromArray([
				CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
				CONDITION_EVAL_TYPE_AND => _('And'),
				CONDITION_EVAL_TYPE_OR => _('Or'),
				CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
			])),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan(''))
			->setId('expression'),
		(new CTextBox('formula', $data['formula']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('formula')
			->setAttribute('placeholder', 'A or (B and C) &hellip;')
	]))->setId('js-item-condition-field')
]);

// macros
$condition_table = (new CTable())
	->setId('conditions')
	->addStyle('width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), _('Action')]);

$conditions = $data['conditions'];

if (!$conditions) {
	$conditions = [[
		'macro' => '',
		'operator' => CONDITION_OPERATOR_REGEXP,
		'value' => '',
		'formulaid' => num2letter(0)
	]];
}
else {
	$conditions = CConditionHelper::sortConditionsByFormulaId($conditions);
}

$operators = CSelect::createOptionsFromArray([
	CONDITION_OPERATOR_REGEXP => _('matches'),
	CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
	CONDITION_OPERATOR_EXISTS => _('exists'),
	CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
]);

// fields
foreach ($conditions as $i => $condition) {
	// formula id
	$formulaid = [
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

	$operator_select = (new CSelect('conditions['.$i.'][operator]'))
		->setValue($condition['operator'])
		->addClass('js-operator')
		->addOptions($operators);

	// value
	$value = (new CTextBox('conditions['.$i.'][value]', $condition['value'], false, 255))
		->addClass('js-value')
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('regular expression'));

	if ($condition['operator'] == CONDITION_OPERATOR_EXISTS
			|| $condition['operator'] == CONDITION_OPERATOR_NOT_EXISTS) {
		$value->addClass(ZBX_STYLE_DISPLAY_NONE);
	}

	// delete button
	$delete_button_cell = [
		(new CButton('conditions_'.$i.'_remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];

	$row = [
		$formulaid,
		$macro,
		$operator_select,
		(new CDiv($value))->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH),
		(new CCol($delete_button_cell))->addClass(ZBX_STYLE_NOWRAP)
	];

	$condition_table->addRow($row, 'form_row');
}

$condition_table->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$condition_tab->addItem([
	new CLabel(_('Filters')),
	new CFormField((new CDiv($condition_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
]);

/*
 * LLD Macro tab.
 */
$lld_macro_tab = new CFormGrid();

$lld_macro_paths_table = (new CTable())
	->setId('lld_macro_paths')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->addStyle('width: 100%;')
	->setHeader([_('LLD macro'), _('JSONPath'), '']);

$lld_macro_paths = $data['lld_macro_paths'];

if (!$lld_macro_paths) {
	$lld_macro_paths = [[
		'lld_macro' => '',
		'path' => ''
	]];
}
elseif (!hasRequest('form_refresh')) {
	CArrayHelper::sort($lld_macro_paths, ['lld_macro']);
}

if (array_key_exists('item', $data)) {
	$templated = ($data['item']['templateid'] != 0);
}
else {
	$templated = false;
}

foreach ($lld_macro_paths as $i => $lld_macro_path) {
	$lld_macro = (new CTextAreaFlexible('lld_macro_paths['.$i.'][lld_macro]', $lld_macro_path['lld_macro'], [
		'readonly' => $templated,
		'maxlength' => DB::getFieldLength('lld_macro_path', 'lld_macro')
	]))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->setAttribute('placeholder', '{#MACRO}');

	$path = (new CTextAreaFlexible('lld_macro_paths['.$i.'][path]', $lld_macro_path['path'], [
		'readonly' => $templated,
		'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
	]))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('$.path.to.node'));

	$remove = [
		(new CButton('lld_macro_paths['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled(!$templated)
	];

	$lld_macro_paths_table->addRow([
		(new CCol($lld_macro))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($path))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($remove))->addClass(ZBX_STYLE_NOWRAP)
	],'form_row');
}

$lld_macro_paths_table->setFooter((new CCol(
	(new CButton('lld_macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$templated)
))->setColSpan(3));

$lld_macro_tab->addItem([
	new CLabel(_('LLD macros')),
	new CFormField(
		(new CDiv($lld_macro_paths_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
]);

// Overrides tab.
$overrides_tab = new CFormGrid();
$overrides_list = (new CTable())
	->addClass('lld-overrides-table')
	->setHeader([
		(new CColHeader())->setWidth('15'),
		(new CColHeader())->setWidth('15'),
		(new CColHeader(_('Name')))->setWidth('350'),
		(new CColHeader(_('Stop processing')))->setWidth('100'),
		(new CColHeader(_('Action')))->setWidth('50')
	])
	->addRow(
		(new CCol(
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$templated)
					->removeId()
			))
		))
	);

$overrides_tab->addItem([
	new CLabel(_('Overrides')),
	new CFormField(
		(new CDiv($overrides_list))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
]);

// Append tabs to form.
$tab = (new CTabView())
	->addTab('itemTab', $data['caption'], $item_tab)
	->addTab('preprocTab', _('Preprocessing'),
		(new CFormGrid())
			->setId('item_preproc_list')
			->addItem([
				new CLabel(_('Preprocessing steps')),
				new CFormField(
					getItemPreprocessing($form, $data['preprocessing'], $data['limited'], $data['preprocessing_types'])
				)
			]),
		TAB_INDICATOR_PREPROCESSING
	)
	->addTab('lldMacroTab', _('LLD macros'), $lld_macro_tab, TAB_INDICATOR_LLD_MACROS)
	->addTab('macroTab', _('Filters'), $condition_tab, TAB_INDICATOR_FILTERS)
	->addTab('overridesTab', _('Overrides'), $overrides_tab, TAB_INDICATOR_OVERRIDES);

if (!hasRequest('form_refresh')) {
	$tab->setSelected(0);
}

// Append buttons to form.
if (!empty($data['itemid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] != HOST_STATUS_TEMPLATE) {
		$buttons[] = (new CSubmit('check_now', _('Execute now')))
			->setEnabled(in_array($data['item']['type'], checkNowAllowedTypes())
					&& $data['item']['status'] == ITEM_STATUS_ACTIVE
					&& $data['host']['status'] == HOST_STATUS_MONITORED
			);
	}

	$buttons[] = (new CSimpleButton(_('Test')))->setId('test_item');

	$buttons[] = (new CButtonDelete(_('Delete discovery rule?'), url_params(['form', 'itemid', 'hostid', 'context']),
		'context'
	))->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_param('context'));

	$form_actions = new CFormActions(new CSubmit('update', _('Update')), $buttons);
}
else {
	$cancel_button = $data['backurl'] !== null
		? new CButtonCancel(null, "redirect('".$data['backurl']."');")
		: new CButtonCancel(url_param('context'));

	$form_actions = new CFormActions(
		new CSubmit('add', _('Add')),
		[
			(new CSimpleButton(_('Test')))->setId('test_item'),
			$cancel_button
		]
	);
}
$tab->setFooter(new CFormGrid($form_actions));

$form->addItem($tab);
$widget->addItem($form);

require_once __DIR__.'/js/configuration.host.discovery.edit.js.php';

$widget->show();

(new CScriptTag('
	item_form.init('.json_encode([
		'interfaces' => $data['interfaces'],
		'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($data['hostid']),
		'field_switches' => CItemData::fieldSwitchingConfiguration($data),
		'interface_types' => itemTypeInterface()
	]).');
'))->show();

(new CScriptTag('
	view.init('.json_encode([
		'form_name' => $form->getName(),
		'counter' => $data['counter']
	]).');
'))
	->setOnDocumentReady()
	->show();
