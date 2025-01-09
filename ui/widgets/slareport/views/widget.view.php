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
 * SLA report widget view.
 *
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
		_x('Service', 'compact table header'),
		_x('SLO', 'compact table header')
	];

	foreach ($data['sli']['periods'] as $period) {
		$header[] = CSlaHelper::getPeriodTag((int) $data['sla']['period'], $period['period_from'], $period['period_to'],
			$data['sla']['timezone']
		)->addClass($data['sla']['period'] != ZBX_SLA_PERIOD_ANNUALLY ? ZBX_STYLE_TEXT_VERTICAL : null);
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

	$total = count($data['services']) > $data['search_limit']
		? $data['search_limit'].'+'
		: count($data['services']);

	if ($total > 0) {
		$report->setFooter(
			(new CCol(_s('Displaying %1$s of %2$s found', $num_rows_displayed, $total)))
				->setColSpan($report->getNumCols())
				->addClass(ZBX_STYLE_LIST_TABLE_FOOTER)
		);
	}

}
else {
	$report->setHeader([
		CSlaHelper::getReportNames(true)[$data['sla']['period']],
		_x('SLO', 'compact table header'),
		_x('SLI', 'compact table header'),
		_x('Uptime', 'compact table header'),
		_x('Downtime', 'compact table header'),
		_x('Error budget', 'compact table header'),
		_x('Excluded downtimes', 'compact table header')
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

(new CWidgetView($data))
	->addItem($report)
	->show();
