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
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
	->setTitle(_('Discovery rules'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_DISCOVERY_EDIT))
	->setNavigation(getHostNavigation('discoveries', $data['hostid'],
		array_key_exists('itemid', $data) ? $data['itemid'] : 0
	));

$url = (new CUrl('host_discovery.php'))
	->setArgument('context', $data['context'])
	->getUrl();

$form = (new CForm('post', $url))
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('host_discovery.php')))->removeId())
	->setId('host-discovery-form')
	->setName('itemForm')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
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
if (!$data['query_fields'] && !$data['limited']) {
	$data['query_fields'][] = [
		'name' => '',
		'value' => ''
	];
}

// Prepare ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER parameters.
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
		''
	])
	->setAttribute('style', 'width: 100%;');

if ($parameters_data) {
	foreach ($parameters_data as $num => $parameter) {
		$parameters_table->addItem(
			(new CRow([
				(new CTextBox('parameters['.$num.'][name]', $parameter['name'], $data['limited'],
					DB::getFieldLength('item_parameter', 'name'))
				)
					->setAttribute('style', 'width: 100%;')
					->removeId(),
				(new CTextBox('parameters['.$num.'][value]', $parameter['value'], $data['limited'],
					DB::getFieldLength('item_parameter', 'value'))
				)
					->setAttribute('style', 'width: 100%;')
					->removeId(),
				(new CButtonLink(_('Remove')))
					->addClass('element-table-remove')
					->setEnabled(!$data['limited'])
			]))->addClass('form_row')
		);
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
		(new CLabel(_('Query fields'), 'query-fields-table'))->setId('js-item-query-fields-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('query-fields-table')
					->setAttribute('style', 'width: 100%;')
					->setHeader(['', _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))
								->addClass('element-table-add')
								->setEnabled(!$data['limited'])
						))->setColSpan(5)
					),
				new CTemplateTag('query-field-row-tmpl',
					(new CRow([
						(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextBox('query_fields[#{rowNum}][name]', '#{name}', $data['limited']))
							->removeId()
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						RARR(),
						(new CTextBox('query_fields[#{rowNum}][value]', '#{value}', $data['limited']))
							->removeId()
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
						(new CButtonLink(_('Remove')))
							->addClass('element-table-remove')
							->setEnabled(!$data['limited'])
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		))->setId('js-item-query-fields-field')
	])
	// Append ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER parameters to form list.
	->addItem([
		(new CLabel(_('Parameters'), $parameters_table->getId()))->setId('js-item-parameters-label'),
		(new CFormField([
			(new CDiv([
				$parameters_table,
				(new CTemplateTag('parameters_table_row'))->addItem(
					(new CRow([
						(new CTextBox('parameters[#{rowNum}][name]', '', false,
							DB::getFieldLength('item_parameter', 'name')
						))
							->setAttribute('style', 'width: 100%;')
							->removeId(),
						(new CTextBox('parameters[#{rowNum}][value]', '', false,
							DB::getFieldLength('item_parameter', 'value')
						))
							->setAttribute('style', 'width: 100%;')
							->removeId(),
						(new CButtonLink(_('Remove')))->addClass('element-table-remove')
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		]))->setId('js-item-parameters-field')
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
	->addItem([
		(new CLabel(_('Script'), 'browser_script'))
			->setAsteriskMark()
			->setId('js-item-browser-script-label'),
		(new CFormField((new CMultilineInput('browser_script', $data['browser_script'], [
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
		))->setId('js-item-browser-script-field')
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
	// Append ITEM_TYPE_HTTPAGENT Request body type to form list.
	->addItem([
		(new CLabel(_('Request body type'), 'post_type'))->setId('js-item-post-type-label'),
		(new CFormField((new CRadioButtonList('post_type', (int) $data['post_type']))
			->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
			->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
			->addValue(_('XML data'), ZBX_POSTTYPE_XML)
			->setReadonly($data['limited'])
			->setModern()
		))->setId('js-item-post-type-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request body to form list.
	->addItem([
		(new CLabel(_('Request body'), 'posts'))->setId('js-item-posts-label'),
		(new CFormField((new CTextArea('posts', $data['posts'], ['readonly' =>  $data['limited']]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableSpellcheck()
		))->setId('js-item-posts-field')
	]);

// Append ITEM_TYPE_HTTPAGENT Headers fields to form list.
if (!$data['headers'] && !$data['limited']) {
	$data['headers'][] = [
		'name' => '',
		'value' => ''
	];
}

$item_tab
	->additem([
		(new CLabel(_('Headers'), 'headers-table'))->setId('js-item-headers-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('headers-table')
					->setAttribute('style', 'width: 100%;')
					->setHeader(['', _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))
								->addClass('element-table-add')
								->setEnabled(!$data['limited'])
						))->setColSpan(5)
					),
				new CTemplateTag('item-header-row-tmpl',
					(new CRow([
						(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextBox('headers[#{rowNum}][name]', '#{name}', $data['limited']))
							->removeId()
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						RARR(),
						(new CTextBox('headers[#{rowNum}][value]', '#{value}', $data['limited'], 2000))
							->removeId()
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
						(new CButtonLink(_('Remove')))
							->addClass('element-table-remove')
							->setEnabled(!$data['limited'])
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
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
			->setReadonly($data['limited'])
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
			->setReadonly($data['limited'] || $data['request_method'] == HTTPCHECK_REQUEST_HEAD)
			->setModern()
		))->setId('js-item-retrieve-mode-field')
	])
	// Append ITEM_TYPE_HTTPAGENT HTTP proxy to form list.
	->addItem([
		(new CLabel(_('HTTP proxy'), 'http_proxy'))->setId('js-item-http-proxy-label'),
		(new CFormField((new CTextBox('http_proxy', $data['http_proxy'], $data['limited'],
				DB::getFieldLength('items', 'http_proxy')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('[protocol://][user[:password]@]proxy.example.com[:port]'))
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
		(new CFormField((new CCheckBox('verify_peer', ZBX_HTTP_VERIFY_PEER_ON))
			->setReadonly($data['limited'])
			->setChecked($data['verify_peer'] == ZBX_HTTP_VERIFY_PEER_ON)
		))->setId('js-item-verify-peer-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL verify host to form list.
	->addItem([
		(new CLabel(_('SSL verify host'), 'verify_host'))->setId('js-item-verify-host-label'),
		(new CFormField(
			(new CCheckBox('verify_host', ZBX_HTTP_VERIFY_HOST_ON))
				->setReadonly($data['limited'])
				->setChecked($data['verify_host'] == ZBX_HTTP_VERIFY_HOST_ON)
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
				'readonly' => $data['limited'],
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
						'normal_only' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-master-item-field')
	]);

if ($data['display_interfaces']) {
	// Append interfaces to form list.
	$select_interface = getInterfaceSelect($data['interfaces'])
		->setId('interface-select')
		->setValue($data['interfaceid'])
		->addClass(ZBX_STYLE_ZSELECT_HOST_INTERFACE)
		->setFocusableElementId('interfaceid')
		->setAriaRequired();

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
}

$item_tab
	->addItem([
		(new CLabel([
			_('SNMP OID'),
			makeHelpIcon([
				_('Field requirements:'),
				(new CList([
					new CListItem([
						(new CSpan('walk[OID1,OID2,...]'))->addClass(ZBX_STYLE_MONOSPACE_FONT),
						' - ',
						_('to retrieve a subtree')
					]),
					new CListItem([
						(new CSpan('discovery[{#MACRO1},OID1,{#MACRO2},OID2,...]'))->addClass(ZBX_STYLE_MONOSPACE_FONT),
						' - ',
						_('(legacy) to retrieve a subtree in JSON')
					])
				]))->addClass(ZBX_STYLE_LIST_DASHED)
			])
		], 'snmp_oid'))
			->setAsteriskMark()
			->setId('js-item-snmp-oid-label'),
		(new CFormField((new CTextBox('snmp_oid', $data['snmp_oid'], $data['limited'], 512))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', 'walk[OID1,OID2,...]')
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
		(new CFormField((new CTextBox('username', $data['username'], false, DB::getFieldLength('items', 'username')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
		(new CFormField((new CTextBox('password', $data['password'], false, DB::getFieldLength('items', 'password')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
			->disableSpellcheck()
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
			->disableSpellcheck()
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
	->setHeader([_('Type'), _('Interval'), _('Period'), ''])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern();

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

$item_tab->addItem([
	(new CLabel(_('Custom intervals')))->setId('js-item-flex-intervals-label'),
	(new CFormField((new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	))->setId('js-item-flex-intervals-field')
]);

/**
 * Append timeout field to form list for item types:
 * ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
 * ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_SNMP, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
 */
$edit_source_timeouts_link = null;

if ($data['can_edit_source_timeouts']
		&& (!$data['limited'] || $data['custom_timeout'] == ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED)) {
	$proxy_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'proxy.edit')
		->setArgument('proxyid', $data['host']['proxyid'])
		->getUrl();

	$edit_source_timeouts_link = $data['host']['proxyid']
		? (new CLink(_('Timeouts'), $proxy_url))->addClass(ZBX_STYLE_LINK)
		: (new CLink(_('Timeouts'),
			(new CUrl('zabbix.php'))->setArgument('action', 'timeouts.edit')
		))
			->addClass(ZBX_STYLE_LINK)
			->setTarget('_blank');
}

$item_tab->addItem([
	(new CLabel(_('Timeout'), 'timeout'))
		->setAsteriskMark()
		->setId('js-item-timeout-label'),
	(new CFormField([
		(new CRadioButtonList('custom_timeout', $data['custom_timeout']))
			->addValue(_('Global'), ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED)
			->addValue(_('Override'), ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED)
			->setReadonly($data['limited'])
			->setModern(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('inherited_timeout', $data['inherited_timeout'], true))->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
		(new CTextBox('timeout', $data['timeout'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired(),
		$edit_source_timeouts_link
	]))->setId('js-item-timeout-field')
]);

$lld_lifetime_help_icons = makeHelpIcon(_('The value should be greater than LLD rule update interval.'));

$item_tab
	->addItem([
		(new CLabel([_('Delete lost resources'), $lld_lifetime_help_icons], 'lifetime'))->setAsteriskMark(),
		new CFormField([
			(new CRadioButtonList('lifetime_type', (int) $data['lifetime_type']))
				->addValue(_('Never'), ZBX_LLD_DELETE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DELETE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DELETE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('lifetime', $data['lifetime']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		])
	])
	->addItem([
		(new CLabel([_('Disable lost resources'), $lld_lifetime_help_icons], 'enabled_lifetime'))
			->setId('js-item-disable-resources-label')
			->setAsteriskMark(),
		(new CFormField([
			(new CRadioButtonList('enabled_lifetime_type', (int) $data['enabled_lifetime_type']))
				->addValue(_('Never'), ZBX_LLD_DISABLE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DISABLE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DISABLE_AFTER)
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('enabled_lifetime', $data['enabled_lifetime']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))->setId('js-item-disable-resources-field')
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
		new CFormField((new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxLength(DB::getFieldLength('hosts', 'description'))
		)
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
		(new CDiv(
			(new CSelect('evaltype'))
				->setFocusableElementId('label-evaltype')
				->setId('evaltype')
				->setValue($data['evaltype'])
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
					CONDITION_EVAL_TYPE_AND => _('And'),
					CONDITION_EVAL_TYPE_OR => _('Or'),
					CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
				]))
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
		))->addClass(ZBX_STYLE_CELL),
		(new CDiv([
			(new CSpan(''))->setId('expression'),
			(new CTextBox('formula', $data['formula']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('formula')
				->setAttribute('placeholder', 'A or (B and C) ...')
		]))
			->addClass(ZBX_STYLE_CELL)
			->addClass(ZBX_STYLE_CELL_EXPRESSION)
	]))->setId('js-item-condition-field')
]);

// macros
$condition_table = (new CTable())
	->setId('conditions')
	->addStyle('width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), '']);

$operators = CSelect::createOptionsFromArray([
	CONDITION_OPERATOR_REGEXP => _('matches'),
	CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
	CONDITION_OPERATOR_EXISTS => _('exists'),
	CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
]);

foreach ($data['conditions'] as $i => $condition) {
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
elseif ($data['form_refresh'] == 0) {
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
		->setAttribute('placeholder', '{#MACRO}')
		->disableSpellcheck();

	$path = (new CTextAreaFlexible('lld_macro_paths['.$i.'][path]', $lld_macro_path['path'], [
		'readonly' => $templated,
		'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
	]))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('$.path.to.node'))
		->disableSpellcheck();

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
	->setId('lld-overrides-table')
	->addClass('lld-overrides-table')
	->setHeader([
		new CColHeader(),
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
					getItemPreprocessing($data['preprocessing'], $data['limited'], $data['preprocessing_types'])
				)
			]),
		TAB_INDICATOR_PREPROCESSING
	)
	->addTab('lldMacroTab', _('LLD macros'), $lld_macro_tab, TAB_INDICATOR_LLD_MACROS)
	->addTab('macroTab', _('Filters'), $condition_tab, TAB_INDICATOR_FILTERS)
	->addTab('overridesTab', _('Overrides'), $overrides_tab, TAB_INDICATOR_OVERRIDES);

if ($data['form_refresh'] == 0) {
	$tab->setSelected(0);
}

// Append buttons to form.
if (!empty($data['itemid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] != HOST_STATUS_TEMPLATE) {
		$buttons[] = (new CSimpleButton(_('Execute now')))
			->setEnabled(in_array($data['item']['type'], checkNowAllowedTypes())
					&& $data['item']['status'] == ITEM_STATUS_ACTIVE
					&& $data['host']['status'] == HOST_STATUS_MONITORED
			)
			->addClass('js-execute-item');
	}

	$buttons[] = (new CSimpleButton(_('Test')))->setId('test_item');
	$buttons[] = (new CButtonDelete(_('Delete discovery rule?'), url_params(['form', 'itemid', 'hostid', 'context']).
		'&'.CSRF_TOKEN_NAME.'='.CCsrfTokenHelper::get('host_discovery.php'),
		'context'
	))->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_param('context'));

	$form_actions = new CFormActions(new CSubmit('update', _('Update')), $buttons);
}
else {
	$cancel_button = $data['backurl'] !== null
		? (new CRedirectButton(_('Cancel'), $data['backurl']))->setId('cancel')
		: new CButtonCancel(url_param('context'));

	$form_actions = new CFormActions(
		new CSubmit('add', _('Add')),
		[
			(new CSimpleButton(_('Test')))->setId('test_item'),
			$cancel_button
		]
	);
}

$tab->setFooter(
	(new CFormGrid($form_actions))->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_ACTIONS)
);

$form->addItem($tab);
$html_page->addItem($form);

require_once __DIR__.'/js/configuration.host.discovery.edit.js.php';

$html_page->show();

(new CScriptTag('
	item_form.init('.json_encode([
		'interfaces' => $data['interfaces'],
		'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($data['hostid']),
		'field_switches' => CItemData::fieldSwitchingConfiguration($data),
		'interface_types' => itemTypeInterface(),
		'inherited_timeouts' => $data['inherited_timeouts']['timeouts']
	]).');
'))->show();

(new CScriptTag('
	view.init('.json_encode([
		'form_name' => $form->getName(),
		'counter' => $data['counter'],
		'context' => $data['context'],
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('item')],
		'readonly' => $data['limited'],
		'query_fields' => $data['query_fields'],
		'headers' => $data['headers']
	]).');
'))
	->setOnDocumentReady()
	->show();
