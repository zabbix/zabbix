<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/views/js/configuration.item.edit.js.php');

$itemWidget = new CWidget('item-edit');
$itemWidget->addPageHeader($this->data['page_header']);

// create form
$itemForm = new CForm();
$itemForm->setName('itemForm');
$itemForm->addVar('form', $this->data['form']);
if (!empty($this->data['parent_discoveryid'])) {
	$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}
if (!empty($this->data['itemid'])) {
	$itemForm->addVar('itemid', $this->data['itemid']);
}

// create form list
$itemFormList = new CFormList('itemFormList');

// append host to form list
if (empty($this->data['parent_discoveryid'])) {
	$itemForm->addVar('form_hostid', $this->data['hostid']);
	$itemFormList->addRow(_('Host'), array(
		new CTextBox('hostname', $this->data['hostname'], ZBX_TEXTBOX_STANDARD_SIZE, true),
		empty($this->data['itemid'])
			? new CButton('btn_host', _('Select'),
				"return PopUp('popup.php?dstfrm=".$itemForm->getName().
				"&dstfld1=hostname&dstfld2=form_hostid&srctbl=hosts_and_templates&srcfld1=name&srcfld2=hostid&noempty=1&submitParent=1', 450, 450);",
				'formlist'
			)
			: null
	));
}
$itemFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']));

