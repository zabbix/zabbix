<?php declare(strict_types = 0);
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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('maintenance')))->removeId())
	->setId('maintenance-form')
	->setName('maintenance_form')
	->addVar('maintenanceid', $data['maintenanceid'] ?: 0)
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

$tags = (new CTable())
	->setId('tags')
	->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setHeader(
		(new CCol(
			(new CRadioButtonList('tags_evaltype', (int) $data['tags_evaltype']))
				->addValue(_('And/Or'), MAINTENANCE_TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), MAINTENANCE_TAG_EVAL_TYPE_OR)
				->setModern()
				->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
		))
	)
	->setFooter(
		(new CCol(
			(new CButtonLink(_('Add')))
				->addClass('element-table-add')
				->setEnabled($data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)
		))
	);

$tag_template = new CTemplateTag('tag-row-tmpl',
	(new CRow([
		(new CTextBox('tags[#{rowNum}][tag]', '#{tag}', false, DB::getFieldLength('maintenance_tag', 'tag')))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('tag'))
			->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL),
		(new CRadioButtonList('tags[#{rowNum}][operator]', MAINTENANCE_TAG_OPERATOR_LIKE))
			->addValue(_('Contains'), MAINTENANCE_TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), MAINTENANCE_TAG_OPERATOR_EQUAL)
			->setModern()
			->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL),
		(new CTextBox('tags[#{rowNum}][value]', '#{value}', false, DB::getFieldLength('maintenance_tag', 'value')))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder',  _('value'))
			->setReadonly(!$data['allowed_edit'] && $data['maintenance_type'] == MAINTENANCE_TYPE_NORMAL),
		(new CButton('tags[#{rowNum}][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	]))->addClass('form_row')
);

$form->addItem(
	(new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['name'], false, DB::getFieldLength('maintenances', 'name')))
					->setAttribute('autofocus', 'autofocus')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
				(new CDiv([$timeperiods, $timeperiod_template]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
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
		->addItem(
			new CFormField((new CLabel(_('At least one host group or host must be selected.')))->setAsteriskMark())
		)
		->addItem([
			new CLabel(_('Tags')),
			new CFormField([$tags, $tag_template])
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

$form->addItem(
	(new CScriptTag('
		maintenance_edit.init('.json_encode([
			'maintenanceid' => $data['maintenanceid'],
			'timeperiods' => $data['timeperiods'],
			'tags' => $data['tags'],
			'allowed_edit' => $data['allowed_edit']
		]).');
	'))->setOnDocumentReady()
);

if ($data['maintenanceid'] !== null) {
	$title = _('Maintenance period');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => $data['allowed_edit'],
			'action' => 'maintenance_edit.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit'],
			'action' => 'maintenance_edit.clone('.json_encode([
				'title' => _('New maintenance period'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'maintenance_edit.submit();'
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
			'confirmation' => _('Delete maintenance period?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => $data['allowed_edit'],
			'action' => 'maintenance_edit.delete();'
		]
	];
}
else {
	$title = _('New maintenance period');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'maintenance_edit.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_MAINTENANCE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('maintenance.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
