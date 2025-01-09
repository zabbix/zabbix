<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('inventory.host.view.js.php');

// Overview tab.
$overviewFormList = new CFormList();

$host_name = (new CLinkAction($data['host']['host']))
	->setMenuPopup(CMenuPopupHelper::getHost($data['host']['hostid'], false));

if ($data['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
	if (array_key_exists($data['host']['maintenanceid'], $data['maintenances'])) {
		$maintenance = $data['maintenances'][$data['host']['maintenanceid']];
		$maintenance_icon = makeMaintenanceIcon($data['host']['maintenance_type'], $maintenance['name'],
			$maintenance['description']
		);
	}
	else {
		$maintenance_icon = makeMaintenanceIcon($data['host']['maintenance_type'], _('Inaccessible maintenance'),
			''
		);
	}

	$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
}

$overviewFormList->addRow(_('Host name'), (new CDiv($host_name))->setWidth(ZBX_TEXTAREA_BIG_WIDTH));

if ($data['host']['host'] !== $data['host']['name']) {
	$overviewFormList->addRow(_('Visible name'), (new CDiv($data['host']['name']))->setWidth(ZBX_TEXTAREA_BIG_WIDTH));
}

$interfaces = [
	INTERFACE_TYPE_AGENT => [],
	INTERFACE_TYPE_SNMP => [],
	INTERFACE_TYPE_JMX => [],
	INTERFACE_TYPE_IPMI => []
];

$interface_names = [
	INTERFACE_TYPE_AGENT => _('Agent interfaces'),
	INTERFACE_TYPE_SNMP => _('SNMP interfaces'),
	INTERFACE_TYPE_JMX => _('JMX interfaces'),
	INTERFACE_TYPE_IPMI => _('IPMI interfaces')
];

foreach ($data['host']['interfaces'] as $interface) {
	$interfaces[$interface['type']][] = $interface;
}

$header_is_set = false;

foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $type) {
	if ($interfaces[$type]) {
		$ifTab = (new CTable());

		if (!$header_is_set) {
			$ifTab->setHeader([_('IP address'), _('DNS name'), _('Connect to'), _('Port'), _('Default')]);
			$header_is_set = true;
		}

		foreach ($interfaces[$type] as $interface) {
			$ifTab->addRow([
				(new CTextBox('ip', $interface['ip'], true, 64))
					->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH)
					->removeId(),
				(new CTextBox('dns', $interface['dns'], true, 64))
					->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH)
					->removeId(),
				(new CRadioButtonList('useip['.$interface['interfaceid'].']', (int) $interface['useip']))
					->addValue('IP', INTERFACE_USE_IP)
					->addValue('DNS', INTERFACE_USE_DNS)
					->setModern()
					->setReadonly(true),
				(new CTextBox('port', $interface['port'], true, 64))
					->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
					->removeId(),
				(new CRadioButtonList('main['.$interface['interfaceid'].']', (int) $interface['main']))
					->addValue(null, INTERFACE_PRIMARY)
					->setReadonly(true)
					->removeId()
			]);
		}

		$overviewFormList->addRow($interface_names[$type],
			(new CDiv($ifTab))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setWidth(ZBX_HOST_INTERFACE_WIDTH)
		);
	}
}

// inventory (OS, Hardware, Software)
foreach (['os', 'hardware', 'software'] as $key) {
	if (array_key_exists($key, $data['host']['inventory'])) {
		if ($data['host']['inventory'][$key] !== '') {
			$overviewFormList->addRow($data['tableTitles'][$key]['title'],
				(new CDiv(zbx_str2links($data['host']['inventory'][$key])))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			);
		}
	}
}

// description
if ($data['host']['description'] !== '') {
	$overviewFormList->addRow(_('Description'),
		(new CDiv(zbx_str2links($data['host']['description'])))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	);
}

