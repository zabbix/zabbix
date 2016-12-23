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
	(new CVisibilityBox('visible[type]', 'type', _('Original')))
		->setLabel(_('Type'))
		->setChecked(isset($this->data['visible']['type'])),
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

	$itemFormList->addRow(
		(new CVisibilityBox('visible[interface]', 'interfaceDiv', _('Original')))
			->setLabel(_('Host interface'))
			->setChecked(isset($this->data['visible']['interface']))
			->setAttribute('data-multiple-interface-types', $this->data['multiple_interface_types']),
		(new CDiv([$interfacesComboBox, $span]))->setId('interfaceDiv'),
		'interface_row'
	);
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}

// append snmp community to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[community]', 'snmp_community', _('Original')))
		->setLabel(_('SNMP community'))
		->setChecked(isset($this->data['visible']['community'])),
	(new CTextBox('snmp_community', $this->data['snmp_community']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 contextname to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[contextname]', 'snmpv3_contextname', _('Original')))
		->setLabel(_('Context name'))
		->setChecked(isset($this->data['visible']['contextname'])),
	(new CTextBox('snmpv3_contextname', $this->data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 securityname to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[securityname]', 'snmpv3_securityname', _('Original')))
		->setLabel(_('Security name'))
		->setChecked(isset($this->data['visible']['securityname'])),
	(new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 securitylevel to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[securitylevel]', 'snmpv3_securitylevel', _('Original')))
		->setLabel(_('Security level'))
		->setChecked(isset($this->data['visible']['securitylevel'])),
	new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel'], null, [
		ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
		ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
	])
);

// append snmpv3 authprotocol to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[authprotocol]', 'authprotocol_div', _('Original')))
		->setLabel(_('Authentication protocol'))
		->setChecked(isset($this->data['visible']['authprotocol'])),
	(new CDiv(
		(new CRadioButtonList('snmpv3_authprotocol', (int) $this->data['snmpv3_authprotocol']))
			->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
			->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
			->setModern(true)
	))->setId('authprotocol_div')
);

// append snmpv3 authpassphrase to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[authpassphrase]', 'snmpv3_authpassphrase', _('Original')))
		->setLabel(_('Authentication passphrase'))
		->setChecked(isset($this->data['visible']['authpassphrase'])),
	(new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append snmpv3 privprotocol to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[privprotocol]', 'privprotocol_div', _('Original')))
		->setLabel(_('Privacy protocol'))
		->setChecked(isset($this->data['visible']['privprotocol'])),
	(new CDiv(
		(new CRadioButtonList('snmpv3_privprotocol', (int) $this->data['snmpv3_privprotocol']))
			->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
			->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
			->setModern(true)
	))
		->setId('privprotocol_div')
);

// append snmpv3 privpassphrase to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[privpassphrase]', 'snmpv3_privpassphrase', _('Original')))
		->setLabel(_('Privacy passphrase'))
		->setChecked(isset($this->data['visible']['privpassphrase'])),
	(new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append port to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[port]', 'port', _('Original')))
		->setLabel(_('Port'))
		->setChecked(isset($this->data['visible']['port'])),
	(new CTextBox('port', $this->data['port']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append value type to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[value_type]', 'value_type', _('Original')))
		->setLabel(_('Type of information'))
		->setChecked(isset($this->data['visible']['value_type'])),
	new CComboBox('value_type', $this->data['value_type'], null, [
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text')
	])
);

// append units to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[units]', 'units', _('Original')))
		->setLabel(_('Units'))
		->setChecked(isset($this->data['visible']['units'])),
	(new CTextBox('units', $this->data['units']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append authtype to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[authtype]', 'authtype', _('Original')))
		->setLabel(_('Authentication method'))
		->setChecked(isset($this->data['visible']['authtype'])),
	new CComboBox('authtype', $this->data['authtype'], null, [
		ITEM_AUTHTYPE_PASSWORD => _('Password'),
		ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
	])
);

// append username to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[username]', 'username', _('Original')))
		->setLabel(_('User name'))
		->setChecked(isset($this->data['visible']['username'])),
	(new CTextBox('username', $this->data['username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append publickey to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[publickey]', 'publickey', _('Original')))
		->setLabel(_('Public key file'))
		->setChecked(isset($this->data['visible']['publickey'])),
	(new CTextBox('publickey', $this->data['publickey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append privatekey to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[privatekey]', 'privatekey', _('Original')))
		->setLabel(_('Private key file'))
		->setChecked(isset($this->data['visible']['privatekey'])),
	(new CTextBox('privatekey', $this->data['privatekey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append password
$itemFormList->addRow(
	(new CVisibilityBox('visible[password]', 'password', _('Original')))
		->setLabel(_('Password'))
		->setChecked(isset($this->data['visible']['password'])),
	(new CTextBox('password', $this->data['password']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

// append item pre-processing
$preprocessing = (new CTable())
	->setId('preprocessing')
	->setHeader([
		'',
		new CColHeader(_('Name')),
		new CColHeader(_('Parameters')),
		new CColHeader(null),
		(new CColHeader(_('Action')))->setWidth(50)
	]);

foreach ($data['preprocessing'] as $i => $step) {
	// Depeding on preprocessing type, display corresponding params field and placeholders.
	$params = [];

	// Use numeric box for multiplier, otherwise use text box.
	if ($step['type'] == ZBX_PREPROC_MULTIPLIER) {
		$params[] = (new CNumericBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : '', 255, false, true
		))->setAttribute('placeholder', _('number'));
	}
	else {
		$params[] = new CTextBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : ''
		);
	}

	// Create a secondary param text box, so it can be hidden if necessary.
	$params[] = (new CTextBox('preprocessing['.$i.'][params][1]',
		(array_key_exists('params', $step) && array_key_exists(1, $step['params']))
			? $step['params'][1]
			: ''
	))->setAttribute('placeholder', _('output'));

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

	$preprocessing->addRow(
		(new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CComboBox('preprocessing['.$i.'][type]', $step['type'], null, get_preprocessing_types())),
			$params[0],
			$params[1],
			(new CButton('preprocessing['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('sortable')
	);
}

$preprocessing->addRow(
	(new CCol(
		(new CButton('param_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(5)
);

$itemFormList->addRow(
	(new CVisibilityBox('visible[preprocessing]', 'preprocessing_div', _('Original')))
		->setLabel(_('Preprocessing'))
		->setChecked(isset($this->data['visible']['preprocessing'])),
	(new CDiv($preprocessing))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('preprocessing_div')
);

// append delay to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[delay]', 'delay', _('Original')))
		->setLabel(_('Update interval (in sec)'))
		->setChecked(isset($this->data['visible']['delay'])),
	(new CNumericBox('delay', $this->data['delay'], 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
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

$itemFormList->addRow(
	(new CVisibilityBox('visible[delay_flex]', 'delayFlexDiv', _('Original')))
		->setLabel(_('Custom intervals'))
		->setChecked(isset($this->data['visible']['delay_flex'])),
	(new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('delayFlexDiv')
)
	->addRow(
		(new CVisibilityBox('visible[history]', 'history', _('Original')))
			->setLabel(_('History storage period (in days)'))
			->setChecked(isset($this->data['visible']['history'])),
		(new CNumericBox('history', $this->data['history'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
)
	->addRow(
		(new CVisibilityBox('visible[trends]', 'trends', _('Original')))
			->setLabel(_('Trend storage period (in days)'))
			->setChecked(isset($this->data['visible']['trends'])),
		(new CNumericBox('trends', $this->data['trends'], 8))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
);

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
foreach ([ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED] as $status) {
	$statusComboBox->addItem($status, item_status2str($status));
}
$itemFormList->addRow(
	(new CVisibilityBox('visible[status]', 'status', _('Original')))
		->setLabel(_('Status'))
		->setChecked(isset($this->data['visible']['status'])),
	$statusComboBox
);

// append logtime to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[logtimefmt]', 'logtimefmt', _('Original')))
		->setLabel(_('Log time format'))
		->setChecked(isset($this->data['visible']['logtimefmt'])),
	(new CTextBox('logtimefmt', $this->data['logtimefmt']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
	(new CVisibilityBox('visible[valuemapid]', 'valuemap', _('Original')))
		->setLabel(_('Show value'))
		->setChecked(isset($this->data['visible']['valuemapid'])),
	(new CDiv([$valueMapsComboBox, SPACE, $valueMapLink]))
		->setId('valuemap')
);

// append trapper hosts to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[trapper_hosts]', 'trapper_hosts', _('Original')))
		->setLabel(_('Allowed hosts'))
		->setChecked(isset($this->data['visible']['trapper_hosts'])),
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
		(new CVisibilityBox('visible[applications]', 'replaceApp', _('Original')))
			->setLabel(_('Replace applications'))
			->setChecked(isset($this->data['visible']['applications'])),
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
		(new CVisibilityBox('visible[new_applications]', 'newApp', _('Original')))
			->setLabel(_('Add new or existing applications'))
			->setChecked(isset($this->data['visible']['new_applications'])),
		$newApp
	);
}

// append description to form list
$itemFormList->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))
		->setLabel(_('Description'))
		->setChecked(isset($this->data['visible']['description'])),
	(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);
// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', _('Mass update'), $itemFormList);

// append buttons to form
$itemTab->setFooter(makeFormFooter(
	new CSubmit('massupdate', _('Update')),
	[new CButtonCancel(url_param('hostid'))]
));

$itemForm->addItem($itemTab);
$itemWidget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.massupdate.js.php';

return $itemWidget;
