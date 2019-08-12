<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

if ($data['hostid'] != 0) {
	$widget->addItem(get_header_host_table('items', $data['hostid']));
}

// Create form.
$form = (new CForm())
	->setName('itemForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('group_itemid', $data['itemids'])
	->addVar('hostid', $data['hostid'])
	->addVar('action', $data['action']);

// Create item form list.
$item_form_list = (new CFormList('item-form-list'))
	// Append type to form list.
	->addRow(
		(new CVisibilityBox('visible[type]', 'type', _('Original')))
			->setLabel(_('Type'))
			->setChecked(isset($data['visible']['type']))
			->setAttribute('autofocus', 'autofocus'),
		new CComboBox('type', $data['type'], null, $data['itemTypes'])
	);

// Append hosts to item form list.
if ($data['displayInterfaces']) {
	$interfaces_combo_box = new CComboBox('interfaceid', $data['interfaceid']);
	$interfaces_combo_box->addItem(new CComboItem(0, '', false, false));

	// Set up interface groups sorted by priority.
	$interface_types = zbx_objectValues($data['hosts']['interfaces'], 'type');
	$interface_groups = [];
	foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $interface_type) {
		if (in_array($interface_type, $interface_types)) {
			$interface_groups[$interface_type] = new COptGroup(interfaceType2str($interface_type));
		}
	}

	// add interfaces to groups
	foreach ($data['hosts']['interfaces'] as $interface) {
		$option = new CComboItem(
			$interface['interfaceid'],
			$interface['useip']
				? $interface['ip'].' : '.$interface['port']
				: $interface['dns'].' : '.$interface['port'],
			($interface['interfaceid'] == $data['interfaceid'])
		);
		$option->setAttribute('data-interfacetype', $interface['type']);
		$interface_groups[$interface['type']]->addItem($option);
	}
	foreach ($interface_groups as $interface_group) {
		$interfaces_combo_box->addItem($interface_group);
	}

	$span = (new CSpan(_('No interface found')))
		->addClass(ZBX_STYLE_RED)
		->setId('interface_not_defined')
		->setAttribute('style', 'display: none;');

	$item_form_list->addRow(
		(new CVisibilityBox('visible[interfaceid]', 'interfaceDiv', _('Original')))
			->setLabel(_('Host interface'))
			->setChecked(isset($data['visible']['interfaceid']))
			->setAttribute('data-multiple-interface-types', $data['multiple_interface_types']),
		(new CDiv([$interfaces_combo_box, $span]))->setId('interfaceDiv'),
		'interface_row'
	);
	$form->addVar('selectedInterfaceId', $data['interfaceid']);
}

