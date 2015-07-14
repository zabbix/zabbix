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


$itemWidget = (new CWidget())->setTitle(_('Items'));

if (!empty($this->data['hostid'])) {
	$itemWidget->addItem(get_header_host_table('items', $this->data['hostid']));
}

// create form
$itemForm = (new CForm())
	->setName('itemForm')
	->addVar('group_itemid', $this->data['itemids'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('action', $this->data['action']);

// create form list
$itemFormList = new CFormList('itemFormList');

// append type to form list
$itemFormList->addRow(
	[
		_('Type'),
		SPACE,
		(new CVisibilityBox('visible[type]', 'type', _('Original')))
			->setChecked(isset($this->data['visible']['type']))
	],
	new CComboBox('type', $this->data['type'], null, $this->data['itemTypes'])
);

// append hosts to form list
if ($this->data['displayInterfaces']) {
	$interfacesComboBox = new CComboBox('interfaceid', $this->data['interfaceid']);
	$interfacesComboBox->addItem(new CComboItem(0, '', null, false));

	// set up interface groups
	$interfaceGroups = [];
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

	$span = (new CSpan(_('No interface found')))
		->addClass(ZBX_STYLE_RED)
		->setId('interface_not_defined')
		->setAttribute('style', 'display: none;');

	$interfaceVisBox = (new CVisibilityBox('visible[interface]', 'interfaceDiv', _('Original')))
		->setChecked(isset($this->data['visible']['interface']))
		->setAttribute('data-multiple-interface-types', $this->data['multiple_interface_types']);
	$itemFormList->addRow(
		[_('Host interface'), SPACE, $interfaceVisBox],
		(new CDiv([$interfacesComboBox, $span]))->setId('interfaceDiv'),
		false,
		'interface_row'
	);
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}

// append snmp community to form list
$itemFormList->addRow(
	[
		_('SNMP community'),
		SPACE,
		(new CVisibilityBox('visible[community]', 'snmp_community', _('Original')))
			->setChecked(isset($this->data['visible']['community']))
	],
	(new CTextBox('snmp_community', $this->data['snmp_community']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 contextname to form list
$itemFormList->addRow(
	[
		_('Context name'),
		SPACE,
		(new CVisibilityBox('visible[contextname]', 'snmpv3_contextname', _('Original')))
			->setChecked(isset($this->data['visible']['contextname']))
	],
	(new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 securityname to form list
$itemFormList->addRow(
	[
		_('Security name'),
		SPACE,
		(new CVisibilityBox('visible[securityname]', 'snmpv3_securityname', _('Original')))
			->setChecked(isset($this->data['visible']['securityname']))
	],
	(new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 securitylevel to form list
$itemFormList->addRow(
	[
		_('Security level'),
		SPACE,
		(new CVisibilityBox('visible[securitylevel]', 'snmpv3_securitylevel', _('Original')))
			->setChecked(isset($this->data['visible']['securitylevel']))
	],
	new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel'], null, [
		ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
		ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
	])
);

// append snmpv3 authprotocol to form list
$authProtocol = (new CDiv(
	[
		(new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_MD5))
			->setId('snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
		new CLabel(_('MD5'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5),
		(new CRadioButton('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_SHA, $this->data['snmpv3_authprotocol'] == ITEM_AUTHPROTOCOL_SHA))
		->setId('snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA),
		new CLabel(_('SHA'), 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
]))
	->addClass('jqueryinputset')
	->addClass('radioset')
	->setId('authprotocol_div');
$itemFormList->addRow(
	[
		_('Authentication protocol'),
		SPACE,
		(new CVisibilityBox('visible[authprotocol]', 'authprotocol_div', _('Original')))
			->setChecked(isset($this->data['visible']['authprotocol']))
	],
	$authProtocol
);

// append snmpv3 authpassphrase to form list
$itemFormList->addRow(
	[
		_('Authentication passphrase'),
		SPACE,
		(new CVisibilityBox('visible[authpassphrase]', 'snmpv3_authpassphrase', _('Original')))
			->setChecked(isset($this->data['visible']['authpassphrase']))
	],
	(new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 privprotocol to form list
$privProtocol = (new CDiv(
	[
		(new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_DES))
			->setId('snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
		new CLabel(_('DES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES),
		(new CRadioButton('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_AES, $this->data['snmpv3_privprotocol'] == ITEM_PRIVPROTOCOL_AES))
			->setId('snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES),
		new CLabel(_('AES'), 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
]))
	->addClass('jqueryinputset')
	->addClass('radioset')
	->setId('privprotocol_div');
$itemFormList->addRow(
	[
		_('Privacy protocol'),
		SPACE,
		(new CVisibilityBox('visible[privprotocol]', 'privprotocol_div', _('Original')))
			->setChecked(isset($this->data['visible']['privprotocol']))
	],
	$privProtocol
);

// append snmpv3 privpassphrase to form list
$itemFormList->addRow(
	[
		_('Privacy passphrase'),
		SPACE,
		(new CVisibilityBox('visible[privpassphras]', 'snmpv3_privpassphrase', _('Original')))
			->setChecked(isset($this->data['visible']['privpassphras']))
	],
	(new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append port to form list
$itemFormList->addRow(
	[
		_('Port'),
		SPACE,
		(new CVisibilityBox('visible[port]', 'port', _('Original')))
			->setChecked(isset($this->data['visible']['port']))
	],
	(new CTextBox('port', $this->data['port']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append value type to form list
$itemFormList->addRow(
	[
		_('Type of information'),
		SPACE,
		(new CVisibilityBox('visible[value_type]', 'value_type', _('Original')))
			->setChecked(isset($this->data['visible']['value_type']))
	],
	new CComboBox('value_type', $this->data['value_type'], null, [
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text')
	])
);

// append data type to form list
$itemFormList->addRow(
	[
		_('Data type'),
		SPACE,
		(new CVisibilityBox('visible[data_type]', 'data_type', _('Original')))
			->setChecked(isset($this->data['visible']['data_type']))
	],
	new CComboBox('data_type', $this->data['data_type'], null, item_data_type2str())
);

// append units to form list
$itemFormList->addRow(
	[
		_('Units'),
		SPACE,
		(new CVisibilityBox('visible[units]', 'units', _('Original')))
			->setChecked(isset($this->data['visible']['units']))
	],
	(new CTextBox('units', $this->data['units']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append authtype to form list
$itemFormList->addRow(
	[
		_('Authentication method'),
		SPACE,
		(new CVisibilityBox('visible[authtype]', 'authtype', _('Original')))
			->setChecked(isset($this->data['visible']['authtype']))
	],
	new CComboBox('authtype', $this->data['authtype'], null, [
		ITEM_AUTHTYPE_PASSWORD => _('Password'),
		ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
	])
);

// append username to form list
$itemFormList->addRow(
	[
		_('User name'),
		SPACE,
		(new CVisibilityBox('visible[username]', 'username', _('Original')))
			->setChecked(isset($this->data['visible']['username']))
	],
	(new CTextBox('username', $this->data['username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append publickey to form list
$itemFormList->addRow(
	[
		_('Public key file'),
		SPACE,
		(new CVisibilityBox('visible[publickey]', 'publickey', _('Original')))
			->setChecked(isset($this->data['visible']['publickey']))
	],
	(new CTextBox('publickey', $this->data['publickey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append privatekey to form list
$itemFormList->addRow(
	[
		_('Private key file'),
		SPACE,
		(new CVisibilityBox('visible[privatekey]', 'privatekey', _('Original')))
			->setChecked(isset($this->data['visible']['privatekey']))
	],
	(new CTextBox('privatekey', $this->data['privatekey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append password
$itemFormList->addRow(
	[
		_('Password'),
		SPACE,
		(new CVisibilityBox('visible[password]', 'password', _('Original')))
			->setChecked(isset($this->data['visible']['password']))
	],
	(new CTextBox('password', $this->data['password']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append formula to form list
$itemFormList->addRow(
	[
		_('Custom multiplier').' (0 - '._('Disabled').')',
		SPACE,
		(new CVisibilityBox('visible[formula]', 'formula', _('Original')))
			->setChecked(isset($this->data['visible']['formula']))
	],
	(new CTextBox('formula', $this->data['formula']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAttribute('style', 'text-align: right;')
);

// append delay to form list
$itemFormList->addRow(
	[
		_('Update interval (in sec)'),
		SPACE,
		(new CVisibilityBox('visible[delay]', 'delay', _('Original')))
			->setChecked(isset($this->data['visible']['delay']))
	],
	(new CNumericBox('delay', $this->data['delay'], 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
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
$itemFormList->addRow(
	[
		_('Flexible intervals'),
		SPACE,
		(new CVisibilityBox('visible[delay_flex]', ['delayFlexDiv', 'row-new-delay-flex-fields'], _('Original')))
			->setChecked(isset($this->data['visible']['delay_flex']))
	],
	(new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setId('delayFlexDiv')
);

// append new delay to form list
$newFlexInt = (new CDiv([
		_('Interval (in sec)'),
		SPACE,
		(new CNumericBox('new_delay_flex[delay]', 50, 5, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
		SPACE,
		_('Period'),
		SPACE,
		(new CTextBox('new_delay_flex[period]', ZBX_DEFAULT_INTERVAL))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		SPACE,
		(new CSubmit('add_delay_flex', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
]))
	->setId('row-new-delay-flex-fields');

$maxFlexMsg = (new CSpan(_('Maximum number of flexible intervals added')))
	->addClass(ZBX_STYLE_RED)
	->setId('row-new-delay-flex-max-reached')
	->setAttribute('style', 'display: none;');

$itemFormList->addRow(_('New flexible interval'), [$newFlexInt, $maxFlexMsg], false, 'row_new_delay_flex', 'new');

// append history to form list
$itemFormList->addRow(
	[
		_('History storage period (in days)'),
		SPACE,
		(new CVisibilityBox('visible[history]', 'history', _('Original')))
			->setChecked(isset($this->data['visible']['history']))
	],
	(new CNumericBox('history', $this->data['history'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
);

// append trends to form list
$itemFormList->addRow(
	[
		_('Trend storage period (in days)'),
		SPACE,
		(new CVisibilityBox('visible[trends]', 'trends', _('Original')))
			->setChecked(isset($this->data['visible']['trends']))
	],
	(new CNumericBox('trends', $this->data['trends'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
);

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
foreach ([ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED] as $status) {
	$statusComboBox->addItem($status, item_status2str($status));
}
$itemFormList->addRow(
	[
		_('Status'),
		SPACE,
		(new CVisibilityBox('visible[status]', 'status', _('Original')))
			->setChecked(isset($this->data['visible']['status']))
	],
	$statusComboBox
);

// append logtime to form list
$itemFormList->addRow(
	[
		_('Log time format'),
		SPACE,
		(new CVisibilityBox('visible[logtimefmt]', 'logtimefmt', _('Original')))
			->setChecked(isset($this->data['visible']['logtimefmt']))
	],
	(new CTextBox('logtimefmt', $this->data['logtimefmt']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append delta to form list
$itemFormList->addRow(
	[
		_('Store value'),
		SPACE,
		(new CVisibilityBox('visible[delta]', 'delta', _('Original')))
			->setChecked(isset($this->data['visible']['delta']))
	],
	new CComboBox('delta', $this->data['delta'], null, [
		0 => _('As is'),
		1 => _('Delta (speed per second)'),
		2 => _('Delta (simple change)')
	])
);

// append valuemap to form list
$valueMapsComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
$valueMapsComboBox->addItem(0, _('As is'));
foreach ($this->data['valuemaps'] as $valuemap) {
	$valueMapsComboBox->addItem($valuemap['valuemapid'], $valuemap['name']);
}
$valueMapLink = (new CLink(_('show value mappings'), 'adm.valuemapping.php'))
	->setAttribute('target', '_blank');

$itemFormList->addRow(
	[
		_('Show value'),
		SPACE,
		(new CVisibilityBox('visible[valuemapid]', 'valuemap', _('Original')))
			->setChecked(isset($this->data['visible']['valuemapid']))
	],
	(new CDiv([$valueMapsComboBox, SPACE, $valueMapLink]))
		->setId('valuemap')
);

// append trapper hosts to form list
$itemFormList->addRow(
	[
		_('Allowed hosts'),
		SPACE,
		(new CVisibilityBox('visible[trapper_hosts]', 'trapper_hosts', _('Original')))
			->setChecked(isset($this->data['visible']['trapper_hosts']))
	],
	(new CTextBox('trapper_hosts', $this->data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append applications to form list
if ($this->data['displayApplications']) {
	// replace applications
	$appToReplace = null;
	if (hasRequest('applications')) {
		$getApps = API::Application()->get([
			'applicationids' => getRequest('applications'),
			'output' => ['applicationid', 'name']
		]);
		foreach ($getApps as $getApp) {
			$appToReplace[] = [
				'id' => $getApp['applicationid'],
				'name' => $getApp['name']
			];
		}
	}

	$replaceApp = (new CDiv(
		(new CMultiSelect([
			'name' => 'applications[]',
			'objectName' => 'applications',
			'objectOptions' => ['hostid' => $this->data['hostid']],
			'data' => $appToReplace,
			'popup' => [
				'parameters' => 'srctbl=applications&dstfrm='.$itemForm->getName().'&dstfld1=applications_'.
					'&srcfld1=applicationid&multiselect=1&noempty=1&hostid='.$this->data['hostid']
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->setId('replaceApp');

	$itemFormList->addRow(
		[_('Replace applications'), SPACE,
			(new CVisibilityBox('visible[applications]', 'replaceApp', _('Original')))
				->setChecked(isset($this->data['visible']['applications']))
		],
		$replaceApp
	);

	// add new or existing applications
	$appToAdd = null;
	if (hasRequest('new_applications')) {
		foreach (getRequest('new_applications') as $newApplication) {
			if (is_array($newApplication) && isset($newApplication['new'])) {
				$appToAdd[] = [
					'id' => $newApplication['new'],
					'name' => $newApplication['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			else {
				$appToAddId[] = $newApplication;
			}
		}

		if (isset($appToAddId)) {
			$getApps = API::Application()->get([
				'applicationids' => $appToAddId,
				'output' => ['applicationid', 'name']
			]);
			foreach ($getApps as $getApp) {
				$appToAdd[] = [
					'id' => $getApp['applicationid'],
					'name' => $getApp['name']
				];
			}
		}
	}

	$newApp = (new CDiv(
		(new CMultiSelect([
			'name' => 'new_applications[]',
			'objectName' => 'applications',
			'objectOptions' => ['hostid' => $this->data['hostid']],
			'data' => $appToAdd,
			'addNew' => true,
			'popup' => [
				'parameters' => 'srctbl=applications&dstfrm='.$itemForm->getName().'&dstfld1=new_applications_'.
					'&srcfld1=applicationid&multiselect=1&noempty=1&hostid='.$this->data['hostid']
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->setId('newApp');

	$itemFormList->addRow(
		[_('Add new or existing applications'), SPACE,
			(new CVisibilityBox('visible[new_applications]', 'newApp', _('Original')))
				->setChecked(isset($this->data['visible']['new_applications']))
		],
		$newApp
	);
}

// append description to form list
$itemFormList->addRow(
	[
		_('Description'),
		SPACE,
		(new CVisibilityBox('visible[description]', 'description', _('Original')))
			->setChecked(isset($this->data['visible']['description']))
	],
	(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);
// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', _('Mass update'), $itemFormList);

// append buttons to form
$itemTab->setFooter(makeFormFooter(
	new CSubmit('massupdate', _('Update')),
	[new CButtonCancel(url_param('groupid').url_param('hostid'))]
));

$itemForm->addItem($itemTab);
$itemWidget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.massupdate.js.php';

return $itemWidget;
