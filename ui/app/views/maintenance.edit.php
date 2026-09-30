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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('maintenance')))->removeId())
	->setId('maintenance-form')
	->setName('maintenance_form')
	->addVar('maintenanceid', $data['maintenanceid'])
	->addItem(getMessages())
	->addStyle('display: none;');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$timeperiods = (new CTable())
	->setId('timeperiods')
	->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setHeader(new CRowHeader([_('Period type'), _('Schedule'), _('Period'), _('Actions')]))
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))
						->addClass('js-add')
						->setEnabled($data['allowed_edit'])
				))
			)
	);

$timeperiod_template = new CTemplateTag('timeperiod-row-tmpl',
	(new CRow([
		(new CCol('#{formatted_type}'))->addItem([
			(new CVar('timeperiods[#{row_index}][timeperiod_type]', '#{timeperiod_type}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][every]', '#{every}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][month]', '#{month}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][dayofweek]', '#{dayofweek}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][day]', '#{day}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][start_time]', '#{start_time}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][period]', '#{period}'))->removeId(),
			(new CVar('timeperiods[#{row_index}][start_date]', '#{start_date}'))->removeId()
		]),
		(new CCol('#{formatted_schedule}'))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol('#{formatted_period}')),
		(new CCol(
			(new CHorList([
				(new CButtonLink(_('Edit')))
					->addClass('js-edit')
					->setEnabled($data['allowed_edit']),
				(new CButtonLink(_('Remove')))
					->addClass('js-remove')
					->setEnabled($data['allowed_edit'])
			]))
		))
	]))->setAttribute('data-row_index', '#{row_index}')
);

