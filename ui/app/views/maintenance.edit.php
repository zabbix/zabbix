<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('maintenance')))->removeId())
	->setId('maintenance-form')
	->setName('maintenance_form')
	->addVar('maintenanceid', $data['maintenanceid'] ?: 0)
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$periods = (new CTable())
	->setId('periods')
	->setHeader(new CRowHeader([_('Period type'), _('Schedule'), _('Period'), _('Action')]))
	->setAriaRequired();

$periods->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add')
					->setEnabled($data['allowed_edit'])
			))->setColSpan(4)
		)
);

$tags = (new CTable())
	->setId('maintenance-tags')
	->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setHeader(
		(new CCol(
			(new CRadioButtonList('tags_evaltype', (int) $data['tags_evaltype']))
				->addValue(_('And/Or'), MAINTENANCE_TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), MAINTENANCE_TAG_EVAL_TYPE_OR)
				->setModern(true)
		))->setColSpan(4)
	)
	->setFooter(
		(new CCol(
			(new CButton('tags_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		))->setColSpan(4)
	);

$template_tag = (new CTemplateTag('maintenance-tag-row-tmpl'))
	->addItem(
		(new CRow([
			(new CTextBox('maintenance_tags[#{rowNum}][tag]', '#{tag}', false,
				DB::getFieldLength('maintenance_tag', 'tag')
			))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('maintenance_tags[#{rowNum}][operator]', MAINTENANCE_TAG_OPERATOR_LIKE))
				->addValue(_('Contains'), MAINTENANCE_TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), MAINTENANCE_TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox('maintenance_tags[#{rowNum}][value]', '#{value}', false,
				DB::getFieldLength('maintenance_tag', 'value')
			))
				->setAttribute('placeholder',  _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CButton('maintenance_tags[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	);

$form->addItem(
	(new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'mname'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('mname', $data['mname'], false, DB::getFieldLength('maintenances', 'name')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
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
					->setModern(true)
					->setEnabled($data['allowed_edit'])
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
				(new CDiv($periods))
					->setId('periods')
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			)
		])
		->addItem([
			new CLabel(_('Host groups'), 'groupids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'groupids[]',
					'object_name' => 'hostGroup',
					'data' => $data['groups_ms'],
					'disabled' => !$data['allowed_edit'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'groupids_',
							'editable' => true
						]
					]
				]))
					->setId('groupids_')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Hosts'), 'hostids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'hostids[]',
					'object_name' => 'hosts',
					'data' => $data['hosts_ms'],
					'disabled' => !$data['allowed_edit'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'hostids_',
							'editable' => true
						]
					]
				]))
					->setId('hostids_')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem(
			new CFormField((new CLabel(_('At least one host group or host must be selected.')))->setAsteriskMark())
		)
		->addItem([
			new CLabel(_('Tags')),
			new CFormField(
				(new CDiv([$tags, $template_tag]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			)
		])
		->addItem([
			new CLabel(_('Description'), 'description'),
			new CFormField(
				(new CTextArea('description', $data['description']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setReadonly(!$data['allowed_edit'])
			)
		])
	);

$form->addItem(
	(new CScriptTag('
		maintenance_edit.init('.json_encode([
			'maintenanceid' => $data['maintenanceid'],
			'timeperiods' => $data['timeperiods'],
			'maintenance_tags' => $data['tags'],
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
		$this->readJsFile('maintenance.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