$item_form_list
	// Append jmx endpoint to item form list.
	->addRow(
		(new CVisibilityBox('visible[jmx_endpoint]', 'jmx_endpoint', _('Original')))
			->setLabel(_('JMX endpoint'))
			->setChecked(array_key_exists('jmx_endpoint', $data['visible'])),
		(new CTextBox('jmx_endpoint', $data['jmx_endpoint']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append ITEM_TYPE_HTTPAGENT URL field.
	->addRow(
		(new CVisibilityBox('visible[url]', 'url', _('Original')))
			->setLabel(_('URL'))
			->setChecked(array_key_exists('url', $data['visible'])),
		(new CTextBox('url', $data['url'], false, DB::getFieldLength('items', 'url')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append ITEM_TYPE_HTTPAGENT Request body type.
	->addRow(
		(new CVisibilityBox('visible[post_type]', 'post_type_container', _('Original')))
			->setLabel(_('Request body type'))
			->setChecked(array_key_exists('post_type', $data['visible'])),
		(new CDiv(
			(new CRadioButtonList('post_type', (int) $data['post_type']))
				->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
				->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
				->addValue(_('XML data'), ZBX_POSTTYPE_XML)
				->setModern(true)
		))->setId('post_type_container')
	)
	// Append ITEM_TYPE_HTTPAGENT Request body.
	->addRow(
		(new CVisibilityBox('visible[posts]', 'posts', _('Original')))
			->setLabel(_('Request body'))
			->setChecked(array_key_exists('posts', $data['visible'])),
		(new CTextArea('posts', $data['posts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append ITEM_TYPE_HTTPAGENT Headers fields.
$headers_data = [];

if (is_array($data['headers']) && $data['headers']) {
	foreach ($data['headers'] as $pair) {
		$headers_data[] = ['name' => key($pair), 'value' => reset($pair)];
	}
}
else {
	$headers_data[] = ['name' => '', 'value' => ''];
}
$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [CJs::encodeJson($headers_data)];

$item_form_list
	->addRow(
		(new CVisibilityBox('visible[headers]', 'headers_pairs', _('Original')))
			->setLabel(_('Headers'))
			->setChecked(array_key_exists('headers', $data['visible'])),
		(new CDiv([
			(new CTable())
				->setAttribute('style', 'width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
				->setFooter(new CRow(
					(new CCol(
						(new CButton(null, _('Add')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setAttribute('data-row-action', 'add_row')
					))->setColSpan(5)
				)),
			(new CTag('script', true))
				->setAttribute('type', 'text/x-jquery-tmpl')
				->addItem(new CRow([
					(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextBox('headers[name][#{index}]', '#{name}'))->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
					'&rArr;',
					(new CTextBox('headers[value][#{index}]', '#{value}', false, 1000))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
					(new CButton(null, _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setAttribute('data-row-action', 'remove_row')
				])),
			$headers
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('headers_pairs')
			->setAttribute('data-sortable-pairs-table', '1')
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
	// Append SNMP community to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmp_community]', 'snmp_community', _('Original')))
			->setLabel(_('SNMP community'))
			->setChecked(isset($data['visible']['snmp_community'])),
		(new CTextBox('snmp_community', $data['snmp_community']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append SNMPv3 contextname to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_contextname]', 'snmpv3_contextname', _('Original')))
			->setLabel(_('Context name'))
			->setChecked(isset($data['visible']['snmpv3_contextname'])),
		(new CTextBox('snmpv3_contextname', $data['snmpv3_contextname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append SNMPv3 securityname to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_securityname]', 'snmpv3_securityname', _('Original')))
			->setLabel(_('Security name'))
			->setChecked(isset($data['visible']['snmpv3_securityname'])),
		(new CTextBox('snmpv3_securityname', $data['snmpv3_securityname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append SNMPv3 securitylevel to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_securitylevel]', 'snmpv3_securitylevel', _('Original')))
			->setLabel(_('Security level'))
			->setChecked(isset($data['visible']['snmpv3_securitylevel'])),
		new CComboBox('snmpv3_securitylevel', $data['snmpv3_securitylevel'], null, [
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
		])
	)
	// Append SNMPv3 authprotocol to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_authprotocol]', 'authprotocol_div', _('Original')))
			->setLabel(_('Authentication protocol'))
			->setChecked(isset($data['visible']['snmpv3_authprotocol'])),
		(new CDiv(
			(new CRadioButtonList('snmpv3_authprotocol', (int) $data['snmpv3_authprotocol']))
				->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
				->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
				->setModern(true)
		))->setId('authprotocol_div')
	)
	// Append SNMPv3 authpassphrase to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_authpassphrase]', 'snmpv3_authpassphrase', _('Original')))
			->setLabel(_('Authentication passphrase'))
			->setChecked(isset($data['visible']['snmpv3_authpassphrase'])),
		(new CTextBox('snmpv3_authpassphrase', $data['snmpv3_authpassphrase']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append SNMPv3 privprotocol to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_privprotocol]', 'privprotocol_div', _('Original')))
			->setLabel(_('Privacy protocol'))
			->setChecked(isset($data['visible']['snmpv3_privprotocol'])),
		(new CDiv(
			(new CRadioButtonList('snmpv3_privprotocol', (int) $data['snmpv3_privprotocol']))
				->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
				->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
				->setModern(true)
		))
			->setId('privprotocol_div')
	)
	// Append SNMPv3 privpassphrase to item form list.
	->addRow(
		(new CVisibilityBox('visible[snmpv3_privpassphrase]', 'snmpv3_privpassphrase', _('Original')))
			->setLabel(_('Privacy passphrase'))
			->setChecked(isset($data['visible']['snmpv3_privpassphrase'])),
		(new CTextBox('snmpv3_privpassphrase', $data['snmpv3_privpassphrase']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append port to item form list.
	->addRow(
		(new CVisibilityBox('visible[port]', 'port', _('Original')))
			->setLabel(_('Port'))
			->setChecked(isset($data['visible']['port'])),
		(new CTextBox('port', $data['port']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append value type to item form list.
	->addRow(
		(new CVisibilityBox('visible[value_type]', 'value_type', _('Original')))
			->setLabel(_('Type of information'))
			->setChecked(isset($data['visible']['value_type'])),
		new CComboBox('value_type', $data['value_type'], null, [
			ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
			ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
			ITEM_VALUE_TYPE_STR => _('Character'),
			ITEM_VALUE_TYPE_LOG => _('Log'),
			ITEM_VALUE_TYPE_TEXT => _('Text')
		])
	)
	// Append units to item form list.
	->addRow(
		(new CVisibilityBox('visible[units]', 'units', _('Original')))
			->setLabel(_('Units'))
			->setChecked(isset($data['visible']['units'])),
		(new CTextBox('units', $data['units']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	// Append authtype to item form list.
	->addRow(
		(new CVisibilityBox('visible[authtype]', 'authtype', _('Original')))
			->setLabel(_('Authentication method'))
			->setChecked(isset($data['visible']['authtype'])),
		new CComboBox('authtype', $data['authtype'], null, [
			ITEM_AUTHTYPE_PASSWORD => _('Password'),
			ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
		])
	)
	// Append username to item form list.
	->addRow(
		(new CVisibilityBox('visible[username]', 'username', _('Original')))
			->setLabel(_('User name'))
			->setChecked(isset($data['visible']['username'])),
		(new CTextBox('username', $data['username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append publickey to item form list.
	->addRow(
		(new CVisibilityBox('visible[publickey]', 'publickey', _('Original')))
			->setLabel(_('Public key file'))
			->setChecked(isset($data['visible']['publickey'])),
		(new CTextBox('publickey', $data['publickey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append privatekey to item form list.
	->addRow(
		(new CVisibilityBox('visible[privatekey]', 'privatekey', _('Original')))
			->setLabel(_('Private key file'))
			->setChecked(isset($data['visible']['privatekey'])),
		(new CTextBox('privatekey', $data['privatekey']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	// Append password to item form list.
	->addRow(
		(new CVisibilityBox('visible[password]', 'password', _('Original')))
			->setLabel(_('Password'))
			->setChecked(isset($data['visible']['password'])),
		(new CTextBox('password', $data['password']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

// Create preprocessing form list.
$preprocessing_form_list = (new CFormList('preprocessing-form-list'))
	// Append item pre-processing to preprocessing form list.
	->addRow(
		(new CVisibilityBox('visible[preprocessing]', 'preprocessing_div', _('Original')))
			->setLabel(_('Preprocessing steps'))
			->setChecked(isset($data['visible']['preprocessing'])),
		(new CDiv(getItemPreprocessing($form, $data['preprocessing'], false, $data['preprocessing_types'])))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('preprocessing_div')
	);

$update_interval = (new CTable())
	->setId('update_interval')
	->addRow([_('Delay'),
		(new CDiv((new CTextBox('delay', $data['delay']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)))
	]);

$custom_intervals = (new CTable())
	->setId('custom_intervals')
	->setHeader([
		new CColHeader(_('Type')),
		new CColHeader(_('Interval')),
		new CColHeader(_('Period')),
		(new CColHeader(_('Action')))->setWidth(50)
	])
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay']))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period']))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]'))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]'))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]'))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule']))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$custom_intervals->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

$custom_intervals->addRow([(new CButton('interval_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

$update_interval->addRow(
	(new CRow([
		(new CCol(_('Custom intervals')))->setAttribute('style', 'vertical-align: top;'),
		new CCol($custom_intervals)
	]))
);

$item_form_list
	// Append delay to form list.
	->addRow(
		(new CVisibilityBox('visible[delay]', 'update_interval_div', _('Original')))
			->setLabel(_('Update interval'))
			->setChecked(isset($data['visible']['delay'])),
		(new CDiv($update_interval))->setId('update_interval_div')
	)
	// Append history to form list.
	->addRow(
		(new CVisibilityBox('visible[history]', 'history_div', _('Original')))
			->setLabel(_('History storage period'))
			->setChecked(isset($data['visible']['history'])),
		(new CDiv([
			(new CRadioButtonList('history_mode', (int) $data['history_mode']))
				->addValue(_('Do not keep history'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('history', $data['history']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('history_div')
	)
	// Append trends to form list.
	->addRow(
		(new CVisibilityBox('visible[trends]', 'trends_div', _('Original')))
			->setLabel(_('Trend storage period'))
			->setChecked(isset($data['visible']['trends'])),
		(new CDiv([
			(new CRadioButtonList('trends_mode', (int) $data['trends_mode']))
				->addValue(_('Do not keep trends'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('trends', $data['trends']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('trends_div')
	);

// Append status to form list.
$status_combo_box = new CComboBox('status', $data['status']);
foreach ([ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED] as $status) {
	$status_combo_box->addItem($status, item_status2str($status));
}
$item_form_list
	->addRow(
		(new CVisibilityBox('visible[status]', 'status', _('Original')))
			->setLabel(_('Status'))
			->setChecked(isset($data['visible']['status'])),
		$status_combo_box
	)
	// Append logtime to form list.
	->addRow(
		(new CVisibilityBox('visible[logtimefmt]', 'logtimefmt', _('Original')))
			->setLabel(_('Log time format'))
			->setChecked(isset($data['visible']['logtimefmt'])),
		(new CTextBox('logtimefmt', $data['logtimefmt']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append valuemap to form list.
$value_maps_combo_box = new CComboBox('valuemapid', $data['valuemapid']);
$value_maps_combo_box->addItem(0, _('As is'));
foreach ($data['valuemaps'] as $valuemap) {
	$value_maps_combo_box->addItem($valuemap['valuemapid'], $valuemap['name']);
}

$item_form_list
	->addRow(
		(new CVisibilityBox('visible[valuemapid]', 'valuemap', _('Original')))
			->setLabel(_('Show value'))
			->setChecked(isset($data['visible']['valuemapid'])),
		(new CDiv([$value_maps_combo_box, SPACE,
			(new CLink(_('show value mappings'), 'adm.valuemapping.php'))->setAttribute('target', '_blank')
		]))->setId('valuemap')
	)
	->addRow(
		(new CVisibilityBox('visible[allow_traps]', 'allow_traps', _('Original')))
			->setLabel(_('Enable trapping'))
			->setChecked(array_key_exists('allow_traps', $data['visible'])),
		(new CCheckBox('allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
			->setChecked($data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON)
	)
	->addRow(
		(new CVisibilityBox('visible[trapper_hosts]', 'trapper_hosts', _('Original')))
			->setLabel(_('Allowed hosts'))
			->setChecked(array_key_exists('trapper_hosts', $data['visible'])),
		(new CTextBox('trapper_hosts', $data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append applications to form list.
if ($data['displayApplications']) {
	$applications = [];

	if (hasRequest('applications')) {
		$applicationids = [];

		foreach (getRequest('applications') as $application) {
			if (is_array($application) && isset($application['new'])) {
				$applications[] = [
					'id' => $application['new'],
					'name' => $application['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			else {
				$applicationids[] = $application;
			}
		}

		$applications = array_merge($applications, $applicationids
			? CArrayHelper::renameObjectsKeys(API::Application()->get([
				'output' => ['applicationid', 'name'],
				'applicationids' => $applicationids
			]), ['applicationid' => 'id'])
			: []);
	}

	$applications_div = (new CDiv([
		(new CRadioButtonList('massupdate_app_action', (int) $data['massupdate_app_action']))
			->addValue(_('Add'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_ACTION_REMOVE)
			->setModern(true),
		(new CMultiSelect([
			'name' => 'applications[]',
			'object_name' => 'applications',
			'add_new' => !($data['massupdate_app_action'] == ZBX_ACTION_REMOVE),
			'data' => $applications,
			'popup' => [
				'parameters' => [
					'srctbl' => 'applications',
					'srcfld1' => 'applicationid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'applications_',
					'hostid' => $data['hostid'],
					'noempty' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setId('applications_div');

	$item_form_list->addRow(
		(new CVisibilityBox('visible[applications]', 'applications_div', _('Original')))
			->setLabel(_('Applications'))
			->setChecked(array_key_exists('applications', $data['visible'])),
		$applications_div
	);
}

// Append master item select to form list.
if ($data['displayMasteritems']) {
	$master_item = (new CDiv([
		(new CMultiSelect([
			'name' => 'master_itemid',
			'object_name' => 'items',
			'multiple' => false,
			'data' => ($data['master_itemid'] != 0)
				? [
					[
						'id' => $data['master_itemid'],
						'prefix' => $data['master_hostname'].NAME_DELIMITER,
						'name' => $data['master_itemname']
					]
				]
				: [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'master_itemid',
					'hostid' => $data['hostid'],
					'excludeids' => $data['itemids'],
					'webitems' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(true)
	]))->setId('master_item');

	$item_form_list->addRow(
		(new CVisibilityBox('visible[master_itemid]', 'master_item', _('Original')))
			->setLabel(_('Master item'))
			->setChecked(array_key_exists('master_itemid', $data['visible'])),
		$master_item
	);
}

// Append description to form list.
$item_form_list->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))
		->setLabel(_('Description'))
		->setChecked(isset($data['visible']['description'])),
	(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

$tabs = (new CTabView())
	->addTab('itemTab', _('Item'), $item_form_list)
	->addTab('preprocessingTab', _('Preprocessing'), $preprocessing_form_list)
	->setFooter(makeFormFooter(
		new CSubmit('massupdate', _('Update')),
		[new CButtonCancel(url_param('hostid'))]
	));

if (!hasRequest('massupdate')) {
	$tabs->setSelected(0);
}

// Append tabs to form.
$form->addItem($tabs);

$widget->addItem($form);

require_once dirname(__FILE__).'/js/configuration.item.massupdate.js.php';

return $widget;