$event_names = (new CTable())
	->setId('event_names')
	->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setHeader(new CRowHeader([_('Operator'), _('Name')]))
	->setFooter(
		(new CCol(
			(new CButtonLink(_('Add')))
				->addClass('element-table-add')
				->setEnabled($data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
		))
	)
	->setAttribute('data-field-type', 'set')
	->setAttribute('data-field-name', 'event_names');

$event_names_template = (new CTemplateTag('event-names-row-tmpl'))
	->addItem(
		(new CRow([
			(new CRadioButtonList('event_names[#{rowNum}][operator]', MAINTENANCE_EVENT_NAME_OPERATOR_LIKE))
				->setAttribute('data-error-container', 'event_names_#{rowNum}_error_container')
				->setAttribute('data-error-label', _('Operator'))
				->addValue(_('Contains'), MAINTENANCE_EVENT_NAME_OPERATOR_LIKE)
				->addValue(_('Does not contain'), MAINTENANCE_EVENT_NAME_OPERATOR_NOT_LIKE)
				->setModern()
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL),
			(new CTextAreaFlexible('event_names[#{rowNum}][value]', '#{value}'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setMaxlength(DB::getFieldLength('maintenance_eventname', 'value'))
				->setAttribute('placeholder',  _('value'))
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
				->setErrorContainer('event_names_#{rowNum}_error_container')
				->setErrorLabel(_('Value')),
			(new CCol(
				(new CButton('event_names[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->setWidth('100%')
		]))->addClass('form_row')
	)
	->addItem(
		(new CRow([
			(new CCol())
				->setId('event_names_#{rowNum}_error_container')
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setColSpan(3)
		]))->addClass('form_row')
	);

$tags_evaltype = (new CDiv(
	(new CRadioButtonList('tags_evaltype', (int) $data['tags_evaltype']))
		->addValue(_('And/Or'), MAINTENANCE_TAG_EVAL_TYPE_AND_OR)
		->addValue(_('Or'), MAINTENANCE_TAG_EVAL_TYPE_OR)
		->setModern()
		->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
))->addStyle('margin-bottom: 10px;');

$tags = (new CTable())
	->setId('tags')
	->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setFooter(
		(new CCol(
			(new CButtonLink(_('Add')))
				->addClass('element-table-add')
				->setEnabled($data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
		))
	)
	->setAttribute('data-field-type', 'set')
	->setAttribute('data-field-name', 'tags');

$tag_template = (new CTemplateTag('tag-row-tmpl'))
	->addItem(
		(new CRow([
			(new CTextAreaFlexible('tags[#{rowNum}][tag]', '#{tag}'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setMaxlength(DB::getFieldLength('maintenance_tag', 'tag'))
				->setAttribute('placeholder', _('tag'))
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
				->setErrorContainer('tags_#{rowNum}_error_container')
				->setErrorLabel(_('Tag')),
			(new CSelect('tags[#{rowNum}][operator]'))
				->setAttribute('data-error-container', 'tags_#{rowNum}_error_container')
				->setAttribute('data-error-label', _('Operator'))
				->addOptions(CSelect::createOptionsFromArray([
					MAINTENANCE_TAG_OPERATOR_EQUAL => _('Equals'),
					MAINTENANCE_TAG_OPERATOR_LIKE => _('Contains'),
					MAINTENANCE_TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					MAINTENANCE_TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(MAINTENANCE_TAG_OPERATOR_LIKE)
				->setAttribute('data-prevent-validation-on-change', 1)
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL),
			(new CTextAreaFlexible('tags[#{rowNum}][value]', '#{value}'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setMaxlength(DB::getFieldLength('maintenance_tag', 'value'))
				->setAttribute('placeholder',  _('value'))
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
				->setErrorContainer('tags_#{rowNum}_error_container')
				->setErrorLabel(_('Value')),
			(new CButton('tags[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	)
	->addItem(
		(new CRow([
			(new CCol())
				->setId('tags_#{rowNum}_error_container')
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setColSpan(4)
		]))->addClass('form_row')
	);

$form->addItem(
	(new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextAreaFlexible('name', $data['name']))
					->setAttribute('autofocus', 'autofocus')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setMaxlength(DB::getFieldLength('maintenances', 'name'))
					->setAriaRequired()
					->setReadonly(!$data['allowed_edit'])
			)
		])
		->addItem([
			(new CLabel(_('Maintenance type'), 'maintenance_type')),
			new CFormField(
				(new CRadioButtonList('maintenance_type', (int) $data['maintenance_type']))
					->addValue(_('With data collection'), MAINTENANCE_TYPE_NORMAL)
					->addValue(_('No data collection'), MAINTENANCE_TYPE_NODATA)
					->setModern()
					->setReadonly(!$data['allowed_edit'])
			)
		])
		->addItem([
			(new CLabel(_('Active since'), 'active_since'))->setAsteriskMark(),
			new CFormField(
				(new CDateSelector('active_since', $data['active_since']))
					->setDateFormat(ZBX_DATE_TIME)
					->setPlaceholder(_('YYYY-MM-DD hh:mm'))
					->setAriaRequired()
					->setReadonly(!$data['allowed_edit'])
			)
		])
		->addItem([
			(new CLabel(_('Active till'), 'active_till'))->setAsteriskMark(),
			new CFormField(
				(new CDateSelector('active_till', $data['active_till']))
					->setDateFormat(ZBX_DATE_TIME)
					->setPlaceholder(_('YYYY-MM-DD hh:mm'))
					->setAriaRequired()
					->setReadonly(!$data['allowed_edit'])
			)
		])
		->addItem([
			(new CLabel(_('Periods')))->setAsteriskMark(),
			new CFormField(
				(new CDiv([$timeperiods, $timeperiod_template]))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'timeperiods')
			)
		])
		->addItem([
			new CLabel(_('Host groups'), 'groupids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'groupids[]',
					'object_name' => 'hostGroup',
					'data' => $data['groups_ms'],
					'readonly' => !$data['allowed_edit'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'groupids_',
							'editable' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Hosts'), 'hostids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'hostids[]',
					'object_name' => 'hosts',
					'data' => $data['hosts_ms'],
					'readonly' => !$data['allowed_edit'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'hostids_',
							'editable' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Triggers'), 'triggerids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'triggerids[]',
					'object_name' => 'triggers',
					'data' => $data['triggers_ms'],
					'readonly' => !$data['allowed_edit'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'triggers',
							'srcfld1' => 'triggerid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'triggerids_',
							'editable' => true,
							'real_hosts' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem(
			new CFormField(
				(new CLabel(_('At least one host group, host or trigger must be selected.')))->setAsteriskMark()
			)
		)
		->addItem([
			(new CLabel(_('Event name'))),
			new CFormField(
				(new CDiv([$event_names, $event_names_template]))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			)
		])
		->addItem([
			new CLabel(_('Tags')),
			new CFormField([$tags_evaltype, $tags, $tag_template])
		])
		->addItem([
			new CLabel(_('Description'), 'description'),
			new CFormField(
				(new CTextArea('description', $data['description']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setReadonly(!$data['allowed_edit'])
					->setMaxlength(DB::getFieldLength('maintenances', 'description'))
			)
		])
	);

if ($data['maintenanceid'] !== null) {
	$title = _('Maintenance period');
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
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		],
		[
			'title' => _('Delete'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit']
		]
	];
}
else {
	$title = _('New maintenance period');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_MAINTENANCE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'dialogue_class' => 'modal-popup-large',
	'script_inline' => getPagePostJs().
		$this->readJsFile('maintenance.edit.js.php').
		'maintenance_edit.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'clone_rules' => $data['js_clone_validation_rules'],
			'timeperiods' => $data['timeperiods'],
			'event_names' => $data['event_names'],
			'tags' => $data['tags'],
			'allowed_edit' => $data['allowed_edit']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
