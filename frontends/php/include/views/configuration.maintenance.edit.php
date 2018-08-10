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


$widget = (new CWidget())->setTitle(_('Maintenance periods'));

// create form
$maintenanceForm = (new CForm())
	->setName('maintenanceForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $this->data['form']);
if (isset($this->data['maintenanceid'])) {
	$maintenanceForm->addVar('maintenanceid', $this->data['maintenanceid']);
}

/*
 * Maintenance tab
 */
$maintenanceFormList = (new CFormList('maintenanceFormList'))
	->addRow(
		(new CLabel(_('Name'), 'mname'))->setAsteriskMark(),
		(new CTextBox('mname', $this->data['mname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('maintenances', 'name'))
	)
	->addRow((new CLabel(_('Maintenance type'), 'maintenance_type')),
		(new CRadioButtonList('maintenance_type', (int) $data['maintenance_type']))
			->addValue(_('With data collection'), MAINTENANCE_TYPE_NORMAL)
			->addValue(_('No data collection'), MAINTENANCE_TYPE_NODATA)
			->setModern(true)
	)
	// Show date and time in shorter format without seconds.
	->addRow((new CLabel(_('Active since'), 'active_since'))->setAsteriskMark(),
		(new CDateSelector('active_since', $data['active_since']))
			->setDateFormat(ZBX_DATE_TIME)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Active till'), 'active_till'))->setAsteriskMark(),
		(new CDateSelector('active_till', $data['active_till']))
			->setDateFormat(ZBX_DATE_TIME)
			->setAriaRequired()
	);

$maintenanceFormList->addRow(_('Description'),
	(new CTextArea('description', $this->data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

/*
 * Maintenance period tab
 */
$maintenancePeriodFormList = new CFormList('maintenancePeriodFormList');
$maintenance_period_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Period type'), _('Schedule'), _('Period'), _('Action')])
	->setId('maintenance_periods')
	->setAriaRequired();

foreach ($data['timeperiods'] as $id => $timeperiod) {
	$maintenance_period_table->addRow([
		(new CCol(timeperiod_type2str($timeperiod['timeperiod_type'])))->addClass(ZBX_STYLE_NOWRAP),
		($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME)
			? $timeperiod['start_date']
			: shedule2str($timeperiod),
		(new CCol(zbx_date2age(0, $timeperiod['period'])))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol(
			new CHorList([
				(new CSimpleButton(_('Edit')))
					->onClick('javascript: submitFormWithParam('.
						'"'.$maintenanceForm->getName().'", "edit_timeperiodid['.$id.']", "1"'.
					');')
					->addClass(ZBX_STYLE_BTN_LINK),
				(new CSimpleButton(_('Remove')))
					->onClick('javascript: submitFormWithParam('.
						'"'.$maintenanceForm->getName().'", "del_timeperiodid['.$id.']", "1"'.
					');')
					->addClass(ZBX_STYLE_BTN_LINK)
			])
		))->addClass(ZBX_STYLE_NOWRAP)
	]);
	if (isset($timeperiod['timeperiodid'])) {
		$maintenanceForm->addVar('timeperiods['.$id.'][timeperiodid]', $timeperiod['timeperiodid']);
	}
	$maintenanceForm
		->addVar('timeperiods['.$id.'][timeperiod_type]', $timeperiod['timeperiod_type'])
		->addVar('timeperiods['.$id.'][every]', $timeperiod['every'])
		->addVar('timeperiods['.$id.'][month]', $timeperiod['month'])
		->addVar('timeperiods['.$id.'][dayofweek]', $timeperiod['dayofweek'])
		->addVar('timeperiods['.$id.'][day]', $timeperiod['day'])
		->addVar('timeperiods['.$id.'][start_time]', $timeperiod['start_time'])
		->addVar('timeperiods['.$id.'][start_date]', $timeperiod['start_date'])
		->addVar('timeperiods['.$id.'][period]', $timeperiod['period']);
}

$periodsDiv = (new CDiv($maintenance_period_table))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;');

if (!isset($_REQUEST['new_timeperiod'])) {
	$periodsDiv->addItem(
		(new CSimpleButton(_('New')))
			->onClick('javascript: submitFormWithParam("'.$maintenanceForm->getName().'", "new_timeperiod", "1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	);
}
$maintenancePeriodFormList->addRow(
	(new CLabel(_('Periods'), $maintenance_period_table->getId()))->setAsteriskMark(), $periodsDiv
);

if ($data['new_timeperiod']) {
	if (is_array($data['new_timeperiod']) && array_key_exists('id', $data['new_timeperiod'])) {
		$save_label = _('Update');
	}
	else {
		$save_label = _('Add');
	}

	$maintenancePeriodFormList->addRow(_('Maintenance period'),
		(new CDiv([
			getTimeperiodForm($data),
			new CHorList([
				(new CSimpleButton($save_label))
					->onClick('javascript: submitFormWithParam("'.$maintenanceForm->getName().'", "add_timeperiod", "1");')
					->addClass(ZBX_STYLE_BTN_LINK),
				(new CSimpleButton(_('Cancel')))
					->onClick('javascript: submitFormWithParam("'.$maintenanceForm->getName().'", "cancel_new_timeperiod", "1");')
					->addClass(ZBX_STYLE_BTN_LINK)
			])
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

// Hosts and groups tab.
$hostsAndGroupsFormList = (new CFormList('hostsAndGroupsFormList'))
	->addRow('',
		(new CLabel(_('At least one host or host group must be selected.')))->setAsteriskMark()
	)
	->addRow(new CLabel(_('Hosts in maintenance'), 'hostids__ms'),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $data['hosts_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $maintenanceForm->getName(),
					'dstfld1' => 'hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Groups in maintenance'), 'groupids__ms'),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $maintenanceForm->getName(),
					'dstfld1' => 'groupids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append tabs to form.
$maintenanceTab = (new CTabView())
	->addTab('maintenanceTab', _('Maintenance'), $maintenanceFormList)
	->addTab('periodsTab', _('Periods'), $maintenancePeriodFormList)
	->addTab('hostTab', _('Hosts & Groups'), $hostsAndGroupsFormList);
if (!$this->data['form_refresh']) {
	$maintenanceTab->setSelected(0);
}

// append buttons to form
if (isset($this->data['maintenanceid'])) {
	$maintenanceTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete maintenance period?'), url_param('form').url_param('maintenanceid')),
			new CButtonCancel()
		]
	));
}
else {
	$maintenanceTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$maintenanceForm->addItem($maintenanceTab);

$widget->addItem($maintenanceForm);

return $widget;
