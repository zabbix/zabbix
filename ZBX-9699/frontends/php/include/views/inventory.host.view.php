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


$hostInventoryWidget = (new CWidget())->setTitle(_('Host inventory'));

/*
 * Overview tab
 */
$overviewFormList = new CFormList();

$hostSpan = (new CSpan($this->data['host']['host']))
	->addClass(ZBX_STYLE_LINK_ACTION)
	->setMenuPopup(CMenuPopupHelper::getHost(
		$this->data['host'],
		$this->data['hostScripts'][$this->data['host']['hostid']],
		false
	));

$hostName = ($this->data['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
	? [$hostSpan, SPACE, (new CDiv())->addClass('icon-maintenance-inline')]
	: $hostSpan;

$overviewFormList->addRow(_('Host name'), $hostName);

if ($this->data['host']['host'] !== $this->data['host']['name']) {
	$overviewFormList->addRow(_('Visible name'), (new CSpan($this->data['host']['name']))->addClass('text-field'));
}

$agentInterfaceRows = [];
$snmpInterfaceRows = [];
$ipmiInterfaceRows = [];
$jmxInterfaceRows = [];

foreach ($this->data['host']['interfaces'] as $interface) {
	$spanClass = $interface['main'] ? 'default_interface' : null;

	switch ($interface['type']) {
		case INTERFACE_TYPE_AGENT:
			$agentInterfaceRows[] = new CRow([
				(new CDiv($interface['ip']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['dns']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
					->addClass($spanClass),
				(new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS')))
					->setWidth(ZBX_TEXTAREA_INTERFACE_USEIP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['port']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->addClass($spanClass)
			]);
			break;

		case INTERFACE_TYPE_SNMP:
			$snmpInterfaceRows[] = new CRow([
				(new CDiv($interface['ip']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['dns']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
					->addClass($spanClass),
				(new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS')))
					->setWidth(ZBX_TEXTAREA_INTERFACE_USEIP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['port']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->addClass($spanClass)
			]);
			break;

		case INTERFACE_TYPE_IPMI:
			$ipmiInterfaceRows[] = new CRow([
				(new CDiv($interface['ip']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['dns']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
					->addClass($spanClass),
				(new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS')))
					->setWidth(ZBX_TEXTAREA_INTERFACE_USEIP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['port']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->addClass($spanClass)
			]);
			break;

		case INTERFACE_TYPE_JMX:
			$jmxInterfaceRows[] = new CRow([
				(new CDiv($interface['ip']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['dns']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
					->addClass($spanClass),
				(new CDiv(($interface['useip'] == INTERFACE_USE_IP) ? _('IP') : _('DNS')))
					->setWidth(ZBX_TEXTAREA_INTERFACE_USEIP_WIDTH)
					->addClass($spanClass),
				(new CDiv($interface['port']))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->addClass($spanClass)
			]);
			break;
	}
}

$interfaceTableHeaderSet = false;

// Agent interface
if ($agentInterfaceRows) {
	$ifTab = (new CTable())->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
	$interfaceTableHeaderSet = true;

	foreach ($agentInterfaceRows as $interface) {
		$ifTab->addRow($interface);
	}

	$overviewFormList->addRow(_('Agent interfaces'), (new CDiv($ifTab))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));
}

// SNMP interface
if ($snmpInterfaceRows) {
	$ifTab = (new CTable());

	if (!$interfaceTableHeaderSet) {
		$ifTab->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
		$interfaceTableHeaderSet = true;
	}

	foreach ($snmpInterfaceRows as $interface) {
		$ifTab->addRow($interface);
	}

	$overviewFormList->addRow(_('SNMP interfaces'), (new CDiv($ifTab))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));
}

// JMX interface
if ($jmxInterfaceRows) {
	$ifTab = (new CTable());

	if (!$interfaceTableHeaderSet) {
		$ifTab->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
		$interfaceTableHeaderSet = true;
	}

	foreach ($jmxInterfaceRows as $interface) {
		$ifTab->addRow($interface);
	}

	$overviewFormList->addRow(_('JMX interfaces'), (new CDiv($ifTab))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));
}

// IPMI interface
if ($ipmiInterfaceRows) {
	$ifTab = (new CTable());

	if (!$interfaceTableHeaderSet) {
		$ifTab->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port')]);
	}

	foreach ($ipmiInterfaceRows as $interface) {
		$ifTab->addRow($interface);
	}

	$overviewFormList->addRow(_('IPMI interfaces'), (new CDiv($ifTab))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));
}

// inventory (OS, Hardware, Software)
if ($this->data['host']['inventory']) {
	if ($this->data['host']['inventory']['os']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['os']['title'],
			[(new CDiv(
				(new CSpan(zbx_str2links($this->data['host']['inventory']['os'])))->addClass('inventory-text-field')
			))
				->addClass('inventory-text-field-wrap')
			]
		);
	}
	if ($this->data['host']['inventory']['hardware']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['hardware']['title'],
			[(new CDiv(
				(new CSpan(zbx_str2links($this->data['host']['inventory']['hardware'])))->addClass('inventory-text-field')
			))
				->addClass('inventory-text-field-wrap')
			]
		);
	}
	if ($this->data['host']['inventory']['software']) {
		$overviewFormList->addRow(
			$this->data['tableTitles']['software']['title'],
			[(new CDiv(
				(new CSpan(zbx_str2links($this->data['host']['inventory']['software'])))->addClass('inventory-text-field')
			))
				->addClass('inventory-text-field-wrap')
			]
		);
	}
}

// description
if ($this->data['host']['description'] !== '') {
	$overviewFormList->addRow(_('Description'),
		[(new CDiv(
			(new CSpan(zbx_str2links($this->data['host']['description'])))->addClass('inventory-text-field')
		))
			->addClass('inventory-text-field-wrap')
		]
	);
}

// latest data
$overviewFormList->addRow(_('Monitoring'), [
	new CLink(_('Web'), 'httpmon.php?hostid='.$this->data['host']['hostid'].url_param('groupid')),
	(new CLink(_('Latest data'),
		'latest.php?form=1&select=&show_details=1&filter_set=Filter&hostids[]='.$this->data['host']['hostid'])
	)
		->addClass('overview-link'),
	(new CLink(_('Triggers'),
		'tr_status.php?filter_set=1&show_triggers=2&ack_status=1&show_events=1&show_events=0&show_details=1'.
		'&txt_select=&show_maintenance=1&hostid='.$this->data['host']['hostid'].url_param('groupid'))
	)
		->addClass('overview-link'),
	(new CLink(_('Events'),
		'events.php?hostid='.$this->data['host']['hostid'].url_param('groupid').'&source='.EVENT_SOURCE_TRIGGERS))
		->addClass('overview-link'),
	(new CLink(_('Graphs'), 'charts.php?hostid='.$this->data['host']['hostid'].url_param('groupid')))
		->addClass('overview-link'),
	(new CLink(_('Screens'), 'host_screen.php?hostid='.$this->data['host']['hostid'].url_param('groupid')))
		->addClass('overview-link')
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
	(new CSpan([$applicationsLink, SPACE, '('.$this->data['host']['applications'].')']))->addClass('overview-link'),
	(new CSpan([$itemsLink, SPACE, '('.$this->data['host']['items'].')']))->addClass('overview-link'),
	(new CSpan([$triggersLink, SPACE, '('.$this->data['host']['triggers'].')']))->addClass('overview-link'),
	(new CSpan([$graphsLink, SPACE, '('.$this->data['host']['graphs'].')']))->addClass('overview-link'),
	(new CSpan([$discoveryLink, SPACE, '('.$this->data['host']['discoveries'].')']))->addClass('overview-link'),
	(new CSpan([$webLink, SPACE, '('.$this->data['host']['httpTests'].')']))->addClass('overview-link')
]);

$hostInventoriesTab = (new CTabView(['remember' => true]))
	->setSelected(0)
	->addTab('overviewTab', _('Overview'), $overviewFormList);

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
				[(new CDiv(
					(new CSpan(zbx_str2links($value)))->addClass('inventory-text-field')
				))->addClass('inventory-text-field-wrap')]
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
$hostInventoriesTab->setFooter(makeFormFooter(null, [new CButtonCancel(url_param('groupid'))]));

$hostInventoryWidget->addItem(
	(new CForm())->addItem($hostInventoriesTab)
);

return $hostInventoryWidget;
