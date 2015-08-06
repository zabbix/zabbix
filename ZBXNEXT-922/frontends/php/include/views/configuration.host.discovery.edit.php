<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
if (!empty($this->data['parent_discoveryid'])) {
	$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
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

	$itemFormList->addRow(_('Host interface'), [$interfacesComboBox, $span], false, 'interface_row');
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}
$itemFormList->addRow(_('SNMP OID'),
	(new CTextBox('snmp_oid', $this->data['snmp_oid'], $this->data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	(new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmpv3_contextname'
);
$itemFormList->addRow(_('SNMP community'),
	(new CTextBox('snmp_community', $this->data['snmp_community'], false, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	(new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmpv3_securityname'
);

// append snmpv3 security level to form list
$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel'], null, [
	ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
]);
$itemFormList->addRow(_('Security level'), $securityLevelComboBox, false, 'row_snmpv3_securitylevel');
$authProtocolRadioButton = [
	(new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_MD5))
		->setId('snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
	new CLabel(_('MD5'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
	(new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_SHA, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_SHA))
		->setId('snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA),
	new CLabel(_('SHA'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
];
$itemFormList->addRow(_('Authentication protocol'),
	(new CDiv($authProtocolRadioButton))
		->addClass('jqueryinputset')
		->addClass('radioset'),
	false, 'row_snmpv3_authprotocol'
);
$itemFormList->addRow(_('Authentication passphrase'),
	(new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmpv3_authpassphrase'
);
$privProtocolRadioButton = [
	(new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_DES))
		->setId('snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
	new CLabel(_('DES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
	(new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_AES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_AES))
		->setId('snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES),
	new CLabel(_('AES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
];
$itemFormList->addRow(_('Privacy protocol'),
	(new CDiv($privProtocolRadioButton))
		->addClass('jqueryinputset')
		->addClass('radioset'),
	false, 'row_snmpv3_privprotocol'
);
$itemFormList->addRow(_('Privacy passphrase'),
	(new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], false, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	(new CTextBox('port', $this->data['port'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH), false, 'row_port'
);
$itemFormList->addRow(_('IPMI sensor'),
	(new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], $this->data['limited'], 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_ipmi_sensor'
);

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype'], null, [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
]);
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, false, 'row_authtype');
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $this->data['username'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_username'
);
$itemFormList->addRow(_('Public key file'),
	(new CTextBox('publickey', $this->data['publickey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	(new CTextBox('privatekey', $this->data['privatekey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false,  'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	(new CTextBox('password', $this->data['password'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_password'
);
$itemFormList->addRow(_('Executed script'),
	(new CTextArea('params_es', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'label_executed_script'
);
$itemFormList->addRow(_('SQL query'),
	(new CTextArea('params_ap', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false,
	'label_params'
);

$itemFormList->addRow(_('Update interval (in sec)'),
	(new CNumericBox('delay', $this->data['delay'], 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
	false, 'row_delay'
);

// append delay flex to form list
$delayFlexTable = (new CTable())
	->setNoDataMessage(_('No flexible intervals defined.'))
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setId('delayFlexTable')
	->setHeader([_('Interval'), _('Period'), _('Action')]);
$i = 0;
$this->data['maxReached'] = false;
foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}
	$itemForm->addVar('delay_flex['.$i.'][delay]', $delayFlex['delay']);
	$itemForm->addVar('delay_flex['.$i.'][period]', $delayFlex['period']);

	$row = (new CRow([
		$delayFlex['delay'],
		$delayFlex['period'],
		(new CButton('remove', _('Remove')))
			->onClick('javascript: removeDelayFlex('.$i.');')
			->addClass(ZBX_STYLE_BTN_LINK)
	]))->setId('delayFlex_'.$i);
	$delayFlexTable->addRow($row);

	// limit count of intervals, 7 intervals by 30 symbols = 210 characters, db storage field is 256
	$i++;
	if ($i == 7) {
		$this->data['maxReached'] = true;
		break;
	}
}
$itemFormList->addRow(_('Flexible intervals'), (new CDiv($delayFlexTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR),
	false, 'row_flex_intervals'
);

// append new flexible interval to form list
$newFlexInt = new CSpan([
	_('Interval (in sec)'),
	SPACE,
	(new CNumericBox('new_delay_flex[delay]', $this->data['new_delay_flex']['delay'], 5, false, false, false))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
	SPACE,
	_('Period'),
	SPACE,
	(new CTextBox('new_delay_flex[period]', $this->data['new_delay_flex']['period']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	SPACE,
	(new CButton('add_delay_flex', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
]);
$newFlexInt->setId('row-new-delay-flex-fields');

$maxFlexMsg = (new CSpan(_('Maximum number of flexible intervals added')))
	->addClass(ZBX_STYLE_RED)
	->setId('row-new-delay-flex-max-reached')
	->setAttribute('style', 'display: none;');

$itemFormList
	->addRow(_('New flexible interval'), [$newFlexInt, $maxFlexMsg], false, 'row_new_delay_flex', 'new')
	->addRow(_('Keep lost resources period (in days)'),
		(new CTextBox('lifetime', $this->data['lifetime'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Allowed hosts'),
		(new CTextBox('trapper_hosts', $this->data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_trapper_hosts');

// append description to form list
$itemFormList->addRow(_('Description'),
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
$formula = (new CTextBox('formula', $this->data['formula']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) &hellip;');

if ($this->data['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION)  {
	$formula->addClass('hidden');
}
$conditionFormList->addRow(_('Type of calculation'),
	[
		new CComboBox('evaltype', $this->data['evaltype'], null, [
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or'),
			CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
		]),
		(new CSpan(''))
			->addClass($this->data['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION ? 'hidden' : '')
			->setId('expression'),
		$formula
	],
	(count($this->data['conditions']) < 2), 'conditionRow'
);

// macros
$conditionTable = (new CTable())
	->setNoDataMessage('')
	->addClass('formElementTable')
	->setId('conditions')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), '']);

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

	$row = [$formulaId, $macro, new CSpan(_('matches')), $value, $deleteButtonCell];
	$conditionTable->addRow($row, 'form_row');
}

$conditionTable->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$conditionFormList->addRow(_('Filters'), (new CDiv($conditionTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

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

	if (!$this->data['limited']) {
		$buttons[] = new CButtonDelete(
			_('Delete discovery rule?'),
			url_params(['form', 'groupid', 'itemid', 'parent_discoveryid', 'hostid'])
		);
	}

	$buttons[] = new CButtonCancel(url_param('groupid').url_param('parent_discoveryid').url_param('hostid'));

	$itemTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('groupid').url_param('parent_discoveryid').url_param('hostid'))]
	));
}

$itemForm->addItem($itemTab);
$widget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.host.discovery.edit.js.php';

return $widget;
