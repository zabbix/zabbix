<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		new CVisibilityBox('visible[type]', isset($this->data['visible']['type']), 'type', _('Original'))
	),
	$typeComboBox
);

// append hosts to form list
if ($this->data['displayInterfaces']) {
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

	$interfaceVisBox = new CVisibilityBox('visible[interface]', isset($this->data['visible']['interface']), 'interfaceDiv', _('Original'));
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
		new CVisibilityBox('visible[community]', isset($this->data['visible']['community']), 'snmp_community', _('Original'))
	),
	new CTextBox('snmp_community', $this->data['snmp_community'], ZBX_TEXTBOX_SMALL_SIZE)
);

// append snmpv3 contextname to form list
$itemFormList->addRow(
	array(
		_('Context name'),
		SPACE,
		new CVisibilityBox('visible[contextname]', isset($this->data['visible']['contextname']), 'snmpv3_contextname', _('Original'))
	),
	new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append snmpv3 securityname to form list
$itemFormList->addRow(
	array(
		_('Security name'),
		SPACE,
		new CVisibilityBox('visible[securityname]', isset($this->data['visible']['securityname']), 'snmpv3_securityname', _('Original'))
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
		_('Security level'),
		SPACE,
		new CVisibilityBox('visible[securitylevel]', isset($this->data['visible']['securitylevel']), 'snmpv3_securitylevel', _('Original'))
	),
	$securityLevelComboBox
);

// append snmpv3 authprotocol to form list
$authProtocol = new CDiv(
	array(
		new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5, null, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_MD5),
		new CLabel(_('MD5'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
		new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_SHA, null, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_SHA),
		new CLabel(_('SHA'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
	),
	'jqueryinputset',
	'authprotocol_div'
);
$itemFormList->addRow(
	array(
		_('Authentication protocol'),
		SPACE,
		new CVisibilityBox('visible[authprotocol]', isset($this->data['visible']['authprotocol']), 'authprotocol_div', _('Original'))
	),
	$authProtocol
);

// append snmpv3 authpassphrase to form list
$itemFormList->addRow(
	array(
		_('Authentication passphrase'),
		SPACE,
		new CVisibilityBox('visible[authpassphrase]', isset($this->data['visible']['authpassphrase']), 'snmpv3_authpassphrase', _('Original'))
	),
	new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append snmpv3 privprotocol to form list
$privProtocol = new CDiv(
	array(
		new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES, null, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_DES),
		new CLabel(_('DES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
		new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_AES, null, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_AES),
		new CLabel(_('AES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
	),
	'jqueryinputset',
	'privprotocol_div'
);
$itemFormList->addRow(
	array(
		_('Privacy protocol'),
		SPACE,
		new CVisibilityBox('visible[privprotocol]', isset($this->data['visible']['privprotocol']), 'privprotocol_div', _('Original'))
	),
	$privProtocol
);

// append snmpv3 privpassphrase to form list
$itemFormList->addRow(
	array(
		_('Privacy passphrase'),
		SPACE,
		new CVisibilityBox('visible[privpassphras]', isset($this->data['visible']['privpassphras']), 'snmpv3_privpassphrase', _('Original'))
	),
	new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append port to form list
$itemFormList->addRow(
	array(
		_('Port'),
		SPACE,
		new CVisibilityBox('visible[port]', isset($this->data['visible']['port']), 'port', _('Original'))
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
		new CVisibilityBox('visible[value_type]', isset($this->data['visible']['value_type']), 'value_type', _('Original'))
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
		new CVisibilityBox('visible[data_type]', isset($this->data['visible']['data_type']), 'data_type', _('Original'))
	),
	$dataTypeComboBox
);

// append units to form list
$itemFormList->addRow(
	array(
		_('Units'),
		SPACE,
		new CVisibilityBox('visible[units]', isset($this->data['visible']['units']), 'units', _('Original'))
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
		new CVisibilityBox('visible[authtype]', isset($this->data['visible']['authtype']), 'authtype', _('Original'))
	),
	$authTypeComboBox
);

// append username to form list
$itemFormList->addRow(
	array(
		_('User name'),
		SPACE,
		new CVisibilityBox('visible[username]', isset($this->data['visible']['username']), 'username', _('Original'))
	),
	new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append publickey to form list
$itemFormList->addRow(
	array(
		_('Public key file'),
		SPACE,
		new CVisibilityBox('visible[publickey]', isset($this->data['visible']['publickey']), 'publickey', _('Original'))
	),
	new CTextBox('publickey', $this->data['publickey'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append privatekey to form list
$itemFormList->addRow(
	array(
		_('Private key file'),
		SPACE,
		new CVisibilityBox('visible[privatekey]', isset($this->data['visible']['privatekey']), 'privatekey', _('Original'))
	),
	new CTextBox('privatekey', $this->data['privatekey'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append password
$itemFormList->addRow(
	array(
		_('Password'),
		SPACE,
		new CVisibilityBox('visible[password]', isset($this->data['visible']['password']), 'password', _('Original'))
	),
	new CTextBox('password', $this->data['password'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append formula to form list
$itemFormList->addRow(
	array(
		_('Custom multiplier').' (0 - '._('Disabled').')',
		SPACE,
		new CVisibilityBox('visible[formula]', isset($this->data['visible']['formula']), 'formula', _('Original'))
	),
	new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append delay to form list
$itemFormList->addRow(
	array(
		_('Update interval (in sec)'),
		SPACE,
		new CVisibilityBox('visible[delay]', isset($this->data['visible']['delay']), 'delay', _('Original'))
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
		new CVisibilityBox('visible[delay_flex]', isset($this->data['visible']['delay_flex']), array('delayFlexDiv', 'row-new-delay-flex-fields'), _('Original'))
	),
	new CDiv($delayFlexTable, 'objectgroup inlineblock border_dotted ui-corner-all', 'delayFlexDiv')
);

// append new delay to form list
$newFlexInt = new CDiv(
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
	'row-new-delay-flex-fields'
);

$maxFlexMsg = new CSpan(_('Maximum number of flexible intervals added'), 'red');
$maxFlexMsg->setAttribute('id', 'row-new-delay-flex-max-reached');
$maxFlexMsg->setAttribute('style', 'display: none;');

$itemFormList->addRow(_('New flexible interval'), array($newFlexInt, $maxFlexMsg), false, 'row_new_delay_flex', 'new');

// append history to form list
$itemFormList->addRow(
	array(
		_('History storage period (in days)'),
		SPACE,
		new CVisibilityBox('visible[history]', isset($this->data['visible']['history']), 'history', _('Original'))
	),
	new CNumericBox('history', $this->data['history'], 8)
);

// append trends to form list
$itemFormList->addRow(
	array(
		_('Trend storage period (in days)'),
		SPACE,
		new CVisibilityBox('visible[trends]', isset($this->data['visible']['trends']), 'trends', _('Original'))
	),
	new CNumericBox('trends', $this->data['trends'], 8)
);

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
foreach (array(ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED) as $status) {
	$statusComboBox->addItem($status, item_status2str($status));
}
$itemFormList->addRow(
	array(
		_('Status'),
		SPACE,
		new CVisibilityBox('visible[status]', isset($this->data['visible']['status']), 'status', _('Original'))
	),
	$statusComboBox
);

// append logtime to form list
$itemFormList->addRow(
	array(
		_('Log time format'),
		SPACE,
		new CVisibilityBox('visible[logtimefmt]', isset($this->data['visible']['logtimefmt']), 'logtimefmt', _('Original'))
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
		new CVisibilityBox('visible[delta]', isset($this->data['visible']['delta']), 'delta', _('Original'))
	),
	$deltaComboBox
);

// append valuemap to form list
$valueMapsComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
$valueMapsComboBox->addItem(0, _('As is'));
foreach ($this->data['valuemaps'] as $valuemap) {
	$valueMapsComboBox->addItem($valuemap['valuemapid'], $valuemap['name']);
}
$valueMapLink = new CLink(_('show value mappings'), 'adm.valuemapping.php');
$valueMapLink->setAttribute('target', '_blank');

$itemFormList->addRow(
	array(
		_('Show value'),
		SPACE,
		new CVisibilityBox('visible[valuemapid]', isset($this->data['visible']['valuemapid']), 'valuemap', _('Original'))
	),
	new CDiv(array($valueMapsComboBox, SPACE, $valueMapLink), null, 'valuemap')
);

// append trapper hosts to form list
$itemFormList->addRow(
	array(
		_('Allowed hosts'),
		SPACE,
		new CVisibilityBox('visible[trapper_hosts]', isset($this->data['visible']['trapper_hosts']), 'trapper_hosts', _('Original'))
	),
	new CTextBox('trapper_hosts', $this->data['trapper_hosts'], ZBX_TEXTBOX_STANDARD_SIZE)
);

// append applications to form list
if ($this->data['displayApplications']) {
	// replace applications
	$appToReplace = null;
	if (hasRequest('applications')) {
		$getApps = API::Application()->get(array(
			'applicationids' => getRequest('applications'),
			'output' => array('applicationid', 'name')
		));
		foreach ($getApps as $getApp) {
			$appToReplace[] = array(
				'id' => $getApp['applicationid'],
				'name' => $getApp['name']
			);
		}
	}

	$replaceApp = new CMultiSelect(array(
		'name' => 'applications[]',
		'objectName' => 'applications',
		'objectOptions' => array('hostid' => $this->data['hostid']),
		'data' => $appToReplace
	));

	$itemFormList->addRow(
		array(_('Replace applications'), SPACE, new CVisibilityBox('visible[applications]',
			isset($this->data['visible']['applications']), 'applications_', _('Original')
		)),
		$replaceApp
	);

	// add new or existing applications
	$appToAdd = null;
	if (hasRequest('new_applications')) {
		foreach (getRequest('new_applications') as $newApplication) {
			if (is_array($newApplication) && isset($newApplication['new'])) {
				$appToAdd[] = array(
					'id' => $newApplication['new'],
					'name' => $newApplication['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				);
			}
			else {
				$appToAddId[] = $newApplication;
			}
		}

		if (isset($appToAddId)) {
			$getApps = API::Application()->get(array(
				'applicationids' => $appToAddId,
				'output' => array('applicationid', 'name')
			));
			foreach ($getApps as $getApp) {
				$appToAdd[] = array(
					'id' => $getApp['applicationid'],
					'name' => $getApp['name']
				);
			}
		}
	}

	$newApp = new CMultiSelect(array(
		'name' => 'new_applications[]',
		'objectName' => 'applications',
		'objectOptions' => array('hostid' => $this->data['hostid']),
		'data' => $appToAdd,
		'addNew' => true
	));

	$itemFormList->addRow(
		array(_('Add new or existing applications'), SPACE, new CVisibilityBox('visible[new_applications]',
			isset($this->data['visible']['new_applications']), 'new_applications_', _('Original')
		)),
		$newApp
	);
}

// append description to form list
$descriptionTextArea = new CTextArea('description', $this->data['description']);
$descriptionTextArea->addStyle('margin-top: 5px;');
$itemFormList->addRow(
	array(
		_('Description'),
		SPACE,
		new CVisibilityBox('visible[description]', isset($this->data['visible']['description']), 'description', _('Original'))
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

require_once dirname(__FILE__).'/js/configuration.item.massupdate.js.php';

return $itemWidget;
