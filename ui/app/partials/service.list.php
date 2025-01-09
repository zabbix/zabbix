<?php declare(strict_types = 0);
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
 * @var CPartial $this
 * @var array    $data
 */

$form = (new CForm())
	->setId('service-list')
	->setName('service_list');

if ($data['is_filtered']) {
	$path = null;

	$header = [
		(new CColHeader(_('Parent services')))->addStyle('width: 15%;'),
		(new CColHeader(_('Name')))->addStyle('width: 10%;')
	];
}
else {
	$path = $data['path'];

	if ($data['service'] !== null) {
		$path[] = $data['service']['serviceid'];
	}

	$header = [
		(new CColHeader(_('Name')))->addStyle('width: 25%;')
	];
}

$table = (new CTableInfo())
	->setHeader(array_merge($header, [
		(new CColHeader(_('Status')))->addStyle('width: 14%;'),
		(new CColHeader(_('Root cause')))->addStyle('width: 25%;'),
		(new CColHeader(_('Created at')))->addStyle('width: 10%;'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3)
	]))
	->setPageNavigation($data['paging']);

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

	foreach (array_slice($service['problem_events'], 0, $data['max_in_table']) as $problem_event) {
		if ($root_cause) {
			$root_cause[] = ', ';
		}

		$root_cause[] = $data['can_monitor_problems'] && $problem_event['triggerid'] !== null
			? new CLink($problem_event['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_set', '1')
					->setArgument('triggerids', [$problem_event['triggerid']])
			)
			: $problem_event['name'];
	}

	$table->addRow(new CRow(array_merge($row, [
		(new CCol([
			(new CLink($service['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'service.list')
					->setArgument('path', $path)
					->setArgument('serviceid', $serviceid)
			))->setAttribute('data-serviceid', $serviceid),
			CViewHelper::showNum($service['children'])
		]))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(CSeverityHelper::getName((int) $service['status'])))
			->addClass(CSeverityHelper::getStyle((int) $service['status'])),
		$root_cause,
		zbx_date2str(DATE_FORMAT, $service['created_at']),
		$data['tags'][$serviceid]
	])));
}

$form
	->addItem($table)
	->show();
