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
	->setTitle(_('Items'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_ITEM_EDIT));

$host = $data['host'];

if (!empty($data['hostid'])) {
	$widget->setNavigation(getHostNavigation('items', $data['hostid']));
}

$url = (new CUrl('items.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// Create form.
$form = (new CForm('post', $url))
	->setId('item-form')
	->setName('itemForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid']);

if (!empty($data['itemid'])) {
	$form->addVar('itemid', $data['itemid']);
}

$item_tab = (new CFormGrid())->setId('itemFormList');

if (!empty($data['templates'])) {
	$item_tab->addItem([
		new CLabel(_('Parent items')),
		new CFormField($data['templates'])
	]);
}

$discovered_item = (array_key_exists('item', $data) && $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED);
$readonly = ($data['limited'] || $discovered_item);

if ($discovered_item) {
	$item_tab->addItem([
		new CLabel(_('Discovered by')),
		new CFormField(new CLink($data['item']['discoveryRule']['name'], (new CUrl('disc_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['item']['discoveryRule']['itemid'])
			->setArgument('itemid', $data['item']['itemDiscovery']['parent_itemid'])
			->setArgument('context', 'host')
		))
	]);
}

$item_tab->addItem([
	(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
	new CFormField((new CTextBox('name', $data['name'], $readonly))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
	)
]);

// Append type to form list.
$item_tab->addItem([
	new CLabel(_('Type'), 'label-type'),
	new CFormField((new CSelect('type'))
		->setId('type')
		->setFocusableElementId('label-type')
		->setValue($data['type'])
		->addOptions(CSelect::createOptionsFromArray($data['types']))
		->setReadonly($readonly)
	)
]);

// Append key to form list.
$key_controls = [(new CTextBox('key', $data['key'], $readonly, DB::getFieldLength('items', 'key_')))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired()
];

if (!$readonly) {
	$key_controls[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$key_controls[] = (new CButton('keyButton', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick(
			'return PopUp("popup.generic", jQuery.extend('.json_encode([
				'srctbl' => 'help_items',
				'srcfld1' => 'key',
				'dstfrm' => $form->getName(),
				'dstfld1' => 'key'
			]).', {itemtype: jQuery("#type").val()}), {dialogue_class: "modal-popup-generic"});'
		);
}

$item_type_options = CSelect::createOptionsFromArray([
	ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
	ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
	ITEM_VALUE_TYPE_STR => _('Character'),
	ITEM_VALUE_TYPE_LOG => _('Log'),
	ITEM_VALUE_TYPE_TEXT => _('Text')
]);
$type_mismatch_hint = (new CSpan(makeWarningIcon(_('This type of information may not match the key.'))))
	->setId('js-item-type-hint')
	->addStyle('margin: 5px 0 0 5px;')
	->addClass(ZBX_STYLE_DISPLAY_NONE);

$item_tab
	// Append item key to form list.
	->addItem([
		(new CLabel(_('Key'), 'key'))->setAsteriskMark(),
		new CFormField($key_controls)
	])
	->addItem([
		new CLabel(_('Type of information'), 'label-value-type'),
		new CFormField([
			(new CSelect('value_type'))
				->setFocusableElementId('label-value-type')
				->setId('value_type')
				->setValue($data['value_type'])
				->addOptions($item_type_options)
				->setReadonly($readonly),
			$type_mismatch_hint
		])
	])
	// Append ITEM_TYPE_HTTPAGENT URL field to form list.
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setAsteriskMark()
			->setId('js-item-url-label'),
		(new CFormField([
			(new CTextBox('url', $data['url'], $readonly, DB::getFieldLength('items', 'url')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('httpcheck_parseurl', _('Parse')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->setEnabled(!$readonly)
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
elseif (!$readonly) {
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
elseif (!$readonly) {
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
			(new CTextBox('parameters[name][]', $parameter['name'], $readonly,
				DB::getFieldLength('item_parameter', 'name'))
			)
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CTextBox('parameters[value][]', $parameter['value'], $readonly,
				DB::getFieldLength('item_parameter', 'value'))
			)
				->setAttribute('style', 'width: 100%;')
				->removeId(),
			(new CButton('', _('Remove')))
				->removeId()
				->onClick('jQuery(this).closest("tr").remove()')
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$readonly)
		]);
	}
}

$parameters_table->addRow((new CButton('parameter_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')
	->setEnabled(!$readonly)
);

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
								->setEnabled(!$readonly)
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
						(new CTextBox('query_fields[name][#{index}]', '#{name}', $readonly))
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						'&rArr;',
						(new CTextBox('query_fields[value][#{index}]', '#{value}', $readonly))
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
						(new CButton(null, _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setEnabled(!$readonly)
							->setAttribute('data-row-action', 'remove_row')
					])),
				$query_fields
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setId('query_fields_pairs')
				->setAttribute('data-sortable-pairs-table', $readonly ? '0': '1')
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
				'readonly' => $readonly
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-script-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request type to form list.
	->addItem([
		(new CLabel(_('Request type'), 'label-request-method'))->setId('js-item-request-method-label'),
		(new CFormField((new CSelect('request_method'))
			->setId('request_method')
			->setFocusableElementId('label-request-method')
			->setValue($data['request_method'])
			->addOptions(CSelect::createOptionsFromArray([
				HTTPCHECK_REQUEST_GET => 'GET',
				HTTPCHECK_REQUEST_POST => 'POST',
				HTTPCHECK_REQUEST_PUT => 'PUT',
				HTTPCHECK_REQUEST_HEAD => 'HEAD'
			]))
			->setReadonly($readonly)
		))->setId('js-item-request-method-field')
	])
	// Append ITEM_TYPE_HTTPAGENT and ITEM_TYPE_SCRIPT timeout field to form list.
	->addItem([
		(new CLabel(_('Timeout'), 'timeout'))
			->setAsteriskMark()
			->setId('js-item-timeout-label'),
		(new CFormField((new CTextBox('timeout', $data['timeout'], $readonly))
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
			->setEnabled(!$readonly)
			->setModern(true)
		))->setId('js-item-post-type-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Request body to form list.
	->addItem([
		(new CLabel(_('Request body'), 'posts'))->setId('js-item-posts-label'),
		(new CFormField((new CTextArea('posts', $data['posts'], compact('readonly')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-posts-field')
	]);

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
elseif (!$readonly) {
	$headers_data[] = [
		'name' => '',
		'value' => '',
		'sortorder' => 0
	];
}

$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [json_encode($headers_data)];

$item_tab
	// Append ITEM_TYPE_HTTPAGENT Headers fields to form list.
	->addItem([
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
								->setEnabled(!$readonly)
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
						(new CTextBox('headers[name][#{index}]', '#{name}', $readonly))
							->setAttribute('placeholder', _('name'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
						'&rArr;',
						(new CTextBox('headers[value][#{index}]', '#{value}', $readonly, 2000))
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
						(new CButton(null, _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setEnabled(!$readonly)
							->setAttribute('data-row-action', 'remove_row')
					])),
				$headers
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setId('headers_pairs')
				->setAttribute('data-sortable-pairs-table', $readonly ? '0': '1')
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
		))->setId('js-item-headers-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Required status codes to form list.
	->addItem([
		(new CLabel(_('Required status codes'), 'status_codes'))->setId('js-item-status-codes-label'),
		(new CFormField((new CTextBox('status_codes', $data['status_codes'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-status-codes-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Follow redirects to form list.
	->addItem([
		(new CLabel(_('Follow redirects'), 'follow_redirects'))->setId('js-item-follow-redirects-label'),
		(new CFormField((new CCheckBox('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON))
			->setEnabled(!$readonly)
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
			->setEnabled(!($readonly || $data['request_method'] == HTTPCHECK_REQUEST_HEAD))
			->setModern(true)
		))->setId('js-item-retrieve-mode-field')
	])
	// Append ITEM_TYPE_HTTPAGENT Convert to JSON to form list.
	->addItem([
		(new CLabel(_('Convert to JSON'), 'output_format'))->setId('js-item-output-format-label'),
		(new CFormField((new CCheckBox('output_format', HTTPCHECK_STORE_JSON))
			->setEnabled(!$readonly)
			->setChecked($data['output_format'] == HTTPCHECK_STORE_JSON)
		))->setId('js-item-output-format-field')
	])
	// Append ITEM_TYPE_HTTPAGENT HTTP proxy to form list.
	->addItem([
		(new CLabel(_('HTTP proxy'), 'http_proxy'))->setId('js-item-http-proxy-label'),
		(new CFormField((new CTextBox('http_proxy', $data['http_proxy'], $readonly,
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
			->setReadonly($readonly)
		))->setId('js-item-http-authtype-field')
	])
	// Append ITEM_TYPE_HTTPAGENT User name to form list.
	->addItem([
		(new CLabel(_('User name'), 'http_username'))->setId('js-item-http-username-label'),
		(new CFormField(
			(new CTextBox('http_username', $data['http_username'], $readonly,
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
			(new CTextBox('http_password', $data['http_password'], $readonly,
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
			->setEnabled(!$readonly)
			->setChecked($data['verify_peer'] == HTTPTEST_VERIFY_PEER_ON)
		))->setId('js-item-verify-peer-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL verify host to form list.
	->addItem([
		(new CLabel(_('SSL verify host'), 'verify_host'))->setId('js-item-verify-host-label'),
		(new CFormField((new CCheckBox('verify_host', HTTPTEST_VERIFY_HOST_ON))
			->setEnabled(!$readonly)
			->setChecked($data['verify_host'] == HTTPTEST_VERIFY_HOST_ON)
		))->setId('js-item-verify-host-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL certificate file to form list.
	->addItem([
		(new CLabel(_('SSL certificate file'), 'ssl_cert_file'))->setId('js-item-ssl-cert-file-label'),
		(new CFormField((new CTextBox('ssl_cert_file', $data['ssl_cert_file'], $readonly,
				DB::getFieldLength('items', 'ssl_cert_file')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-cert-file-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL key file to form list.
	->addItem([
		(new CLabel(_('SSL key file'), 'ssl_key_file'))->setId('js-item-ssl-key-file-label'),
		(new CFormField((new CTextBox('ssl_key_file', $data['ssl_key_file'], $readonly,
				DB::getFieldLength('items', 'ssl_key_file')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-key-file-field')
	])
	// Append ITEM_TYPE_HTTPAGENT SSL key password to form list.
	->addItem([
		(new CLabel(_('SSL key password'), 'ssl_key_password'))->setId('js-item-ssl-key-password-label'),
		(new CFormField(
			(new CTextBox('ssl_key_password', $data['ssl_key_password'], $readonly,
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
				'disabled' => $readonly,
				'data' => ($data['master_itemid'] > 0)
					? [
						[
							'id' => $data['master_itemid'],
							'prefix' => $host['name'].NAME_DELIMITER,
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
						'excludeids' => $data['itemid'] != 0 ? [$data['itemid']] : [],
						'normal_only' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-master-item-field')
	]);

// Append interface(s) to form list.
if ($data['display_interfaces']) {
	if ($discovered_item) {
		if ($data['interfaceid'] != 0) {
			$data['interfaces'] = zbx_toHash($data['interfaces'], 'interfaceid');
			$interface = $data['interfaces'][$data['interfaceid']];

			$form->addVar('selectedInterfaceId', $data['interfaceid']);
			$item_tab->addItem([
				(new CLabel(_('Host interface'), 'interface'))
					->setAsteriskMark()
					->setId('js-item-interface-label'),
				(new CFormField((new CTextBox('interface', getHostInterface($interface), true))->setAriaRequired()))
					->setId('js-item-interface-field')
			]);
		}
		else {
			$item_tab->addItem([
				(new CLabel(_('Host interface'), 'interface'))->setId('js-item-interface-label'),
				(new CFormField(
					(new CTextBox('interface', interfaceType2str(INTERFACE_TYPE_OPT), true))
						->setAttribute('disabled', 'disabled')
				))->setId('js-item-interface-field')
			]);
		}
	}
	else {
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
					->setId('interface_not_defined')
					->addClass(ZBX_STYLE_RED)
					->setAttribute('style', 'display: none;')
			]))->setId('js-item-interface-field')
		]);
		$form->addVar('selectedInterfaceId', $data['interfaceid']);
	}
}

// Append SNMP common fields.
$item_tab->addItem([
	(new CLabel(_('SNMP OID'), 'snmp_oid'))
		->setAsteriskMark()
		->setId('js-item-snmp-oid-label'),
	(new CFormField((new CTextBox('snmp_oid', $data['snmp_oid'], $readonly, 512))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('placeholder', '[IF-MIB::]ifInOctets.1')
		->setAriaRequired()
	))->setId('js-item-snmp-oid-field')
]);

$item_tab
	->addItem([
		(new CLabel(_('IPMI sensor'), 'ipmi_sensor'))->setId('js-item-impi-sensor-label'),
		(new CFormField((new CTextBox('ipmi_sensor', $data['ipmi_sensor'], $readonly, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-impi-sensor-field')
	])
	->addItem([
		(new CLabel(_('Authentication method'), 'label-authtype'))->setId('js-item-authtype-label'),
		(new CFormField(
			(new CSelect('authtype'))
				->setId('authtype')
				->setFocusableElementId('label-authtype')
				->setValue($data['authtype'])
				->addOptions(CSelect::createOptionsFromArray([
					ITEM_AUTHTYPE_PASSWORD => _('Password'),
					ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
				]))
				->setReadonly($discovered_item)
		))->setId('js-item-authtype-field')
	])
	->addItem([
		(new CLabel(_('JMX endpoint'), 'jmx_endpoint'))
			->setAsteriskMark()
			->setId('js-item-jmx-endpoint-label'),
		(new CFormField((new CTextBox('jmx_endpoint', $data['jmx_endpoint'], $discovered_item, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		))->setId('js-item-jmx-endpoint-field')
	])
	->addItem([
		(new CLabel(_('User name'), 'username'))->setId('js-item-username-label'),
		(new CFormField((new CTextBox('username', $data['username'], $discovered_item, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete()
		))->setId('js-item-username-field')
	])
	->addItem([
		(new CLabel(_('Public key file'), 'publickey'))
			->setAsteriskMark()
			->setId('js-item-public-key-label'),
		(new CFormField((new CTextBox('publickey', $data['publickey'], $discovered_item, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-public-key-field')
	])
	->addItem([
		(new CLabel(_('Private key file'), 'privatekey'))
			->setAsteriskMark()
			->setId('js-item-private-key-label'),
		(new CFormField((new CTextBox('privatekey', $data['privatekey'],  $discovered_item, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-private-key-field')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))->setId('js-item-password-label'),
		(new CFormField((new CTextBox('password', $data['password'], $discovered_item, 64))
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
			->setReadonly($discovered_item)
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
			->setReadonly($discovered_item)
		))->setId('js-item-sql-query-field')
	])
	->addItem([
		(new CLabel(_('Formula'), 'params_f'))
			->setAsteriskMark()
			->setId('js-item-formula-label'),
		(new CFormField((new CTextArea('params_f', $data['params']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setReadonly($discovered_item)
		))->setId('js-item-formula-field')
	])
	->addItem([
		(new CLabel(_('Units'), 'units'))->setId('js-item-units-label'),
		(new CFormField((new CTextBox('units', $data['units'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)))
			->setId('js-item-units-field')
	])
	->addItem([
		(new CLabel(_('Update interval'), 'delay'))
			->setAsteriskMark()
			->setId('js-item-delay-label'),
		(new CFormField((new CTextBox('delay', $data['delay'], $discovered_item))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
		))->setId('js-item-delay-field')
	]);

// Append custom intervals to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), $discovered_item ? null : _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	if ($discovered_item) {
		$form->addVar('delay_flex['.$i.'][type]', (int) $delay_flex['type']);
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type_name]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
			->setModern(true)
			->setEnabled(false);
	}
	else {
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
			->setModern(true);
	}

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay'], $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period'], $discovered_item))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', '', $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', '', $discovered_item))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule'], $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = $discovered_item
		? null
		: (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove');

	$delayFlexTable->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

if (!$discovered_item) {
	$delayFlexTable->addRow([(new CButton('interval_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')]);
}

$item_tab->addItem([
	(new CLabel(_('Custom intervals')))->setId('js-item-flex-intervals-label'),
	(new CFormField((new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	))->setId('js-item-flex-intervals-field')
]);

// Append history storage to form list.
$keep_history_hint = null;

if ($data['config']['hk_history_global']  && ($host['status'] == HOST_STATUS_MONITORED
			|| $host['status'] == HOST_STATUS_NOT_MONITORED)) {
	$link = (CWebUser::getType() == USER_TYPE_SUPER_ADMIN)
		? (new CLink(_x('global housekeeping settings', 'item_form'), (new CUrl('zabbix.php'))
				->setArgument('action', 'housekeeping.edit')
				->getUrl()
			))
				->setTarget('_blank')
		: _x('global housekeeping settings', 'item_form');

	$keep_history_hint = (new CSpan(makeWarningIcon([
		' '._x('Overridden by', 'item_form').' ',
		$link,
		' ('.$data['config']['hk_history'].')'
	])))
		->addStyle('margin: 5px 0 0 5px;')
		->setId('history_mode_hint');
}

$item_tab->addItem([
	(new CLabel(_('History storage period'), 'history'))->setAsteriskMark(),
	new CFormField([
		(new CRadioButtonList('history_mode', (int) $data['history_mode']))
			->addValue(_('Do not keep history'), ITEM_STORAGE_OFF)
			->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
			->setReadonly($discovered_item)
			->setModern(true),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('history', $data['history'], $discovered_item))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired(),
		$keep_history_hint
	])
]);

// Append trend storage to form list.
$keep_trend_hint = null;
if ($data['config']['hk_trends_global'] && ($host['status'] == HOST_STATUS_MONITORED
			|| $host['status'] == HOST_STATUS_NOT_MONITORED)) {
	$link = (CWebUser::getType() == USER_TYPE_SUPER_ADMIN)
		? (new CLink(_x('global housekeeping settings', 'item_form'), (new CUrl('zabbix.php'))
				->setArgument('action', 'housekeeping.edit')
				->getUrl()
			))
				->setTarget('_blank')
		: _x('global housekeeping settings', 'item_form');

	$keep_trend_hint = (new CSpan(makeWarningIcon([
		' '._x('Overridden by', 'item_form').' ',
		$link,
		' ('.$data['config']['hk_trends'].')'
	])))
		->addStyle('margin: 5px 0 0 5px;')
		->setId('trends_mode_hint');
}

$item_tab
	->addItem([
		(new CLabel(_('Trend storage period'), 'trends'))
			->setAsteriskMark()
			->setId('js-item-trends-label'),
		(new CFormField([
			(new CRadioButtonList('trends_mode', (int) $data['trends_mode']))
				->addValue(_('Do not keep trends'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setReadonly($discovered_item)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('trends', $data['trends'], $discovered_item))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			$keep_trend_hint
		]))->setId('js-item-trends-field')
	])
	->addItem([
		(new CLabel(_('Log time format'), 'logtimefmt'))->setId('js-item-log-time-format-label'),
		(new CFormField(
			(new CTextBox('logtimefmt', $data['logtimefmt'], $readonly, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-log-time-format-field')
	]);

if ($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
	$item_tab->addItem([
		(new CLabel(_('Value mapping'), 'valuemapid_ms'))->setId('js-item-value-map-label'),
		(new CFormField((new CMultiSelect([
				'name' => 'valuemapid',
				'object_name' => 'valuemaps',
				'disabled' => $readonly,
				'multiple' => false,
				'data' => $data['valuemap'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'valuemaps',
						'srcfld1' => 'valuemapid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'valuemapid',
						'hostids' => [$data['hostid']],
						'context' => $data['context'],
						'editable' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-value-map-field')
	]);
}

$item_tab
	->addItem([
		(new CLabel(_('Enable trapping'), 'allow_traps'))->setId('js-item-allow-traps-label'),
		(new CFormField([
			$discovered_item ? new CVar('allow_traps', $data['allow_traps']) : null,
			(new CCheckBox($discovered_item ? '' : 'allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
				->setEnabled(!$discovered_item)
				->setChecked($data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON)
		]))->setId('js-item-allow-traps-field')
	])
	->addItem([
		(new CLabel(_('Allowed hosts'), 'trapper_hosts'))->setId('js-item-trapper-hosts-label'),
		(new CFormField((new CTextBox('trapper_hosts', $data['trapper_hosts']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-trapper-hosts-field')
	]);

// Append populate host to form list.
if ($discovered_item) {
	$form->addVar('inventory_link', 0);
}
else {
	$host_inventory_select = (new CSelect('inventory_link'))
		->setFocusableElementId('label-host-inventory')
		->setValue($data['inventory_link'])
		->addOption(new CSelectOption(0, '-'._('None').'-'));

	// A list of available host inventory fields.
	foreach ($data['possibleHostInventories'] as $fieldNo => $fieldInfo) {
		if (isset($data['alreadyPopulated'][$fieldNo])) {
			$enabled = isset($data['item']['inventory_link'])
				? $data['item']['inventory_link'] == $fieldNo
				: $data['inventory_link'] == $fieldNo && !hasRequest('clone');
		}
		else {
			$enabled = true;
		}

		$host_inventory_select->addOption((new CSelectOption($fieldNo, $fieldInfo['title']))->setDisabled(!$enabled));
		if ($data['inventory_link'] == $fieldNo && !$enabled) {
			$host_inventory_select->setValue(0);
		}
	}

	$item_tab->addItem([
		(new CLabel(_('Populates host inventory field'), $host_inventory_select->getFocusableElementId()))
			->setId('js-item-inventory-link-label'),
		(new CFormField($host_inventory_select))->setId('js-item-inventory-link-field')
	]);
}

// Append description to form list.
$item_tab
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField((new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('items', 'description'))
			->setReadonly($discovered_item)
		)
	])
	// Append status to form list.
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField((new CCheckBox('status', ITEM_STATUS_ACTIVE))->setChecked($data['status'] == ITEM_STATUS_ACTIVE))
	]);

	// Add link to Latest data.
if (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA) && $data['itemid'] != 0
		&& $data['context'] === 'host') {
	$item_tab->addItem(
		(new CFormField((new CLink(_('Latest data'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'latest.view')
				->setArgument('hostids[]', $data['hostid'])
				->setArgument('name', $data['name'])
				->setArgument('filter_name', '')
		))->setTarget('_blank')))
	);
}

// Append tabs to form.
$item_tabs = (new CTabView())
	->addTab('itemTab', $data['caption'], $item_tab)
	->addTab('tags-tab', _('Tags'),
		new CPartial('configuration.tags.tab', [
			'source' => 'item',
			'tags' => $data['tags'],
			'show_inherited_tags' => $data['show_inherited_tags'],
			'readonly' => $discovered_item,
			'tabs_id' => 'tabs',
			'tags_tab_id' => 'tags-tab'
		]),
		TAB_INDICATOR_TAGS
	)
	->addTab('preprocTab', _('Preprocessing'),
		(new CFormGrid())
			->setId('item_preproc_list')
			->addItem([
				new CLabel(_('Preprocessing steps')),
				new CFormField(
					getItemPreprocessing($form, $data['preprocessing'], $readonly, $data['preprocessing_types'])
				)
			])
			->addItem([
				(new CLabel(_('Type of information'), 'label-value-type-steps'))
					->addClass('js-item-preprocessing-type'),
				(new CFormField((new CSelect('value_type_steps'))
					->setFocusableElementId('label-value-type-steps')
					->setValue($data['value_type'])
					->addOptions($item_type_options)
					->setReadonly($readonly)
				))->addClass('js-item-preprocessing-type')
			]),
		TAB_INDICATOR_PREPROCESSING
	);

if (!hasRequest('form_refresh')) {
	$item_tabs->setSelected(0);
}

// Append buttons to form.
if ($data['itemid'] != 0) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] != HOST_STATUS_TEMPLATE) {
		$buttons[] = (new CSimpleButton(_('Execute now')))
			->setEnabled(in_array($data['item']['type'], checkNowAllowedTypes())
					&& $data['item']['status'] == ITEM_STATUS_ACTIVE
					&& $data['host']['status'] == HOST_STATUS_MONITORED
			)
			->onClick('view.checkNow(this);');
	}

	$buttons[] = (new CSimpleButton(_('Test')))->setId('test_item');

	if ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED) {
		$buttons[] = ($data['config']['compression_status'])
			? new CSubmit('del_history', _('Clear history and trends'))
			: new CButtonQMessage(
				'del_history',
				_('Clear history and trends'),
				_('History clearing can take a long time. Continue?')
			);
	}

	$buttons[] = (new CButtonDelete(_('Delete item?'), url_params(['form', 'itemid', 'hostid', 'context']), 'context'))
		->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_params(['hostid', 'context']));

	$item_tabs->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$item_tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[(new CSimpleButton(_('Test')))->setId('test_item'), new CButtonCancel(url_params(['hostid', 'context']))]
	));
}

$form->addItem($item_tabs);
$widget->addItem($form);

require_once __DIR__.'/js/configuration.item.edit.js.php';

$widget->show();

(new CScriptTag('
	item_form.init('.json_encode([
		'interfaces' => $data['interfaces'],
		'value_type_by_keys' => CItemData::getValueTypeByKey(),
		'keys_by_item_type' => CItemData::getKeysByItemType(),
		'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($data['hostid']),
		'field_switches' => CItemData::fieldSwitchingConfiguration($data),
		'interface_types' => itemTypeInterface()
	]).');
'))->show();

(new CScriptTag('
	view.init('.json_encode([
		'form_name' => $form->getName(),
		'trends_default' => $data['trends_default']
	]).');
'))
	->setOnDocumentReady()
	->show();
