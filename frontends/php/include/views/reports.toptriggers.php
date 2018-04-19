<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/js/reports.toptriggers.js.php';

$filterForm = (new CFilter('web.toptriggers.filter.state'))
	->addVar('filter_from', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_from']))
	->addVar('filter_till', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_till']));

// severities
$severity_columns = [0 => [], 1 => []];

foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
	$severity_columns[$severity % 2][] = (new CCheckBox('severities['.$severity.']'))
		->setLabel(getSeverityName($severity, $this->data['config']))
		->setChecked(in_array($severity, $this->data['filter']['severities']));
}

$filterColumn1 = (new CFormList())
	->addRow(_('Host groups'),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['multiSelectHostGroupData'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $filterForm->getName(),
					'dstfld1' => 'groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Hosts'),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $data['multiSelectHostData'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $filterForm->getName(),
					'dstfld1' => 'hostids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Severity'),
		(new CTable())
			->addRow($severity_columns[0])
			->addRow($severity_columns[1])
	);

$filterColumn2 = (new CFormList())
	->addRow(_('From'), createDateSelector('filter_from', $this->data['filter']['filter_from']))
	->addRow(_('Till'), createDateSelector('filter_till', $this->data['filter']['filter_till']))
	->addRow(null, [
		new CHorList([
			(new CButton(null, _('Today')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_TODAY.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Yesterday')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_YESTERDAY.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Current week')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_CURRENT_WEEK.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Current month')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_CURRENT_MONTH.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Current year')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_CURRENT_YEAR.');')
				->addClass(ZBX_STYLE_BTN_LINK)
		]),
		new CHorList([
			(new CButton(null, _('Last week')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_LAST_WEEK.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Last month')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_LAST_MONTH.');')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton(null, _('Last year')))
				->onClick('javascript: setPeriod('.REPORT_PERIOD_LAST_YEAR.');')
				->addClass(ZBX_STYLE_BTN_LINK)
		])
	]);

$filterForm
	->addColumn($filterColumn1)
	->addColumn($filterColumn2);

// table
$table = (new CTableInfo())->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Number of status changes')]);

foreach ($this->data['triggers'] as $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];

	$hostName = (new CLinkAction($trigger['hosts'][0]['name']))
		->setMenuPopup(CMenuPopupHelper::getHost($this->data['hosts'][$hostId], $this->data['scripts'][$hostId]));
	if ($this->data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	$triggerDescription = (new CLinkAction($trigger['description']))
		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	$table->addRow([
		$hostName,
		$triggerDescription,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$trigger['cnt_event']
	]);
}

return (new CWidget())
	->setTitle(_('100 busiest triggers'))
	->addItem($filterForm)
	->addItem($table);
