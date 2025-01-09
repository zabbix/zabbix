<?php
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

$form_grid = new CFormGrid();

$user_multiselect = (new CMultiSelect([
	'name' => 'userid',
	'object_name' => 'users',
	'multiple' => false,
	'readonly' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) || !$data['allowed_edit'],
	'data' => $data['ms_user'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'users',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname',
			'dstfrm' => $data['form'],
			'dstfld1' => 'userid'
		]
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$dashboard_multiselect = (new CMultiSelect([
	'name' => 'dashboardid',
	'object_name' => 'dashboard',
	'multiple' => false,
	'readonly' => !$data['allowed_edit'],
	'data' => $data['ms_dashboard'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'dashboard',
			'srcfld1' => 'dashboardid',
			'dstfrm' => $data['form'],
			'dstfld1' => 'dashboardid'
		]
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid
	->addItem([
		(new CLabel(_('Owner'), 'userid_ms'))->setAsteriskMark(),
		new CFormField($user_multiselect)
	])
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], !$data['allowed_edit'], DB::getFieldLength('report', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('maxlength', DB::getFieldLength('report', 'name'))
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('Dashboard'), 'dashboardid_ms'))->setAsteriskMark(),
		new CFormField($dashboard_multiselect)
	])
	->addItem([
		new CLabel(_('Period'), 'period'),
		new CFormField(
			(new CRadioButtonList('period', (int) $data['period']))
				->addValue(_('Previous day'), ZBX_REPORT_PERIOD_DAY)
				->addValue(_('Previous week'), ZBX_REPORT_PERIOD_WEEK)
				->addValue(_('Previous month'), ZBX_REPORT_PERIOD_MONTH)
				->addValue(_('Previous year'), ZBX_REPORT_PERIOD_YEAR)
				->setReadonly(!$data['allowed_edit'])
				->setModern(true)
		)
	])
	->addItem([
		new CLabel(_('Cycle'), 'cycle'),
		new CFormField(
			(new CRadioButtonList('cycle', (int) $data['cycle']))
				->addValue(_('Daily'), ZBX_REPORT_CYCLE_DAILY)
				->addValue(_('Weekly'), ZBX_REPORT_CYCLE_WEEKLY)
				->addValue(_('Monthly'), ZBX_REPORT_CYCLE_MONTHLY)
				->addValue(_('Yearly'), ZBX_REPORT_CYCLE_YEARLY)
				->setReadonly(!$data['allowed_edit'])
				->setModern(true)
		)
	])
	->addItem([
		new CLabel(_('Start time'), 'hours'),
		new CFormField(
			(new CDiv([
				(new CNumericBox('hours', $data['hours'], 2))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					->setReadonly(!$data['allowed_edit']),
				' : ',
				(new CNumericBox('minutes', $data['minutes'], 2))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					->setReadonly(!$data['allowed_edit'])
			]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
		)
	]);

$show_weekdays = ($data['cycle'] == ZBX_REPORT_CYCLE_WEEKLY);

$weekdays = [];
foreach (range(1, 7) as $day) {
	$value = 1 << ($day - 1);
	$weekdays[] = [
		'label' => getDayOfWeekCaption($day),
		'value' => $value,
		'checked' => (bool) ($value & $data['weekdays'])
	];
}

$form_grid
	->addItem([
		(new CLabel(_('Repeat on'), 'weekdays'))
			->setId('weekdays-label')
			->setAsteriskMark()
			->addClass($show_weekdays ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBoxList('weekdays'))
				->setOptions($weekdays)
				->setVertical(true)
				->setColumns(3)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setReadonly(!$data['allowed_edit'])
		))
			->setId('weekdays')
			->addClass($show_weekdays ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		new CLabel(_('Start date'), 'active_since'),
		new CFormField(
			(new CDateSelector('active_since', $data['active_since']))
				->setDateFormat(ZBX_DATE)
				->setPlaceholder(_('YYYY-MM-DD'))
				->setReadonly(!$data['allowed_edit'])
		)
	])
	->addItem([
		new CLabel(_('End date'), 'active_till'),
		new CFormField(
			(new CDateSelector('active_till', $data['active_till']))
				->setDateFormat(ZBX_DATE)
				->setPlaceholder(_('YYYY-MM-DD'))
				->setReadonly(!$data['allowed_edit'])
		)
	])
	->addItem([
		new CLabel(_('Subject'), 'subject'),
		new CFormField(
			(new CTextBox('subject', $data['subject']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('media_type_message', 'subject'))
				->setReadonly(!$data['allowed_edit'])
		)
	])
	->addItem([
		new CLabel(_('Message'), 'message'),
		new CFormField(
			(new CTextArea('message', $data['message']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('report_param', 'value'))
				->setReadonly(!$data['allowed_edit'])
		)
	])
	->addItem([
		(new CLabel(_('Subscriptions'), 'subscriptions'))->setAsteriskMark(),
		new CFormField(new CPartial('scheduledreport.subscription', $data))
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxLength(DB::getFieldLength('report', 'description'))
				->setReadonly(!$data['allowed_edit'])
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_REPORT_STATUS_ENABLED))
				->setChecked($data['status'] == ZBX_REPORT_STATUS_ENABLED)
				->setUncheckedValue(ZBX_REPORT_STATUS_DISABLED)
				->setReadonly(!$data['allowed_edit'])
		)
	]);

if ($data['source'] === 'reports') {
	$test_button = (new CSimpleButton(_('Test')))
		->setId('test')
		->setEnabled($data['allowed_edit']);

	$cancel_button = (new CRedirectButton(_('Cancel'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'scheduledreport.list')
			->setArgument('page', CPagerHelper::loadPage('scheduledreport.list', null))
	))->setId('cancel');

	$buttons = [$test_button, $cancel_button];

	if ($data['reportid'] != 0) {
		$buttons = [
			(new CSimpleButton(_('Clone')))
				->setId('clone')
				->setEnabled($data['allowed_edit']),
			$test_button,
			(new CRedirectButton(_('Delete'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'scheduledreport.delete')
					->setArgument('reportids', [$data['reportid']])
					->setArgument(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('scheduledreport')),
				_('Delete selected scheduled report?')
			))
				->setId('delete')
				->setEnabled($data['allowed_edit']),
			$cancel_button
		];
	}

	$form_grid->addItem(
		(new CFormActions(
			($data['reportid'] != 0)
				? (new CSubmitButton(_('Update'), 'action', 'scheduledreport.update'))
					->setId('update')
					->setEnabled($data['allowed_edit'])
				: (new CSubmitButton(_('Add'), 'action', 'scheduledreport.create'))
					->setId('add')
					->setEnabled($data['allowed_edit']),
			$buttons
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	);
}

$form_grid->show();

$this->includeJsFile('scheduledreport.formgrid.js.php', [
	'user_multiselect' => $user_multiselect->getPostJS(),
	'dashboard_multiselect' => $dashboard_multiselect->getPostJS()
]);
