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
	$itemWidget->addItem(get_header_host_table('items', $this->data['hostid'],
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
	$itemFormList->addRow(_('Parent items'), $this->data['templates']);
}

$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']);
$nameTextBox->attr('autofocus', 'autofocus');
$itemFormList->addRow(_('Name'), $nameTextBox);

// append type to form list
if ($this->data['limited']) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow(_('Type'),
		new CTextBox('typename', item_type2str($this->data['type']), ZBX_TEXTBOX_STANDARD_SIZE, 'yes')
	);
}
else {
	$typeComboBox = new CComboBox('type', $this->data['type']);
	$typeComboBox->addItems($this->data['types']);
	$itemFormList->addRow(_('Type'), $typeComboBox);
}

// append key to form list
$itemFormList->addRow(_('Key'), array(
	new CTextBox('key', $this->data['key'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']),
	!$this->data['limited']
		? new CButton('keyButton', _('Select'),
			'return PopUp("popup.php?srctbl=help_items&srcfld1=key'.
				'&dstfrm='.$itemForm->getName().'&dstfld1=key&itemtype="+jQuery("#type option:selected").val());',
			'formlist')
		: null
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
	new CTextBox('snmp_community', $this->data['snmp_community'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64),
	false, 'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64),
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
	new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64),
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
	new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64),
	false, 'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	new CTextBox('port', $this->data['port'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_port'
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
	new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_username'
);
$itemFormList->addRow(_('Public key file'),
	new CTextBox('publickey', $this->data['publickey'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_publickey'
);
$itemFormList->addRow(_('Private key file'),
	new CTextBox('privatekey', $this->data['privatekey'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false,  'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	new CTextBox('password', $this->data['password'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_password'
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
$itemFormList->addRow(_('Formula'),
	new CTextArea('params_f', $this->data['params'], array('rows' => ZBX_TEXTAREA_STANDARD_ROWS, 'width' => ZBX_TEXTAREA_STANDARD_WIDTH)),
	false, 'label_formula'
);

// append value type to form list
if ($this->data['limited']) {
	$itemForm->addVar('value_type', $this->data['value_type']);
	$itemFormList->addRow(_('Type of information'),
		new CTextBox('value_type_name', itemValueTypeString($this->data['value_type']), ZBX_TEXTBOX_STANDARD_SIZE, 'yes')
	);
}
else {
	$valueTypeComboBox = new CComboBox('value_type', $this->data['value_type']);
	$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_UINT64, _('Numeric (unsigned)'));
	$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_FLOAT, _('Numeric (float)'));
	$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_STR, _('Character'));
	$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_LOG, _('Log'));
	$valueTypeComboBox->addItem(ITEM_VALUE_TYPE_TEXT, _('Text'));
	$itemFormList->addRow(_('Type of information'), $valueTypeComboBox);
}

// append data type to form list
if ($this->data['limited']) {
	$itemForm->addVar('data_type', $this->data['data_type']);
	$dataType = new CTextBox('data_type_name', item_data_type2str($this->data['data_type']), ZBX_TEXTBOX_SMALL_SIZE, 'yes');
}
else {
	$dataType = new CComboBox('data_type', $this->data['data_type']);
	$dataType->addItems(item_data_type2str());
}
$itemFormList->addRow(_('Data type'), $dataType, false, 'row_data_type');
$itemFormList->addRow(_('Units'),
	new CTextBox('units', $this->data['units'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']), false, 'row_units'
);

// append multiplier to form list
$multiplier = array();
if ($this->data['limited']) {
	$itemForm->addVar('multiplier', $this->data['multiplier']);

	$multiplierCheckBox = new CCheckBox('multiplier', $this->data['multiplier'] == 1 ? 'yes':'no');
	$multiplierCheckBox->setAttribute('disabled', 'disabled');
	$multiplier[] = $multiplierCheckBox;

	$multiplier[] = SPACE;
	$formulaTextBox = new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_SMALL_SIZE, 1);
	$formulaTextBox->setAttribute('style', 'text-align: right;');
	$multiplier[] = $formulaTextBox;
}
else {
	$multiplierCheckBox = new CCheckBox('multiplier', $this->data['multiplier'] == 1 ? 'yes': 'no',
		'var editbx = document.getElementById(\'formula\'); if (editbx) { editbx.disabled = !this.checked; }', 1
	);
	$multiplier[] = $multiplierCheckBox;
	$multiplier[] = SPACE;
	$formulaTextBox = new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_SMALL_SIZE);
	$formulaTextBox->setAttribute('style', 'text-align: right;');
	$multiplier[] = $formulaTextBox;
}
$itemFormList->addRow(_('Use custom multiplier'), $multiplier, false, 'row_multiplier');

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
	new CNumericBox('new_delay_flex[delay]', $this->data['new_delay_flex']['delay'], 5, 'no', false, false),
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

$dataConfig = select_config();
$keepHistory = array();
$keepHistory[] =  new CNumericBox('history', $this->data['history'], 8);
if ($dataConfig['hk_history_global'] && !$data['parent_discoveryid'] && !$data['is_template']) {
	$keepHistory[] = SPACE;
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$keepHistory[] = new CSpan(_x('Overridden by', 'item_form'));
		$keepHistory[] = SPACE;
		$link = new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php');
		$link->setAttribute('target', '_blank');
		$keepHistory[] =  $link;
		$keepHistory[] = SPACE;
		$keepHistory[] = new CSpan('('._n('%1$s day', '%1$s days', $dataConfig['hk_history']).')');
	}
	else {
		$keepHistory[] = new CSpan(_('Overriden by global housekeeping settings').
			'('._n('%1$s day', '%1$s days', $dataConfig['hk_history']).')'
		);
	}
}
$itemFormList->addRow(_('History storage period (in days)'), $keepHistory);

$keepTrend = array();
$keepTrend[] =  new CNumericBox('trends', $this->data['trends'], 8);
if ($dataConfig['hk_trends_global'] && !$data['parent_discoveryid'] && !$data['is_template']) {
	$keepTrend[] = SPACE;
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$keepTrend[] = new CSpan(_x('Overridden by', 'item_form'));
		$keepTrend[] = SPACE;
		$link = new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php');
		$link->setAttribute('target', '_blank');
		$keepTrend[] =  $link;
		$keepTrend[] = SPACE;
		$keepTrend[] = new CSpan('('._n('%1$s day', '%1$s days', $dataConfig['hk_trends']).')');
	}
	else {
		$keepTrend[] = new CSpan(_('Overriden by global housekeeping settings').
			'('._n('%1$s day', '%1$s days', $dataConfig['hk_trends']).')'
		);
	}
}

$itemFormList->addRow(_('Trend storage period (in days)'), $keepTrend, false, 'row_trends');
$itemFormList->addRow(_('Log time format'),
	new CTextBox('logtimefmt', $this->data['logtimefmt'], ZBX_TEXTBOX_SMALL_SIZE, $this->data['limited'], 64),
	false, 'row_logtimefmt'
);

// append delta to form list
$deltaOptions = array(
	0 => _('As is'),
	1 => _('Delta (speed per second)'),
	2 => _('Delta (simple change)')
);
if ($this->data['limited']) {
	$itemForm->addVar('delta', $this->data['delta']);
	$deltaComboBox = new CTextBox('delta_name', $deltaOptions[$this->data['delta']], null, 'yes');
}
else {
	$deltaComboBox= new CComboBox('delta', $this->data['delta']);
	$deltaComboBox->addItems($deltaOptions);
}
$itemFormList->addRow(_('Store value'), $deltaComboBox, false, 'row_delta');

// append valuemap to form list
if ($this->data['limited']) {
	$itemForm->addVar('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox = new CTextBox('valuemap_name', !empty($this->data['valuemaps']) ? $this->data['valuemaps'] : _('As is'), ZBX_TEXTBOX_SMALL_SIZE, 'yes');
}
else {
	$valuemapComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox->addItem(0, _('As is'));
	foreach ($this->data['valuemaps'] as $valuemap) {
		$valuemapComboBox->addItem($valuemap['valuemapid'], CHtml::encode($valuemap['name']));
	}
}
$link = new CLink(_('show value mappings'), 'adm.valuemapping.php');
$link->setAttribute('target', '_blank');
$itemFormList->addRow(_('Show value'), array($valuemapComboBox, SPACE, $link), null, 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'),
	new CTextBox('trapper_hosts', $this->data['trapper_hosts'], ZBX_TEXTBOX_STANDARD_SIZE),
	false, 'row_trapper_hosts');

// append applications to form list
$itemFormList->addRow(_('New application'),
	new CTextBox('new_application', $this->data['new_application'], ZBX_TEXTBOX_STANDARD_SIZE), false, null, 'new'
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
			$enabled ? 'yes' : 'no'
		);
	}
	$itemFormList->addRow(_('Populates host inventory field'), $hostInventoryFieldComboBox, false, 'row_inventory_link');
}