// append key to form list
$itemFormList->addRow(_('Key'), array(
	new CTextBox('key', $this->data['key'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']),
	!$this->data['limited']
		? new CButton('keyButton', _('Select'),
			"return PopUp('popup.php?dstfrm=".$itemForm->getName()."&dstfld1=key&srctbl=help_items&srcfld1=key_&itemtype='+jQuery('#type option:selected').val());",
			'formlist')
		: null
));

// append type to form list
if ($this->data['limited']) {
	$itemForm->addVar('type', $this->data['type']);
	$itemFormList->addRow(_('Type'), new CTextBox('typename', item_type2str($this->data['type']), ZBX_TEXTBOX_STANDARD_SIZE, 'yes'));
}
else {
	$typeComboBox = new CComboBox('type', $this->data['type']);
	$typeComboBox->addItems($this->data['types']);
	$itemFormList->addRow(_('Type'), $typeComboBox);
}

// append interfaces to form list
if (!empty($this->data['interfaces'])) {
	$intereacesComboBox = new CComboBox('interfaceid', $this->data['interfaceid']);
	foreach ($this->data['interfaces'] as $interface) {
		$option = new CComboItem($interface['interfaceid'], $interface['useip'] ? $interface['ip'].' : '.$interface['port'] : $interface['dns'].' : '.$interface['port']);
		$option->setAttribute('data-interfacetype', $interface['type']);
		$intereacesComboBox->addItem($option);
	}
	$itemFormList->addRow(_('Host interface'), $intereacesComboBox, false, 'interface_row');
	$itemForm->addVar('selectedInterfaceId', $this->data['interfaceid']);
}
$itemFormList->addRow(_('SNMP OID'), new CTextBox('snmp_oid', $this->data['snmp_oid'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']), false, 'row_snmp_oid');
$itemFormList->addRow(_('SNMP community'), new CTextBox('snmp_community', $this->data['snmp_community'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64), false, 'row_snmp_community');
$itemFormList->addRow(_('SNMPv3 security name'), new CTextBox('snmpv3_securityname', $this->data['snmpv3_securityname'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64), false, 'row_snmpv3_securityname');

// append snmpv3 security level to form list
$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $this->data['snmpv3_securitylevel']);
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv');
$securityLevelComboBox->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv');
$itemFormList->addRow(_('SNMPv3 security level'), $securityLevelComboBox, false, 'row_snmpv3_securitylevel');
$itemFormList->addRow(_('SNMPv3 auth passphrase'), new CTextBox('snmpv3_authpassphrase', $this->data['snmpv3_authpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64), false, 'row_snmpv3_authpassphrase');
$itemFormList->addRow(_('SNMPv3 priv passphrase'), new CTextBox('snmpv3_privpassphrase', $this->data['snmpv3_privpassphrase'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64), false, 'row_snmpv3_privpassphrase');
$itemFormList->addRow(_('Port'), new CTextBox('port', $this->data['port'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_port');
$itemFormList->addRow(_('IPMI sensor'), new CTextBox('ipmi_sensor', $this->data['ipmi_sensor'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited'], 128), false, 'row_ipmi_sensor');

// append authentication method to form list
$authTypeComboBox = new CComboBox('authtype', $this->data['authtype']);
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
$authTypeComboBox->addItem(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'));
$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, false, 'row_authtype');
$itemFormList->addRow(_('User name'), new CTextBox('username', $this->data['username'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_username');
$itemFormList->addRow(_('Public key file'), new CTextBox('publickey', $this->data['publickey'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_publickey');
$itemFormList->addRow(_('Private key file'), new CTextBox('privatekey', $this->data['privatekey'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false,  'row_privatekey');
$itemFormList->addRow(_('Password'), new CTextBox('password', $this->data['password'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64), false, 'row_password');
$itemFormList->addRow(_('Executed script'), new CTextArea('params', $this->data['params'], ZBX_TEXTAREA_STANDARD_ROWS, ZBX_TEXTAREA_STANDARD_WIDTH), false, 'label_executed_script');
$itemFormList->addRow(_('Additional parameters'), new CTextArea('params', $this->data['params'], ZBX_TEXTAREA_STANDARD_ROWS, ZBX_TEXTAREA_STANDARD_WIDTH), false, 'label_params');
$itemFormList->addRow(_('Formula'), new CTextArea('params', $this->data['params'], ZBX_TEXTAREA_STANDARD_ROWS, ZBX_TEXTAREA_STANDARD_WIDTH), false, 'label_formula');

// append value type to form list
if ($this->data['limited']) {
	$itemForm->addVar('value_type', $this->data['value_type']);
	$itemFormList->addRow(_('Type of information'), new CTextBox('value_type_name', item_value_type2str($this->data['value_type']), ZBX_TEXTBOX_STANDARD_SIZE, 'yes'));
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
$itemFormList->addRow(_('Units'), new CTextBox('units', $this->data['units'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']), false, 'row_units');

// append multiplier to form list
$multiplier = array();
if ($this->data['limited']) {
	$itemForm->addVar('multiplier', $this->data['multiplier']);

	$multiplierCheckBox = new CCheckBox('multiplier', $this->data['multiplier'] == 1 ? 'yes':'no');
	$multiplierCheckBox->setAttribute('disabled', 'disabled');
	$multiplier[] = $multiplierCheckBox;
	if ($this->data['multiplier']) {
		$multiplier[] = SPACE;
		$formulaTextBox = new CTextBox('formula', $this->data['formula'], ZBX_TEXTBOX_SMALL_SIZE, 1);
		$formulaTextBox->setAttribute('style', 'text-align: right;');
		$multiplier[] = $formulaTextBox;
	}
}
else {
	$multiplier[] = new CCheckBox(
		'multiplier',
		$this->data['multiplier'] == 1 ? 'yes': 'no',
		'var editbx = document.getElementById(\'formula\'); if (editbx) { editbx.disabled = !this.checked; }',
		1
	);
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
$maxReached = false;
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
		$maxReached = true;
		break;
	}
}
$itemFormList->addRow(_('Flexible intervals (sec)'), new CDiv($delayFlexTable, 'objectgroup inlineblock border_dotted ui-corner-all'), false, 'row_flex_intervals');

// append new flexible interval to form list
$itemFormList->addRow(
	_('New flexible interval'),
	array(
		_('Interval'),
		SPACE,
		new CNumericBox('new_delay_flex[delay]', 50, 5),
		SPACE,
		_('Period'),
		SPACE,
		new CTextBox('new_delay_flex[period]', ZBX_DEFAULT_INTERVAL, 20),
		SPACE,
		new CSubmit('add_delay_flex', _('Add'), null, 'formlist')
	),
	$maxReached,
	'row_new_delay_flex',
	'new'
);

// append keep history to form list
$itemFormList->addRow(_('Keep history (in days)'), new CNumericBox('history', $this->data['history'], 8));
$itemFormList->addRow(_('Keep trends (in days)'), new CNumericBox('trends', $this->data['trends'], 8), false, 'row_trends');
$itemFormList->addRow(_('Log time format'), new CTextBox('logtimefmt', $this->data['logtimefmt'], ZBX_TEXTBOX_SMALL_SIZE, $this->data['limited'], 64), false, 'row_logtimefmt');

// append delta to form list
$deltaComboBox= new CComboBox('delta', $this->data['delta']);
$deltaComboBox->addItem(0, _('As is'));
$deltaComboBox->addItem(1, _('Delta (speed per second)'));
$deltaComboBox->addItem(2, _('Delta (simple change)'));
$itemFormList->addRow(_('Store value'), $deltaComboBox, false, 'row_delta');

// append valuemap to form list
if ($this->data['limited']) {
	$itemForm->addVar('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox = new CTextBox('valuemap_name', $this->data['valuemaps'], ZBX_TEXTBOX_SMALL_SIZE, 'yes');
}
else {
	$valuemapComboBox = new CComboBox('valuemapid', $this->data['valuemapid']);
	$valuemapComboBox->addItem(0, _('As is'));
	foreach ($this->data['valuemaps'] as $valuemap) {
		$valuemapComboBox->addItem($valuemap['valuemapid'], get_node_name_by_elid($valuemap['valuemapid'], null, ': ').$valuemap['name']);
	}
}
$link = new CLink(_('show value mappings'), 'adm.valuemapping.php');
$link->setAttribute('target', '_blank');
$itemFormList->addRow(_('Show value'), array($valuemapComboBox, SPACE, $link), null, 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'), new CTextBox('trapper_hosts', $this->data['trapper_hosts'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_trapper_hosts');
$itemFormList->addRow(_('New application'), new CTextBox('new_application', $this->data['new_application'], ZBX_TEXTBOX_STANDARD_SIZE), false, null, 'new');

// append applications to form list
$applicationComboBox = new CListBox('applications[]', $this->data['applications'], 6);
$applicationComboBox->addItem(0, '-'._('None').'-');
foreach ($this->data['db_applications'] as $application) {
	$applicationComboBox->addItem($application['applicationid'], $application['name']);
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

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
foreach (array(ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED, ITEM_STATUS_NOTSUPPORTED) as $status) {
	$statusComboBox->addItem($status, item_status2str($status));
}
$itemFormList->addRow(_('Status'), $statusComboBox);

// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', $this->data['caption'], $itemFormList);
$itemForm->addItem($itemTab);

// append buttons to form
$buttons = array();
if (!empty($this->data['itemid'])) {
	array_push($buttons, new CSubmit('clone', _('Clone')));

	if (!empty($this->data['itemid']) && empty($this->data['parent_discoveryid'])) {
		array_push($buttons, new CButtonQMessage('del_history', _('Clear history and trends'), _('History clearing can take a long time. Continue?')));
	}
	if (!$this->data['limited']) {
		array_push($buttons, new CButtonDelete(_('Delete selected item?'), url_param('form').url_param('groupid').url_param('itemid').url_param('parent_discoveryid')));
	}
}
array_push($buttons, new CButtonCancel(url_param('groupid').url_param('parent_discoveryid')));
$itemForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), $buttons));
$itemWidget->addItem($itemForm);

/*
 * Visibility
 */
$typeVisibility = array();
$i = 0;
foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}
	foreach ($this->data['types'] as $type => $label) {
		if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP) {
			continue;
		}
		zbx_subarray_push($typeVisibility, $type, 'delay_flex['.$i.'][delay]');
		zbx_subarray_push($typeVisibility, $type, 'delay_flex['.$i.'][period]');
		zbx_subarray_push($typeVisibility, $type, 'rem_delay_flex['.$i.']');
	}
	$i++;
	if ($i == 7) {
		break;
	}
}
if (!empty($this->data['interfaces'])) {
	zbx_subarray_push($typeVisibility, ITEM_TYPE_ZABBIX, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_ZABBIX, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SIMPLE, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SIMPLE, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_EXTERNAL, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_EXTERNAL, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'interfaceid');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPTRAP, 'interface_row');
	zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPTRAP, 'interfaceid');
}
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_oid');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_community');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_community');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_community');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_community');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_port');
zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'ipmi_sensor');
zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'row_ipmi_sensor');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'authtype');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_authtype');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'row_username');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'row_password');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'label_executed_script');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'label_executed_script');
zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'label_params');
zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'label_formula');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'params_script');
zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_params');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'params_script');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_params');
zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'row_params');
zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'params_calculted');
zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'row_params');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'trapper_hosts');
zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'row_trapper_hosts');
foreach ($this->data['types'] as $type => $label) {
	switch ($type) {
		case ITEM_TYPE_DB_MONITOR:
			zbx_subarray_push($typeVisibility, $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_DB_MONITOR));
			zbx_subarray_push($typeVisibility, $type, array('id' => 'params_dbmonitor', 'defaultValue' => 'DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>'));
			break;
		case ITEM_TYPE_SSH:
			zbx_subarray_push($typeVisibility, $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_SSH));
			break;
		case ITEM_TYPE_TELNET:
			zbx_subarray_push($typeVisibility, $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_TELNET));
			break;
		case ITEM_TYPE_JMX:
			zbx_subarray_push($typeVisibility, $type, array('id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_JMX));
			break;
		default:
			zbx_subarray_push($typeVisibility, $type, array('id' => 'key', 'defaultValue' => ''));
	}
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP) {
		continue;
	}
	zbx_subarray_push($typeVisibility, $type, 'row_flex_intervals');
	zbx_subarray_push($typeVisibility, $type, 'row_new_delay_flex');
	zbx_subarray_push($typeVisibility, $type, 'new_delay_flex[delay]');
	zbx_subarray_push($typeVisibility, $type, 'new_delay_flex[period]');
	zbx_subarray_push($typeVisibility, $type, 'add_delay_flex');
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP) {
		continue;
	}
	zbx_subarray_push($typeVisibility, $type, 'delay');
	zbx_subarray_push($typeVisibility, $type, 'row_delay');
}

$valueTypeVisibility = array();
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'data_type');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_data_type');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'units');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_units');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'units');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_units');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'multiplier');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_multiplier');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'multiplier');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_multiplier');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'trends');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_trends');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'trends');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_trends');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'logtimefmt');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'delta');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_delta');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'delta');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_delta');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemapid');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemap_name');
if (empty($this->data['parent_discoveryid'])) {
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_STR, 'inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_STR, 'row_inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_TEXT, 'inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_TEXT, 'row_inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'inventory_link');
	zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_inventory_link');
}

$securityLevelVisibility = array();
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');

$authTypeVisibility = array();
zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');
zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

zbx_add_post_js("var valueTypeSwitcher = new CViewSwitcher('value_type', 'change', ".zbx_jsvalue($valueTypeVisibility, true).');');
zbx_add_post_js("var authTypeSwitcher = new CViewSwitcher('authtype', 'change', ".zbx_jsvalue($authTypeVisibility, true).');');
zbx_add_post_js("var typeSwitcher = new CViewSwitcher('type', 'change', ".zbx_jsvalue($typeVisibility, true).(!empty($this->data['itemid']) ? ', true' : '').');');
zbx_add_post_js("var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change', ".zbx_jsvalue($securityLevelVisibility, true).');');
zbx_add_post_js("var multpStat = document.getElementById('multiplier'); if (multpStat && multpStat.onclick) { multpStat.onclick(); }");
zbx_add_post_js("var mnFrmTbl = document.getElementById('web.items.item.php'); if (mnFrmTbl) { mnFrmTbl.style.visibility = 'visible'; }");
if ($maxReached) {
	zbx_add_post_js('jQuery("#row_new_delay_flex").css("display", "none");');
}

return $itemWidget;
?>
