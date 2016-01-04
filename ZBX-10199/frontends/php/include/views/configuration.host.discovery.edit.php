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


$itemWidget = new CWidget();

if (!empty($this->data['hostid'])) {
	$itemWidget->addItem(get_header_host_table('discoveries', $this->data['hostid'],
		isset($this->data['parent_discoveryid']) ? $this->data['parent_discoveryid'] : null
	));
}

$itemWidget->addPageHeader($this->data['page_header']);

// create form
$itemForm = new CForm();
$itemForm->setName('itemForm');
$itemForm->addVar('form', $this->data['form']);
$itemForm->addVar('hostid', $this->data['hostid']);
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

$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']);
$nameTextBox->attr('autofocus', 'autofocus');
$itemFormList->addRow(_('Name'), $nameTextBox);

// append type to form list
if ($this->data['limited']) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow(_('Type'),
		new CTextBox('typename', item_type2str($this->data['type']), ZBX_TEXTBOX_STANDARD_SIZE, true)
	);
}
else {
	$typeComboBox = new CComboBox('type', $this->data['type']);
	$typeComboBox->addItems($this->data['types']);
	$itemFormList->addRow(_('Type'), $typeComboBox);
}

// append key to form list
$itemFormList->addRow(_('Key'), array(
	new CTextBox('key', $this->data['key'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited'])
));

// append interfaces to form list
if (!empty($this->data['interfaces'])) {
	$interfacesComboBox = new CComboBox('interfaceid', $this->data['interfaceid']);

	// set up interface groups
	$interfaceGroups = array();
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

	$span = new CSpan(_('No interface found'), 'red');
	$span->setAttribute('id', 'interface_not_defined');
	$span->setAttribute('style', 'display: none;');

	$itemFormList->addRow(_('Host interface'), array($interfacesComboBox, $span), false, 'interface_row');
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}
$itemFormList->addRow(_('SNMP OID'),
	new CTextBox('snmp_oid', $this->data['snmp_oid'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']),
	false, 'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname'], ZBX_TEXTBOX_STANDARD_SIZE),
	false, 'row_snmpv3_contextname'
);
$itemFormList->addRow(_('SNMP community'),
	new CTextBox('snmp_community', $this->data['snmp_community'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64),
	false, 'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64),
	false, 'row_snmpv3_securityname'
);

// append snmpv3 security level to form list
$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel']);
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv');
$itemFormList->addRow(_('Security level'), $securityLevelComboBox, false, 'row_snmpv3_securitylevel');
$authProtocolRadioButton = array(
	new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5, null, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_MD5),
	new CLabel(_('MD5'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
	new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_SHA, null, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_SHA),
	new CLabel(_('SHA'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
);
$itemFormList->addRow(_('Authentication protocol'),
	new CDiv($authProtocolRadioButton, 'jqueryinputset'),
	false, 'row_snmpv3_authprotocol'
);
$itemFormList->addRow(_('Authentication passphrase'),
	new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64),
	false, 'row_snmpv3_authpassphrase'
);
$privProtocolRadioButton = array(
	new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES, null, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_DES),
	new CLabel(_('DES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
	new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_AES, null, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_AES),
	new CLabel(_('AES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
);
$itemFormList->addRow(_('Privacy protocol'),
	new CDiv($privProtocolRadioButton, 'jqueryinputset'),
	false, 'row_snmpv3_privprotocol'
);
$itemFormList->addRow(_('Privacy passphrase'),
	new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64),
	false, 'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	new CTextBox('port', $this->data['port'], ZBX_TEXTBOX_SMALL_SIZE, false, 64), false, 'row_port'
);
$itemFormList->addRow(_('IPMI sensor'),
	new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited'], 128),
	false, 'row_ipmi_sensor'
);

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype']);
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'));
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, false, 'row_authtype');
$itemFormList->addRow(_('User name'),
	new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_SMALL_SIZE, false, 64), false, 'row_username'
);
$itemFormList->addRow(_('Public key file'),
	new CTextBox('publickey', $this->data['publickey'], ZBX_TEXTBOX_SMALL_SIZE, false, 64), false, 'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	new CTextBox('privatekey', $this->data['privatekey'], ZBX_TEXTBOX_SMALL_SIZE, false, 64), false,  'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	new CTextBox('password', $this->data['password'], ZBX_TEXTBOX_SMALL_SIZE, false, 64), false, 'row_password'
);
$itemFormList->addRow(_('Executed script'),
	new CTextArea('params_es', $this->data['params'], array('rows' => ZBX_TEXTAREA_STANDARD_ROWS, 'width' => ZBX_TEXTAREA_STANDARD_WIDTH)),
	false, 'label_executed_script'
);
$itemFormList->addRow(_('SQL query'),
	new CTextArea('params_ap',
		$this->data['params'],
		array('rows' => ZBX_TEXTAREA_STANDARD_ROWS, 'width' => ZBX_TEXTAREA_STANDARD_WIDTH)
	),
	false,
	'label_params'
);

$itemFormList->addRow(_('Update interval (in sec)'), new CNumericBox('delay', $this->data['delay'], 5), false, 'row_delay');

// append delay flex to form list
$delayFlexTable = new CTable(_('No flexible intervals defined.'), 'formElementTable');
$delayFlexTable->setAttribute('style', 'min-width: 310px;');
$delayFlexTable->setAttribute('id', 'delayFlexTable');
$delayFlexTable->setHeader(array(_('Interval'), _('Period'), _('Action')));
$i = 0;
$this->data['maxReached'] = false;
foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}
	$itemForm->addVar('delay_flex['.$i.'][delay]', $delayFlex['delay']);
	$itemForm->addVar('delay_flex['.$i.'][period]', $delayFlex['period']);

	$row = new CRow(array(
		$delayFlex['delay'],
		$delayFlex['period'],
		new CButton('remove', _('Remove'), 'javascript: removeDelayFlex('.$i.');', 'link_menu')
	));
	$row->setAttribute('id', 'delayFlex_'.$i);
	$delayFlexTable->addRow($row);

	// limit count of intervals, 7 intervals by 30 symbols = 210 characters, db storage field is 256
	$i++;
	if ($i == 7) {
		$this->data['maxReached'] = true;
		break;
	}
}
$itemFormList->addRow(_('Flexible intervals'),
	new CDiv($delayFlexTable, 'objectgroup inlineblock border_dotted ui-corner-all'), false, 'row_flex_intervals'
);

// append new flexible interval to form list
$newFlexInt = new CSpan(array(
	_('Interval (in sec)'),
	SPACE,
	new CNumericBox('new_delay_flex[delay]', $this->data['new_delay_flex']['delay'], 5, false, false, false),
	SPACE,
	_('Period'),
	SPACE,
	new CTextBox('new_delay_flex[period]', $this->data['new_delay_flex']['period'], 20),
	SPACE,
	new CButton('add_delay_flex', _('Add'), null, 'formlist')
));
$newFlexInt->setAttribute('id', 'row-new-delay-flex-fields');

$maxFlexMsg = new CSpan(_('Maximum number of flexible intervals added'), 'red');
$maxFlexMsg->setAttribute('id', 'row-new-delay-flex-max-reached');
$maxFlexMsg->setAttribute('style', 'display: none;');

$itemFormList->addRow(_('New flexible interval'), array($newFlexInt, $maxFlexMsg), false, 'row_new_delay_flex', 'new');

$itemFormList->addRow(_('Keep lost resources period (in days)'), new CTextBox('lifetime', $this->data['lifetime'], ZBX_TEXTBOX_SMALL_SIZE, false, 64));

$itemFormList->addRow(_('Allowed hosts'),
	new CTextBox('trapper_hosts', $this->data['trapper_hosts'], ZBX_TEXTBOX_STANDARD_SIZE),
	false, 'row_trapper_hosts');

// append description to form list
$description = new CTextArea('description', $this->data['description']);
$description->addStyle('margin-top: 5px;');
$itemFormList->addRow(_('Description'), $description);

// status
$enabledCheckBox = new CCheckBox('status', !$this->data['status'], null, ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

/*
 * Condition tab
 */
$conditionFormList = new CFormList('conditionlist');

// type of calculation
$formula = new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_STANDARD_SIZE);
$formula->attr('id', 'formula');
$formula->attr('placeholder', 'A or (B and C) &hellip;');
if ($this->data['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION)  {
	$formula->addClass('hidden');
}
$conditionFormList->addRow(_('Type of calculation'),
	array(
		new CComboBox('evaltype', $this->data['evaltype'], null, array(
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or'),
			CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
		)),
		new CSpan('', ($this->data['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) ? 'hidden' : '', 'expression'),
		$formula
	),
	(count($this->data['conditions']) < 2), 'conditionRow'
);

// macros
$conditionTable = new CTable('', 'formElementTable');
$conditionTable->attr('id', 'conditions');
$conditionTable->addRow(array(_('Label'), _('Macro'), SPACE, _('Regular expression'), SPACE));

$conditions = $this->data['conditions'];
if (!$conditions) {
	$conditions = array(array('macro' => '', 'value' => '', 'formulaid' => num2letter(0)));
}
else {
	$conditions = CConditionHelper::sortConditionsByFormulaId($conditions);
}

// fields
foreach ($conditions as $i => $condition) {
	// formula id
	$formulaId = array(
		new CSpan($condition['formulaid']),
		new CVar('conditions['.$i.'][formulaid]', $condition['formulaid'])
	);

	// macro
	$macro = new CTextBox('conditions['.$i.'][macro]', $condition['macro'], 30, false, 64);
	$macro->addClass('macro');
	$macro->setAttribute('placeholder', '{#MACRO}');
	$macro->setAttribute('data-formulaid', $condition['formulaid']);

	// value
	$value = new CTextBox('conditions['.$i.'][value]', $condition['value'], 40, false, 255);
	$value->setAttribute('placeholder', _('regular expression'));

	// delete button
	$deleteButtonCell = array(new CButton('conditions_'.$i.'_remove', _('Remove'), null, 'link_menu element-table-remove'));

	$row = array($formulaId, $macro, new CSpan(_('matches')), $value, $deleteButtonCell);
	$conditionTable->addRow($row, 'form_row');
}

$addButton = new CButton('macro_add', _('Add'), null, 'link_menu element-table-add');
$buttonColumn = new CCol($addButton);
$buttonColumn->setAttribute('colspan', 5);

$buttonRow = new CRow();
$buttonRow->setAttribute('id', 'row_new_macro');
$buttonRow->addItem($buttonColumn);

$conditionTable->addRow($buttonRow);

$conditionFormList->addRow(_('Filters'), new CDiv($conditionTable, 'objectgroup inlineblock border_dotted ui-corner-all'));


// append tabs to form
$itemTab = new CTabView();
if (!hasRequest('form_refresh')) {
	$itemTab->setSelected(0);
}
$itemTab->addTab('itemTab', $this->data['caption'], $itemFormList);
$itemTab->addTab('macroTab', _('Filters'), $conditionFormList);
$itemForm->addItem($itemTab);

// append buttons to form
if (!empty($this->data['itemid'])) {
	if (!$this->data['limited']) {
		$btnDelete = new CButtonDelete(
			_('Delete discovery rule?'),
			url_params(array('form', 'groupid', 'itemid', 'parent_discoveryid', 'hostid'))
		);
	}
	else {
		$btnDelete = null;
	}

	$itemForm->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('clone', _('Clone')),
			$btnDelete,
			new CButtonCancel(url_param('groupid').url_param('parent_discoveryid').url_param('hostid'))
		)
	));
}
else {
	$itemForm->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel(url_param('groupid').url_param('parent_discoveryid').url_param('hostid'))
	));
}
$itemWidget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.host.discovery.edit.js.php';

return $itemWidget;
