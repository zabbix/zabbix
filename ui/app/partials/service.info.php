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

$parents = [];

while ($parent = array_shift($data['service']['parents'])) {
	$parents[] = (new CLink($parent['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'service.list')
			->setArgument('serviceid', $parent['serviceid'])
	))->setAttribute('data-serviceid', $parent['serviceid']);

	$parents[] = CViewHelper::showNum($parent['children']);

	if (!$data['service']['parents']) {
		break;
	}

	$parents[] = ', ';
}

$slas = [];

if (array_key_exists('slas', $data)) {
	foreach ($data['slas'] as $sla) {
		$sla_html = [
			new CLink($sla['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'slareport.list')
					->setArgument('filter_slaid', $sla['slaid'])
					->setArgument('filter_set', 1)
			)
		];

		if ($sla['sli']['sli']) {
			$hint = (new CTable())
				->addClass(ZBX_STYLE_LIST_TABLE)
				->setHeader([_('Reporting period'), _('SLO'), _('SLI'), _('Uptime'), _('Downtime'), _('Error budget')]);

			foreach (array_reverse($sla['sli']['sli'], true) as $period_index => $sli) {
				$hint->addRow([
					CSlaHelper::getPeriodTag((int) $sla['period'], $sla['sli']['periods'][$period_index]['period_from'],
						$sla['sli']['periods'][$period_index]['period_to'], $sla['timezone']
					),
					CSlaHelper::getSloTag((float) $sla['slo']),
					CSlaHelper::getSliTag($sli[0]['sli'], (float) $sla['slo']),
					CSlaHelper::getUptimeTag($sli[0]['uptime']),
					CSlaHelper::getDowntimeTag($sli[0]['downtime']),
					CSlaHelper::getErrorBudgetTag($sli[0]['error_budget'])
				]);
			}

			$current_period_sli = $sla['sli']['sli'][count($sla['sli']['sli']) - 1][0]['sli'];

			$sla_html[] = ': ';
			$sla_html[] = CSlaHelper::getSliTag($current_period_sli, (float) $sla['slo']);
			$sla_html[] = (new CButtonIcon(ZBX_ICON_ALERT_WITH_CONTENT))
				->setAttribute('data-content', '?')
				->setHint($hint);
		}

		$slas[] = (new CDiv($sla_html))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE_SLA);
	}

	if ($data['slas_count'] > count($data['slas'])) {
		$slas[] = (new CDiv(HELLIP()))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE_SLA);
	}
}

(new CDiv([
	(new CDiv())
		->addClass(ZBX_STYLE_SERVICE_INFO_GRID)
		->addItem([
			(new CDiv($data['service']['name']))->addClass(ZBX_STYLE_SERVICE_NAME),
			(new CDiv(
				$data['is_editable']
					? (new CButtonIcon(ZBX_ICON_PENCIL, _('Edit')))
						->addClass('js-edit-service')
						->setAttribute('data-serviceid', $data['service']['serviceid'])
						->setEnabled(!$data['service']['readonly'])
					: null
			))->addClass(ZBX_STYLE_SERVICE_ACTIONS)
		]),
	(new CDiv())
		->addClass(ZBX_STYLE_SERVICE_INFO_GRID)
		->addItem([
			(new CDiv(_('Parent services')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($parents))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('Status')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv(
				(new CDiv(CSeverityHelper::getName((int) $data['service']['status'])))
					->addClass(ZBX_STYLE_SERVICE_STATUS))
			)->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('SLA')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($slas))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
		->addItem([
			(new CDiv(_('Tags')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($data['service']['tags']))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
]))
	->addClass(ZBX_STYLE_SERVICE_INFO)
	->addClass('service-status-'.CSeverityHelper::getStyle((int) $data['service']['status']))
	->show();
