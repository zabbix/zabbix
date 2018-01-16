<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$widget = (new CWidget())->setTitle(_('Item prototypes'));

if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('items', $this->data['hostid'], $this->data['parent_discoveryid']));
}

// create form
$itemForm = (new CForm())
	->setName('itemForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $this->data['form'])
	->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

if (!empty($this->data['itemid'])) {
	$itemForm->addVar('itemid', $this->data['itemid']);
}

// create form list
$itemFormList = new CFormList('itemFormList');
if (!empty($this->data['templates'])) {
	$itemFormList->addRow(_('Parent items'), $this->data['templates']);
}

$readonly = false;
if ($data['limited']) {
	$readonly = true;
}

$itemFormList->addRow(_('Name'),
	(new CTextBox('name', $this->data['name'], $readonly))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
);

// append type to form list
if ($readonly) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow(_('Type'),
		(new CTextBox('typename', item_type2str($this->data['type']), true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow(_('Type'), new CComboBox('type', $this->data['type'], null, $this->data['types']));
}

// append key to form list
$key_controls = [
	(new CTextBox('key', $this->data['key'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
];
if (!$readonly) {
	$key_controls[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$key_controls[] = (new CButton('keyButton', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",jQuery.extend('.
			CJs::encodeJson([
				'srctbl' => 'help_items',
				'srcfld1' => 'key',
				'dstfrm' => $itemForm->getName(),
				'dstfld1' => 'key'
			]).
				',{itemtype: jQuery("#type option:selected").val()}));'
		);

}

$itemFormList->addRow(_('Key'), $key_controls);

// Append master item select.
$master_item = [(new CTextBox('master_itemname', $data['master_itemname'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CVar('master_itemid', $data['master_itemid'], 'master_itemid'))
];

if (!$readonly) {
	$master_item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$master_item[] = (new CButton('button', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.
			CJs::encodeJson([
				'srctbl' => 'item_prototypes',
				'srcfld1' => 'itemid',
				'srcfld2' => 'master_itemname',
				'dstfrm' => $itemForm->getName(),
				'dstfld1' => 'master_itemid',
				'dstfld2' => 'master_itemname',
				'parent_discoveryid' => $data['parent_discoveryid'],
				'excludeids' => [$data['itemid']]
			]).');'
		);
}

$itemFormList->addRow(_('Master item'), $master_item, 'row_master_item');

// append interfaces to form list
if (!empty($this->data['interfaces'])) {
	$interfacesComboBox = new CComboBox('interfaceid', $data['interfaceid']);

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
		$interfacesComboBox->addItem($interface_group);
	}

	$span = (new CSpan(_('No interface found')))
		->addClass(ZBX_STYLE_RED)
		->setId('interface_not_defined')
		->setAttribute('style', 'display: none;');

	$itemFormList->addRow(_('Host interface'), [$interfacesComboBox, $span], 'interface_row');
	$itemForm->addVar('selectedInterfaceId', $data['interfaceid']);
}
$itemFormList->addRow(_('SNMP OID'),
	(new CTextBox('snmp_oid', $this->data['snmp_oid'], $readonly, 512))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	(new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_contextname'
);
$itemFormList->addRow(_('SNMP community'),
	(new CTextBox('snmp_community', $this->data['snmp_community'], false, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
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
$itemFormList->addRow(_('Authentication protocol'),
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
$itemFormList->addRow(_('Privacy protocol'),
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
$itemFormList->addRow(_('IPMI sensor'),
	(new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], $readonly, 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_ipmi_sensor'
);

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype'], null, [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
]);
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, 'row_authtype');
$itemFormList->addRow(_('JMX endpoint'),
	(new CTextBox('jmx_endpoint', $data['jmx_endpoint'], false, 255))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_jmx_endpoint'
);
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $this->data['username'], false, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_username'
);
$itemFormList->addRow(_('Public key file'),
	(new CTextBox('publickey', $this->data['publickey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	(new CTextBox('privatekey', $this->data['privatekey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	(new CTextBox('password', $this->data['password'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_password'
);
$itemFormList->addRow(_('Executed script'),
	(new CTextArea('params_es', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'label_executed_script'
);
$itemFormList->addRow(_('SQL query'),
	(new CTextArea('params_ap', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'label_params'
);
$itemFormList->addRow(_('Formula'),
	(new CTextArea('params_f', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'label_formula'
);

// append value type to form list
if ($readonly) {
	$itemForm->addVar('value_type', $this->data['value_type']);
	$itemFormList->addRow(_('Type of information'),
		(new CTextBox('value_type_name', itemValueTypeString($this->data['value_type']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow(_('Type of information'), new CComboBox('value_type', $this->data['value_type'], null, [
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text')
	]));
}

$itemFormList->addRow(_('Units'),
	(new CTextBox('units', $this->data['units'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_units'
);

$itemFormList->addRow(_('Update interval'),
	(new CTextBox('delay', $data['delay']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
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
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]'))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]'))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]'))
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
	->addClass('element-table-add')]);

$itemFormList->addRow(_('Custom intervals'),
	(new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'row_flex_intervals'
);

$keepHistory = [];
$keepHistory[] = (new CTextBox('history', $data['history']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
$itemFormList->addRow(_('History storage period'), $keepHistory);

$keepTrend = [];
$keepTrend[] = (new CTextBox('trends', $data['trends']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
$itemFormList->addRow(_('Trend storage period'), $keepTrend, 'row_trends');

$itemFormList->addRow(_('Log time format'),
	(new CTextBox('logtimefmt', $this->data['logtimefmt'], $readonly, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_logtimefmt'
);

// append valuemap to form list
if ($readonly) {
	$itemForm->addVar('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox = (new CTextBox('valuemap_name',
		!empty($this->data['valuemaps']) ? $this->data['valuemaps'] : _('As is'),
		true
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
}
else {
	$valuemapComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox->addItem(0, _('As is'));
	foreach ($this->data['valuemaps'] as $valuemap) {
		$valuemapComboBox->addItem($valuemap['valuemapid'], CHtml::encode($valuemap['name']));
	}
}
$link = (new CLink(_('show value mappings'), 'adm.valuemapping.php'))
	->setAttribute('target', '_blank');
$itemFormList->addRow(_('Show value'), [$valuemapComboBox, SPACE, $link], 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'),
	(new CTextBox('trapper_hosts', $this->data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_trapper_hosts');

// append applications to form list
$itemFormList->addRow(new CLabel(_('New application'), 'new_application'),
	(new CSpan(
		(new CTextBox('new_application', $this->data['new_application']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->addClass(ZBX_STYLE_FORM_NEW_GROUP)
);

$applicationComboBox = new CListBox('applications[]', $this->data['applications'], 6);
$applicationComboBox->addItem(0, '-'._('None').'-');
foreach ($this->data['db_applications'] as $application) {
	$applicationComboBox->addItem($application['applicationid'], CHtml::encode($application['name']));
}
$itemFormList->addRow(_('Applications'), $applicationComboBox);

// Append application prototypes to form list.
$itemFormList->addRow(new CLabel(_('New application prototype'), 'new_application_prototype'),
	(new CSpan(
		(new CTextBox('new_application_prototype', $data['new_application_prototype']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->addClass(ZBX_STYLE_FORM_NEW_GROUP)
);

$application_prototype_listbox = new CListBox('application_prototypes[]', $data['application_prototypes'], 6);
$application_prototype_listbox->addItem(0, '-'._('None').'-');
foreach ($data['db_application_prototypes'] as $application_prototype) {
	$application_prototype_listbox->addItem($application_prototype['name'], $application_prototype['name']);
}
$itemFormList->addRow(_('Application prototypes'), $application_prototype_listbox);

// append description to form list
$itemFormList->addRow(_('Description'),
	(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// status
$enabledCheckBox = (new CCheckBox('status', ITEM_STATUS_ACTIVE))
	->setChecked($this->data['status'] == ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Create enabled'), $enabledCheckBox);

$preprocessing = (new CTable())
	->setId('preprocessing')
	->setHeader([
		$readonly ? null : '',
		new CColHeader(_('Name')),
		new CColHeader(_('Parameters')),
		new CColHeader(null),
		$readonly ? null : (new CColHeader(_('Action')))->setWidth(50)
	]);

foreach ($data['preprocessing'] as $i => $step) {
	// Depeding on preprocessing type, display corresponding params field and placeholders.
	$params = [];

	// Use numeric box for multiplier, otherwise use text box.
	if ($step['type'] == ZBX_PREPROC_MULTIPLIER) {
		$params[] = (new CTextBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : ''
		))
			->setAttribute('placeholder', _('number'))
			->setReadonly($readonly);
	}
	else {
		$params[] = (new CTextBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : ''
		))->setReadonly($readonly);
	}

	// Create a secondary param text box, so it can be hidden if necessary.
	$params[] = (new CTextBox('preprocessing['.$i.'][params][1]',
		(array_key_exists('params', $step) && array_key_exists(1, $step['params']))
			? $step['params'][1]
			: ''
	))
		->setAttribute('placeholder', _('output'))
		->setReadonly($readonly);

	// Add corresponding placeholders and show or hide text boxes.
	switch ($step['type']) {
		case ZBX_PREPROC_MULTIPLIER:
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_RTRIM:
		case ZBX_PREPROC_LTRIM:
		case ZBX_PREPROC_TRIM:
			$params[0]->setAttribute('placeholder', _('list of characters'));
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_XPATH:
		case ZBX_PREPROC_JSONPATH:
			$params[0]->setAttribute('placeholder', _('path'));
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_REGSUB:
			$params[0]->setAttribute('placeholder', _('pattern'));
			break;

		case ZBX_PREPROC_BOOL2DEC:
		case ZBX_PREPROC_OCT2DEC:
		case ZBX_PREPROC_HEX2DEC:
		case ZBX_PREPROC_DELTA_VALUE:
		case ZBX_PREPROC_DELTA_SPEED:
			$params[0]->addStyle('display: none;');
			$params[1]->addStyle('display: none;');
			break;
	}

	if ($readonly) {
		$itemForm->addVar('preprocessing['.$i.'][type]', $step['type']);
	}

	$preproc_types_cbbox = new CComboBox('preprocessing['.$i.'][type]', $step['type']);

	foreach (get_preprocessing_types() as $group) {
		$cb_group = new COptGroup($group['label']);

		foreach ($group['types'] as $type => $label) {
			$cb_group->addItem(new CComboItem($type, $label, ($type == $step['type'])));
		}

		$preproc_types_cbbox->addItem($cb_group);
	}

	$preprocessing->addRow(
		(new CRow([
			$readonly
				? null
				: (new CCol(
					(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
				))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			$readonly
				? (new CTextBox('preprocessing['.$i.'][type_name]', get_preprocessing_types($step['type'])))
					->setReadonly(true)
				: $preproc_types_cbbox,
			$params[0],
			$params[1],
			$readonly
				? null
				: (new CButton('preprocessing['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
		]))->addClass('sortable')
	);
}

$preprocessing->addRow(
	$readonly
		? null
		: (new CCol(
			(new CButton('param_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		))->setColSpan(5)
);

$item_preproc_list = (new CFormList('item_preproc_list'))
	->addRow(_('Preprocessing steps'), $preprocessing);

// append tabs to form
$itemTab = (new CTabView())
	->addTab('itemTab', $this->data['caption'], $itemFormList)
	->addTab('preprocTab', _('Preprocessing'), $item_preproc_list);

if (!hasRequest('form_refresh')) {
	$itemTab->setSelected(0);
}

// append buttons to form
if ($this->data['itemid'] != 0) {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			(new CButtonDelete(_('Delete item prototype?'),
				url_params(['form', 'itemid', 'parent_discoveryid'])
			))->setEnabled(!$readonly),
			new CButtonCancel(url_params(['parent_discoveryid']))
		]
	));
}
else {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_params(['parent_discoveryid']))]
	));
}

$itemForm->addItem($itemTab);
$widget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.prototype.edit.js.php';

return $widget;
