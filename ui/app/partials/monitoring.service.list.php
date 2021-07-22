<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 */

$form = (new CForm())
	->setId('service-list')
	->setName('service-list');

if ($data['is_filtered']) {
	$path = null;

	$header = [
		(new CColHeader(_('Parent services')))->addStyle('width: 15%'),
		(new CColHeader(_('Name')))->addStyle('width: 10%')
	];
}
else {
	$path = $data['path'];
	if ($data['service'] !== null) {
		$path[] = $data['service']['serviceid'];
	}

	$header = [
		(new CColHeader(_('Name')))->addStyle('width: 25%')
	];
}

$table = (new CTableInfo())
	->setHeader(array_merge($header, [
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3)
	]));

foreach ($data['services'] as $serviceid => $service) {
	$row = [];

	if ($data['is_filtered']) {
		$parents = [];

		foreach (array_slice($service['parents'], 0, $data['max_in_table']) as $parent) {
			if ($parents) {
				$parents[] = ', ';
			}

			$parents[] = (new CLink($parent['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'service.list')
					->setArgument('serviceid', $parent['serviceid'])
			))->setAttribute('data-serviceid', $parent['serviceid']);
		}

		$row[] = $parents;
	}

	$root_cause = [];

	foreach ($data['events'][$serviceid] as $event) {
		if ($root_cause) {
			$root_cause[] = ', ';
		}

		$root_cause[] = $data['can_monitor_problems']
			? new CLink($event['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('triggerids', [$event['objectid']])
			)
			: $event['name'];
	}

	$table->addRow(new CRow(array_merge($row, [
		($service['children'] > 0)
			? [
				(new CLink($service['name'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'service.list')
						->setArgument('path', $path)
						->setArgument('serviceid', $serviceid)
				))->setAttribute('data-serviceid', $serviceid),
				CViewHelper::showNum($service['children'])
			]
			: $service['name'],
		in_array($service['status'], [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])
			? (new CCol(_('OK')))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(getSeverityName($service['status'])))->addClass(getSeverityStyle($service['status'])),
		$root_cause,
		($service['showsla'] == SERVICE_SHOW_SLA_ON) ? sprintf('%.4f', $service['goodsla']) : '',
		$data['tags'][$serviceid]
	])));
}

$form
	->addItem([$table, $data['paging']])
	->show();
