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


include('include/views/js/configuration.services.edit.js.php');

$service = $this->data['service'];

$widget = (new CWidget())->setTitle(_('Services'));

// create form
$servicesForm = (new CForm())
	->setName('servicesForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $this->data['form'])
	->addVar('parentid', $this->data['parentid'])
	->addVar('parentname', $this->data['parentname'])
	->addVar('triggerid', $this->data['triggerid']);
if (isset($this->data['service'])) {
	$servicesForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create form list
$servicesFormList = (new CFormList('servicesFormList'))
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $this->data['name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	);

// append parent link to form list
$parent_service_popup_options = [
	'pservices' => '1'
];
if ($this->data['service']['serviceid']) {
	$parent_service_popup_options['serviceid'] = $this->data['service']['serviceid'];
}
$servicesFormList->addRow((new CLabel(_('Parent service'), 'parent_name'))->setAsteriskMark(), [
	(new CTextBox('parent_name', $this->data['parentname'], true, 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('select_parent', _x('Change', 'verb')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.services",jQuery.extend('.
			CJs::encodeJson($parent_service_popup_options).
				',{parentid: this.form.parentid.value}), null, this);'
		)
]);

// append algorithm to form list
$servicesFormList->addRow(
	(new CLabel(_('Status calculation algorithm'), 'algorithm')),
	(new CComboBox('algorithm', $data['algorithm'], null, serviceAlgorithm()))
);

// append SLA to form list
$showslaCheckbox = (new CCheckBox('showsla'))->setChecked($this->data['showsla'] == SERVICE_SHOW_SLA_ON);
$goodslaTextBox = (new CTextBox('goodsla', $this->data['goodsla'], false, 8))->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
if (!$this->data['showsla']) {
	$goodslaTextBox->setAttribute('disabled', 'disabled');
}
$servicesFormList->addRow(_('Calculate SLA, acceptable SLA (in %)'), [
	$showslaCheckbox, (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), $goodslaTextBox
]);

// append trigger to form list
$servicesFormList->addRow(_('Trigger'), [
	(new CTextBox('trigger', $this->data['trigger'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('btn1', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.
			CJs::encodeJson([
				'srctbl' => 'triggers',
				'srcfld1' => 'triggerid',
				'srcfld2' => 'description',
				'dstfrm' => $servicesForm->getName(),
				'dstfld1' => 'triggerid',
				'dstfld2' => 'trigger',
				'real_hosts' => '1',
				'with_triggers' => '1'
			]).', null, this);'
		)
]);
$servicesFormList->addRow((new CLabel(_('Sort order (0->999)'), 'sortorder'))->setAsteriskMark(),
	(new CTextBox('sortorder', $this->data['sortorder'], false, 3))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		->setAriaRequired()
);

/*
 * Dependencies tab
 */
$servicesChildTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setId('service_children')
	->setHeader([_('Services'), _('Soft'), _('Trigger'), _('Action')]);
foreach ($this->data['children'] as $child) {
	$childrenLink = (new CLink($child['name'], 'services.php?form=1&serviceid='.$child['serviceid']))
		->setAttribute('target', '_blank');

	$servicesChildTable->addRow(
		(new CRow([
			[
				$childrenLink,
				new CVar('children['.$child['serviceid'].'][name]', $child['name']),
				new CVar('children['.$child['serviceid'].'][serviceid]', $child['serviceid']),
				new CVar('children['.$child['serviceid'].'][trigger]', $child['trigger'])
			],
			(new CCheckBox('children['.$child['serviceid'].'][soft]'))
				->setChecked(isset($child['soft']) && !empty($child['soft'])),
			!empty($child['trigger']) ? $child['trigger'] : '',
			(new CCol(
				(new CButton('remove', _('Remove')))
					->onClick('javascript: removeDependentChild(\''.$child['serviceid'].'\');')
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('children_'.$child['serviceid'])
	);
}
$servicesDependenciesFormList = new CFormList('servicesDependensiesFormList');

$dep_service_popup_options = [
	'cservices' => '1'
];
if ($this->data['service']['serviceid']) {
	$dep_service_popup_options['serviceid'] = $this->data['service']['serviceid'];
}

$servicesDependenciesFormList->addRow(
	_('Depends on'),
	(new CDiv([
		$servicesChildTable,
		(new CButton('add_child_service', _('Add')))
			->onClick('return PopUp("popup.services",jQuery.extend('.
				CJs::encodeJson($dep_service_popup_options).
					',{parentid: this.form.parentid.value}), null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Service times tab
 */
$servicesTimeFormList = new CFormList('servicesTimeFormList');
$servicesTimeTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Type'), _('Interval'), _('Note'), _('Action')]);

$i = 0;
foreach ($this->data['times'] as $serviceTime) {
	switch ($serviceTime['type']) {
		case SERVICE_TIME_TYPE_UPTIME:
			$type = (new CSpan(_('Uptime')))->addClass('enabled');
			$from = dowHrMinToStr($serviceTime['ts_from']);
			$to = dowHrMinToStr($serviceTime['ts_to'], true);
			break;
		case SERVICE_TIME_TYPE_DOWNTIME:
			$type = (new CSpan(_('Downtime')))->addClass('disabled');
			$from = dowHrMinToStr($serviceTime['ts_from']);
			$to = dowHrMinToStr($serviceTime['ts_to'], true);
			break;
		case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
			$type = (new CSpan(_('One-time downtime')))->addClass('disabled');
			$from = zbx_date2str(DATE_TIME_FORMAT, $serviceTime['ts_from']);
			$to = zbx_date2str(DATE_TIME_FORMAT, $serviceTime['ts_to']);
			break;
	}
	$row = new CRow([
		[
			$type,
			new CVar('times['.$i.'][type]', $serviceTime['type']),
			new CVar('times['.$i.'][ts_from]', $serviceTime['ts_from']),
			new CVar('times['.$i.'][ts_to]', $serviceTime['ts_to']),
			new CVar('times['.$i.'][note]', $serviceTime['note'])
		],
		$from.' - '.$to,
		htmlspecialchars($serviceTime['note']),
		(new CCol(
			(new CButton('remove', _('Remove')))
				->onClick('javascript: removeTime(\''.$i.'\');')
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		))->addClass(ZBX_STYLE_NOWRAP)
	]);
	$row->setId('times_'.$i);
	$servicesTimeTable->addRow($row);
	$i++;
}
$servicesTimeFormList->addRow(_('Service times'),
	(new CDiv($servicesTimeTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// create service time table
$serviceTimeTable = (new CFormList())
	->addRow(
		(new CLabel(_('Period type'), 'new_service_time[type]')),
		(new CComboBox('new_service_time[type]', $data['new_service_time']['type'], 'submit()', [
			SERVICE_TIME_TYPE_UPTIME => _('Uptime'),
			SERVICE_TIME_TYPE_DOWNTIME => _('Downtime'),
			SERVICE_TIME_TYPE_ONETIME_DOWNTIME => _('One-time downtime')
		]))
	);

if ($this->data['new_service_time']['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
	// downtime since
	if (isset($_REQUEST['new_service_time']['from'])) {
		$fromYear = getRequest('new_service_time_from_year');
		$fromMonth = getRequest('new_service_time_from_month');
		$fromDay = getRequest('new_service_time_from_day');
		$fromHours = getRequest('new_service_time_from_hour');
		$fromMinutes = getRequest('new_service_time_from_minute');
		$fromDate = [
			'y' => $fromYear,
			'm' => $fromMonth,
			'd' => $fromDay,
			'h' => $fromHours,
			'i' => $fromMinutes
		];
		$serviceTimeFrom = $fromYear.$fromMonth.$fromDay.$fromHours.$fromMinutes;
	}
	else {
		$downtimeSince = date(TIMESTAMP_FORMAT_ZERO_TIME);
		$fromDate = zbxDateToTime($downtimeSince);
		$serviceTimeFrom = $downtimeSince;
	}
	$servicesForm->addVar('new_service_time[from]', $serviceTimeFrom);

	// downtime till
	if (isset($_REQUEST['new_service_time']['to'])) {
		$toYear = getRequest('new_service_time_to_year');
		$toMonth = getRequest('new_service_time_to_month');
		$toDay = getRequest('new_service_time_to_day');
		$toHours = getRequest('new_service_time_to_hour');
		$toMinutes = getRequest('new_service_time_to_minute');
		$toDate = [
			'y' => $toYear,
			'm' => $toMonth,
			'd' => $toDay,
			'h' => $toHours,
			'i' => $toMinutes
		];
		$serviceTimeTo = $toYear.$toMonth.$toDay.$toHours.$toMinutes;
	}
	else {
		$downtimeTill = date(TIMESTAMP_FORMAT_ZERO_TIME, time() + SEC_PER_DAY);
		$toDate = zbxDateToTime($downtimeTill);
		$serviceTimeTo = $downtimeTill;
	}
	$servicesForm->addVar('new_service_time[to]', $serviceTimeTo);

	$serviceTimeTable
		->addRow(
			_('Note'),
			(new CTextBox('new_service_time[note]'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('short description'))
		)
		->addRow(
			(new CLabel(_('From'), 'new_service_time_from'))->setAsteriskMark(),
			(new CDiv(createDateSelector('new_service_time_from', $fromDate)))
		)
		->addRow(
			(new CLabel(_('Till'), 'new_service_time_to'))->setAsteriskMark(),
			(new CDiv(createDateSelector('new_service_time_to', $toDate)))
		);
}
else {
	$weekFromComboBox = new CComboBox('new_service_time[from_week]', isset($_REQUEST['new_service_time']['from_week'])
			? $_REQUEST['new_service_time']['from_week'] : 0);
	$weekToComboBox = new CComboBox('new_service_time[to_week]', isset($_REQUEST['new_service_time']['from_week'])
			? $_REQUEST['new_service_time']['to_week'] : 0);
	for ($dow = 0; $dow < 7; $dow++) {
		$weekFromComboBox->addItem($dow, getDayOfWeekCaption($dow));
		$weekToComboBox->addItem($dow, getDayOfWeekCaption($dow));
	}
	$timeFromHourTextBox = (new CTextBox('new_service_time[from_hour]', isset($_REQUEST['new_service_time']['from_hour'])
			? $_REQUEST['new_service_time']['from_hour'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAriaRequired()
		->setAttribute('placeholder', _('hh'));
	$timeFromMinuteTextBox = (new CTextBox('new_service_time[from_minute]', isset($_REQUEST['new_service_time']['from_minute'])
			? $_REQUEST['new_service_time']['from_minute'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAriaRequired()
		->setAttribute('placeholder', _('mm'));
	$timeToHourTextBox = (new CTextBox('new_service_time[to_hour]', isset($_REQUEST['new_service_time']['to_hour'])
			? $_REQUEST['new_service_time']['to_hour'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAriaRequired()
		->setAttribute('placeholder', _('hh'));
	$timeToMinuteTextBox = (new CTextBox('new_service_time[to_minute]', isset($_REQUEST['new_service_time']['to_minute'])
			? $_REQUEST['new_service_time']['to_minute'] : '', false, 2))
		->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
		->setAriaRequired()
		->setAttribute('placeholder', _('mm'));

	$serviceTimeTable
		->addRow(
			(new CLabel(_('From'), 'new_service_time_from'))->setAsteriskMark(),
			(new CDiv([
					$weekFromComboBox,
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					_('Time'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$timeFromHourTextBox,
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					':',
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$timeFromMinuteTextBox
			]))
		)
		->addRow(
			(new CLabel(_('Till'), 'new_service_time_to'))->setAsteriskMark(),
			(new CDiv([
				$weekToComboBox,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				_('Time'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$timeToHourTextBox,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				':',
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$timeToMinuteTextBox
			]))
		);
	$servicesForm->addVar('new_service_time[note]', '');
}

$servicesTimeFormList->addRow(_('New service time'),
	(new CDiv([
		$serviceTimeTable,
		(new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$servicesForm->getName().'", "add_service_time", "1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Append tabs to form
 */
$servicesTab = new CTabView();
if (!$this->data['form_refresh']) {
	$servicesTab->setSelected(0);
}
$servicesTab
	->addTab('servicesTab', _('Service'), $servicesFormList)
	->addTab('servicesDependenciesTab', _('Dependencies'), $servicesDependenciesFormList)
	->addTab('servicesTimeTab', _('Time'), $servicesTimeFormList);

// append buttons to form
if ($service['serviceid']) {
	$buttons = [new CButtonCancel()];
	if (!$service['dependencies']) {
		array_unshift($buttons, new CButtonDelete(
			'Delete selected service?',
			url_param('form').url_param('serviceid').'&saction=1'
		));
	}

	$servicesTab->setFooter(makeFormFooter(
		(new CSubmit('update', _('Update')))->onClick('javascript: document.forms[0].action += \'?saction=1\';'),
		$buttons
	));
}
else {
	$servicesTab->setFooter(makeFormFooter(
		(new CSubmit('add', _('Add')))->onClick('javascript: document.forms[0].action += \'?saction=1\';'),
		[new CButtonCancel()]
	));
}

$servicesForm->addItem($servicesTab);

// append form to widget
$widget->addItem($servicesForm);

return $widget;
