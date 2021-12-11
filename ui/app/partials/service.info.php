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
					->setArgument('action', 'sla_report')
					->setArgument('slaid', $sla['slaid'])
			)
		];

		forech...
		if ($sla['sli']['sli']) {
			$sli = $sla['sli']['sli'][0][0];

			$sla_html[] = ': ';
			$sla_html[] = CSlaHelper::getSliTag($sli['sli'], (float) $sla['slo']);
			$sla_html[] = (new CSpan())
				->addClass(ZBX_STYLE_ICON_DESCRIPTION)
				->setHint(
					(new CTable())
						->addClass(ZBX_STYLE_LIST_TABLE)
						->setHeader([_('Reporting period'), _('SLO'), _('SLI'), _('Uptime'), _('Downtime'),
							_('Error budget')
						])
						->addRow([
							CSlaHelper::getPeriodTag((int) $sla['period'], $sla['sli']['periods'][0]['period_from'],
								$sla['sli']['periods'][0]['period_to'], $sla['timezone']
							),
							CSlaHelper::getSloTag((float) $sla['slo']),
							CSlaHelper::getSliTag($sli['sli'], (float) $sla['slo']),
							CSlaHelper::getUptimeTag($sli['uptime']),
							CSlaHelper::getDowntimeTag($sli['downtime']),
							CSlaHelper::getErrorBudgetTag($sli['error_budget'])
						])
				);
		}

		$slas[] = new CDiv($sla_html);
	}

	if ($data['slas_count'] > count($data['slas'])) {
		$slas[] = new CDiv('&hellip;');
	}
}

(new CDiv([
	(new CDiv())
		->addClass(ZBX_STYLE_SERVICE_INFO_GRID)
		->addItem([
			(new CDiv($data['service']['name']))->addClass(ZBX_STYLE_SERVICE_NAME),
			(new CDiv(
				$data['is_editable']
					? (new CButton(null))
						->addClass(ZBX_STYLE_BTN_EDIT)
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
			(new CDiv($slas))
				->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
				->addClass(ZBX_STYLE_SERVICE_INFO_VALUE_SLA)
		])
		->addItem([
			(new CDiv(_('Tags')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
			(new CDiv($data['service']['tags']))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
		])
]))
	->addClass(ZBX_STYLE_SERVICE_INFO)
	->addClass('service-status-'.CSeverityHelper::getStyle((int) $data['service']['status']))
	->show();
