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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('correlation')))->removeId())
	->setId('correlation-form')
	->addVar('correlationid', $data['correlationid'])
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	]);

$remove_button = (new CButtonLink(_('Remove')))->addClass('js-condition-remove');

$condition_tag_template = (new CTemplateTag('condition-tag-row-tmpl'))
	->addItem(
		(new CRow([
			(new CCol('#{label}'))
				->addClass('label')
				->setAttribute('data-conditiontype', '#{conditiontype}')
				->setAttribute('data-formulaid', '#{label}'),
			(new CCol([
				'#{condition_name}', ' ', new CTag('em', true, '#{data}')
			]))
				->addClass(ZBX_STYLE_WORDWRAP)
				->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;'),
			(new CCol([
				$remove_button,
				(new CInput('hidden'))
					->setAttribute('value', '#{conditiontype}')
					->setName('conditions[#{row_index}][type]'),
				(new CInput('hidden'))
					->setAttribute('value', '#{operator}')
					->setName('conditions[#{row_index}][operator]'),
				(new CInput('hidden'))
					->setAttribute('value', '#{tag}')
					->setName('conditions[#{row_index}][tag]'),
				(new CInput('hidden'))
					->setAttribute('value', '#{label}')
					->setName('conditions[#{row_index}][formulaid]')
			]))
		]))->setId('conditions_#{row_index}')
	);

$condition_hostgroup_template = (new CTemplateTag('condition-hostgr-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol([
			'#{condition_name}', ' ', new CTag('em', true, '#{data}')
		]))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		(new CCol([
			$remove_button,
			(new CInput('hidden'))
				->setAttribute('value', '#{conditiontype}')
				->setName('conditions[#{row_index}][type]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{operator}')
				->setName('conditions[#{row_index}][operator]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{groupid}')
				->setName('conditions[#{row_index}][groupid]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{label}')
				->setName('conditions[#{row_index}][formulaid]')
		]))
	]))->setId('conditions_#{row_index}')
);

$condition_tag_pair_template = (new CTemplateTag('condition-tag-pair-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol([
			'#{condition_name}', ' ', new CTag('em', true, '#{data_old_tag}'), ' ', '#{condition_operator}', ' ',
			'#{condition_name2}', ' ', new CTag('em', true, '#{data_new_tag}')
		]))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		(new CCol([
			$remove_button,
			(new CInput('hidden'))
				->setAttribute('value', '#{conditiontype}')
				->setName('conditions[#{row_index}][type]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{operator}')
				->setName('conditions[#{row_index}][operator]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{oldtag}')
				->setName('conditions[#{row_index}][oldtag]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{newtag}')
				->setName('conditions[#{row_index}][newtag]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{label}')
				->setName('conditions[#{row_index}][formulaid]')
		]))
	]))->setId('conditions_#{row_index}')
);

$condition_old_new_tag_template = (new CTemplateTag('condition-old-new-tag-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol([
			'#{condition_name}', ' ', new CTag('em', true, '#{tag}'), ' ',
			'#{condition_operator}', ' ', new CTag('em', true, '#{value}')
		]))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH),
		(new CCol([
			$remove_button,
			(new CInput('hidden'))
				->setAttribute('value', '#{conditiontype}')
				->setName('conditions[#{row_index}][type]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{operator}')
				->setName('conditions[#{row_index}][operator]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{tag}')
				->setName('conditions[#{row_index}][tag]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{value}')
				->setName('conditions[#{row_index}][value]'),
			(new CInput('hidden'))
				->setAttribute('value', '#{label}')
				->setName('conditions[#{row_index}][formulaid]')
		]))
	]))->setId('conditions_#{row_index}')
);

// Create condition table, add HTML templates and add the "Add" link. Table content is generated by JS.
$condition_table = (new CTable())
	->setId('condition_table')
	->addClass(ZBX_STYLE_TABLE_FORMS)
	->setHeader([_('Label'), _('Name'), _('Action')])
	->addItem([
		$condition_tag_template,
		$condition_hostgroup_template,
		$condition_tag_pair_template,
		$condition_old_new_tag_template
	])
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))
						->setAttribute('data-action', 'add')
						->addClass('js-condition-add')
				))->setColSpan(4)
			)
	);

$form_grid
	->addItem([
		(new CLabel(_('Type of calculation'), 'evaltype_select'))->setId('label-evaltype'),
		(new CFormField(
			[
				(new CDiv(
					(new CSelect('evaltype'))
					->setId('evaltype')
					->setValue($data['evaltype'])
					->setFocusableElementId('evaltype_select')
					->addOptions(CSelect::createOptionsFromArray([
						CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
						CONDITION_EVAL_TYPE_AND => _('And'),
						CONDITION_EVAL_TYPE_OR => _('Or'),
						CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
					]))
					->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
				))->addClass(ZBX_STYLE_CELL),
				(new CDiv([
					(new CSpan())->setId('expression'),
					(new CTextBox('formula', $data['formula']))
						->addStyle('width: 100%;')
						->setId('formula')
						->setAttribute('placeholder', 'A or (B and C) ...')
				]))
					->addClass(ZBX_STYLE_CELL)
					->addClass(ZBX_STYLE_CELL_EXPRESSION)
					->addStyle('width: 100%;')
			]
		))->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	])
	->addItem([
		(new CLabel(_('Conditions'), $condition_table->getId()))->setAsteriskMark(),
		(new CFormField($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			->setAriaRequired()
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
		)
	])
	->addItem([
		new CLabel(_('Operations')),
		new CFormField(
			(new CCheckBoxList())
				->setOptions([
					[
						'label' => _('Close old events'),
						'checked' => $data['op_close_old'],
						'name' => 'op_close_old',
						'id' => 'operation_0_type',
						'value' => '1'
					],
					[
						'label' => _('Close new event'),
						'checked' => $data['op_close_new'],
						'name' => 'op_close_new',
						'id' => 'operation_1_type',
						'value' => '1'
					]
				])
		)
	])
	->addItem([
		new CFormField((new CLabel(_('At least one operation must be selected.')))->setAsteriskMark())
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_CORRELATION_ENABLED))
				->setChecked($data['status'] == ZBX_CORRELATION_ENABLED)
				->setUncheckedValue(ZBX_CORRELATION_DISABLED)
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag(
			'correlation_edit_popup.init('.json_encode([
				'correlation' => $data
			], JSON_THROW_ON_ERROR).');'
		))->setOnDocumentReady()
	);

if ($data['correlationid'] === null) {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'correlation_edit_popup.submit();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'correlation_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT, 'js-clone',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'correlation_edit_popup.clone('.json_encode([
				'title' => _('New event correlation'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'correlation_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => ZBX_STYLE_BTN_ALT,
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete event correlation?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'correlation_edit_popup.delete();'
		]
	];
}

$output = [
	'header' => $data['correlationid'] === null ? _('New event correlation') : _('Event correlation'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CORRELATION_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('correlation.edit.js.php'),
	'dialogue_class' => 'modal-popup-medium'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
