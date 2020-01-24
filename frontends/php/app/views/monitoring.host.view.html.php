<?php
declare(strict_types=1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


// create form
$form = (new CForm())->setName('host_view');

// create table
$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'host.view')
	->getUrl();

$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

$view_url = $data['view_curl']->getUrl();

$table->setHeader([
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url)
		->addStyle('width: 13%'),
	(new CColHeader(_('Interface')))->addStyle('width: 134px;'),
	(new CColHeader(_('Availability')))->addStyle('width: 117px;'),
	(new CColHeader(_('Tags')))->addStyle('width: 17%'),
	(new CColHeader(_('Problems')))->addStyle('width: 117px;'),
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $view_url)
		->addStyle('width: 5%'),
	(new CColHeader(_('Latest data')))->addStyle('width: 6%'),
	(new CColHeader(_('Problems')))->addStyle('width: 7%'),
	(new CColHeader(_('Graphs')))->addStyle('width: 7%'),
	(new CColHeader(_('Screens')))->addStyle('width: 7%'),
	(new CColHeader(_('Web')))->addStyle('width: 5%')
]);

foreach ($data['hosts'] as $hostid => $host) {
	$host_name = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostid));

	$interface = null;
	foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $interface_type) {
		$host_interfaces = array_filter($host['interfaces'], function($host_interface) use($interface_type) {
			return $host_interface['type'] == $interface_type;
		});
		if ($host_interfaces) {
			$interface = reset($host_interfaces);
			break;
		}
	}

	$host_interface = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
	$host_interface .= $interface['port'] ? NAME_DELIMITER.$interface['port'] : '';
	$problem_count = count($host['problems']);

	$problems_div = (new CDiv())->addClass(ZBX_STYLE_PROBLEM_ICON_LIST);

	$problems = [];
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$problems[$severity] = [
			'count' => 0,
			'severity_name' => $data['config']['severity_name_'.$severity],
			'severity_color' => $data['config']['severity_color_'.$severity],
		];
	}

	foreach ($host['problems'] as $problem) {
		$problems[$problem['severity']]['count']++;
	}

	// Sort by severity starting from highest severity.
	krsort($problems);

	foreach ($problems as $problem) {
		if ($problem['count'] > 0) {
			$problems_div->addItem((new CSpan($problem['count']))
				->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
				->setAttribute('title', $problem['severity_name'])
				->addStyle('background: #'.$problem['severity_color'])
			);
		}
	}

	$maintenance_icon = '';

	if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
		if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
			$maintenance = $data['maintenances'][$host['maintenanceid']];
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
				$maintenance['description']
			);
		}
		else {
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'],
				_('Inaccessible maintenance'), ''
			);
		}
	}

	$table->addRow([
		[$host_name, $maintenance_icon],
		$host_interface,
		getHostAvailabilityTable($host),
		$host['tags'],
		$problems_div,
		($host['status'] == HOST_STATUS_MONITORED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED),
		[
			new CLink(_('Latest data'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'latest.view')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
			)
		],
		[
			new CLink(_('Problems'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
			),
			CViewHelper::showNum($problem_count)
		],
		$host['graphs']
			? [
				new CLink(_('Graphs'), (new CUrl('charts.php'))->setArgument('filter_hostid', $host['hostid'])),
				CViewHelper::showNum($host['graphs'])
			]
			: (new CSpan(_('Graphs')))->addClass(ZBX_STYLE_DISABLED),
		$host['screens']
			? [
				new CLink(_('Screens'), (new CUrl('host_screen.php'))->setArgument('hostid', $host['hostid'])),
				CViewHelper::showNum($host['screens'])
			]
			: (new CSpan(_('Screens')))->addClass(ZBX_STYLE_DISABLED),
		$host['httpTests']
			? [
				new CLink(_('Web'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'web.view')
						->setArgument('filter_hostid', $host['hostid'])
				),
				CViewHelper::showNum($host['httpTests'])
			]
			: (new CSpan(_('Web')))->addClass(ZBX_STYLE_DISABLED)
	]);
}

$form->addItem([$table,	$data['paging']]);

echo $form;
