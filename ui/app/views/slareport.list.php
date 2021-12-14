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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('class.calendar.js');

$filter = (new CFilter())
	->addVar('action', 'slareport.list')
	->setResetUrl($data['reset_curl'])
	->setProfile('web.slareport.filter')
	->setActiveTab($data['active_tab']);

$filter->addFilterTab(_('Filter'), [
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			new CLabel(_('SLA'), 'filter_slaid'),
			(new CMultiSelect([
				'name' => 'filter_slaid',
				'object_name' => 'sla',
				'data' => $data['ms_sla'],
				'multiple' => false,
				'popup' => [
					'parameters' => [
						'srctbl' => 'sla',
						'srcfld1' => 'slaid',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_slaid'
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
			new CLabel(_('Service'), 'filter_serviceid'),
			(new CMultiSelect([
				'name' => 'filter_serviceid',
				'object_name' => 'service',
				'data' => $data['ms_service'],
				'multiple' => false,
				'popup' => [
					'parameters' => [
						'srctbl' => 'services',
						'srcfld1' => 'serviceid',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'filter_serviceid'
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
		]),
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			new CLabel(_('From'), 'filter_period_from'),
			new CFormField(
				(new CDateSelector('filter_period_from', $data['filter']['period_from']))
					->setDateFormat(DATE_FORMAT)
					->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
					->setAriaRequired()
			),
			new CLabel(_('To'), 'filter_period_to'),
			new CFormField(
				(new CDateSelector('filter_period_to', $data['filter']['period_to']))
					->setDateFormat(DATE_FORMAT)
					->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
					->setAriaRequired()
			)
		])
	]);

$form = (new CForm())
	->setId('slareport-list')
	->setName('slareport_list');

$slareport_list = (new CTableInfo());

if (!$data['services']) {
	if ($data['filter']['slaid'] === '' && $data['filter']['serviceid'] === '') {
		$slareport_list->setNoDataMessage(_('Select SLA to display SLA report.'));
	}
}
elseif (array_key_exists('sla', $data)) {
	if (count($data['services']) != 1) {
		$slareport_list = (new CTableInfo());
		$header = [
			make_sorting_header(_('Service'), 'name', $data['sort'], $data['sortorder'], $data['filter_url'])
				->addStyle('width: 14%'),
			(new CColHeader(_('SLO')))->setWidth('7%')
		];

		foreach ($data['periods'] as $period) {
			$header[] = CSlaHelper::getPeriodTag(
				$data['sla']['period'],
				$period['period_from'],
				$period['period_to'],
				$data['sla']['timezone']
			)->addClass('vertical');
		}

		$slareport_list->setHeader($header);

		foreach ($data['services'] as $serviceid => $service) {
			if ($data['can_edit']) {
				$name_element = (new CLink(($service['name']),
					$data['service_curl']->setArgument('filter_serviceid', $serviceid)
				));
			}
			else {
				$name_element = $service['name'];
			}

			$row = [
				$name_element,
				CSlaHelper::getSloTag($data['sla']['slo'])
			];

			if (array_key_exists('sli', $service)) {
				foreach ($service['sli'] as $sli) {
					$row[] = CSlaHelper::getSliTag($sli['sli'], $data['sla']['slo']);
				}
			}

			$slareport_list->addRow(new CRow($row));
		}
	}
	elseif (false != ($service = reset($data['services']))) {
		$slareport_list = (new CTableInfo())
			->setHeader([
				CSlaHelper::getPeriodNames()[$data['sla']['period']],
				_('SLO'),
				_('SLI'),
				_('Uptime'),
				_('Downtime'),
				_('Error budget'),
				_('Excluded downtimes')
			]);

		foreach ($data['periods'] as $period_key => $period) {
			$sli = $service['sli'][$period_key];

			foreach ($sli['excluded_downtimes'] as &$excluded_downtime) {
				$excluded_downtime = CSlaHelper::getExcludedDowntimeTag($excluded_downtime, $data['sla']['timezone']);
			}
			unset($excluded_downtime);

			$slareport_list->addRow([
				CSlaHelper::getPeriodTag(
					$data['sla']['period'],
					$period['period_from'],
					$period['period_to'],
					$data['sla']['timezone']
				),
				CSlaHelper::getSloTag($data['sla']['slo']),
				CSlaHelper::getSliTag($sli['sli'], $data['sla']['slo']),
				CSlaHelper::getUptimeTag($sli['uptime']),
				CSlaHelper::getDowntimeTag($sli['downtime']),
				CSlaHelper::getErrorBudgetTag($sli['error_budget']),
				$sli['excluded_downtimes']
			]);
		}
	}
};

$form->addItem([
	$slareport_list,
	$data['paging']
]);

(new CWidget())
	->setTitle(_('SLA report'))
	->addItem($filter)
	->addItem($form)
	->show();
