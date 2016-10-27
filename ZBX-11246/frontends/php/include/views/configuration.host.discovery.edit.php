<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->addItem(get_header_host_table('discoveries', $this->data['hostid'],
		isset($this->data['parent_discoveryid']) ? $this->data['parent_discoveryid'] : 0
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

$itemFormList->addRow(_('Name'), (new CTextBox('name', $this->data['name'], $this->data['limited']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('autofocus', 'autofocus')
);

// append type to form list
if ($this->data['limited']) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow(_('Type'),
		(new CTextBox('typename', item_type2str($this->data['type']), true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$typeComboBox = (new CComboBox('type', $this->data['type']))
		->addItems($this->data['types']);
	$itemFormList->addRow(_('Type'), $typeComboBox);
}

// append key to form list
$itemFormList->addRow(_('Key'), [
	(new CTextBox('key', $this->data['key'], $this->data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// append interfaces to form list
if (!empty($this->data['interfaces'])) {
	$interfacesComboBox = new CComboBox('interfaceid', $this->data['interfaceid']);

	// set up interface groups
	$interfaceGroups = [];
	foreach (zbx_objectValues($this->data['interfaces'], 'type') as $interfaceType) {
		$interfaceGroups[$interfaceType] = new COptGroup(interfaceType2str($interfaceType));
	}

	// add interfaces to groups
	foreach ($this->data['interfaces'] as $interface) {
		$option = new CComboItem(
			$interface['interfaceid'],
			$interface['useip'] ? $interface['ip'].' : '.$interface['port'] : $interface['dns'].' : '.$interface['port'],
			$interface['interfaceid'] == $this->data['interfaceid'] ? 'yes' : 'no'
		);
		$option->setAttribute('data-interfacetype', $interface['type']);
		$interfaceGroups[$interface['type']]->addItem($option);
	}
	foreach ($interfaceGroups as $interfaceGroup) {
		$interfacesComboBox->addItem($interfaceGroup);
	}

	$span = (new CSpan(_('No interface found')))
		->addClass(ZBX_STYLE_RED)
		->setId('interface_not_defined')
		->setAttribute('style', 'display: none;');

	$itemFormList->addRow(_('Host interface'), [$interfacesComboBox, $span], 'interface_row');
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}
$itemFormList->addRow(_('SNMP OID'),
	(new CTextBox('snmp_oid', $this->data['snmp_oid'], $this->data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
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
	(new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], $this->data['limited'], 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_ipmi_sensor'
);

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype'], null, [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
]);
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, 'row_authtype');
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $this->data['username'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
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

$itemFormList->addRow(_('Update interval (in sec)'),
	(new CNumericBox('delay', $this->data['delay'], 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
	'row_delay'
);

// Append delay_flex to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEX_TYPE_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_FLEX_TYPE_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
		$delay_input = (new CNumericBox('delay_flex['.$i.'][delay]', $delay_flex['delay'], 5, false, true, false))
			->setAttribute('placeholder', 50);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period'], false, 255))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', '', false, 255))
			->setAttribute('placeholder', 'wd1-5h9-18')
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CNumericBox('delay_flex['.$i.'][delay]', '', 5, false, true, false))
			->setAttribute('placeholder', 50)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', '', false, 255))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule'], false, 255))
			->setAttribute('placeholder', 'wd1-5h9-18');
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
	)
	->addRow(_('Keep lost resources period (in days)'),
		(new CTextBox('lifetime', $this->data['lifetime'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
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
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			(new CButtonDelete(_('Delete discovery rule?'), url_params(['form', 'itemid', 'hostid'])))
				->setEnabled(!$data['limited']),
			new CButtonCancel(url_param('hostid'))
		]
	));
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
