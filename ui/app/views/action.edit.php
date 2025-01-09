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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('action')))->removeId())
	->setName('action.edit')
	->setId('action-form')
	->addVar('actionid', $data['actionid'] ?: 0)
	->addVar('eventsource', $data['eventsource'])
	->addStyle('display: none;');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

// Action tab.
$action_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['action']['name'] ?: ''))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	]);

// Create condition table.
$condition_table = (new CTable())
	->setId('conditionTable')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$formula = (new CTextBox('formula', $data['formula'], false, DB::getFieldLength('actions', 'formula')))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) ...');

$condition_hidden_data = (new CCol([
	(new CButtonLink(_('Remove')))
		->addClass('js-remove-condition'),
	(new CInput('hidden'))
		->setAttribute('value', '#{conditiontype}')
		->setName('conditions[#{row_index}][conditiontype]'),
	(new CInput('hidden'))
		->setAttribute('value', '#{operator}')
		->setName('conditions[#{row_index}][operator]'),
	(new CInput('hidden'))
		->setAttribute('value', '#{value}')
		->setName('conditions[#{row_index}][value]'),
	(new CInput('hidden'))
		->setAttribute('value', '#{value2}')
		->setName('conditions[#{row_index}][value2]'),
	(new CInput('hidden'))
		->setAttribute('value', '#{label}')
		->setName('conditions[#{row_index}][formulaid]')
]));

$condition_suppressed_template = (new CTemplateTag('condition-suppressed-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol('#{condition_name}'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH),
		$condition_hidden_data
	]))->setAttribute('data-row_index', '#{row_index}')
);

$condition_template_default = (new CTemplateTag('condition-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol([
			'#{condition_name}', new CTag('em', true, '#{data}')
		]))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH),
		$condition_hidden_data
	]))->setAttribute('data-row_index', '#{row_index}')
);

$condition_tag_value_template = (new CTemplateTag('condition-tag-value-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{label}'))
			->addClass('label')
			->setAttribute('data-conditiontype', '#{conditiontype}')
			->setAttribute('data-formulaid', '#{label}'),
		(new CCol([
			_('Value of tag'), ' ', new CTag('em', true, '#{value2}'), ' ',
			'#{operator_name}', ' ', new CTag('em', true, '#{value}')
		]))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH),
		$condition_hidden_data
	]))->setAttribute('data-row_index', '#{row_index}')
);

$action_tab->addItem([
	(new CLabel(_('Type of calculation'), 'evaltype_select'))->setId('label-evaltype'),
	(new CFormField([
		(new CDiv([
			(new CSelect('evaltype'))
				->setId('evaltype')
				->setFocusableElementId('evaltype_select')
				->setValue($data['action']['filter']['evaltype'])
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
					CONDITION_EVAL_TYPE_AND => _('And'),
					CONDITION_EVAL_TYPE_OR => _('Or'),
					CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
				])),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
		]))->addClass(ZBX_STYLE_CELL),
		(new CDiv([
			(new CSpan(''))->setId('expression'),
			$formula
		]))
			->addClass(ZBX_STYLE_CELL)
			->addClass(ZBX_STYLE_CELL_EXPRESSION),
		$condition_suppressed_template,
		$condition_template_default,
		$condition_tag_value_template
	]))->setId('evaltype-formfield')
])->setId('actionCalculationRow');

$condition_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CButtonLink(_('Add')))
					->addClass('js-condition-create')
					->setAttribute('data-eventsource', $data['eventsource'])
			))->setColSpan(4)
		)
);

// action tab
$action_tab
	->addItem([
		new CLabel(_('Conditions')),
		(new CFormField($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ACTION_STATUS_ENABLED))
				->setChecked($data['action']['status'] == ACTION_STATUS_ENABLED)
		)
	])
	->addItem(
		new CFormField((new CLabel(_('At least one operation must exist.')))->setAsteriskMark())
	);

// Operations tab.
$operations_tab = (new CFormGrid());
if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_tab->addItem([
		(new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('esc_period', $data['action']['esc_period']))
				->setId('esc_period')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
	]);
}

// Operations table.
$data['esc_period'] = $data['action']['esc_period'];

$operations_tab->addItem([
	new CLabel(_('Operations')),
	(new CFormField(new CPartial('action.operations', $data)))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setId('operations-container')
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);

// Recovery operations table.
if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_tab->addItem([
		new CLabel(_('Recovery operations')),
		(new CFormField(new CPartial('action.recovery.operations', $data)))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('recovery-operations-container')
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);
}

// Update operations table.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_SERVICE) {
	$operations_tab->addItem([
		new CLabel(_('Update operations')),
		(new CFormField(new CPartial('action.update.operations', $data)))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('update-operations-container')
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);
}

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$operations_tab
		->addItem([
			new CLabel(_('Pause operations for symptom problems'), 'pause_symptoms'),
			new CFormField((new CCheckBox('pause_symptoms', ACTION_PAUSE_SYMPTOMS_TRUE))
				->setChecked($data['action']['pause_symptoms'] == ACTION_PAUSE_SYMPTOMS_TRUE)
			)
		])
		->addItem([
			new CLabel(_('Pause operations for suppressed problems'), 'pause_suppressed'),
			new CFormField((new CCheckBox('pause_suppressed', ACTION_PAUSE_SUPPRESSED_TRUE))
				->setChecked($data['action']['pause_suppressed'] == ACTION_PAUSE_SUPPRESSED_TRUE)
			)
		])
		->addItem([
			new CLabel(_('Notify about canceled escalations'), 'notify_if_canceled'),
			new CFormField((new CCheckBox('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_TRUE))
				->setChecked($data['action']['notify_if_canceled'] == ACTION_NOTIFY_IF_CANCELED_TRUE)
			)
		]);
}

$operations_tab->addItem(
	new CFormField((new CLabel(_('At least one operation must exist.')))->setAsteriskMark())
);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('action-tab', _('Action'), $action_tab)
	->addTab('action-operations-tab', _('Operations'), $operations_tab, TAB_INDICATOR_OPERATIONS);

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			action_edit_popup.init('.json_encode([
				'condition_operators' => condition_operator2str(),
				'condition_types' => condition_type2str(),
				'conditions' => $data['action']['filter']['conditions'],
				'actionid' => $data['actionid'] ?: 0,
				'eventsource' => $data['eventsource'],
				'allowed_operations' => $data['allowedOperations']
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

if ($data['actionid'] !== 0) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'action_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'action_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete current action?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'action_edit_popup.delete();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'action_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $data['actionid'] !== 0 ? _('Action') : _('New action'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::ALERTS_ACTION_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('action.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
