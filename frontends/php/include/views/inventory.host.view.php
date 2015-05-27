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


$hostInventoryWidget = (new CWidget('inventory-host'))->setTitle(_('Host inventory'));

/*
 * Overview tab
 */
$overviewFormList = new CFormList();

$hostSpan = new CSpan($this->data['host']['host'], ZBX_STYLE_LINK_ACTION.' link_menu');
$hostSpan->setMenuPopup(CMenuPopupHelper::getHost(
	$this->data['host'],
	$this->data['hostScripts'][$this->data['host']['hostid']],
	false
));

$hostName = ($this->data['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
	? [$hostSpan, SPACE, new CDiv(null, 'icon-maintenance-inline')]
	: $hostSpan;

$overviewFormList->addRow(_('Host name'), $hostName);

if ($this->data['host']['host'] !== $this->data['host']['name']) {
	$overviewFormList->addRow(_('Visible name'), new CSpan($this->data['host']['name'], 'text-field'));
}

$agentInterfaceRows = $snmpInterfaceRows = $ipmiInterfaceRows = $jmxInterfaceRows = [];

foreach ($this->data['host']['interfaces'] as $interface) {
	$spanClass = $interface['main'] ? ' default_interface' : '';

	switch ($interface['type']) {
		case INTERFACE_TYPE_AGENT:
			$agentInterfaceRows[] = new CRow([
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass)
			]);
			break;

		case INTERFACE_TYPE_SNMP:
			$snmpInterfaceRows[] = new CRow([
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass)
			]);
			break;

		case INTERFACE_TYPE_IPMI:
			$ipmiInterfaceRows[] = new CRow([
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass)
			]);
			break;

		case INTERFACE_TYPE_JMX:
			$jmxInterfaceRows[] = new CRow([
				new CDiv($interface['ip'], 'ip'.$spanClass),
				new CDiv($interface['dns'], 'dns'.$spanClass),
				new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS'), 'useip'.$spanClass),
				new CDiv($interface['port'], 'port'.$spanClass)
			]);
			break;
	}
}

$interfaceTableHeaderSet = false;

// Agent interface
if ($agentInterfaceRows) {
	$agentInterfacesTable = (new CTable())->
		addClass('formElementTable')->
		addClass('border_dotted')->
		addClass('objectgroup')->
		addClass('element-row-first')->
		addClass('interfaces')->
		setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
	$interfaceTableHeaderSet = true;

	foreach ($agentInterfaceRows as $interface) {
		$agentInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(_('Agent interfaces'), new CDiv($agentInterfacesTable));
}

// SNMP interface
if ($snmpInterfaceRows) {
	$snmpInterfacesTable = (new CTable())->
		addClass('formElementTable')->
		addClass('border')->
		addClass('dotted')->
		addClass('objectgroup')->
		addClass('interfaces');

	if ($interfaceTableHeaderSet) {
		$snmpInterfacesTable->addClass('element-row');
	}
	else {
		$snmpInterfacesTable->addClass('element-row-first');
		$snmpInterfacesTable->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
		$interfaceTableHeaderSet = true;
	}

	foreach ($snmpInterfaceRows as $interface) {
		$snmpInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(_('SNMP interfaces'), new CDiv($snmpInterfacesTable));
}

// JMX interface
if ($jmxInterfaceRows) {
	$jmxInterfacesTable = (new CTable())->
		addClass('formElementTable')->
		addClass('border_dotted')->
		addClass('objectgroup')->
		addClass('interfaces');

	if ($interfaceTableHeaderSet) {
		$jmxInterfacesTable->addClass('element-row');
	}
	else {
		$jmxInterfacesTable->addClass('element-row-first');
		$jmxInterfacesTable->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
	}

	foreach ($jmxInterfaceRows as $interface) {
		$jmxInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(_('JMX interfaces'), new CDiv($jmxInterfacesTable));
}

// IPMI interface
if ($ipmiInterfaceRows) {
	$ipmiInterfacesTable = (new CTable())->
		addClass('formElementTable')->
		addClass('border_dotted')->
		addClass('objectgroup')->
		addClass('interfaces');

	if ($interfaceTableHeaderSet) {
		$ipmiInterfacesTable->addClass('element-row');
	}
	else {
		$ipmiInterfacesTable->addClass('element-row-first');
		$ipmiInterfacesTable->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
		$interfaceTableHeaderSet = true;
	}

	foreach ($ipmiInterfaceRows as $interface) {
		$ipmiInterfacesTable->addRow($interface);
	}

	$overviewFormList->addRow(_('IPMI interfaces'), new CDiv($ipmiInterfacesTable));
}

// inventory (OS, Hardware, Software)
if ($this->data['host']['inventory']) {
	if ($this->data['host']['inventory']['os']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['os']['title'],
			[new CDiv(new CSpan(
				zbx_str2links($this->data['host']['inventory']['os']), 'inventory-text-field'),
				'inventory-text-field-wrap'
			)]
		);
	}
	if ($this->data['host']['inventory']['hardware']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['hardware']['title'],
			[new CDiv(new CSpan(
				zbx_str2links($this->data['host']['inventory']['hardware']), 'inventory-text-field'),
				'inventory-text-field-wrap'
			)]
		);
	}
	if ($this->data['host']['inventory']['software']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['software']['title'],
			[new CDiv(new CSpan(
				zbx_str2links($this->data['host']['inventory']['software']), 'inventory-text-field'),
				'inventory-text-field-wrap'
			)]
		);
	}
}

