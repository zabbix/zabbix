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

if ($this->data['parent_discoveryid'] == 0) {
	$host = $this->data['host'];
	$title = _('Items');
}
else {
	$title = _('Item prototypes');
}

$widget = (new CWidget())->setTitle($title);

if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('items', $this->data['hostid'], $this->data['parent_discoveryid']));
}

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
	$itemFormList->addRow(_('Parent items'), $this->data['templates']);
}

$itemFormList->addRow(_('Name'),
	(new CTextBox('name', $this->data['name'], $this->data['limited']))
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
	$itemFormList->addRow(_('Type'), new CComboBox('type', $this->data['type'], null, $this->data['types']));
}

$key_controls = [
	(new CTextBox('key', $this->data['key'], $this->data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
];
if (!$this->data['limited']) {
	$key_controls[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$key_controls[] = (new CButton('keyButton', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.php?srctbl=help_items&srcfld1=key&dstfrm='.$itemForm->getName().
			'&dstfld1=key&itemtype="+jQuery("#type option:selected").val());');

}

// append key to form list
$itemFormList->addRow(_('Key'), $key_controls);

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
	(new CTextBox('username', $this->data['username'], false, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_username'
);
$itemFormList->addRow(_('Public key file'),
	(new CTextBox('publickey', $this->data['publickey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	(new CTextBox('privatekey', $this->data['privatekey'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	false, 'row_privatekey'
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
	false, 'label_params'
);
$itemFormList->addRow(_('Formula'),
	(new CTextArea('params_f', $this->data['params']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'label_formula'
);

// append value type to form list
if ($this->data['limited']) {
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

// append data type to form list
if ($this->data['limited']) {
	$itemForm->addVar('data_type', $this->data['data_type']);
	$dataType = (new CTextBox('data_type_name', item_data_type2str($this->data['data_type']), true))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}
else {
	$dataType = new CComboBox('data_type', $this->data['data_type'], null,  item_data_type2str());
}
$itemFormList->addRow(_('Data type'), $dataType, false, 'row_data_type');
$itemFormList->addRow(_('Units'),
	(new CTextBox('units', $this->data['units'], $this->data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_units'
);

// append multiplier to form list
if ($this->data['limited']) {
	$itemForm->addVar('multiplier', $this->data['multiplier']);

	$multiplier = [
		(new CCheckBox('multiplier'))
			->setChecked($this->data['multiplier'] == 1)
			->setAttribute('disabled', 'disabled'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('formula', $this->data['formula'], true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('style', 'text-align: right;')
	];
}
else {
	$multiplier = [
		(new CCheckBox('multiplier'))
			->setChecked($this->data['multiplier'] == 1)
			->onClick('var editbx = document.getElementById(\'formula\'); if (editbx) { editbx.disabled = !this.checked; }'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('formula', $this->data['formula']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('style', 'text-align: right;')
	];
}
$itemFormList->addRow(_('Use custom multiplier'), $multiplier, false, 'row_multiplier');

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

	$row = new CRow([
		$delayFlex['delay'],
		$delayFlex['period'],
		(new CButton('remove', _('Remove')))
			->onClick('javascript: removeDelayFlex('.$i.');')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);
	$row->setId('delayFlex_'.$i);
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

$itemFormList->addRow(_('New flexible interval'), [$newFlexInt, $maxFlexMsg], false, 'row_new_delay_flex', 'new');

$keepHistory = [];
$keepHistory[] = (new CNumericBox('history', $this->data['history'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
if ($data['config']['hk_history_global'] && $data['parent_discoveryid'] == 0
		&& ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)) {

	$keepHistory[] = ' '._x('Overridden by', 'item_form').' ';
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$link = (new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php'))
			->setAttribute('target', '_blank');
		$keepHistory[] = $link;
	}
	else {
		$keepHistory[] = _x('global housekeeping settings', 'item_form');
	}
	$keepHistory[] = ' ('._n('%1$s day', '%1$s days', $data['config']['hk_history']).')';
}
$itemFormList->addRow(_('History storage period (in days)'), $keepHistory);

$keepTrend = [];
$keepTrend[] = (new CNumericBox('trends', $this->data['trends'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
if ($data['config']['hk_trends_global'] && $data['parent_discoveryid'] == 0
		&& ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)) {

	$keepTrend[] = ' '._x('Overridden by', 'item_form').' ';
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$link = (new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php'))
			->setAttribute('target', '_blank');
		$keepTrend[] = $link;
	}
	else {
		$keepTrend[] = _x('global housekeeping settings', 'item_form');
	}
	$keepTrend[] = ' ('._n('%1$s day', '%1$s days', $data['config']['hk_trends']).')';
}

$itemFormList->addRow(_('Trend storage period (in days)'), $keepTrend, false, 'row_trends');
$itemFormList->addRow(_('Log time format'),
	(new CTextBox('logtimefmt', $this->data['logtimefmt'], $this->data['limited'], 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_logtimefmt'
);

// append delta to form list
$deltaOptions = [
	0 => _('As is'),
	1 => _('Delta (speed per second)'),
	2 => _('Delta (simple change)')
];
if ($this->data['limited']) {
	$itemForm->addVar('delta', $this->data['delta']);
	$deltaComboBox = (new CTextBox('delta_name', $deltaOptions[$this->data['delta']], true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}
else {
	$deltaComboBox= new CComboBox('delta', $this->data['delta'], null, $deltaOptions);
}
$itemFormList->addRow(_('Store value'), $deltaComboBox, false, 'row_delta');

// append valuemap to form list
if ($this->data['limited']) {
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
$itemFormList->addRow(_('Show value'), [$valuemapComboBox, SPACE, $link], null, 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'),
	(new CTextBox('trapper_hosts', $this->data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	false, 'row_trapper_hosts');

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

// append populate host to form list
if (empty($this->data['parent_discoveryid'])) {
	$itemCloned = isset($_REQUEST['clone']);
	$hostInventoryFieldComboBox = new CComboBox('inventory_link');
	$hostInventoryFieldComboBox->addItem(0, '-'._('None').'-', $this->data['inventory_link'] == '0' ? 'yes' : null);

	// a list of available host inventory fields
	foreach ($this->data['possibleHostInventories'] as $fieldNo => $fieldInfo) {
		if (isset($this->data['alreadyPopulated'][$fieldNo])) {
			$enabled = isset($this->data['item']['inventory_link'])
				? $this->data['item']['inventory_link'] == $fieldNo
				: $this->data['inventory_link'] == $fieldNo && !$itemCloned;
		}
		else {
			$enabled = true;
		}
		$hostInventoryFieldComboBox->addItem(
			$fieldNo,
			$fieldInfo['title'],
			$this->data['inventory_link'] == $fieldNo && $enabled ? 'yes' : null,
			$enabled
		);
	}
	$itemFormList->addRow(_('Populates host inventory field'), $hostInventoryFieldComboBox, false, 'row_inventory_link');
}

// append description to form list
$itemFormList->addRow(_('Description'),
	(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// status
$enabledCheckBox = (new CCheckBox('status', ITEM_STATUS_ACTIVE))->setChecked($this->data['status'] != ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

// append tabs to form
$itemTab = (new CTabView())->addTab('itemTab', $this->data['caption'], $itemFormList);

// append buttons to form
if ($this->data['itemid'] != 0) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($this->data['parent_discoveryid'] == 0
			&& ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)) {
		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

	if (!$this->data['limited']) {
		$buttons[] = new CButtonDelete(
			$this->data['parent_discoveryid'] ? _('Delete item prototype?') : _('Delete item?'),
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

require_once dirname(__FILE__).'/js/configuration.item.edit.js.php';

return $widget;
