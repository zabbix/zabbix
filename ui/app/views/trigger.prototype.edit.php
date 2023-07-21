<?php
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

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'trigger.prototype.list')
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

$trigger_form = (new CForm('post', $url))
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('trigger')))->removeId())
	->setId('trigger-prototype-form')
	->setName('trigger_edit_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem((new CVar('parent_discoveryid', $data['parent_discoveryid']))->removeId())
	->addVar('context', $data['context'])
	->addVar('expression_full', $data['expression_full'], 'expression-full')
	->addVar('recovery_expression_full', $data['recovery_expression_full'], 'recovery-expression-full');

if ($data['triggerid'] !== null) {
	$trigger_form->addVar('triggerid', $data['triggerid']);
}

if ($data['limited']) {
	$trigger_form
		->addItem((new CVar('opdata', $data['opdata']))->removeId())
		->addItem((new CVar('recovery_mode', $data['recovery_mode']))->removeId())
		->addItem((new CVar('type', $data['type']))->removeId())
		->addItem((new CVar('correlation_mode', $data['correlation_mode']))->removeId())
		->addItem((new CVar('manual_close', $data['manual_close']))->removeId());
}

$trigger_form_grid = new CFormGrid();
if ($data['templates']) {
	$trigger_form_grid->addItem([new CLabel(_('Parent triggers')), new CFormField($data['templates'])]);
}

$trigger_form_grid
	->addItem([
		(new CLabel(_('Name'), 'description'))->setAsteriskMark(),
		(new CTextBox('description', $data['description'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	])
	->addItem([
		(new CLabel(_('Event name'), 'event_name')),
		(new CTextAreaFlexible('event_name', $data['event_name']))
			->setReadonly($data['limited'])
			->setMaxlength(DB::getFieldLength('triggers', 'event_name'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([
		new CLabel(_('Operational data'), 'opdata'),
		(new CTextBox('opdata', $data['opdata'], $data['limited']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([
		new CLabel(_('Severity')),
		new CSeverity('priority', (int) $data['priority'])
	]);

$expression_popup_parameters = [
	'dstfrm' => $trigger_form->getName(),
	'dstfld1' => 'expression',
	'context' => $data['context'],
	'parent_discoveryid' => $data['parent_discoveryid']
];

if ($data['hostid']) {
	$expression_popup_parameters['hostid'] = $data['hostid'];
}

$expression_row = [
	(new CTextArea('expression', $data['expression']))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($data['limited'])
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('add_expression', _('Add')))
		->setId('insert-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$data['limited'])
];

$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
// Append "Insert expression" button.
$expression_row[] = (new CButton('insert_macro', _('Insert expression')))
	->setId('insert-macro')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setMenuPopup(CMenuPopupHelper::getTriggerMacro())
	->setEnabled(!$data['limited']);

$expression_constructor_buttons = [];
// Append "Add" button.
$expression_constructor_buttons[] = (new CButton('add_expression', _('Add')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "And" button.
$expression_constructor_buttons[] = (new CButton('and_expression', _('And')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "Or" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[] = (new CButton('or_expression', _('Or')))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "Replace" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[]= (new CButton('replace_expression', 'Replace'))
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

$input_method_toggle = (new CButtonLink(_('Expression constructor')))->setId('expression-constructor');
$expression_row[] = [
	(new CDiv($expression_constructor_buttons))
		->setId('expression-constructor-buttons')
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid->addItem([
	(new CLabel(_('Expression'), 'expression_field'))->setAsteriskMark(),
	(new CFormField($expression_row))->setId('expression-row')
]);

$trigger_form_grid->addItem(
	(new CFormField())
		->setId('expression-table')
		->addStyle('display: none')
);

$input_method_toggle = new CDiv((new CButtonLink(_('Close expression constructor')))
	->setId('close-expression-constructor')
);
$trigger_form_grid->addItem((new CFormField([null, $input_method_toggle]))
	->addStyle('display: none')
	->setId('close-expression-constructor-field')
);

$trigger_form_grid->addItem([new CLabel(_('OK event generation')),
	(new CRadioButtonList('recovery_mode', (int) $data['recovery_mode']))
		->addValue(_('Expression'), ZBX_RECOVERY_MODE_EXPRESSION)
		->addValue(_('Recovery expression'), ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION)
		->addValue(_('None'), ZBX_RECOVERY_MODE_NONE)
		->setModern()
		->setEnabled(!$data['limited'])
]);

$recovery_popup_parameters = [
	'dstfrm' => $trigger_form->getName(),
	'dstfld1' => 'recovery_expression',
	'context' => $data['context'],
	'parent_discoveryid' => $data['parent_discoveryid']
];

if ($data['hostid']) {
	$recovery_popup_parameters['hostid'] = $data['hostid'];
}

$recovery_expression_row = [
	(new CTextArea('recovery_expression', $data['recovery_expression']))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($data['limited'])
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', _('Add')))
		->setId('insert-recovery-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$data['limited'])
];

$recovery_constructor_buttons = [];

// Append "Add" button.
$recovery_constructor_buttons[] = (new CButton('add_expression_recovery', _('Add')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "And" button.
$recovery_constructor_buttons[] = (new CButton('and_expression_recovery', _('And')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "Or" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CButton('or_expression_recovery', _('Or')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

// Append "Replace" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CButton('replace_expression_recovery', _('Replace')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$data['limited']);

$input_method_toggle = (new CButtonLink(_('Expression constructor')))
	->setId('recovery-expression-constructor');

$recovery_expression_row[] = [
	(new CDiv($recovery_constructor_buttons))
		->setId('recovery-constructor-buttons')
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid->addItem([
	(new CLabel(_('Recovery expression'), 'recovery_expression_field'))->setAsteriskMark(),
	(new CFormField($recovery_expression_row))->setId('recovery-expression-row')
]);

$trigger_form_grid->addItem(
	(new CFormField())
		->setId('recovery-expression-table')
		->addStyle('display: none')
);

$input_method_toggle = (new CButtonLink(_('Close expression constructor')))
	->setId('close-recovery-expression-constructor');

$trigger_form_grid->addItem((new CFormField([null, $input_method_toggle]))
	->addStyle('display: none')
	->setId('close-recovery-expression-constructor-field')
);

$trigger_form_grid
	->addItem([new CLabel(_('PROBLEM event generation mode')),
		(new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('Single'), TRIGGER_MULT_EVENT_DISABLED)
			->addValue(_('Multiple'), TRIGGER_MULT_EVENT_ENABLED)
			->setModern()
			->setEnabled(!$data['limited'])
	])
	->addItem([new CLabel(_('OK event closes')),
		(new CRadioButtonList('correlation_mode', (int) $data['correlation_mode']))
			->addValue(_('All problems'), ZBX_TRIGGER_CORRELATION_NONE)
			->addValue(_('All problems if tag values match'), ZBX_TRIGGER_CORRELATION_TAG)
			->setModern()
			->setId('ok-event-closes')
			->setEnabled(!$data['limited'])
	])
	->addItem([(new CLabel(_('Tag for matching'), 'correlation_tag'))->setAsteriskMark(),
		(new CTextBox('correlation_tag', $data['correlation_tag'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setId('correlation-tag')
	])
	->addItem([new CLabel(_('Allow manual close')),
		new CFormField(
			(new CCheckBox('manual_close'))
				->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
				->setEnabled(!$data['limited'])
		)
	]);

// append status to form list
if (!$data['triggerid']) {
	$status = true;
}
else {
	$status = ($data['status'] == TRIGGER_STATUS_ENABLED);
}

$trigger_form_grid
	->addItem([
		new CLabel([
			_('Menu entry name'),
			makeHelpIcon([_('Menu entry name is used as a label for the trigger URL in the event context menu.')])
		]),
		(new CTextBox('url_name', array_key_exists('url_name', $data) ? $data['url_name'] : '', false,
			DB::getFieldLength('triggers', 'url_name')
		))
			->setAttribute('placeholder', _('Trigger URL'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([
		new CLabel(_('Menu entry URL')),
		(new CTextBox('url', array_key_exists('url', $data) ? $data['url'] : '', false,
			DB::getFieldLength('triggers', 'url')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([new CLabel(_('Description')),
		(new CTextArea('comments', array_key_exists('comments', $data) ? $data['comments'] : ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('triggers', 'comments'))
	])
	->addItem([new CLabel(_('Create enabled')),
		new CFormField((new CCheckBox('status'))->setChecked($status))
	])
	->addItem([new CLabel(_('Discover')),
		new CFormField(
			(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))
				->setChecked($data['discover'] == ZBX_PROTOTYPE_DISCOVER)
				->setUncheckedValue(ZBX_PROTOTYPE_NO_DISCOVER)
		)
	]);

// Append tabs to form.
$triggers_tab = new CTabView();
if ($data['form_refresh'] == 0) {
	$triggers_tab->setSelected(0);
}
$triggers_tab->addTab('triggersTab',_('Trigger prototype'), $trigger_form_grid);

// tags
$triggers_tab->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'trigger_prototype',
		'tags' => $data['tags'],
		'show_inherited_tags' => $data['show_inherited_tags'],
		'readonly' => false,
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]), TAB_INDICATOR_TAGS
);

/*
 * Dependencies tab
 */
$dependencies_form_grid = new CFormGrid();
$dependencies_table = (new CTable())
	->setId('dependency-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('Action')]);

$dependency_template_default = (new CTemplateTag('dependency-row-tmpl'))->addItem(
	(new CRow([
		(new CLink(['#{description}']))
			->addClass('js-related-trigger-edit')
			->setAttribute('data-triggerid', '#{triggerid}')
			->setAttribute('data-hostid', $data['hostid'])
			->setAttribute('data-context', $data['context']),
		(new CButtonLink(_('Remove')))
			->addClass('js-remove-dependency')
			->setAttribute('data-triggerid', '#{triggerid}'),
		(new CInput('hidden', 'dependencies[]', '#{triggerid}'))
			->setId('dependencies_'.'#{triggerid}')
	]))->setId('dependency_'.'#{triggerid}')
);

$buttons = $data['context'] === 'host'
	? new CHorList([
		(new CButton('add_dep_trigger', _('Add')))
			->setAttribute('data-hostid', $data['hostid'])
			->setId('add-dep-trigger')
			->addClass(ZBX_STYLE_BTN_LINK),
		(new CButton('add_dep_trigger_prototype', _('Add prototype')))
			->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
			->setId('add-dep-trigger-prototype')
			->addClass(ZBX_STYLE_BTN_LINK)
	])
	: new CHorList([
		(new CButton('add_dep_trigger', _('Add')))
			->setAttribute('data-templateid', $data['hostid'])
			->setId('add-dep-template-trigger')
			->addClass(ZBX_STYLE_BTN_LINK),
		(new CButton('add_dep_trigger_prototype', _('Add prototype')))
			->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
			->setId('add-dep-trigger-prototype')
			->addClass(ZBX_STYLE_BTN_LINK),
		(new CButton('add_dep_host_trigger', _('Add host trigger')))
			->setId('add-dep-host-trigger')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$dependencies_table
	->addItem((new CTag('tfoot', true))->addItem((new CCol($buttons))->setColSpan(4)))
	->addItem($dependency_template_default);

$dependencies_form_grid->addItem([new CLabel(_('Dependencies')),
	(new CDiv([$dependencies_table]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);

$triggers_tab->addTab('dependenciesTab', _('Dependencies'), $dependencies_form_grid, TAB_INDICATOR_DEPENDENCY);

if (!$data['triggerid']) {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'trigger_edit_popup.submit();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'trigger_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT, 'js-clone',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'trigger_edit_popup.clone('.json_encode([
					'title' => _('New trigger'),
					'buttons' => [
						[
							'title' => _('Add'),
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'trigger_edit_popup.submit();'
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
			'confirmation' => _('Delete trigger prototype?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => !$data['limited'],
			'action' => 'trigger_edit_popup.delete();'
		]
	];
}

$trigger_form
	->addItem($triggers_tab)
	->addItem((new CScriptTag('trigger_edit_popup.init('.json_encode([
			'triggerid' => $data['triggerid'],
			'expression_popup_parameters' => $expression_popup_parameters,
			'recovery_popup_parameters' => $recovery_popup_parameters,
			'readonly' => $data['limited'],
			'db_dependencies' => $data['db_dependencies'],
			'action' => 'trigger.prototype.edit'
		]).');'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['triggerid'] === null ? _('New trigger prototype') : _('Trigger prototype'),
	'body' => $trigger_form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('trigger.edit.js.php')
];

echo json_encode($output);