// description
if ($this->data['host']['description'] !== '') {
	$overviewFormList->addRow(_('Description'),
		[new CDiv(new CSpan(
			zbx_str2links($this->data['host']['description']), 'inventory-text-field'),
			'inventory-text-field-wrap'
		)]
	);
}

// latest data
$overviewFormList->addRow(_('Monitoring'), [
	new CLink(_('Web'), 'httpmon.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
	new CLink(_('Latest data'),
		'latest.php?form=1&select=&show_details=1&filter_set=Filter&hostids[]='.$this->data['host']['hostid'],
		'overview-link'
	),
	new CLink(_('Triggers'),
		'tr_status.php?filter_set=1&show_triggers=2&ack_status=1&show_events=1&show_events=0&show_details=1'.
		'&txt_select=&show_maintenance=1&hostid='.$this->data['host']['hostid'].url_param('groupid'), 'overview-link'),
	new CLink(_('Events'),
		'events.php?hostid='.$this->data['host']['hostid'].url_param('groupid').'&source='.EVENT_SOURCE_TRIGGERS,
		'overview-link'
	),
	new CLink(_('Graphs'), 'charts.php?hostid='.$this->data['host']['hostid'].url_param('groupid'), 'overview-link'),
	new CLink(_('Screens'), 'host_screen.php?hostid='.$this->data['host']['hostid'].url_param('groupid'),
		'overview-link')
]);

// configuration
if ($this->data['rwHost']) {
	$hostLink = new CLink(_('Host'),
		'hosts.php?form=update&hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$applicationsLink = new CLink(_('Applications'),
		'applications.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$itemsLink = new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$triggersLink = new CLink(_('Triggers'), 'triggers.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$graphsLink = new CLink(_('Graphs'), 'graphs.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$discoveryLink = new CLink(_('Discovery'),
		'host_discovery.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
	$webLink = new CLink(_('Web'), 'httpconf.php?hostid='.$this->data['host']['hostid'].url_param('groupid'));
}
else {
	$hostLink = _('Host');
	$applicationsLink = _('Application');
	$itemsLink = _('Items');
	$triggersLink = _('Triggers');
	$graphsLink = _('Graphs');
	$discoveryLink = _('Discovery');
	$webLink = _('Web');
}

$overviewFormList->addRow(_('Configuration'), [
	$hostLink,
	new CSpan([$applicationsLink, SPACE, '('.$this->data['host']['applications'].')'], 'overview-link'),
	new CSpan([$itemsLink, SPACE, '('.$this->data['host']['items'].')'], 'overview-link'),
	new CSpan([$triggersLink, SPACE, '('.$this->data['host']['triggers'].')'], 'overview-link'),
	new CSpan([$graphsLink, SPACE, '('.$this->data['host']['graphs'].')'], 'overview-link'),
	new CSpan([$discoveryLink, SPACE, '('.$this->data['host']['discoveries'].')'], 'overview-link'),
	new CSpan([$webLink, SPACE, '('.$this->data['host']['httpTests'].')'], 'overview-link')
]);

$hostInventoriesTab = new CTabView(['remember' => true]);
$hostInventoriesTab->setSelected(0);
$hostInventoriesTab->addTab('overviewTab', _('Overview'), $overviewFormList);

/*
 * Details tab
 */
$detailsFormList = new CFormList();

$inventoryValues = false;
if ($this->data['host']['inventory']) {
	foreach ($this->data['host']['inventory'] as $key => $value) {
		if (!zbx_empty($value)) {
			$detailsFormList->addRow(
				$this->data['tableTitles'][$key]['title'],
				[new CDiv(new CSpan(zbx_str2links($value), 'inventory-text-field'), 'inventory-text-field-wrap')]
			);

			$inventoryValues = true;
		}
	}
}

if (!$inventoryValues) {
	$hostInventoriesTab->setDisabled([1]);
}

$hostInventoriesTab->addTab('detailsTab', _('Details'), $detailsFormList);

// append tabs and form
$hostInventoriesForm = new CForm();
$hostInventoriesTab->setFooter(makeFormFooter(null, [new CButtonCancel(url_param('groupid'))]));

$hostInventoriesForm->addItem($hostInventoriesTab);
$hostInventoryWidget->addItem($hostInventoriesForm);

return $hostInventoryWidget;