// append description to form list
$description = new CTextArea('description', $this->data['description']);
$description->addStyle('margin-top: 5px;');
$itemFormList->addRow(_('Description'), $description);

// status
$enabledCheckBox = new CCheckBox('status', !$this->data['status'], null, ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', $this->data['caption'], $itemFormList);
$itemForm->addItem($itemTab);

// append buttons to form
$buttons = array();
if (!empty($this->data['itemid'])) {
	array_push($buttons, new CSubmit('clone', _('Clone')));

	if (!$this->data['is_template'] && !empty($this->data['itemid']) && empty($this->data['parent_discoveryid'])) {
		array_push($buttons,
			new CButtonQMessage('del_history', _('Clear history and trends'), _('History clearing can take a long time. Continue?'))
		);
	}
	if (!$this->data['limited']) {
		$buttons[] = new CButtonDelete(
			$this->data['parent_discoveryid'] ? _('Delete item prototype?') : _('Delete item?'),
			url_params(array('form', 'groupid', 'itemid', 'parent_discoveryid', 'hostid'))
		);
	}
}
array_push($buttons, new CButtonCancel(url_param('groupid').url_param('parent_discoveryid').url_param('hostid')));
$itemForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $buttons));
$itemWidget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.edit.js.php';

return $itemWidget;
