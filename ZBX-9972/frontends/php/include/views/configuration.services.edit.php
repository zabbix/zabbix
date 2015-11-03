<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


include('include/views/js/configuration.services.edit.js.php');

$service = $this->data['service'];

$widget = (new CWidget())->setTitle(_('IT services'));

// create form
$services_form = (new CForm())
	->setName('servicesForm')
	->addVar('form', $this->data['form'])
	->addVar('parentid', $this->data['parentid'])
	->addVar('parentname', $this->data['parentname'])
	->addVar('triggerid', $this->data['triggerid']);
if (isset($this->data['service'])) {
	$services_form->addVar('serviceid', $this->data['service']['serviceid']);
}

// create form list
$services_form_list = (new CFormList('servicesFormList'))
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// append parent link to form list
$services_form_list->addRow(_('Parent service'), [
	(new CTextBox('parent_name', $this->data['parentname'], true, 128))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('select_parent', _('Change')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick(
			"javascript: openWinCentered('services.php?pservices=1".url_param('serviceid')."', ".
				"'ZBX_Services_List', 740, 420, 'scrollbars=1, toolbar=0, menubar=0, resizable=1, dialog=0');")
]);

// append algorithm to form list
$services_form_list->addRow(_('Status calculation algorithm'),
	new CComboBox('algorithm', $this->data['algorithm'], null, serviceAlgorythm())
);

// append SLA to form list
$showsla_checkbox = (new CCheckBox('showsla'))->setChecked($this->data['showsla'] == 1);
$goodsla_text_box = (new CTextBox('goodsla', (float)$this->data['goodsla'], false, 7))->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
if (!$this->data['showsla']) {
	$goodsla_text_box->setAttribute('disabled', 'disabled');
}
$services_form_list->addRow(_('Calculate SLA, acceptable SLA (in %)'), [$showsla_checkbox, $goodsla_text_box]);

// append trigger to form list
$services_form_list->addRow(_('Trigger'), [
	(new CTextBox('trigger', $this->data['trigger'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('btn1', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.php?'.
			'dstfrm='.$services_form->getName().
			'&dstfld1=triggerid'.
			'&dstfld2=trigger'.
			'&srctbl=triggers'.
			'&srcfld1=triggerid'.
			'&srcfld2=description'.
			'&real_hosts=1'.
			'&with_triggers=1");'
		)
]);
$services_form_list->addRow(_('Sort order (0->999)'), (new CTextBox('sortorder', $this->data['sortorder'], false, 3))
	->setWidth(ZBX_TEXTAREA_TINY_WIDTH));

/*
 * Dependencies tab
 */
$services_child_table = (new CTable())
	->setNoDataMessage(_('No dependencies defined.'))
	->setAttribute('style', 'width: 100%;')
	->setId('service_children')
	->setHeader([_('Services'), _('Soft'), _('Trigger'), _('Action')]);
foreach ($this->data['children'] as $child) {
	$children_link = (new CLink($child['name'], 'services.php?form=1&serviceid='.$child['serviceid']))
		->setAttribute('target', '_blank');

	$services_child_table->addRow(
		(new CRow([
			[
				$children_link,
				new CVar('children['.$child['serviceid'].'][name]', $child['name']),
				new CVar('children['.$child['serviceid'].'][serviceid]', $child['serviceid'])
			],
			(new CCheckBox('children['.$child['serviceid'].'][soft]'))
				->setChecked(isset($child['soft']) && !empty($child['soft'])),
			!empty($child['trigger']) ? $child['trigger'] : '',
			(new CCol(
				(new CButton('remove', _('Remove')))
					->onClick('javascript: removeDependentChild(\''.$child['serviceid'].'\');')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('children_'.$child['serviceid'])
	);
}
$services_dependencies_form_list = new CFormList('servicesDependensiesFormList');
$services_dependencies_form_list->addRow(
	_('Depends on'),
	(new CDiv([
		$services_child_table,
		(new CButton('add_child_service', _('Add')))
			->onClick("javascript: openWinCentered('services.php?cservices=1".url_param('serviceid')."', 'ZBX_Services_List', 640, 520, 'scrollbars=1, toolbar=0, menubar=0, resizable=0');")
			->addClass(ZBX_STYLE_BTN_LINK)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Service times tab
 */
$services_time_form_list = new CFormList('servicesTimeFormList');
$services_time_table = (new CTable())
	->setNoDataMessage(_('No times defined. Work 24x7.'))
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Type'), _('Interval'), _('Note'), _('Action')]);

$i = 0;
foreach ($this->data['times'] as $service_time) {
	switch ($service_time['type']) {
		case SERVICE_TIME_TYPE_UPTIME:
			$type = (new CSpan(_('Uptime')))->addClass('enabled');
			$from = dowHrMinToStr($service_time['ts_from']);
			$to = dowHrMinToStr($service_time['ts_to'], true);
			break;
		case SERVICE_TIME_TYPE_DOWNTIME:
			$type = (new CSpan(_('Downtime')))->addClass('disabled');
			$from = dowHrMinToStr($service_time['ts_from']);
			$to = dowHrMinToStr($service_time['ts_to'], true);
			break;
		case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
			$type = (new CSpan(_('One-time downtime')))->addClass('disabled');
			$from = zbx_date2str(DATE_TIME_FORMAT, $service_time['ts_from']);
			$to = zbx_date2str(DATE_TIME_FORMAT, $service_time['ts_to']);
			break;
	}
	$row = new CRow([
		[
			$type,
			new CVar('times['.$i.'][type]', $service_time['type']),
			new CVar('times['.$i.'][ts_from]', $service_time['ts_from']),
			new CVar('times['.$i.'][ts_to]', $service_time['ts_to']),
			new CVar('times['.$i.'][note]', $service_time['note'])
		],
		$from.' - '.$to,
		htmlspecialchars($service_time['note']),
		(new CCol(
			(new CButton('remove', _('Remove')))
				->onClick('javascript: removeTime(\''.$i.'\');')
				->addClass(ZBX_STYLE_BTN_LINK)
		))->addClass(ZBX_STYLE_NOWRAP)
	]);
	$row->setId('times_'.$i);
	$services_time_table->addRow($row);
	$i++;
}
$services_time_form_list->addRow(_('Service times'),
	(new CDiv($services_time_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// create service time table
$service_time_table = (new CTable())
	->addRow([
		_('Period type'),
		new CComboBox('new_service_time[type]', $this->data['new_service_time']['type'], 'submit()', [
			SERVICE_TIME_TYPE_UPTIME => _('Uptime'),
			SERVICE_TIME_TYPE_DOWNTIME => _('Downtime'),
			SERVICE_TIME_TYPE_ONETIME_DOWNTIME => _('One-time downtime')
		])
	]);

if ($this->data['new_service_time']['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
	// downtime since
	if (isset($_REQUEST['new_service_time']['from'])) {
		$from_year = getRequest('new_service_time_from_year');
		$from_month = getRequest('new_service_time_from_month');
		$from_day = getRequest('new_service_time_from_day');
		$from_hours = getRequest('new_service_time_from_hour');
		$from_minutes = getRequest('new_service_time_from_minute');
		$from_date = [
			'y' => $from_year,
			'm' => $from_month,
			'd' => $from_day,
			'h' => $from_hours,
			'i' => $from_minutes
		];
		$service_time_from = $from_year.$from_month.$from_day.$from_hours.$from_minutes;
	}
	else {
		$downtime_since = date(TIMESTAMP_FORMAT_ZERO_TIME);
		$from_date = zbxDateToTime($downtime_since);
		$service_time_from = $downtime_since;
	}
	$services_form->addVar('new_service_time[from]', $service_time_from);

	// downtime till
	if (isset($_REQUEST['new_service_time']['to'])) {
		$to_year = getRequest('new_service_time_to_year');
		$to_month = getRequest('new_service_time_to_month');
		$to_day = getRequest('new_service_time_to_day');
		$to_hours = getRequest('new_service_time_to_hour');
		$to_minutes = getRequest('new_service_time_to_minute');
		$to_date = [
			'y' => $to_year,
			'm' => $to_month,
			'd' => $to_day,
			'h' => $to_hours,
			'i' => $to_minutes
		];
		$service_time_to = $to_year.$to_month.$to_day.$to_hours.$to_minutes;
	}
	else {
		$downtime_till = date(TIMESTAMP_FORMAT_ZERO_TIME, time() + SEC_PER_DAY);
		$to_date = zbxDateToTime($downtime_till);
		$service_time_to = $downtime_till;
	}
	$services_form->addVar('new_service_time[to]', $service_time_to);

	$service_time_table
		->addRow([
			_('Note'),
			(new CTextBox('new_service_time[note]'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('short description'))
		])
		->addRow([_('From'), createDateSelector('new_service_time_from', $from_date, 'new_service_time_to')])
		->addRow([_('Till'), createDateSelector('new_service_time_to', $to_date, 'new_service_time_from')]);
}
else {
	$week_from_combo_box = new CComboBox('new_service_time[from_week]', isset($_REQUEST['new_service_time']['from_week'])
			? $_REQUEST['new_service_time']['from_week'] : 0);
	$week_to_combo_box = new CComboBox('new_service_time[to_week]', isset($_REQUEST['new_service_time']['from_week'])
			? $_REQUEST['new_service_time']['to_week'] : 0);
	for ($dow = 0; $dow < 7; $dow++) {
		$week_from_combo_box->addItem($dow, getDayOfWeekCaption($dow));
		$week_to_combo_box->addItem($dow, getDayOfWeekCaption($dow));
	}
	$time_from_hour_text_box = (new CTextBox('new_service_time[from_hour]', isset($_REQUEST['new_service_time']['from_hour'])
			? $_REQUEST['new_service_time']['from_hour'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAttribute('placeholder', _('hh'));
	$time_from_minute_text_box = (new CTextBox('new_service_time[from_minute]', isset($_REQUEST['new_service_time']['from_minute'])
			? $_REQUEST['new_service_time']['from_minute'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAttribute('placeholder', _('mm'));
	$time_to_hour_text_box = (new CTextBox('new_service_time[to_hour]', isset($_REQUEST['new_service_time']['to_hour'])
			? $_REQUEST['new_service_time']['to_hour'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAttribute('placeholder', _('hh'));
	$time_to_minute_text_box = (new CTextBox('new_service_time[to_minute]', isset($_REQUEST['new_service_time']['to_minute'])
			? $_REQUEST['new_service_time']['to_minute'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAttribute('placeholder', _('mm'));

	$service_time_table->addRow([
		_('From'),
		[
			$week_from_combo_box,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('Time'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$time_from_hour_text_box,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			':',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$time_from_minute_text_box
		]
	]);
	$service_time_table->addRow([
		_('Till'),
		[
			$week_to_combo_box,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('Time'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$time_to_hour_text_box,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			':',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$time_to_minute_text_box
		]
	]);
	$services_form->addVar('new_service_time[note]', '');
}

$services_time_form_list->addRow(_('New service time'),
	(new CDiv([
		$service_time_table,
		(new CButton('add_service_time', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Append tabs to form
 */
$services_tab = new CTabView();
if (!$this->data['form_refresh']) {
	$services_tab->setSelected(0);
}
$services_tab
	->addTab('servicesTab', _('Service'), $services_form_list)
	->addTab('servicesDependenciesTab', _('Dependencies'), $services_dependencies_form_list)
	->addTab('servicesTimeTab', _('Time'), $services_time_form_list);

// append buttons to form
if ($service['serviceid']) {
	$buttons = [new CButtonCancel()];
	if (!$service['dependencies']) {
		array_unshift($buttons, new CButtonDelete(
			'Delete selected service?',
			url_param('form').url_param('serviceid').'&saction=1'
		));
	}

	$services_tab->setFooter(makeFormFooter(
		(new CSubmit('update', _('Update')))->onClick('javascript: document.forms[0].action += \'?saction=1\';'),
		$buttons
	));
}
else {
	$services_tab->setFooter(makeFormFooter(
		(new CSubmit('add', _('Add')))->onClick('javascript: document.forms[0].action += \'?saction=1\';'),
		[new CButtonCancel()]
	));
}

$services_form->addItem($services_tab);

// append form to widget
$widget->addItem($services_form);

return $widget;
