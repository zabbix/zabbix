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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('correlation')))->removeId())
	->setId('correlation-form')
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

if ($data['correlation']['correlationid'] !== null) {
	$form->addItem(
		(new CInput('hidden', 'correlationid', $data['correlation']['correlationid']))
			->setAttribute('data-field-type', 'hidden')
	);
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['correlation']['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	]);

$id_prefix = 'condition-row-tmpl-';
$form_grid
	->addItem([
		(new CLabel(_('Type of calculation'), 'evaltype_select'))->setId('label-evaltype'),
		(new CFormField(
			[
				(new CDiv(
					(new CSelect('evaltype'))
						->setId('evaltype')
						->setValue($data['correlation']['filter']['evaltype'])
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
					(new CTextBox('formula', $data['correlation']['filter']['formula']))
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
		(new CLabel(_('Conditions'), 'condition_table'))->setAsteriskMark(),
		(new CFormField(
			(new CDiv(
				(new CTable())
					->setId('condition_table')
					->addClass(ZBX_STYLE_TABLE_FORMS)
					->setHeader([_('Label'), _('Name'), _('Action')])
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_OLD_EVENT_TAG))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_OLD_EVENT_TAG)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('Old event tag name'))
								->addItem(' ')
								->addItem(_('equals'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{tag}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{tag}')
									->setName('conditions[#{row_index}][tag]')
									->setAttribute('data-field-type', 'hidden')
								)
							)
						)
					)
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_NEW_EVENT_TAG))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_NEW_EVENT_TAG)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('New event tag name'))
								->addItem(' ')
								->addItem(_('equals'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{tag}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{tag}')
									->setName('conditions[#{row_index}][tag]')
									->setAttribute('data-field-type', 'hidden')
								)
							)
						)
					)
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('New event host group'))
								->addItem(' ')
								->addItem('#{operator_name}')
								->addItem(' ')
								->addItem(new CTag('em', true, '#{group_name}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem(
									(new CInput('hidden'))
										->setAttribute('value', '#{operator}')
										->setName('conditions[#{row_index}][operator]')
										->setAttribute('data-field-type', 'hidden'),
								)
								->addItem(
									(new CInput('hidden'))
										->setAttribute('value', '#{groupid}')
										->setName('conditions[#{row_index}][groupid]')
										->setAttribute('data-field-type', 'hidden'),
								)
							)
						)
					)
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_EVENT_TAG_PAIR))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_EVENT_TAG_PAIR)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('Value of old event tag'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{oldtag}'))
								->addItem(' ')
								->addItem(_('equals'))
								->addItem(' ')
								->addItem(_('value of new event tag'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{newtag}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{oldtag}')
									->setName('conditions[#{row_index}][oldtag]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{newtag}')
									->setName('conditions[#{row_index}][newtag]')
									->setAttribute('data-field-type', 'hidden')
								)
							)
						)
					)
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('Value of old event tag'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{tag}'))
								->addItem(' ')
								->addItem('#{operator_name}')
								->addItem(' ')
								->addItem(new CTag('em', true, '#{value}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{tag}')
									->setName('conditions[#{row_index}][tag]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{operator}')
									->setName('conditions[#{row_index}][operator]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{value}')
									->setName('conditions[#{row_index}][value]')
									->setAttribute('data-field-type', 'hidden')
								)
							)
						)
					)
					->addItem(
						(new CTemplateTag($id_prefix.ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE))->addItem((new CRow())
							->addItem((new CCol('#{formulaid}'))
								->setAttribute('data-formulaid', '#{formulaid}')
								->setAttribute('data-conditiontype', ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE)
								->setAttribute('data-row_index', '#{row_index}')
							)
							->addItem((new CCol())
								->addClass(ZBX_STYLE_WORDWRAP)
								->addStyle(ZBX_TEXTAREA_BIG_WIDTH.'px;')
								->addItem(_('Value of new event tag'))
								->addItem(' ')
								->addItem(new CTag('em', true, '#{tag}'))
								->addItem(' ')
								->addItem('#{operator_name}')
								->addItem(' ')
								->addItem(new CTag('em', true, '#{value}'))
							)
							->addItem((new CCol())
								->addItem((new CButtonLink(_('Remove')))->addClass('js-condition-remove'))
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{formulaid}')
									->setName('conditions[#{row_index}][formulaid]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{type}')
									->setName('conditions[#{row_index}][type]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{tag}')
									->setName('conditions[#{row_index}][tag]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{operator}')
									->setName('conditions[#{row_index}][operator]')
									->setAttribute('data-field-type', 'hidden')
								)
								->addItem((new CInput('hidden'))
									->setAttribute('value', '#{value}')
									->setName('conditions[#{row_index}][value]')
									->setAttribute('data-field-type', 'hidden')
								)
							)
						)
					)
					->addItem(
						(new CTag('tfoot', true))
							->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))
										->setAttribute('data-action', 'add')
										->addClass('js-condition-add')
								))->setColSpan(4)
							)
					)
				))
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'conditions')
				->setAttribute('data-error-container', 'conditions-error-container')
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->setAriaRequired()
		))->addItem((new CDiv())->setId('conditions-error-container')->addClass(ZBX_STYLE_ERROR_CONTAINER))
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['correlation']['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
		)
	])
	->addItem([
		(new CLabel(_('Operations')))->setAsteriskMark(),
		new CFormField([
			(new CCheckBoxList())
				->setAttribute('data-field-name', 'operations')
				->setAttribute('data-field-type', 'array')
				->setOptions([
					[
						'label' => _('Close old events'),
						'name' => 'operations['.ZBX_CORR_OPERATION_CLOSE_OLD.']',
						'value' => ZBX_CORR_OPERATION_CLOSE_OLD,
						'checked' => boolval(array_filter($data['correlation']['operations'], fn (array $operation) =>
							$operation['type'] == ZBX_CORR_OPERATION_CLOSE_OLD
						))
					],
					[
						'label' => _('Close new event'),
						'name' => 'operations['.ZBX_CORR_OPERATION_CLOSE_NEW.']',
						'value' => ZBX_CORR_OPERATION_CLOSE_NEW,
						'checked' => boolval(array_filter($data['correlation']['operations'], fn (array $operation) =>
							$operation['type'] == ZBX_CORR_OPERATION_CLOSE_NEW
						))
					]
				])
		])
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_CORRELATION_ENABLED))
				->setChecked($data['correlation']['status'] == ZBX_CORRELATION_ENABLED)
				->setUncheckedValue(ZBX_CORRELATION_DISABLED)
		)
	]);

