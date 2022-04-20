<?php declare(strict_types = 1);
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

$form = (new CForm('post'))
	->setId('sla-form')
	->setName('sla_form')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

// SLA tab.

$schedule = (new CTable())->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

for ($weekday = 0; $weekday < 7; $weekday++) {
	$schedule->addRow(new CRow([
		(new CCheckBox('schedule_enabled['.$weekday.']', $weekday))
			->setLabel(getDayOfWeekCaption($weekday))
			->setChecked($data['form']['schedule_periods'][$weekday] !== ''),
		(new CTextBox('schedule_periods['.$weekday.']', $data['form']['schedule_periods'][$weekday]))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('placeholder', '8:00-17:00, &hellip;')
	]));
}

$sla_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('sla', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('SLO'), 'slo'))->setAsteriskMark(),
		new CFormField([
			(new CTextBox('slo', $data['form']['slo'], false, 7))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAttribute('placeholder', DB::getDefault('sla', 'slo'))
				->setAriaRequired(),
			' %'
		])
	])
	->addItem([
		new CLabel(_('Reporting period')),
		new CFormField(
			(new CRadioButtonList('period', (int) $data['form']['period']))
				->addValue(_('Daily'), ZBX_SLA_PERIOD_DAILY)
				->addValue(_('Weekly'), ZBX_SLA_PERIOD_WEEKLY)
				->addValue(_('Monthly'), ZBX_SLA_PERIOD_MONTHLY)
				->addValue(_('Quarterly'), ZBX_SLA_PERIOD_QUARTERLY)
				->addValue(_('Annually'), ZBX_SLA_PERIOD_ANNUALLY)
				->setModern(true)
		)
	])
	->addItem([
		new CLabel(_('Time zone'), 'timezone-focusable'),
		new CFormField(
			(new CSelect('timezone'))
				->setId('timezone')
				->setFocusableElementId('timezone-focusable')
				->setValue($data['form']['timezone'])
				->addOptions(CSelect::createOptionsFromArray([
					ZBX_DEFAULT_TIMEZONE => CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(),
						_('System default')
					)
				] + CTimezoneHelper::getList()))
		)
	])
	->addItem([
		new CLabel(_('Schedule')),
		new CFormField(
			(new CRadioButtonList('schedule_mode', (int) $data['form']['schedule_mode']))
				->addValue(_('24x7'), CSlaHelper::SCHEDULE_MODE_24X7)
				->addValue(_('Custom'), CSlaHelper::SCHEDULE_MODE_CUSTOM)
				->setModern(true)
		)
	])
	->addItem([
		(new CFormField(
			(new CDiv($schedule))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))
			->setId('schedule')
			->addStyle('display: none;')
	])
	->addItem([
		(new CLabel(_('Effective date'), 'effective_date'))->setAsteriskMark(),
		new CFormField(
			(new CDateSelector('effective_date', $data['form']['effective_date']))
				->setDateFormat(ZBX_DATE)
				->setPlaceholder(_('YYYY-MM-DD'))
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Service tags')))->setAsteriskMark(),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('service-tags')
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader(
						(new CRowHeader([_('Name'), _('Operation'), _('Value'), _('Action')]))
							->addClass(ZBX_STYLE_GREY)
					)
					->setFooter(
						(new CCol(
							(new CSimpleButton(_('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-add')
						))
					),
				(new CScriptTemplate('service-tag-row-tmpl'))
					->addItem(
						(new CRow([
							(new CTextBox('service_tags[#{rowNum}][tag]', '#{tag}', false,
								DB::getFieldLength('sla_service_tag', 'tag')
							))
								->setAttribute('placeholder', _('tag'))
								->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
							(new CSelect('service_tags[#{rowNum}][operator]'))
								->addOptions(CSelect::createOptionsFromArray([
									ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL => _('Equals'),
									ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE => _('Contains')
								]))
								->setValue(ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL),
							(new CTextBox('service_tags[#{rowNum}][value]', '#{value}', false,
								DB::getFieldLength('sla_service_tag', 'value')
							))
								->setAttribute('placeholder', _('value'))
								->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-remove')
						]))->addClass('form_row')
					)
			]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('sla', 'description'))
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_SLA_STATUS_ENABLED))
				->setChecked($data['form']['status'] == ZBX_SLA_STATUS_ENABLED)
		)
	]);

$excluded_downtimes = (new CTable())
	->setId('excluded-downtimes')
	->setHeader(
		(new CRowHeader([_('Start time'), _('Duration'), _('Name'), _('Action')]))->addClass(ZBX_STYLE_GREY)
	);

$excluded_downtimes->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add')
			))->setColSpan(4)
		)
);

$excluded_downtimes_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Excluded downtimes')),
		new CFormField([
			(new CDiv($excluded_downtimes))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		])
	]);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('sla-tab', _('SLA'), $sla_tab)
	->addTab('excluded-downtimes-tab', _('Excluded downtimes'), $excluded_downtimes_tab,
		TAB_INDICATOR_EXCLUDED_DOWNTIMES
	);

// Output.

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			sla_edit_popup.init('.json_encode([
				'slaid' => $data['slaid'],
				'service_tags' => $data['form']['service_tags'],
				'excluded_downtimes' => $data['form']['excluded_downtimes'],
				'create_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'sla.create')
					->getUrl(),
				'update_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'sla.update')
					->getUrl(),
				'delete_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'sla.delete')
					->getUrl()
			]).');
		'))->setOnDocumentReady()
	);

if ($data['slaid'] !== null) {
	$title = _('SLA');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'sla_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'sla_edit_popup.clone('.json_encode([
				'title' => _('New SLA'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'sla_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected SLA?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'sla_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New SLA');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'sla_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.sla.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
