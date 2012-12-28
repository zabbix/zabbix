<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$maintenanceWidget = new CWidget();
$maintenanceWidget->addPageHeader(_('CONFIGURATION OF MAINTENANCE PERIODS'));

// create form
$maintenanceForm = new CForm();
$maintenanceForm->setName('maintenanceForm');
$maintenanceForm->addVar('form', $this->data['form']);
if (isset($this->data['maintenanceid'])) {
	$maintenanceForm->addVar('maintenanceid', $this->data['maintenanceid']);
}

/*
 * Maintenance tab
 */
$maintenanceFormList = new CFormList('maintenanceFormList');
$nameTextBox = new CTextBox('mname', $this->data['mname'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$maintenanceFormList->addRow(_('Name'), $nameTextBox);
$typeComboBox = new CComboBox('maintenance_type', $this->data['maintenance_type']);
$typeComboBox->addItem(MAINTENANCE_TYPE_NORMAL, _('With data collection'));
$typeComboBox->addItem(MAINTENANCE_TYPE_NODATA, _('No data collection'));
$maintenanceFormList->addRow(_('Maintenance type'), $typeComboBox);

$calendarIcon = new CImg('images/general/bar/cal.gif', 'calendar', 16, 12, 'pointer');
$calendarIcon->addAction('onclick', 'javascript: var pos = getPosition(this); pos.top += 10; pos.left += 16; CLNDR["active_since_calendar"].clndr.clndrshow(pos.top, pos.left); CLNDR["active_till_calendar"].clndr.clndrhide();');

if (isset($_REQUEST['active_since'])) {
	$year = get_request('active_since_year');
	$month = get_request('active_since_month');
	$day = get_request('active_since_day');
	$hours = get_request('active_since_hour');
	$minutes = get_request('active_since_minute');
}
elseif ($this->data['active_since'] > 0) {
	$year = date('Y', $this->data['active_since']);
	$month = date('m', $this->data['active_since']);
	$day = date('d', $this->data['active_since']);
	$hours = date('H', $this->data['active_since']);
	$minutes = date('i', $this->data['active_since']);
}
else {
	$year = '';
	$month = '';
	$day = '';
	$hours = '';
	$minutes = '';
}

$maintenanceForm->addVar('active_since', $year.$month.$day.$hours.$minutes);

$maintenanceSinceDay = new CNumericBox('active_since_day', $day, 2);
$maintenanceSinceDay->setAttribute('placeholder', _('dd'));
$maintenanceSinceMonth = new CNumericBox('active_since_month', $month, 2);
$maintenanceSinceMonth->setAttribute('placeholder', _('mm'));
$maintenanceSinceYear = new CNumericBox('active_since_year', $year, 4);
$maintenanceSinceYear->setAttribute('placeholder', _('yyyy'));
$maintenanceSinceHour = new CNumericBox('active_since_hour', $hours, 2);
$maintenanceSinceHour->setAttribute('placeholder', _('hh'));
$maintenanceSinceMinute = new CNumericBox('active_since_minute', $minutes, 2);
$maintenanceSinceMinute->setAttribute('placeholder', _('mm'));

$maintenanceFormList->addRow(_('Active since'), array($maintenanceSinceDay, '/', $maintenanceSinceMonth, '/', $maintenanceSinceYear, SPACE, $maintenanceSinceHour, ':', $maintenanceSinceMinute, $calendarIcon));

zbx_add_post_js('create_calendar(null, ["active_since_day", "active_since_month", "active_since_year", "active_since_hour", "active_since_minute"], "active_since_calendar", "active_since");');

$calendarIcon->addAction('onclick', 'javascript: var pos = getPosition(this); pos.top += 10; pos.left += 16; CLNDR["active_till_calendar"].clndr.clndrshow(pos.top, pos.left); CLNDR["active_since_calendar"].clndr.clndrhide();');

if (isset($_REQUEST['active_till'])) {
	$year = get_request('active_till_year');
	$month = get_request('active_till_month');
	$day = get_request('active_till_day');
	$hours = get_request('active_till_hour');
	$minutes = get_request('active_till_minute');
}
elseif ($this->data['active_till'] > 0) {
	$year = date('Y', $this->data['active_till']);
	$month = date('m', $this->data['active_till']);
	$day = date('d', $this->data['active_till']);
	$hours = date('H', $this->data['active_till']);
	$minutes = date('i', $this->data['active_till']);
}
else {
	$year = '';
	$month = '';
	$day = '';
	$hours = '';
	$minutes = '';
}

$maintenanceForm->addVar('active_till', $year.$month.$day.$hours.$minutes);

$maintenanceTillDay = new CNumericBox('active_till_day', $day, 2);
$maintenanceTillDay->setAttribute('placeholder', _('dd'));
$maintenanceTillMonth = new CNumericBox('active_till_month', $month, 2);
$maintenanceTillMonth->setAttribute('placeholder', _('mm'));
$maintenanceTillYear = new CNumericBox('active_till_year', $year, 4);
$maintenanceTillYear->setAttribute('placeholder', _('yyyy'));
$maintenanceTillHour = new CNumericBox('active_till_hour', $hours, 2);
$maintenanceTillHour->setAttribute('placeholder', _('hh'));
$maintenanceTillMinute = new CNumericBox('active_till_minute', $minutes, 2);
$maintenanceTillMinute->setAttribute('placeholder', _('mm'));

$maintenanceFormList->addRow(_('Active till'), array($maintenanceTillDay, '/', $maintenanceTillMonth, '/', $maintenanceTillYear, SPACE, $maintenanceTillHour, ':', $maintenanceTillMinute, $calendarIcon));
zbx_add_post_js('create_calendar(null, ["active_till_day", "active_till_month", "active_till_year", "active_till_hour", "active_till_minute"], "active_till_calendar", "active_till");');

$maintenanceFormList->addRow(_('Description'), new CTextArea('description', $this->data['description']));

/*
 * Maintenance period tab
 */
$maintenancePeriodFormList = new CFormList('maintenancePeriodFormList');
$maintenancePeriodTable = new CTable(_('No maintenance period defined.'), 'formElementTable');
$maintenancePeriodTable->setHeader(array(
	_('Period type'),
	_('Schedule'),
	_('Period'),
	_('Action')
));

foreach ($this->data['timeperiods'] as $id => $timeperiod) {
	$maintenancePeriodTable->addRow(array(
		timeperiod_type2str($timeperiod['timeperiod_type']),
		new CCol(shedule2str($timeperiod), 'wraptext'),
		zbx_date2age(0, $timeperiod['period']),
		array(
			new CSubmit('edit_timeperiodid['.$id.']', _('Edit'), null, 'link_menu'),
			SPACE.SPACE,
			new CSubmit('del_timeperiodid['.$id.']', _('Remove'), null, 'link_menu')
		)
	));
	if (isset($timeperiod['timeperiodid'])) {
		$maintenanceForm->addVar('timeperiods['.$id.'][timeperiodid]', $timeperiod['timeperiodid']);
	}
	$maintenanceForm->addVar('timeperiods['.$id.'][timeperiod_type]', $timeperiod['timeperiod_type']);
	$maintenanceForm->addVar('timeperiods['.$id.'][every]', $timeperiod['every']);
	$maintenanceForm->addVar('timeperiods['.$id.'][month]', $timeperiod['month']);
	$maintenanceForm->addVar('timeperiods['.$id.'][dayofweek]', $timeperiod['dayofweek']);
	$maintenanceForm->addVar('timeperiods['.$id.'][day]', $timeperiod['day']);
	$maintenanceForm->addVar('timeperiods['.$id.'][start_time]', $timeperiod['start_time']);
	$maintenanceForm->addVar('timeperiods['.$id.'][start_date]', $timeperiod['start_date']);
	$maintenanceForm->addVar('timeperiods['.$id.'][period]', $timeperiod['period']);
}

$periodsDiv = new CDiv($maintenancePeriodTable, 'objectgroup inlineblock border_dotted');
if (!isset($_REQUEST['new_timeperiod'])) {
	$periodsDiv->addItem(new CSubmit('new_timeperiod', _('New'), null, 'link_menu'));
}
$maintenancePeriodFormList->addRow(_('Periods'), $periodsDiv);

if (isset($_REQUEST['new_timeperiod'])) {
	if (is_array($_REQUEST['new_timeperiod']) && isset($_REQUEST['new_timeperiod']['id'])) {
		$saveLabel = _('Save');
	}
	else {
		$saveLabel = _('Add');
	}

	$footer = array(
		new CSubmit('add_timeperiod', $saveLabel, null, 'link_menu'),
		SPACE.SPACE,
		new CSubmit('cancel_new_timeperiod', _('Cancel'), null, 'link_menu')
	);

	$maintenancePeriodFormList->addRow(_('Maintenance period'),
		new CDiv(array(get_timeperiod_form(), $footer), 'objectgroup inlineblock border_dotted')
	);
}

/*
 * Hosts & groups tab
 */
$hostsAndGroupsFormList = new CFormList('hostsAndGroupsFormList');
$hostTweenBox = new CTweenBox($maintenanceForm, 'hostids', $this->data['hostids'], 10);
foreach ($this->data['hosts'] as $host) {
	$hostTweenBox->addItem($host['hostid'], $host['name']);
}
$groupsComboBox = new CComboBox('twb_groupid', $this->data['twb_groupid'], 'submit()');
foreach ($this->data['all_groups'] as $group) {
	$groupsComboBox->addItem($group['groupid'], $group['name']);
}
$hostTable = new CTable(null, 'formElementTable');
$hostTable->addRow($hostTweenBox->get(_('In maintenance'), array(_('Other hosts | Group').SPACE, $groupsComboBox)));
$hostsAndGroupsFormList->addRow(_('Hosts in maintenance'), $hostTable);

$groupTable = new CTable(null, 'formElementTable');
$groupTweenBox = new CTweenBox($maintenanceForm, 'groupids', $this->data['groupids'], 10);
foreach ($this->data['all_groups'] as $group) {
	$groupTweenBox->addItem($group['groupid'], $group['name']);
}
$groupTable->addRow($groupTweenBox->get(_('In maintenance'), _('Other groups')));

$hostsAndGroupsFormList->addRow(_('Groups in maintenance'), $groupTable);

// append tabs to form
$maintenanceTab = new CTabView(array('remember' => true));
if (!$this->data['form_refresh']) {
	$maintenanceTab->setSelected(0);
}
$maintenanceTab->addTab('maintenanceTab', _('Maintenance'), $maintenanceFormList);
$maintenanceTab->addTab('periodsTab', _('Periods'), $maintenancePeriodFormList);
$maintenanceTab->addTab('hostTab', _('Hosts & Groups'), $hostsAndGroupsFormList);
$maintenanceForm->addItem($maintenanceTab);

// append buttons to form
if (empty($this->data['maintenanceid'])) {
	$maintenanceForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(new CButtonCancel())
	));
}
else {
	$maintenanceForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete maintenance period?'), url_param('form').url_param('maintenanceid')),
			new CButtonCancel())
	));
}

$maintenanceWidget->addItem($maintenanceForm);

return $maintenanceWidget;
