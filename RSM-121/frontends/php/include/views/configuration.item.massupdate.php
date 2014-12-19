<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
$itemWidget = new CWidget();

if (!empty($this->data['hostid'])) {
	$itemWidget->addItem(get_header_host_table('items', $this->data['hostid']));
}

$itemWidget->addPageHeader(_('CONFIGURATION OF ITEMS'));

// create form
$itemForm = new CForm();
$itemForm->setName('itemForm');
$itemForm->addVar('massupdate', 1);
$itemForm->addVar('group_itemid', $this->data['itemids']);
$itemForm->addVar('hostid', $this->data['hostid']);

// create form list
$itemFormList = new CFormList('itemFormList');

// append type to form list
$typeComboBox = new CComboBox('type', $this->data['type']);
$typeComboBox->addItems($this->data['itemTypes']);
$itemFormList->addRow(
	array(
		_('Type'),
		SPACE,
		new CVisibilityBox('type_visible', get_request('type_visible'), 'type', _('Original'))
	),
	$typeComboBox
);

// append hosts to form list
if (!empty($this->data['hosts']) && !empty($this->data['hosts']['interfaces']) && !$this->data['is_multiple_hosts']) {
	$interfacesComboBox = new CComboBox('interfaceid', $this->data['interfaceid']);
	$interfacesComboBox->addItem(new CComboItem(0, '', null, 'no'));

	// set up interface groups
	$interfaceGroups = array();
	foreach (zbx_objectValues($this->data['hosts']['interfaces'], 'type') as $interfaceType) {
		$interfaceGroups[$interfaceType] = new COptGroup(interfaceType2str($interfaceType));
	}

	// add interfaces to groups
	foreach ($this->data['hosts']['interfaces'] as $interface) {
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

	$interfaceVisBox = new CVisibilityBox('interface_visible', get_request('interface_visible'), 'interfaceDiv', _('Original'));
	$interfaceVisBox->setAttribute('data-multiple-interface-types', $this->data['multiple_interface_types']);
	$itemFormList->addRow(
		array(_('Host interface'), SPACE, $interfaceVisBox),
		new CDiv(array($interfacesComboBox, $span), null, 'interfaceDiv'),
		false,
		'interface_row'
	);
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}

// append snmp community to form list
$itemFormList->addRow(
	array(
		_('SNMP community'),
		SPACE,
		new CVisibilityBox('community_visible', get_request('community_visible'), 'snmp_community', _('Original'))
	),
	new CTextBox('snmp_community', $this->data['snmp_community'], ZBX_TEXTBOX_SMALL_SIZE)
);

// append snmpv3 securityname to form list
$itemFormList->addRow(
	array(
		_('SNMPv3 security name'),
		SPACE,
		new CVisibilityBox('securityname_visible', get_request('securityname_visible'), 'snmpv3_securityname', _('Original'))
	),
	new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append snmpv3 securitylevel to form list
$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel']);
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv');
$itemFormList->addRow(
	array(
		_('SNMPv3 security level'),
		SPACE,
		new CVisibilityBox('securitylevel_visible', get_request('securitylevel_visible'), 'snmpv3_securitylevel', _('Original'))
	),
	$securityLevelComboBox
);

// append snmpv3 authpassphrase to form list
$itemFormList->addRow(
	array(
		_('SNMPv3 auth passphrase'),
		SPACE,
		new CVisibilityBox('authpassphrase_visible', get_request('authpassphrase_visible'), 'snmpv3_authpassphrase', _('Original'))
	),
	new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append snmpv3 privpassphrase to form list
$itemFormList->addRow(
	array(
		_('SNMPv3 priv passphrase'),
		SPACE,
		new CVisibilityBox('privpassphras_visible', get_request('privpassphras_visible'), 'snmpv3_privpassphrase', _('Original'))
	),
	new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append port to form list
$itemFormList->addRow(
	array(
		_('Port'),
		SPACE,
		new CVisibilityBox('port_visible', get_request('port_visible'), 'port', _('Original'))
	),
	new CTextBox('port', $this->data['port'], ZBX_TEXTBOX_SMALL_SIZE)
);

// append value type to form list
$valueTypeComboBox = new CComboBox('value_type', $this->data['value_type']);
$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_UINT64, _('Numeric (unsigned)'));
$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_FLOAT, _('Numeric (float)'));
$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_STR, _('Character'));
$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_LOG, _('Log'));
$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_TEXT, _('Text'));
$itemFormList->addRow(
	array(
		_('Type of information'),
		SPACE,
		new CVisibilityBox('value_type_visible', get_request('value_type_visible'), 'value_type', _('Original'))
	),
	$valueTypeComboBox
);

