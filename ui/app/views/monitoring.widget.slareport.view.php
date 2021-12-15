<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$report = (new CTableInfo())->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

if (count($data['services']) > 1) {
	$header = [
		_('Service'),
		_('SLO')
	];

	foreach ($data['periods'] as $period) {
		$header[] = CSlaHelper::getPeriodTag($data['sla']['period'], $period['period_from'], $period['period_to'],
			$data['sla']['timezone']
		)->addClass($data['sla']['period'] !== ZBX_SLA_PERIOD_ANNUALLY ? 'date-vertical' : null);
	}

	$report->setHeader($header);
}
else {
	$report->setHeader([
		CSlaHelper::getPeriodNames()[$data['sla']['period']],
		_('SLO'),
		_('SLI'),
		_('Uptime'),
		_('Downtime'),
		_('Error budget'),
		_('Excluded downtimes')
	]);

	foreach (array_reverse($data['periods'], true) as $index => $period) {
		$excluded_downtimes = [];

		foreach ($data['sli'][$index][0]['excluded_downtimes'] as $excluded_downtime) {
			$excluded_downtimes[] = CSlaHelper::getExcludedDowntimeTag($excluded_downtime);
		}

		$report->addRow([
			CSlaHelper::getPeriodTag($data['sla']['period'], $period['period_from'], $period['period_to'],
				$data['sla']['timezone']
			),
			CSlaHelper::getSloTag($data['sla']['slo']),
			CSlaHelper::getSliTag($data['sli'][$index][0]['sli'], $data['sla']['slo']),
			CSlaHelper::getUptimeTag($data['sli'][$index][0]['uptime']),
			CSlaHelper::getDowntimeTag($data['sli'][$index][0]['downtime']),
			CSlaHelper::getErrorBudgetTag($data['sli'][$index][0]['error_budget']),
			$excluded_downtimes
		]);
	}
}

$output = [
	'name' => $data['name'],
	'body' => (new CDiv($report))->addClass('dashboard-grid-widget-slareport')->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
