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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$report = (new CTableInfo())->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);

if ($data['has_permissions_error']) {
	$report->setNoDataMessage(_('No permissions to referred object or it does not exist!'));
}
elseif ($data['sla']['status'] != ZBX_SLA_STATUS_ENABLED) {
	$report->setNoDataMessage(_('SLA is disabled.'));
}
elseif (!$data['has_serviceid']) {
	$header = [
		_('Service'),
		_('SLO')
	];

	foreach ($data['sli']['periods'] as $period) {
		$header[] = CSlaHelper::getPeriodTag((int) $data['sla']['period'], $period['period_from'], $period['period_to'],
			$data['sla']['timezone']
		)->addClass($data['sla']['period'] != ZBX_SLA_PERIOD_ANNUALLY ? 'date-vertical' : null);
	}

	$report->setHeader($header);

	$service_index = array_flip($data['sli']['serviceids']);

	$num_rows_displayed = 0;

	foreach (array_intersect_key($data['services'], $service_index) as $serviceid => $service) {
		$row = [
			(new CCol($data['has_access'][CRoleHelper::ACTIONS_MANAGE_SLA]
				? new CLink(
					$service['name'],
					(new CUrl('zabbix.php'))
						->setArgument('action', 'slareport.list')
						->setArgument('filter_slaid', $data['sla']['slaid'])
						->setArgument('filter_serviceid', $serviceid)
						->setArgument('filter_set', 1)
						->getUrl()
				)
				: $service['name']
			))->addClass(ZBX_STYLE_WORDBREAK),
			CSlaHelper::getSloTag((float) $data['sla']['slo'])
		];

		foreach (array_keys($data['sli']['periods']) as $period_index) {
			$row[] = CSlaHelper::getSliTag(
				$data['sli']['sli'][$period_index][$service_index[$serviceid]]['sli'],
				(float) $data['sla']['slo']
			);
		}

		$report->addRow($row);

		if (++$num_rows_displayed == $data['rows_per_page']) {
			break;
		}
	}

	$report->setFooter(
		(new CCol(_s('Displaying %1$s of %2$s found', $num_rows_displayed,
			count($data['services']) > $data['search_limit']
				? $data['search_limit'].'+'
				: count($data['services'])
		)))
			->setColSpan($report->getNumCols())
			->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
	);
}
else {
	$report->setHeader([
		CSlaHelper::getReportNames()[$data['sla']['period']],
		_('SLO'),
		_('SLI'),
		_('Uptime'),
		_('Downtime'),
		_('Error budget'),
		_('Excluded downtimes')
	]);

	if ($data['sli']['serviceids']) {
		$service_index = 0;

		foreach (array_reverse($data['sli']['periods'], true) as $period_index => $period) {
			$sli = $data['sli']['sli'][$period_index][$service_index];

			$excluded_downtime_tags = [];
			foreach ($sli['excluded_downtimes'] as $excluded_downtime) {
				$excluded_downtime_tags[] = CSlaHelper::getExcludedDowntimeTag($excluded_downtime);
			}

			$report->addRow([
				CSlaHelper::getPeriodTag((int) $data['sla']['period'], $period['period_from'], $period['period_to'],
					$data['sla']['timezone']
				),
				CSlaHelper::getSloTag((float) $data['sla']['slo']),
				CSlaHelper::getSliTag($sli['sli'], (float) $data['sla']['slo']),
				CSlaHelper::getUptimeTag($sli['uptime']),
				CSlaHelper::getDowntimeTag($sli['downtime']),
				CSlaHelper::getErrorBudgetTag($sli['error_budget']),
				$excluded_downtime_tags
			]);
		}
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