// latest data
$overviewFormList->addRow(_('Monitoring'),
	new CHorList([
		$data['allowed_ui_hosts']
			? new CLink(_('Web'), (new CUrl('zabbix.php'))
				->setArgument('action', 'web.view')
				->setArgument('filter_hostids[]', $data['host']['hostid'])
				->setArgument('filter_set', '1')
			)
			: _('Web'),
		$data['allowed_ui_latest_data']
			? new CLink(_('Latest data'), (new CUrl('zabbix.php'))
				->setArgument('action', 'latest.view')
				->setArgument('hostids[]', $data['host']['hostid'])
				->setArgument('show_details', '1')
				->setArgument('filter_set', '1')
			)
			: _('Latest data'),
		$data['allowed_ui_problems']
			? new CLink(_('Problems'), (new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('hostids', [$data['host']['hostid']])
				->setArgument('filter_set', '1')
			)
			: _('Problems'),
		$data['allowed_ui_hosts']
			? new CLink(_('Graphs'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'charts.view')
					->setArgument('filter_hostids', [$data['host']['hostid']])
					->setArgument('filter_set', '1')
		)
			: _('Graphs'),
		$data['allowed_ui_hosts']
			? new CLink(_('Dashboards'), (new CUrl('zabbix.php'))
				->setArgument('action', 'host.dashboard.view')
				->setArgument('hostid', $data['host']['hostid'])
			)
			: _('Dashboards')
	])
);

// configuration
if ($data['allowed_ui_conf_hosts'] && $data['rwHost']) {
	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'host.edit')
		->setArgument('hostid', $data['host']['hostid'])
		->getUrl();

	$hostLink = (new CLink(_('Host'), $host_url))
		->setAttribute('data-hostid', $data['host']['hostid'])
		->setAttribute('data-action', 'host.edit');

	$itemsLink = new CLink(_('Items'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'item.list')
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$data['host']['hostid']])
			->setArgument('context', 'host')
	);
	$triggersLink = new CLink(_('Triggers'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'trigger.list')
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$data['host']['hostid']])
			->setArgument('context', 'host')
	);
	$graphsLink = new CLink(_('Graphs'),
		(new CUrl('graphs.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$data['host']['hostid']])
			->setArgument('context', 'host')
	);
	$discoveryLink = new CLink(_('Discovery'),
		(new CUrl('host_discovery.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$data['host']['hostid']])
			->setArgument('context', 'host')
	);
	$webLink = new CLink(_('Web'),
		(new CUrl('httpconf.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$data['host']['hostid']])
			->setArgument('context', 'host')
	);
}
else {
	$hostLink = _('Host');
	$itemsLink = _('Items');
	$triggersLink = _('Triggers');
	$graphsLink = _('Graphs');
	$discoveryLink = _('Discovery');
	$webLink = _('Web');
}

$overviewFormList->addRow(_('Configuration'),
	new CHorList([
		$hostLink,
		(new CSpan([$itemsLink, CViewHelper::showNum($data['host']['items'])])),
		(new CSpan([$triggersLink, CViewHelper::showNum($data['host']['triggers'])])),
		(new CSpan([$graphsLink, CViewHelper::showNum($data['host']['graphs'])])),
		(new CSpan([$discoveryLink, CViewHelper::showNum($data['host']['discoveries'])])),
		(new CSpan([$webLink, CViewHelper::showNum($data['host']['httpTests'])]))
	])
);

$hostInventoriesTab = (new CTabView(['remember' => true]))
	->setSelected(0)
	->addTab('overviewTab', _('Overview'), $overviewFormList);

/*
 * Details tab
 */
$detailsFormList = new CFormList();

$inventoryValues = false;
foreach ($data['host']['inventory'] as $key => $value) {
	if ($value !== '') {
		$detailsFormList->addRow($data['tableTitles'][$key]['title'],
			(new CDiv(zbx_str2links($value)))
				->addClass(ZBX_STYLE_WORDWRAP)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
		);

		$inventoryValues = true;
	}
}

if (!$inventoryValues) {
	$hostInventoriesTab->setDisabled([1]);
}

$hostInventoriesTab->addTab('detailsTab', _('Details'), $detailsFormList);

// append tabs and form
$hostInventoriesTab->setFooter(makeFormFooter(null, [new CButtonCancel()]));

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();

(new CHtmlPage())
	->setTitle(_('Host inventory'))
	->addItem(
		(new CForm())
			->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
			->addItem($hostInventoriesTab)
	)
	->show();
