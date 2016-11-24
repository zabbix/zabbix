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


$widget = (new CWidget())->setTitle(_('Items'));

$host = $data['host'];

if (!empty($data['hostid'])) {
	$widget->addItem(get_header_host_table('items', $data['hostid']));
}

// Create form.
$itemForm = (new CForm())
	->setName('itemForm')
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid']);

if (!empty($data['itemid'])) {
	$itemForm->addVar('itemid', $data['itemid']);
}

// Create form list.
$itemFormList = new CFormList('itemFormList');
if (!empty($data['templates'])) {
	$itemFormList->addRow(_('Parent items'), $data['templates']);
}

$discovered_item = false;
if (array_key_exists('item', $data) && $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$discovered_item = true;
}
$readonly = false;
if ($data['limited'] || $discovered_item) {
	$readonly = true;
}

if ($discovered_item) {
	$itemFormList->addRow(_('Discovered by'), new CLink($data['item']['discoveryRule']['name'],
		'disc_prototypes.php?parent_discoveryid='.$data['item']['discoveryRule']['itemid']
	));
}

$itemFormList->addRow(_('Name'),
	(new CTextBox('name', $data['name'], $readonly))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
);

// Append type to form list.
if ($readonly) {
	$itemForm->addVar('type', $data['type']);
	$itemFormList->addRow(_('Type'),
		(new CTextBox('type_name', item_type2str($data['type']), true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow(_('Type'), new CComboBox('type', $data['type'], null, $data['types']));
}

// Append key to form list.
$key_controls = [(new CTextBox('key', $data['key'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)];

if (!$readonly) {
	$key_controls[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$key_controls[] = (new CButton('keyButton', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.php?srctbl=help_items&srcfld1=key&dstfrm='.$itemForm->getName().
			'&dstfld1=key&itemtype="+jQuery("#type option:selected").val());');
}

$itemFormList->addRow(_('Key'), $key_controls);

// Append interface(s) to form list.
if ($data['interfaces']) {
	if ($discovered_item) {
		if ($data['interfaceid'] != 0) {
			$data['interfaces'] = zbx_toHash($data['interfaces'], 'interfaceid');
			$interface = $data['interfaces'][$data['interfaceid']];

			$itemFormList->addRow(_('Host interface'), new CTextBox('interface',
				$interface['useip']
					? $interface['ip'].' : '.$interface['port']
					: $interface['dns'].' : '.$interface['port'],
				true
			), 'interface_row');
		}
	}
	else {
		$interfacesComboBox = new CComboBox('interfaceid', $data['interfaceid']);

		// set up interface groups
		$interfaceGroups = [];
		foreach (zbx_objectValues($data['interfaces'], 'type') as $interfaceType) {
			$interfaceGroups[$interfaceType] = new COptGroup(interfaceType2str($interfaceType));
		}

		// add interfaces to groups
		foreach ($data['interfaces'] as $interface) {
			$option = new CComboItem(
				$interface['interfaceid'],
				$interface['useip']
					? $interface['ip'].' : '.$interface['port']
					: $interface['dns'].' : '.$interface['port'],
				($interface['interfaceid'] == $data['interfaceid']) ? 'yes' : 'no'
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
		$itemForm->addVar('selectedInterfaceId', $data['interfaceid']);
	}
}

// Append SNMP common fields fields.
$itemFormList->addRow(_('SNMP OID'),
	(new CTextBox('snmp_oid', $data['snmp_oid'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	(new CTextBox('snmpv3_contextname', $data['snmpv3_contextname'], $discovered_item))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_contextname'
);
$itemFormList->addRow(_('SNMP community'),
	(new CTextBox('snmp_community', $data['snmp_community'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	(new CTextBox('snmpv3_securityname', $data['snmpv3_securityname'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_securityname'
);

// Append snmpv3 security level to form list.
$security_levels = [
	ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
];
if ($discovered_item) {
	$itemForm->addVar('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
	$securityLevelComboBox = new CTextBox('snmpv3_securitylevel_name', $security_levels[$data['snmpv3_securitylevel']],
		true
	);
}
else {
	$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $data['snmpv3_securitylevel'], null,
		$security_levels
	);
}
$itemFormList->addRow(_('Security level'), $securityLevelComboBox, 'row_snmpv3_securitylevel');

// Append snmpv3 authentication protocol to form list.
if ($discovered_item) {
	$itemForm->addVar('snmpv3_authprotocol', (int) $data['snmpv3_authprotocol']);
	$snmpv3_authprotocol = (new CRadioButtonList('snmpv3_authprotocol_names', (int) $data['snmpv3_authprotocol']))
		->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
		->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
		->setModern(true)
		->setEnabled(!$discovered_item);
}
else {
	$snmpv3_authprotocol = (new CRadioButtonList('snmpv3_authprotocol', (int) $data['snmpv3_authprotocol']))
		->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
		->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
		->setModern(true);
}

$itemFormList->addRow(_('Authentication protocol'), $snmpv3_authprotocol, 'row_snmpv3_authprotocol');

// Append snmpv3 authentication passphrase to form list.
$itemFormList->addRow(_('Authentication passphrase'),
	(new CTextBox('snmpv3_authpassphrase', $data['snmpv3_authpassphrase'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_authpassphrase'
);

// Append snmpv3 privacy protocol to form list.
if ($discovered_item) {
	$itemForm->addVar('snmpv3_privprotocol', (int) $data['snmpv3_privprotocol']);
	$snmpv3_privprotocol = (new CRadioButtonList('snmpv3_privprotocol_names', (int) $data['snmpv3_privprotocol']))
		->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
		->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
		->setModern(true)
		->setEnabled(!$discovered_item);
}
else {
	$snmpv3_privprotocol = (new CRadioButtonList('snmpv3_privprotocol', (int) $data['snmpv3_privprotocol']))
		->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
		->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
		->setModern(true);
}
$itemFormList->addRow(_('Privacy protocol'), $snmpv3_privprotocol, 'row_snmpv3_privprotocol');

// Append snmpv3 privacy passphrase to form list.
$itemFormList->addRow(_('Privacy passphrase'),
	(new CTextBox('snmpv3_privpassphrase', $data['snmpv3_privpassphrase'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	(new CTextBox('port', $data['port'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_port'
);
$itemFormList->addRow(_('IPMI sensor'),
	(new CTextBox('ipmi_sensor', $data['ipmi_sensor'], $readonly, 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_ipmi_sensor'
);

// Append authentication method to form list.
$auth_types = [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
];
if ($discovered_item) {
	$itemForm->addVar('authtype', $data['authtype']);
	$authTypeComboBox = new CTextBox('authtype_name', $auth_types[$data['authtype']], true);
}
else {
	$authTypeComboBox = new CComboBox('authtype', $data['authtype'], null, $auth_types);
}

$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, 'row_authtype');
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $data['username'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_username'
);
$itemFormList->addRow(_('Public key file'),
	(new CTextBox('publickey', $data['publickey'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	(new CTextBox('privatekey', $data['privatekey'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	(new CTextBox('password', $data['password'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_password'
);
$itemFormList->addRow(_('Executed script'),
	(new CTextArea('params_es', $data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($discovered_item),
	'label_executed_script'
);
$itemFormList->addRow(_('SQL query'),
	(new CTextArea('params_ap', $data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($discovered_item),
	'label_params'
);
$itemFormList->addRow(_('Formula'),
	(new CTextArea('params_f', $data['params'], $discovered_item))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($discovered_item),
	'label_formula'
);

// Append value type to form list.
if ($readonly) {
	$itemForm->addVar('value_type', $data['value_type']);
	$itemFormList->addRow(_('Type of information'),
		(new CTextBox('value_type_name', itemValueTypeString($data['value_type']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow(_('Type of information'), new CComboBox('value_type', $data['value_type'], null, [
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text')
	]));
}

// Append data type to form list.
if ($readonly) {
	$itemForm->addVar('data_type', $data['data_type']);
	$dataType = (new CTextBox('data_type_name', item_data_type2str($data['data_type']), true))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}
else {
	$dataType = new CComboBox('data_type', $data['data_type'], null, item_data_type2str());
}
$itemFormList->addRow(_('Data type'), $dataType, 'row_data_type');
$itemFormList->addRow(_('Units'),
	(new CTextBox('units', $data['units'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_units'
);

// Append multiplier to form list.
if ($readonly) {
	$itemForm->addVar('multiplier', $data['multiplier']);

	$multiplier = [
		(new CCheckBox('multiplier'))
			->setChecked($data['multiplier'] == 1)
			->setAttribute('disabled', 'disabled'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('formula', $data['formula'], true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('style', 'text-align: right;')
	];
}
else {
	$multiplier = [
		(new CCheckBox('multiplier'))
			->setChecked($data['multiplier'] == 1)
			->onClick('var editbx = document.getElementById(\'formula\'); if (editbx) { editbx.disabled = !this.checked; }'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox('formula', $data['formula']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('style', 'text-align: right;')
	];
}
$itemFormList->addRow(_('Use custom multiplier'), $multiplier, 'row_multiplier');

$itemFormList->addRow(_('Update interval (in sec)'),
	(new CNumericBox('delay', $data['delay'], 5, $discovered_item))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
	'row_delay'
);

// Append custom intervals to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), $discovered_item ? null : _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	if ($discovered_item) {
		$itemForm->addVar('delay_flex['.$i.'][type]', (int) $delay_flex['type']);
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type_name]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEX_TYPE_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_FLEX_TYPE_SCHEDULING)
			->setModern(true)
			->setEnabled(!$discovered_item);
	}
	else {
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEX_TYPE_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_FLEX_TYPE_SCHEDULING)
			->setModern(true);
	}

	if ($delay_flex['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
		$delay_input = (new CNumericBox('delay_flex['.$i.'][delay]', $delay_flex['delay'], 5, $discovered_item, true,
			false
		))->setAttribute('placeholder', 50);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period'], $discovered_item, 255))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', '', $discovered_item, 255))
			->setAttribute('placeholder', 'wd1-5h9-18')
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CNumericBox('delay_flex['.$i.'][delay]', '', 5, $discovered_item, true, false))
			->setAttribute('placeholder', 50)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', '', $discovered_item, 255))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule'], $discovered_item, 255))
			->setAttribute('placeholder', 'wd1-5h9-18');
	}

	$button = $discovered_item
		? null
		: (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove');

	$delayFlexTable->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

if (!$discovered_item) {
	$delayFlexTable->addRow([(new CButton('interval_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')]);
}

$itemFormList->addRow(_('Custom intervals'),
	(new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'row_flex_intervals'
);

// Append history storage to form list.
$keepHistory = [];
$keepHistory[] = (new CNumericBox('history', $data['history'], 8, $discovered_item))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);

if ($data['config']['hk_history_global']
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

// Append trend storage to form list.
$keepTrend = [];
$keepTrend[] = (new CNumericBox('trends', $data['trends'], 8, $discovered_item))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);

if ($data['config']['hk_trends_global']
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

$itemFormList->addRow(_('Trend storage period (in days)'), $keepTrend, 'row_trends');

$itemFormList->addRow(_('Log time format'),
	(new CTextBox('logtimefmt', $data['logtimefmt'], $readonly, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_logtimefmt'
);

// Append delta to form list.
$deltaOptions = [
	0 => _('As is'),
	1 => _('Delta (speed per second)'),
	2 => _('Delta (simple change)')
];
if ($readonly) {
	$itemForm->addVar('delta', $data['delta']);
	$deltaComboBox = (new CTextBox('delta_name', $deltaOptions[$data['delta']], true))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
}
else {
	$deltaComboBox = new CComboBox('delta', $data['delta'], null, $deltaOptions);
}
$itemFormList->addRow(_('Store value'), $deltaComboBox, 'row_delta');

// Append valuemap to form list.
if ($readonly) {
	$itemForm->addVar('valuemapid', $data['valuemapid']);
	$valuemapComboBox = (new CTextBox('valuemap_name',
		!empty($data['valuemaps']) ? $data['valuemaps'] : _('As is'),
		true
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
}
else {
	$valuemapComboBox = new CComboBox('valuemapid', $data['valuemapid']);
	$valuemapComboBox->addItem(0, _('As is'));
	foreach ($data['valuemaps'] as $valuemap) {
		$valuemapComboBox->addItem($valuemap['valuemapid'], CHtml::encode($valuemap['name']));
	}
}
$link = (new CLink(_('show value mappings'), 'adm.valuemapping.php'))
	->setAttribute('target', '_blank');
$itemFormList->addRow(_('Show value'), [$valuemapComboBox, SPACE, $link], 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'),
	(new CTextBox('trapper_hosts', $data['trapper_hosts'], $discovered_item))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_trapper_hosts');

// Add "New application" and list of applications to form list.
if ($discovered_item) {
	$itemForm->addVar('new_application', '');
	foreach ($data['db_applications'] as $db_application) {
		foreach ($data['applications'] as $application) {
			if ($db_application['applicationid'] == $application) {
				$itemForm->addVar('applications[]', $db_application['applicationid']);
			}
		}
	}

	$applicationComboBox = new CListBox('applications_names[]', $data['applications'], 6);
	foreach ($data['db_applications'] as $application) {
		$applicationComboBox->addItem($application['applicationid'], CHtml::encode($application['name']));
	}
	$applicationComboBox->setEnabled(!$discovered_item);
}
else {
	$itemFormList->addRow(new CLabel(_('New application'), 'new_application'), (new CSpan(
		(new CTextBox('new_application', $data['new_application']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->addClass(ZBX_STYLE_FORM_NEW_GROUP));

	$applicationComboBox = new CListBox('applications[]', $data['applications'], 6);
	$applicationComboBox->addItem(0, '-'._('None').'-');
	foreach ($data['db_applications'] as $application) {
		$applicationComboBox->addItem($application['applicationid'], CHtml::encode($application['name']));
	}
}

$itemFormList->addRow(_('Applications'), $applicationComboBox);

// Append populate host to form list.
if ($discovered_item) {
	$itemForm->addVar('inventory_link', 0);
}
else {
	$hostInventoryFieldComboBox = new CComboBox('inventory_link');
	$hostInventoryFieldComboBox->addItem(0, '-'._('None').'-', $data['inventory_link'] == '0' ? 'yes' : null);

	// A list of available host inventory fields.
	foreach ($data['possibleHostInventories'] as $fieldNo => $fieldInfo) {
		if (isset($data['alreadyPopulated'][$fieldNo])) {
			$enabled = isset($data['item']['inventory_link'])
				? $data['item']['inventory_link'] == $fieldNo
				: $data['inventory_link'] == $fieldNo && !hasRequest('clone');
		}
		else {
			$enabled = true;
		}
		$hostInventoryFieldComboBox->addItem(
			$fieldNo,
			$fieldInfo['title'],
			$data['inventory_link'] == $fieldNo && $enabled ? 'yes' : null,
			$enabled
		);
	}

	$itemFormList->addRow(_('Populates host inventory field'), $hostInventoryFieldComboBox, 'row_inventory_link');
}

// Append description to form list.
$itemFormList->addRow(_('Description'),
	(new CTextArea('description', $data['description']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($discovered_item)
);

// Append status to form list.
$enabledCheckBox = (new CCheckBox('status', ITEM_STATUS_ACTIVE))
	->setChecked($data['status'] == ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

// Append tabs to form.
$itemTab = (new CTabView())->addTab('itemTab', $data['caption'], $itemFormList);

// Append buttons to form.
if ($data['itemid'] != 0) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED) {
		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

	$buttons[] = (new CButtonDelete(_('Delete item?'), url_params(['form', 'itemid', 'hostid'])))
		->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_param('hostid'));

	$itemTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('hostid'))]
	));
}

$itemForm->addItem($itemTab);
$widget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.edit.js.php';

return $widget;
