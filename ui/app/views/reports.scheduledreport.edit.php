<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('scheduledreport')))->removeId())
	->setId('scheduledreport-form')
	->setName('scheduledreport-form')
	->addVar('reportid', $data['reportid'])
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

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
			'dstfrm' => $form->getName(),
			'dstfld1' => 'userid'
		]
	]
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
			'dstfrm' => $form->getName(),
			'dstfld1' => 'dashboardid'
		]
	]
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
				(new CNumericBox('hours', $data['hours'], 2, !$data['allowed_edit'], false, false))
					->padWithZeroes(2)
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				' : ',
				(new CNumericBox('minutes', $data['minutes'], 2, !$data['allowed_edit'], false, false))
					->padWithZeroes(2)
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
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
				->setAttribute('data-field-type', 'array')
				->setAttribute('data-field-name', 'weekdays')
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

$form->addItem((new CTabView())->addTab('scheduledreport_tab', _('Scheduled report'), $form_grid));

if ($data['reportid']) {
	$title = _('Scheduled report');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => $data['allowed_edit']
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT.' js-clone',
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		],
		[
			'title' => _('Test'),
			'class' => ZBX_STYLE_BTN_ALT.' js-test',
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		],
		[
			'title' => _('Delete'),
			'class' => ZBX_STYLE_BTN_ALT.' js-delete',
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		]
	];
}
else {
	$title = _('New scheduled report');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Test'),
			'class' => ZBX_STYLE_BTN_ALT.' js-test',
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		]
	];
}

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form->addItem(
	(new CScriptTag('scheduledreport_edit.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'reportid' => $data['reportid'],
		'dashboard_inaccessible' => $data['dashboard_inaccessible'],
		'rules_for_clone' => $data['js_validation_create_rules'],
		'owner_inaccessible' => array_key_exists('owner_inaccessible', $data),
		'allowed_edit' => $data['allowed_edit'],
		'current_user_id' => CWebUser::$data['userid'],
		'current_user_name' => getUserFullname(CWebUser::$data)
	]).');'))->setOnDocumentReady()
);

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::REPORTS_SCHEDULEDREPORT_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('reports.scheduledreport.edit.js.php'),
	'dialogue_class' => 'modal-popup-static'
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