// append data type to form list
$dataTypeComboBox = new CComboBox('data_type', $this->data['data_type']);
$dataTypeComboBox->addItems(item_data_type2str());
$itemFormList->addRow(
	array(
		_('Data type'),
		SPACE,
		new CVisibilityBox('data_type_visible', get_request('data_type_visible'), 'data_type', _('Original'))
	),
	$dataTypeComboBox
);

// append units to form list
$itemFormList->addRow(
	array(
		_('Units'),
		SPACE,
		new CVisibilityBox('units_visible', get_request('units_visible'), 'units', _('Original'))
	),
	new CTextBox('units', $this->data['units'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append authtype to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype']);
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'));
$itemFormList->addRow(
	array(
		_('Authentication method'),
		SPACE,
		new CVisibilityBox('authtype_visible', get_request('authtype_visible'), 'authtype', _('Original'))
	),
	$authTypeComboBox
);

// append username to form list
$itemFormList->addRow(
	array(
		_('User name'),
		SPACE,
		new CVisibilityBox('username_visible', get_request('username_visible'), 'username', _('Original'))
	),
	new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append publickey to form list
$itemFormList->addRow(
	array(
		_('Public key file'),
		SPACE,
		new CVisibilityBox('publickey_visible', get_request('publickey_visible'), 'publickey', _('Original'))
	),
	new CTextBox('publickey', $this->data['publickey'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append privatekey to form list
$itemFormList->addRow(
	array(
		_('Private key file'),
		SPACE,
		new CVisibilityBox('privatekey_visible', get_request('privatekey_visible'), 'privatekey', _('Original'))
	),
	new CTextBox('privatekey', $this->data['privatekey'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append password
$itemFormList->addRow(
	array(
		_('Password'),
		SPACE,
		new CVisibilityBox('password_visible', get_request('password_visible'), 'password', _('Original'))
	),
	new CTextBox('password', $this->data['password'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append formula to form list
$itemFormList->addRow(
	array(
		_('Custom multiplier').' (0 - '._('Disabled').')',
		SPACE,
		new CVisibilityBox('formula_visible', get_request('formula_visible'), 'formula', _('Original'))
	),
	new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append delay to form list
$itemFormList->addRow(
	array(
		_('Update interval (in sec)'),
		SPACE,
		new CVisibilityBox('delay_visible', get_request('delay_visible'), 'delay', _('Original'))
	),
	new CNumericBox('delay', $this->data['delay'], 5)
);

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
$itemFormList->addRow(
	array(
		_('Flexible intervals'),
		SPACE,
		new CVisibilityBox('delay_flex_visible', get_request('delay_flex_visible'), array('delayFlexDiv', 'newDelayFlexDiv'), _('Original'))
	),
	new CDiv($delayFlexTable, 'objectgroup inlineblock border_dotted ui-corner-all', 'delayFlexDiv')
);

// append new delay to form list
$itemFormList->addRow(
	_('New flexible interval'),
	new CDiv(
		array(
			_('Interval (in sec)'),
			SPACE,
			new CNumericBox('new_delay_flex[delay]', 50, 5),
			SPACE,
			_('Period'),
			SPACE,
			new CTextBox('new_delay_flex[period]', ZBX_DEFAULT_INTERVAL, 20),
			SPACE,
			new CSubmit('add_delay_flex', _('Add'), null, 'formlist')
		),
		null,
		'newDelayFlexDiv'
	),
	$this->data['maxReached'],
	'row_new_delay_flex',
	'new'
);

// append history to form list
$itemFormList->addRow(
	array(
		_('Keep history (in days)'),
		SPACE,
		new CVisibilityBox('history_visible', get_request('history_visible'), 'history', _('Original'))
	),
	new CNumericBox('history', $this->data['history'], 8)
);

// append trends to form list
$itemFormList->addRow(
	array(
		_('Keep trends (in days)'),
		SPACE,
		new CVisibilityBox('trends_visible', get_request('trends_visible'), 'trends', _('Original'))
	),
	new CNumericBox('trends', $this->data['trends'], 8)
);

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
foreach (array(ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED, ITEM_STATUS_NOTSUPPORTED) as $status) {
	$statusComboBox->addItem($status, item_status2str($status));
}
$itemFormList->addRow(
	array(
		_('Status'),
		SPACE,
		new CVisibilityBox('status_visible', get_request('status_visible'), 'status', _('Original'))
	),
	$statusComboBox
);

// append logtime to form list
$itemFormList->addRow(
	array(
		_('Log time format'),
		SPACE,
		new CVisibilityBox('logtimefmt_visible', get_request('logtimefmt_visible'), 'logtimefmt', _('Original'))
	),
	new CTextBox('logtimefmt', $this->data['logtimefmt'], ZBX_TEXTBOX_SMALL_SIZE)
);

// append delta to form list
$deltaComboBox = new CComboBox('delta', $this->data['delta']);
$deltaComboBox->addItem(0, _('As is'));
$deltaComboBox->addItem(1, _('Delta (speed per second)'));
$deltaComboBox->addItem(2, _('Delta (simple change)'));
$itemFormList->addRow(
	array(
		_('Store value'),
		SPACE,
		new CVisibilityBox('delta_visible', get_request('delta_visible'), 'delta', _('Original'))
	),
	$deltaComboBox
);

// append valuemap to form list
$valueMapsComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
$valueMapsComboBox->addItem(0, _('As is'));
foreach ($this->data['valuemaps'] as $valuemap) {
	$valueMapsComboBox->addItem($valuemap['valuemapid'], get_node_name_by_elid($valuemap['valuemapid'], null, ': ').$valuemap['name']);
}
$valueMapLink = new CLink(_('show value mappings'), 'adm.valuemapping.php');
$valueMapLink->setAttribute('target', '_blank');

$itemFormList->addRow(
	array(
		_('Show value'),
		SPACE,
		new CVisibilityBox('valuemapid_visible', get_request('valuemapid_visible'), 'valuemap', _('Original'))
	),
	new CDiv(array($valueMapsComboBox, SPACE, $valueMapLink), null, 'valuemap')
);

// append trapper hosts to form list
$itemFormList->addRow(
	array(
		_('Allowed hosts'),
		SPACE,
		new CVisibilityBox('trapper_hosts_visible', get_request('trapper_hosts_visible'), 'trapper_hosts', _('Original'))
	),
	new CTextBox('trapper_hosts', $this->data['trapper_hosts'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append applications to form list
if (!$this->data['is_multiple_hosts']) {
	$applicationsComboBox = new CListBox('applications[]', $this->data['applications'], 6);
	$applicationsComboBox->addItem(0, '-'._('None').'-');
	if (!empty($this->data['db_applications'])) {
		foreach ($this->data['db_applications'] as $application) {
			$applicationsComboBox->addItem($application['applicationid'], $application['name']);
		}
	}
	$itemFormList->addRow(
		array(
			_('Applications'),
			SPACE,
			new CVisibilityBox('applications_visible', get_request('applications_visible'), 'applications_', _('Original'))
		),
		$applicationsComboBox
	);
}

// append description to form list
$descriptionTextArea = new CTextArea('description', $this->data['description']);
$descriptionTextArea->addStyle('margin-top: 5px;');
$itemFormList->addRow(
	array(
		_('Description'),
		SPACE,
		new CVisibilityBox('description_visible', get_request('description_visible'), 'description', _('Original'))
	),
	$descriptionTextArea
);

// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', _('Mass update'), $itemFormList);
$itemForm->addItem($itemTab);

// append buttons to form
$itemForm->addItem(makeFormFooter(new CSubmit('update', _('Update')), new CButtonCancel(url_param('groupid').url_param('hostid').url_param('config'))));
$itemWidget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.edit.js.php';
return $itemWidget;
?>
