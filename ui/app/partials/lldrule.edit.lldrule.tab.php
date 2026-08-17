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
 * @var CPartial $this
 * @var array    $data
 */

$lldrule = $data['lldrule'];
$readonly = $data['readonly'];

$formgrid = (new CFormGrid());

if (!empty($lldrule['templates'])) {
	$formgrid->addItem([
		new CLabel(_('Parent discovery rules')),
		new CFormField($lldrule['templates'])
	]);
}

if ($lldrule['discovered']) {
	$discovered_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'lldrule.prototype.edit')
		->setArgument('parent_discoveryid', $lldrule['discoveryData']['lldruleid'])
		->setArgument('itemid', $lldrule['discoveryData']['parent_itemid'])
		->setArgument('context', 'host')
		->getUrl();

	$formgrid->addItem([
		new CLabel(_('Discovered by')),
		new CFormField((new CLink($lldrule['discoveryRule']['name'], $discovered_url)))
	]);
}


$formgrid
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextAreaFlexible('name', $lldrule['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setReadonly($readonly)
				->setAriaRequired()
				->setMaxlength(DB::getFieldLength('items', 'name'))
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Type'), 'label-type'),
		new CFormField(
			(new CSelect('type'))
				->setId('type')
				->setFocusableElementId('label-type')
				->setValue($lldrule['type'])
				->addOptions(CSelect::createOptionsFromArray($data['types']))
				->setReadonly($readonly)
		)
	])
	->addItem([
		(new CLabel(_('Key'), 'key'))->setAsteriskMark(),
		new CFormField((new CTextAreaFlexible('key', $lldrule['key']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('item_discovery', 'key_'))
			->setReadonly($readonly)
			->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setAsteriskMark()
			->setId('js-item-url-label'),
		(new CFormField([
			(new CTextAreaFlexible('url', $lldrule['url']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('items', 'url'))
				->setReadonly($readonly)
				->setErrorContainer('url-error-container')
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSimpleButton(_('Parse')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-parseurl')
				->setEnabled(!$readonly),
			(new CDiv())->setId('url-error-container')
		]))->setId('js-item-url-field'),
	])
	->addItem([
		(new CLabel(_('Query fields')))->setId('js-item-query-fields-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('query-fields-table')
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'query_fields')
					->setHeader(['', _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))
								->addClass('element-table-add')
								->setEnabled(!$readonly)
						))->setColSpan(5)
					),
				new CTemplateTag('query-field-row-tmpl', (new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextAreaFlexible('query_fields[#{rowNum}][name]', '#{name}'))
						->removeId()
						->setAttribute('placeholder', _('name'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
						->setReadonly($readonly),
					RARR(),
					(new CTextAreaFlexible('query_fields[#{rowNum}][value]', '#{value}'))
						->removeId()
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
						->setReadonly($readonly),
					(new CButtonLink(_('Remove')))
						->addClass('element-table-remove')
						->setEnabled(!$readonly)
				]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH .'px;')
		))->setId('js-item-query-fields-field')
	])
	->addItem([
		(new CLabel(_('Parameters'), 'parameters-table'))->setId('js-item-parameters-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'parameters')
					->setId('parameters-table')
					->setHeader([
						(new CColHeader(_('Name')))->setWidth('50%'),
						(new CColHeader(_('Value')))->setWidth('50%'),
						''
					])
					->setFooter((new CCol(
						(new CButtonLink(_('Add')))
							->addClass('element-table-add')
							->setEnabled(!$readonly)
					))->setColSpan(3)
					),
				new CTemplateTag('parameter-row-tmpl', (new CRow([
					(new CTextBox('parameters[#{rowNum}][name]', '#{name}', $readonly,
						DB::getFieldLength('item_parameter', 'name')
					))
						->setAttribute('style', 'width: 100%;')
						->removeId(),
					(new CTextBox('parameters[#{rowNum}][value]', '#{value}', $readonly,
						DB::getFieldLength('item_parameter', 'value')
					))
						->setAttribute('style', 'width: 100%;')
						->removeId(),
					(new CButtonLink(_('Remove')))
						->addClass('element-table-remove')
						->setEnabled(!$readonly)
				]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('js-item-parameters-field')
	])
	->addItem([
		(new CLabel(_('Script'), 'script'))
			->setAsteriskMark()
			->setId('js-item-script-label'),
		(new CFormField(
			(new CMultilineInput('script', $lldrule['script'], [
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
	->addItem([
		(new CLabel(_('Script'), 'browser_script'))
			->setAsteriskMark()
			->setId('js-item-browser-script-label'),
		(new CFormField(
			(new CMultilineInput('browser_script', $lldrule['browser_script'], [
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
		))->setId('js-item-browser-script-field')
	])
	->addItem([
		(new CLabel(_('Request type'), 'label-request-method'))->setId('js-item-request-method-label'),
		(new CFormField(
			(new CSelect('request_method'))
				->setId('request_method')
				->setFocusableElementId('label-request-method')
				->setValue($lldrule['request_method'])
				->addOptions(CSelect::createOptionsFromArray([
					HTTPCHECK_REQUEST_GET => 'GET',
					HTTPCHECK_REQUEST_POST => 'POST',
					HTTPCHECK_REQUEST_PUT => 'PUT',
					HTTPCHECK_REQUEST_HEAD => 'HEAD'
				]))
				->setReadonly($readonly)
		))->setId('js-item-request-method-field')
	])
	->addItem([
		(new CLabel(_('Request body type'), 'post_type'))->setId('js-item-post-type-label'),
		(new CFormField(
			(new CRadioButtonList('post_type', (int) $lldrule['post_type']))
				->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
				->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
				->addValue(_('XML data'), ZBX_POSTTYPE_XML)
				->setReadonly($readonly)
				->setModern()
		))->setId('js-item-post-type-field')
	])
	->addItem([
		(new CLabel(_('Request body'), 'posts'))->setId('js-item-posts-label'),
		(new CFormField(
			(new CTextArea('posts', $lldrule['posts']))
				->setReadonly($readonly)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableSpellcheck()
		))->setId('js-item-posts-field')
	])
	->addItem([
		(new CLabel(_('Headers'), 'headers-table'))->setId('js-item-headers-label'),
		(new CFormField((new CDiv([
			(new CTable())
				->setId('headers-table')
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'headers')
				->setAttribute('style', 'width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->setFooter((new CCol(
					(new CButtonLink(_('Add')))
						->addClass('element-table-add')
						->setEnabled(!$readonly)
				))->setColSpan(5)
				),
			new CTemplateTag('item-header-row-tmpl',
				(new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextAreaFlexible('headers[#{rowNum}][name]', '#{name}'))
						->removeId()
						->setAttribute('placeholder', _('name'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
						->setReadonly($readonly),
					RARR(),
					(new CTextAreaFlexible('headers[#{rowNum}][value]', '#{value}'))
						->removeId()
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
						->setMaxlength(2000)
						->setReadonly($readonly),
					(new CButtonLink(_('Remove')))
						->addClass('element-table-remove')
						->setEnabled(!$readonly)
				]))->addClass('form_row')
			)
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
		))->setId('js-item-headers-field')
	])
	->addItem([
		(new CLabel(_('Required status codes'), 'status_codes'))->setId('js-item-status-codes-label'),
		(new CFormField(
			(new CTextBox('status_codes', $lldrule['status_codes'], $readonly))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-status-codes-field')
	])
	->addItem([
		(new CLabel(_('Follow redirects'), 'follow_redirects'))->setId('js-item-follow-redirects-label'),
		(new CFormField(
			(new CCheckBox('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON))
				->setReadonly($readonly)
				->setChecked($lldrule['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON)
				->setUncheckedValue(HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF)
		))->setId('js-item-follow-redirects-field')
	])
	->addItem([
		(new CLabel(_('Retrieve mode'), 'retrieve_mode'))->setId('js-item-retrieve-mode-label'),
		(new CFormField(
			(new CRadioButtonList('retrieve_mode', (int) $lldrule['retrieve_mode']))
				->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
				->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
				->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
				->setReadonly($readonly || $lldrule['request_method'] == HTTPCHECK_REQUEST_HEAD)
				->setModern()
		))->setId('js-item-retrieve-mode-field')
	])
	->addItem([
		(new CLabel(_('Convert to JSON'), 'output_format'))->setId('js-item-output-format-label'),
		(new CFormField(
			(new CCheckBox('output_format', HTTPCHECK_STORE_JSON))
				->setReadonly($readonly)
				->setChecked($lldrule['output_format'] == HTTPCHECK_STORE_JSON)
				->setUncheckedValue(HTTPCHECK_STORE_RAW)
		))->setId('js-item-output-format-field')
	])
	->addItem([
		(new CLabel(_('HTTP proxy'), 'http_proxy'))->setId('js-item-http-proxy-label'),
		(new CFormField(
			(new CTextBox('http_proxy', $lldrule['http_proxy'], $readonly, DB::getFieldLength('items', 'http_proxy')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('[protocol://][user[:password]@]proxy.example.com[:port]'))
				->disableAutocomplete()
		))->setId('js-item-http-proxy-field')
	])
	->addItem([
		(new CLabel(_('HTTP authentication'), 'label-http-authtype'))->setId('js-item-http-authtype-label'),
		(new CFormField(
			(new CSelect('http_authtype'))
				->setValue($lldrule['http_authtype'])
				->setId('http_authtype')
				->setFocusableElementId('label-http-authtype')
				->addOptions(CSelect::createOptionsFromArray(httptest_authentications()))
				->setReadonly($readonly)
		))->setId('js-item-http-authtype-field')
	])
	->addItem([
		(new CLabel(_('User name'), 'http_username'))->setId('js-item-http-username-label'),
		(new CFormField(
			(new CTextBox('http_username', $lldrule['http_username'], $readonly, DB::getFieldLength('items', 'username')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-http-username-field')
	])
	->addItem([
		(new CLabel(_('Password'), 'http_password'))->setId('js-item-http-password-label'),
		(new CFormField(
			(new CTextBox('http_password', $lldrule['http_password'], $readonly, DB::getFieldLength('items', 'password')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('data-notrim', '')
				->disableAutocomplete()
		))->setId('js-item-http-password-field')
	])
	->addItem([
		(new CLabel(_('SSL verify peer'), 'verify_peer'))->setId('js-item-verify-peer-label'),
		(new CFormField(
			(new CCheckBox('verify_peer', ZBX_HTTP_VERIFY_PEER_ON))
				->setReadonly($readonly)
				->setChecked($lldrule['verify_peer'] == ZBX_HTTP_VERIFY_PEER_ON)
				->setUncheckedValue(ZBX_HTTP_VERIFY_PEER_OFF)
		))->setId('js-item-verify-peer-field')
	])
	->addItem([
		(new CLabel(_('SSL verify host'), 'verify_host'))->setId('js-item-verify-host-label'),
		(new CFormField(
			(new CCheckBox('verify_host', ZBX_HTTP_VERIFY_HOST_ON))
				->setReadonly($readonly)
				->setChecked($lldrule['verify_host'] == ZBX_HTTP_VERIFY_HOST_ON)
				->setUncheckedValue(ZBX_HTTP_VERIFY_HOST_OFF)
		))->setId('js-item-verify-host-field')
	])
	->addItem([
		(new CLabel(_('SSL certificate file'), 'ssl_cert_file'))->setId('js-item-ssl-cert-file-label'),
		(new CFormField(
			(new CTextBox('ssl_cert_file', $lldrule['ssl_cert_file'], $readonly,
				DB::getFieldLength('items', 'ssl_cert_file')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-cert-file-field')
	])
	->addItem([
		(new CLabel(_('SSL key file'), 'ssl_key_file'))->setId('js-item-ssl-key-file-label'),
		(new CFormField(
			(new CTextBox('ssl_key_file', $lldrule['ssl_key_file'], $readonly,
				DB::getFieldLength('items', 'ssl_key_file')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('js-item-ssl-key-file-field')
	])
	->addItem([
		(new CLabel(_('SSL key password'), 'ssl_key_password'))->setId('js-item-ssl-key-password-label'),
		(new CFormField(
			(new CTextBox('ssl_key_password', $lldrule['ssl_key_password'], $readonly,
				DB::getFieldLength('items', 'ssl_key_password')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('data-notrim', '')
				->disableAutocomplete()
		))->setId('js-item-ssl-key-password-field')
	])
	->addItem([
		(new CLabel(_('Master item'), 'master_itemid_ms'))
			->setAsteriskMark()
			->setId('js-item-master-item-label'),
		(new CFormField(
			(new CMultiSelect([
				'name' => 'master_itemid',
				'object_name' => 'items',
				'multiple' => false,
				'readonly' => $readonly,
				'data' => $lldrule['master_item']
					? [[
						'id' => $lldrule['master_item']['itemid'],
						'prefix' => $data['host']['name'].NAME_DELIMITER,
						'name' => $lldrule['master_item']['name']
					]]
					: [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'dstfrm' => $data['form_name'],
						'dstfld1' => 'master_itemid',
						'hostid' => $lldrule['hostid'],
						'excludeids' => $lldrule['itemid'] != 0 ? [$lldrule['itemid']] : [],
						'normal_only' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-master-item-field')
	]);

if ($data['host']['status'] == HOST_STATUS_MONITORED || $data['host']['status'] == HOST_STATUS_NOT_MONITORED) {
	$formgrid->addItem(
		new CPartial('host.interface.selector',
			['interfaces' => $data['host']['interfaces'], 'discovered' => $lldrule['discovered_lld'],
				'interfaceid' => $lldrule['interfaceid']
			]
		)
	);
}

$delay_flex_table = (new CTable())
	->setId('delay-flex-table')
	->setHeader([
		_('Type'), _('Interval'), _('Period'), ''
	])
	->setFooter(
		(new CCol((new CButtonLink(_('Add')))
			->addClass('element-table-add')
			->setEnabled(!$lldrule['discovered_lld'])
		))->setColSpan($lldrule['discovered_lld'] ? 3 : 4)
	);

$formgrid
	->addItem([
		(new CLabel(
			[
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
			],
			'snmp_oid'
		))
			->setAsteriskMark()
			->setId('js-item-snmp-oid-label'),
		(new CFormField(
			(new CTextAreaFlexible('snmp_oid', $lldrule['snmp_oid']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setReadonly($readonly)
				->setMaxlength(DB::getFieldLength('items', 'snmp_oid'))
				->setAttribute('placeholder', 'walk[OID1,OID2,...]')
				->setAriaRequired()
		))->setId('js-item-snmp-oid-field')
	])
	->addItem([
		(new CLabel(_('IPMI sensor'), 'ipmi_sensor'))->setId('js-item-impi-sensor-label'),
		(new CFormField(
			(new CTextAreaFlexible('ipmi_sensor', $lldrule['ipmi_sensor']))
				->setAttribute('data-notrim', '')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('items', 'ipmi_sensor'))
				->setReadonly($readonly)
		))->setId('js-item-impi-sensor-field')
	])
	->addItem([
		(new CLabel(_('Authentication method'), 'label-authtype'))->setId('js-item-authtype-label'),
		(new CFormField(
			(new CSelect('authtype'))
				->setId('authtype')
				->setFocusableElementId('label-authtype')
				->setValue($lldrule['authtype'])
				->addOptions(CSelect::createOptionsFromArray([
					ITEM_AUTHTYPE_PASSWORD => _('Password'),
					ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
				]))
				->setReadonly($lldrule['discovered_lld'])
		))->setId('js-item-authtype-field')
	])
	->addItem([
		(new CLabel(_('JMX endpoint'), 'jmx_endpoint'))
			->setAsteriskMark()
			->setId('js-item-jmx-endpoint-label'),
		(new CFormField(
			(new CTextBox('jmx_endpoint', $lldrule['jmx_endpoint'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'jmx_endpoint')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('js-item-jmx-endpoint-field')
	])
	->addItem([
		(new CLabel(_('User name'), 'username'))->setId('js-item-username-label'),
		(new CFormField(
			(new CTextBox('username', $lldrule['username'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'username')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-username-field')
	])
	->addItem([
		(new CLabel(_('Public key file'), 'publickey'))
			->setAsteriskMark()
			->setId('js-item-public-key-label'),
		(new CFormField(
			(new CTextBox('publickey', $lldrule['publickey'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'publickey')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('js-item-public-key-field')
	])
	->addItem([
		(new CLabel(_('Private key file'), 'privatekey'))
			->setAsteriskMark()
			->setId('js-item-private-key-label'),
		(new CFormField(
			(new CTextBox('privatekey', $lldrule['privatekey'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'privatekey')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('js-item-private-key-field')
	])
	->addItem([
		(new CLabel(_('Key passphrase'), 'passphrase'))->setId('js-item-passphrase-label'),
		(new CFormField(
			(new CTextBox('passphrase', $lldrule['password'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'password')
			))
				->setAttribute('data-notrim', '')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-passphrase-field')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))->setId('js-item-password-label'),
		(new CFormField(
			(new CTextBox('password', $lldrule['password'], $lldrule['discovered_lld'],
				DB::getFieldLength('items', 'password')
			))
				->setAttribute('data-notrim', '')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->setId('js-item-password-field')
	])
	->addItem([
		(new CLabel(_('Executed script'), 'params_es'))
			->setAsteriskMark()
			->setId('js-item-executed-script-label'),
		(new CFormField(
			(new CTextArea('params_es', $lldrule['params_es']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->disableSpellcheck()
				->setReadonly($lldrule['discovered_lld'])
		))->setId('js-item-executed-script-field')
	])
	->addItem([
		(new CLabel(_('SQL query'), 'params_ap'))
			->setAsteriskMark()
			->setId('js-item-sql-query-label'),
		(new CFormField(
			(new CTextArea('params_ap', $lldrule['params_ap']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->disableSpellcheck()
				->setReadonly($lldrule['discovered_lld'])
		))->setId('js-item-sql-query-field')
	])
	->addItem([
		(new CLabel(_('Update interval'), 'delay'))
			->setAsteriskMark()
			->setId('js-item-delay-label'),
		(new CFormField(
			(new CTextBox('delay', $lldrule['delay'], $lldrule['discovered_lld']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('js-item-delay-field')
	])
	->addItem([
		(new CLabel(_('Custom intervals'), 'delay-flex-table'))->setId('js-item-flex-intervals-label'),
		(new CFormField(
			(new CDiv([
				$delay_flex_table,
				new CTemplateTag('delay-flex-row-tmpl', [
					(new CRow([
						(new CRadioButtonList("delay_flex[#{rowNum}][type]", ITEM_DELAY_FLEXIBLE))
							->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
							->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
							->setReadonly($lldrule['discovered_lld'])
							->setModern(),
						[
							(new CTextBox("delay_flex[#{rowNum}][delay]", '#{delay}'))
								->setErrorContainer("delay_flex-#{rowNum}-error-container")
								->setAttribute('data-error-label', _('Interval'))
								->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
								->setReadonly($lldrule['discovered_lld']),
							(new CTextBox("delay_flex[#{rowNum}][schedule]", '#{schedule}'))
								->setErrorContainer("delay_flex-#{rowNum}-error-container")
								->setAttribute('data-error-label', _('Interval'))
								->addClass(ZBX_STYLE_DISPLAY_NONE)
								->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
								->setReadonly($lldrule['discovered_lld'])
						],
						(new CTextBox("delay_flex[#{rowNum}][period]", '#{period}'))
							->setErrorContainer("delay_flex-#{rowNum}-error-container")
							->setAttribute('data-error-label', _('Period'))
							->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
							->setReadonly($lldrule['discovered_lld']),
						(new CButtonLink(_('Remove')))
							->addClass('element-table-remove')
							->setEnabled(!$lldrule['discovered_lld'])
					]))->addClass('form_row'),
					(new CRow())
						->addClass('error-container-row')
						->addItem((new CCol())->setId("delay_flex-#{rowNum}-error-container")->setColSpan(4))
				])
			]))
				->setAttribute('data-field-name', 'delay_flex')
				->setAttribute('data-field-type', 'set')
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		))->setId('js-item-flex-intervals-field')
	]);

$edit_source_timeouts_link = null;
$custom_timeout_enabled = $lldrule['custom_timeout'] == ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED;

if ($data['can_edit_source_timeouts'] && (!$readonly || !$custom_timeout_enabled)) {
	$edit_source_timeouts_link = $data['host']['proxyid']
		? (new CLink(_('Timeouts'), (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'proxy.edit')
			->setArgument('proxyid', $data['host']['proxyid'])
			->getUrl()
		))->addClass(ZBX_STYLE_LINK)
		: (new CLink(_('Timeouts'),
			(new CUrl('zabbix.php'))->setArgument('action', 'timeouts.edit')
		))
			->addClass(ZBX_STYLE_LINK)
			->setTarget('_blank');
}

$formgrid->addItem([
	(new CLabel(_('Timeout'), 'timeout'))
		->setAsteriskMark()
		->setId('js-item-timeout-label'),
	(new CFormField([
		(new CRadioButtonList('custom_timeout', (int) $lldrule['custom_timeout']))
			->addValue(_('Global'), ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED)
			->addValue(_('Override'), ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED)
			->setReadonly($readonly)
			->setModern(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('inherited_timeout', $lldrule['inherited_timeout']))
			->setReadonly(true)
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->addClass($custom_timeout_enabled ? ZBX_STYLE_DISPLAY_NONE : null),
		(new CTextBox('timeout', $lldrule['timeout'], $readonly))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->addClass($custom_timeout_enabled ? null : ZBX_STYLE_DISPLAY_NONE)
			->setAriaRequired(),
		$edit_source_timeouts_link
	]))->setId('js-item-timeout-field')
]);

$lld_lifetime_help_icons = makeHelpIcon(_('The value should be greater than LLD rule update interval.'));

$disabled_by_lld_icon = $lldrule['status'] == ITEM_STATUS_DISABLED && $lldrule['discovered_lld']
&& $lldrule['discoveryData']['disable_source'] == ZBX_DISABLE_SOURCE_LLD
	? makeWarningIcon(_('Disabled automatically by an LLD rule.'))
	: null;

$formgrid
	->addItem([
		(new CLabel([_('Delete lost resources'), $lld_lifetime_help_icons], 'lifetime'))->setAsteriskMark(),
		new CFormField([
			(new CRadioButtonList('lifetime_type', (int) $lldrule['lifetime_type']))
				->addValue(_('Never'), ZBX_LLD_DELETE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DELETE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DELETE_AFTER)
				->setReadonly($lldrule['discovered_lld'])
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('lifetime', $lldrule['lifetime'], $lldrule['discovered_lld']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		])
	])
	->addItem([
		(new CLabel([_('Disable lost resources'), $lld_lifetime_help_icons]))
			->addClass('js-item-disable-resources')
			->setAsteriskMark(),
		(new CFormField([
			(new CRadioButtonList('enabled_lifetime_type', (int) $lldrule['enabled_lifetime_type']))
				->addValue(_('Never'), ZBX_LLD_DISABLE_NEVER)
				->addValue(_('Immediately'), ZBX_LLD_DISABLE_IMMEDIATELY)
				->addValue(_('After'), ZBX_LLD_DISABLE_AFTER)
				->setReadonly($lldrule['discovered_lld'])
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('enabled_lifetime', $lldrule['enabled_lifetime'], $lldrule['discovered_lld']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))->addClass('js-item-disable-resources')
	])
	->addItem([
		(new CLabel(_('Enable trapping'), 'allow_traps'))->setId('js-item-allow-traps-label'),
		(new CFormField((new CCheckBox('allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
			->setChecked($lldrule['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON)
			->setEnabled(!$lldrule['discovered_lld'])
			->setUncheckedValue(HTTPCHECK_ALLOW_TRAPS_OFF)
		))->setId('js-item-allow-traps-field')
	])
	->addItem([
		(new CLabel(_('Allowed hosts'), 'trapper_hosts'))->setId('js-item-trapper-hosts-label'),
		(new CFormField(
			(new CTextAreaFlexible('trapper_hosts', $lldrule['trapper_hosts']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setReadonly($lldrule['discovered_lld'])
				->setMaxlength(DB::getFieldLength('items', 'trapper_hosts'))
		))->setId('js-item-trapper-hosts-field')
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField((new CTextArea('description', $lldrule['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('items', 'description'))
			->setReadonly($lldrule['discovered_lld'])
		)
	])
	->addItem([
		new CLabel([_('Enabled'), $disabled_by_lld_icon], 'status'),
		new CFormField((new CCheckBox('status', ITEM_STATUS_ACTIVE))
			->setUncheckedValue(ITEM_STATUS_DISABLED)
			->setChecked($lldrule['status'] == ITEM_STATUS_ACTIVE))
	]);

$formgrid->show();