$templates_data = [];
foreach ($data['correlation']['filter']['conditions'] as $index => $condition) {
	$type = (int) $condition['type'];

	$template_data = [
		'row_index' => $index,
		'type' => $condition['type'],
		'formulaid' => $condition['formulaid']
	];

	$templates_data[] = $template_data + match ($type) {
		ZBX_CORR_CONDITION_OLD_EVENT_TAG,
		ZBX_CORR_CONDITION_NEW_EVENT_TAG => [
			'tag' => $condition['tag']
		],
		ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP => [
			'groupid' => $condition['groupid'],
			'group_name' => $data['hostgroup_names'][$condition['groupid']],
			'operator' => $condition['operator'],
			'operator_name' => CCorrelationHelper::getLabelByOperator($condition['operator'])
		],
		ZBX_CORR_CONDITION_EVENT_TAG_PAIR => [
			'oldtag' => $condition['oldtag'],
			'newtag' => $condition['newtag']
		],
		ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
		ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE => [
			'tag' => $condition['tag'],
			'value' => $condition['value'],
			'operator' => $condition['operator'],
			'operator_name' => CCorrelationHelper::getLabelByOperator($condition['operator'])
		]
	};
}

$form
	->addItem($form_grid);

if ($data['correlation']['correlationid'] === null) {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$output = [
	'header' => $data['correlation']['correlationid'] === null ? _('New event correlation') : _('Event correlation'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CORRELATION_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('correlation.edit.js.php').
		'correlation_edit_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'clone_rules' => $data['js_clone_validation_rules'],
			'templates_data' => $templates_data,
			'templates_types' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE, ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]
		]).');',
	'dialogue_class' => 'modal-popup-medium'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
