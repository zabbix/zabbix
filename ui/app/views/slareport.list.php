<?php declare(strict_types = 0);
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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('class.calendar.js');

$this->includeJsFile('slareport.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'slareport.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'slareport.list'))
	->setProfile('web.slareport.list.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('SLA'), 'filter_slaid'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_slaid',
						'object_name' => 'sla',
						'data' => $data['sla'] !== null
							? [CArrayHelper::renameKeys($data['sla'], ['slaid' => 'id'])]
							: [],
						'multiple' => false,
						'popup' => [
							'parameters' => [
								'srctbl' => 'sla',
								'srcfld1' => 'slaid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_slaid',
								'enabled_only' => 1
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				),
				new CLabel(_('Service'), 'filter_serviceid'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_serviceid',
						'object_name' => 'service',
						'data' => $data['service'] !== null
							? [CArrayHelper::renameKeys($data['service'], ['serviceid' => 'id'])]
							: [],
						'multiple' => false,
						'custom_select' => true
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('From'), 'filter_date_from'),
				new CFormField(
					(new CDateSelector('filter_date_from', $data['filter']['date_from']))
						->setDateFormat(ZBX_DATE)
						->setPlaceholder(_('YYYY-MM-DD'))
				),
				new CLabel(_('To'), 'filter_date_to'),
				new CFormField(
					(new CDateSelector('filter_date_to', $data['filter']['date_to']))
						->setDateFormat(ZBX_DATE)
						->setPlaceholder(_('YYYY-MM-DD'))
				)
			])
	]);

$widget = (new CWidget())
	->setTitle(_('SLA report'))
	->addItem($filter);

$report = new CTableInfo();

$form = (new CForm())
	->setId('slareport-list')
	->setName('slareport_list');

if ($data['sla'] === null || $data['has_errors']) {
	if ($data['sla'] === null) {
		$report->setNoDataMessage(_('Select SLA to display SLA report.'));
	}

	$form->addItem($report);
}
elseif ($data['service'] === null) {
	$header = [
		make_sorting_header(_('Service'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'slareport.list')
				->getUrl()
		)->addStyle('width: 15%;'),
		_('SLO')
	];

	foreach ($data['sli']['periods'] as $period) {
		$header[] = CSlaHelper::getPeriodTag((int) $data['sla']['period'], $period['period_from'], $period['period_to'],
			$data['sla']['timezone']
		)->addClass($data['sla']['period'] != ZBX_SLA_PERIOD_ANNUALLY ? 'vertical' : null);
	}

	$report->setHeader($header);

	$service_index = array_flip($data['sli']['serviceids']);

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
	}

	$form
		->addItem($report)
		->addItem($data['paging']);
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

	$form->addItem($report);
}

$widget
	->addItem($form)
	->show();

(new CScriptTag('
	view.init();
'))
	->setOnDocumentReady()
	->show();
