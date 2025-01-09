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
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->setId('maintenance-timeperiod-form')
	->setName('maintenance_timeperiod_form')
	->addVar('row_index', $data['row_index'])
	->addStyle('display: none;')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$weekly_days_options = [];
$monthly_days_options = [];

foreach (range(0, 6) as $day) {
	$value = 1 << $day;

	$weekly_days_options[] = [
		'label' => getDayOfWeekCaption($day + 1),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY
			&& ($value & $data['form']['dayofweek'])
	];

	$monthly_days_options[] = [
		'label' => getDayOfWeekCaption($day + 1),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY
			&& ($value & $data['form']['dayofweek'])
	];
}

$months_options = [];

foreach (range(0, 11) as $month) {
	$value = 1 << $month;

	$months_options[] = [
		'label' => getMonthCaption($month + 1),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY
			&& ($value & $data['form']['month'])
	];
}

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Period type'), 'timeperiod_type-focusable'),
		(new CFormField(
			(new CSelect('timeperiod_type'))
				->setFocusableElementId('timeperiod-type-focusable')
				->setValue($data['form']['timeperiod_type'])
				->addOptions(CSelect::createOptionsFromArray([
					TIMEPERIOD_TYPE_ONETIME => _('One time only'),
					TIMEPERIOD_TYPE_DAILY => _('Daily'),
					TIMEPERIOD_TYPE_WEEKLY => _('Weekly'),
					TIMEPERIOD_TYPE_MONTHLY => _('Monthly')
				]))
		))
	])
	->addItem([
		(new CLabel(_('Every day(s)'), 'every_day'))
			->addClass('js-every-day')
			->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('every_day',
				$data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY ? $data['form']['every'] : 1, 3
			))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		))->addClass('js-every-day')
	])
	->addItem([
		(new CLabel(_('Every week(s)'), 'every_week'))
			->addClass('js-every-week')
			->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('every_week',
				$data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY ? $data['form']['every'] : 1, 2
			))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		))->addClass('js-every-week')
	])
	->addItem([
		(new CLabel(_('Day of week'), 'weekly_days'))
			->addClass('js-weekly-days')
			->setAsteriskMark(),
		(new CFormField(
			(new CCheckBoxList('weekly_days'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($weekly_days_options)
				->setVertical()
				->setColumns(3)
		))->addClass('js-weekly-days')
	])
	->addItem([
		(new CLabel(_('Month'), 'months'))
			->addClass('js-months')
			->setAsteriskMark(),
		(new CFormField(
			(new CCheckBoxList('months'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($months_options)
				->setVertical()
				->setColumns(3)
		))->addClass('js-months')
	])
	->addItem([
		(new CLabel(_('Date'), 'month_date_type'))->addClass('js-month-date-type'),
		(new CFormField(
			(new CRadioButtonList('month_date_type', (int) $data['form']['month_date_type']))
				->addValue(_('Day of month'), 0)
				->addValue(_('Day of week'), 1)
				->setModern()
		))->addClass('js-month-date-type')
	])
	->addItem([
		(new CLabel(_('Day of week'), 'every-dow-focusable'))
			->addClass('js-every-dow')
			->setAsteriskMark(),
		(new CFormField(
			(new CSelect('every_dow'))
				->setFocusableElementId('every-dow-focusable')
				->addOptions(CSelect::createOptionsFromArray(CMaintenanceHelper::getTimePeriodEveryNames()))
				->setValue($data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY
						&& $data['form']['month_date_type'] == 1
					? $data['form']['every']
					: 1
				)
		))->addClass('js-every-dow')
	])
	->addItem(
		(new CFormField(
			(new CCheckBoxList('monthly_days'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($monthly_days_options)
				->setVertical()
				->setColumns(3)
		))->addClass('js-monthly-days')
	)
	->addItem([
		(new CLabel(_('Day of month'), 'day'))
			->addClass('js-day')
			->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('day', $data['form']['month_date_type'] == 0 ? $data['form']['day'] : 1, 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		))->addClass('js-day')
	])
	->addItem([
		(new CLabel(_('Date'), 'start_date'))
			->addClass('js-start-date')
			->setAsteriskMark(),
		(new CFormField(
			(new CDateSelector('start_date', $data['form']['start_date']))
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
		))->addClass('js-start-date')
	])
	->addItem([
		(new CLabel(_('At (hour:minute)'), 'hour'))->addClass('js-hour-minute'),
		(new CFormField([
			(new CNumericBox('hour', $data['form']['hour'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			' : ',
			(new CNumericBox('minute', $data['form']['minute'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		]))->addClass('js-hour-minute')
	])
	->addItem([
		(new CLabel(_('Maintenance period length'), 'period_days'))->setAsteriskMark(),
		(new CFormField([
			(new CDiv([
				(new CNumericBox('period_days', $data['form']['period_days'], 3))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				new CLabel(_('Days'), 'period_days'),
				(new CSelect('period_hours'))
					->setFocusableElementId('period-hours-focusable')
					->addOptions(CSelect::createOptionsFromArray(range(0, 23)))
					->setValue($data['form']['period_hours']),
				new CLabel(_('Hours'), 'period-hours-focusable'),
				(new CSelect('period_minutes'))
					->setFocusableElementId('period-minutes-focusable')
					->addOptions(CSelect::createOptionsFromArray(range(0, 59)))
					->setValue($data['form']['period_minutes']),
				new CLabel(_('Minutes'), 'period-minutes-focusable')
			]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
		]))
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			maintenance_timeperiod_edit.init();
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'maintenance_timeperiod_edit.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('maintenance.timeperiod.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
