<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $form
 * @var array $data
 */

$form = (new CForm())->setName('host_view');

$table = (new CTableInfo());

$view_url = $data['view_curl']->getUrl();

$table->setHeader([
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
	(new CColHeader(_('Interface'))),
	(new CColHeader(_('Availability'))),
	(new CColHeader(_('Tags'))),
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $view_url),
	(new CColHeader(_('Latest data'))),
	(new CColHeader(_('Problems'))),
	(new CColHeader(_('Graphs'))),
	(new CColHeader(_('Dashboards'))),
	(new CColHeader(_('Web')))
]);

foreach ($data['hosts'] as $hostid => $host) {
	$host_name = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostid));

	$interface = null;
	if ($host['interfaces']) {
		foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
			$host_interfaces = array_filter($host['interfaces'], function(array $host_interface) use ($interface_type) {
				return ($host_interface['type'] == $interface_type);
			});
			if ($host_interfaces) {
				$interface = reset($host_interfaces);
				break;
			}
		}
	}

	$problems_link = new CLink('', (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_name', '')
		->setArgument('severities', $data['filter']['severities'])
		->setArgument('hostids', [$host['hostid']]));

	$total_problem_count = 0;

	// Fill the severity icons by problem count and style, and calculate the total number of problems.
	foreach ($host['problem_count'] as $severity => $count) {
		if (($count > 0 && $data['filter']['severities'] && in_array($severity, $data['filter']['severities']))
				|| (!$data['filter']['severities'] && $count > 0)) {
			$total_problem_count += $count;

			$problems_link->addItem((new CSpan($count))
				->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
				->addClass(CSeverityHelper::getStatusStyle($severity))
				->setAttribute('title', CSeverityHelper::getName($severity))
			);
		}

	}

	if ($total_problem_count == 0) {
		$problems_link->addItem('Problems');
	}
	else {
		$problems_link->addClass(ZBX_STYLE_PROBLEM_ICON_LINK);
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
		(new CCol(getHostInterface($interface)))->addClass(ZBX_STYLE_NOWRAP),
		getHostAvailabilityTable($host['interfaces']),
		$host['tags'],
		($host['status'] == HOST_STATUS_MONITORED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED),
		[
			$data['allowed_ui_latest_data']
				? new CLink(_('Latest data'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'latest.view')
						->setArgument('hostids', [$host['hostid']])
						->setArgument('filter_name', '')
				)
				: _('Latest data')
		],
		$problems_link,
		$host['graphs']
			? [
				new CLink(_('Graphs'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'charts.view')
						->setArgument('filter_hostids', (array) $host['hostid'])
						->setArgument('filter_show', GRAPH_FILTER_HOST)
						->setArgument('filter_set', '1')
				),
				CViewHelper::showNum($host['graphs'])
			]
			: (new CSpan(_('Graphs')))->addClass(ZBX_STYLE_DISABLED),
		$host['dashboards']
			? [
				new CLink(_('Dashboards'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'host.dashboard.view')
						->setArgument('hostid', $host['hostid'])
				),
				CViewHelper::showNum($host['dashboards'])
			]
			: (new CSpan(_('Dashboards')))->addClass(ZBX_STYLE_DISABLED),
		$host['httpTests']
			? [
				new CLink(_('Web'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'web.view')
						->setArgument('filter_set', '1')
						->setArgument('filter_hostids', (array) $host['hostid'])
				),
				CViewHelper::showNum($host['httpTests'])
			]
			: (new CSpan(_('Web')))->addClass(ZBX_STYLE_DISABLED)
	]);
}

$form->addItem([$table,	$data['paging']]);

echo $form;
