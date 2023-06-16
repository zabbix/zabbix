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
	->setArgument('action', ($data['triggerid'] == 0) ? 'trigger.create' : 'trigger.update')
	->setArgument('context', $data['context'])
	->getUrl();

// Create form.
$trigger_form = (new CForm('post', $url))
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('trigger')))->removeId())
	->setid('trigger-edit')
	->setName('trigger_edit_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('hostid', $data['hostid'])
	->addVar('expression_full', $data['expression_full'], 'expression-full')
	->addVar('recovery_expression_full', $data['recovery_expression_full'], 'recovery-expression-full');

$discovered_trigger = false;

if ($data['triggerid'] !== null) {
	$trigger_form->addVar('triggerid', $data['triggerid']);

	if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$discovered_trigger = true;
	}
}

$readonly = ($data['limited'] || $discovered_trigger);

if ($readonly) {
	$trigger_form
		->addItem((new CVar('opdata', $data['opdata']))->removeId())
		->addItem((new CVar('recovery_mode', $data['recovery_mode']))->removeId())
		->addItem((new CVar('type', $data['type']))->removeId())
		->addItem((new CVar('correlation_mode', $data['correlation_mode']))->removeId())
		->addItem((new CVar('manual_close', $data['manual_close']))->removeId());
}

// Create form list.
$trigger_form_grid = new CFormGrid();
if (!empty($data['templates'])) {
	$trigger_form_grid->addItem([new CLabel(_('Parent triggers')), new CFormField($data['templates'])]);
}

if ($discovered_trigger) {
	$trigger_form_grid->addItem([new CLabel(_('Discovered by')), new CLink($data['discoveryRule']['name'],
		(new CUrl('trigger_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
			->setArgument('triggerid', $data['triggerDiscovery']['parent_triggerid'])
			->setArgument('context', $data['context'])
	)]);
}

$trigger_form_grid
	->addItem([
		(new CLabel(_('Name'), 'description'))->setAsteriskMark(),
		new CFormField((new CTextBox('description', $data['description'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)])
	->addItem([
		(new CLabel(_('Event name'), 'event_name')),
		new CFormField((new CTextAreaFlexible('event_name', $data['event_name']))
			->setReadonly($readonly)
			->setMaxlength(DB::getFieldLength('triggers', 'event_name'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)])
	->addItem([
		new CLabel(_('Operational data'), 'opdata'),
		new CFormField((new CTextBox('opdata', $data['opdata'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	]);

if ($discovered_trigger) {
	$trigger_form_grid->addItem([(new CVar('priority', (int) $data['priority']))->removeId()]);
	$severity = new CSeverity('priority_names', (int) $data['priority'], false);
}
else {
	$severity = new CSeverity('priority', (int) $data['priority']);
}

$trigger_form_grid->addItem([new CLabel(_('Severity')), $severity]);

if ($data['recovery_expression_field_readonly']) {
	$trigger_form->addItem((new CVar('recovery_expression', $data['recovery_expression']))->removeId());
}

$expression_popup_parameters = [
	'dstfrm' => $trigger_form->getName(),
	'dstfld1' => 'expression',
	'context' => $data['context']
];

if ($data['hostid']) {
	$expression_popup_parameters['hostid'] = $data['hostid'];
}

$expression_row = [
	(new CTextArea(
		'expression',
		$data['expression']
	))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($readonly)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', _('Add')))
		->setId('insert-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$readonly)
];

// Append "Insert expression" button.
$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_row[] = (new CButton('insert_macro', _('Insert expression')))
	->setId('insert-macro')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setMenuPopup(CMenuPopupHelper::getTriggerMacro())
	->setEnabled(!$readonly);

$expression_constructor_buttons = [];

// Append "Add" button.
$expression_constructor_buttons[] = (new CButton('add_expression', _('Add')))
	->addStyle('display: none')
	->setId('add-expression')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "And" button.
$expression_constructor_buttons[] = (new CButton('and_expression', _('And')))
	->setId('and-expression')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Or" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[] = (new CButton('or_expression', _('Or')))
	->setId('or-expression')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Replace" button.
$expression_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$expression_constructor_buttons[] = (new CButton('replace_expression', _('Replace')))
	->setId('replace-expression')
	->addStyle('display: none')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

$input_method_toggle = (new CButton('expression_constructor', _('Expression constructor')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->setId('expression-constructor');

$expression_row[] = [
	(new CDiv($expression_constructor_buttons))
		->setId('expression-constructor-buttons')
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid->addItem([
	(new CLabel(_('Expression'), 'expression-field'))->setAsteriskMark(),
	(new CFormField($expression_row))->setId('expression-row'),
]);

$trigger_form_grid->addItem(
	(new CFormField())
		->setId('expression-table')
		->addStyle('display: none')
);

$input_method_toggle = (new CDiv(
	(new CSimpleButton(_('Close expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
));
$trigger_form_grid->addItem((new CFormField([null, $input_method_toggle]))
	->addStyle('display: none')
	->setId('close-expression-constructor')
);

$trigger_form_grid->addItem([new CLabel(_('OK event generation')),
	(new CRadioButtonList('recovery_mode', (int) $data['recovery_mode']))
		->addValue(_('Expression'), ZBX_RECOVERY_MODE_EXPRESSION)
		->addValue(_('Recovery expression'), ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION)
		->addValue(_('None'), ZBX_RECOVERY_MODE_NONE)
		->setModern()
		->setEnabled(!$readonly)
]);

$recovery_popup_parameters = [
	'dstfrm' => $trigger_form->getName(),
	'dstfld1' => 'recovery_expression',
	'context' => $data['context']
];

if ($data['hostid']) {
	$popup_parameters['hostid'] = $data['hostid'];
}

$recovery_expression_row = [
	(new CTextArea(
		'recovery_expression',
		$data['recovery_expression']
	))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($readonly)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', _('Add')))
		->setId('insert-recovery-expression')
		->addClass(ZBX_STYLE_BTN_GREY)
		->setEnabled(!$readonly)
];

$recovery_constructor_buttons = [];

// Append "Add" button.
$recovery_constructor_buttons[] = (new CSimpleButton(_('Add')))
	->setId('add-recovery-expression')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "And" button.
$recovery_constructor_buttons[] = (new CSimpleButton(_('And')))
	->setId('and-recovery-expression')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Or" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CSimpleButton(_('Or')))
	->setId('or-recovery-expression')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);

// Append "Replace" button.
$recovery_constructor_buttons[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
$recovery_constructor_buttons[] = (new CSimpleButton(_('Replace')))
	->setId('replace-recovery-expression')
	->addClass(ZBX_STYLE_BTN_GREY)
	->setEnabled(!$readonly);


$input_method_toggle = (new CSimpleButton(_('Expression constructor')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->setId('recovery-expression-constructor');

$recovery_expression_row[] = [
	(new CDiv($recovery_constructor_buttons))
		->setId('recovery-constructor-buttons')
		->addStyle('display: none'),
	new CDiv($input_method_toggle)
];

$trigger_form_grid->addItem([
	(new CLabel(_('Recovery expression'), 'recovery-expression-field'))->setAsteriskMark(),
	(new CFormField($recovery_expression_row))->setId('recovery-expression-row')
]);

$trigger_form_grid->addItem(
	(new CFormField())
		->setId('recovery-expression-table')
		->addStyle('display: none')
);

$input_method_toggle = (new CSimpleButton(_('Close expression constructor')))
	->addClass(ZBX_STYLE_BTN_LINK);

$trigger_form_grid->addItem((new CFormField([null, $input_method_toggle]))
	->addStyle('display: none')
	->setId('close-recovery-expression-constructor')
);

$trigger_form_grid
	->addItem([new CLabel(_('PROBLEM event generation mode')),
		(new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('Single'), TRIGGER_MULT_EVENT_DISABLED)
			->addValue(_('Multiple'), TRIGGER_MULT_EVENT_ENABLED)
			->setModern(true)
			->setEnabled(!$readonly)
	])
	->addItem([(new CLabel(_('OK event closes'))),
		(new CRadioButtonList('correlation_mode', (int) $data['correlation_mode']))
			->addValue(_('All problems'), ZBX_TRIGGER_CORRELATION_NONE)
			->addValue(_('All problems if tag values match'), ZBX_TRIGGER_CORRELATION_TAG)
			->setModern(true)
			->setId('ok-event-closes')
			->setEnabled(!$readonly)
	])
	->addItem([
		(new CLabel(_('Tag for matching'), 'correlation_tag'))->setAsteriskMark(),
		(new CTextBox('correlation_tag', $data['correlation_tag'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setId('correlation-tag')
	])
	->addItem([new CLabel(_('Allow manual close')),
		new CFormField(
			(new CCheckBox('manual_close'))
				->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
				->setEnabled(!$readonly)
		)
	]);

// Append status to form list.
if (empty($data['triggerid'])) {
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
		(new CTextBox('url_name', array_key_exists('url_name', $data) ? $data['url_name'] : '', $discovered_trigger,
			DB::getFieldLength('triggers', 'url_name')
		))
			->setAttribute('placeholder', _('Trigger URL'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([
		new CLabel(_('Menu entry URL')),
		(new CTextBox('url', array_key_exists('url', $data) ? $data['url'] : '', $discovered_trigger,
			DB::getFieldLength('triggers', 'url')
		))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addItem([new CLabel(_('Description')),
		(new CTextArea('comments', array_key_exists('comments', $data) ? $data['comments'] : ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('triggers', 'comments'))
			->setReadonly($discovered_trigger)
	])
	->addItem([new CLabel(_('Enabled')), new CFormField((new CCheckBox('status'))->setChecked($status))]);

// Append tabs to form.
$triggersTab = new CTabView();
$triggersTab->setSelected(0);
$triggersTab->addTab('triggersTab', _('Trigger'), $trigger_form_grid);

// tags
$triggersTab->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'trigger',
		'tags' => $data['tags'],
		'show_inherited_tags' => array_key_exists('show_inherited_tags', $data) ? $data['show_inherited_tags'] : false,
		'readonly' => $discovered_trigger,
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]),
	TAB_INDICATOR_TAGS
);

/*
 * Dependencies tab
 */
$dependenciesFormGrid = new CFormGrid();
$dependenciesTable = (new CTable())
	->setId('dependency-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), $discovered_trigger ? null : _('Action')]);

if ($data['db_dependencies']) {
	foreach ($data['db_dependencies'] as $dependency) {
		$trigger_form->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

		$dep_trigger_description =
			implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description'];

		$dependenciesTable->addRow(
			(new CRow([
				(new CLink($dep_trigger_description,
					(new CUrl('triggers.php'))
						->setArgument('form', 'update')
						->setArgument('triggerid', $dependency['triggerid'])
						->setArgument('context', $data['context'])
				))->setTarget('_blank'),
				(new CCol(
					$discovered_trigger
						? null
						: (new CButton('remove', _('Remove')))
						->setAttribute('data-triggerid', $dependency['triggerid'])
						->onClick('view.removeDependency(this.dataset.triggerid)')
						->addClass(ZBX_STYLE_BTN_LINK)
						->removeId()
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('dependency_'.$dependency['triggerid'])
		);
	}
}


$buttons = null;

if (!$discovered_trigger) {
	$buttons = $data['context'] === 'host'
		? (new CButton('add_dep_trigger', _('Add')))
			->setAttribute('data-hostid', $data['hostid'])
//			->setId('add-dep-trigger')
			->onClick('
				PopUp("popup.generic", {
					srctbl: "triggers",
					srcfld1: "triggerid",
					reference: "deptrigger",
					hostid: this.dataset.hostid,
					multiselect: 1,
					with_triggers: 1,
					real_hosts: 1
				}, {dialogue_class: "modal-popup-generic"});
			')
			->addClass(ZBX_STYLE_BTN_LINK)
		: new CHorList([
				(new CButton('add_dep_trigger', _('Add')))
					->setAttribute('data-templateid', $data['hostid'])
					->setId('add-dep-template-trigger')
					->onClick('
						PopUp("popup.generic", {
							srctbl: "template_triggers",
							srcfld1: "triggerid",
							reference: "deptrigger",
							templateid: this.dataset.templateid,
							multiselect: 1,
							with_triggers: 1
						}, {dialogue_class: "modal-popup-generic"});
					')
					->addClass(ZBX_STYLE_BTN_LINK),
				(new CButton('add_dep_host_trigger', _('Add host trigger')))
					->onClick('
						PopUp("popup.generic", {
							srctbl: "triggers",
							srcfld1: "triggerid",
							reference: "deptrigger",
							multiselect: 1,
							with_triggers: 1,
							real_hosts: 1
						}, {dialogue_class: "modal-popup-generic"});
					')
					->addClass(ZBX_STYLE_BTN_LINK)
		]);
}

$dependenciesTable->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol($buttons))->setColSpan(4)
		)
);

$dependenciesFormGrid->addItem([_('Dependencies'),
	(new CDiv($dependenciesTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);


$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormGrid, TAB_INDICATOR_DEPENDENCY);

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
							'class' => 'js-add',
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
			'confirmation' => _('Delete trigger?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => !$data['limited'],
			'action' => 'trigger_edit_popup.delete();'
		]
	];
}

// Append tabs to form.
$trigger_form
	->addItem($triggersTab)
	->addItem((new CScriptTag('trigger_edit_popup.init('.json_encode([
			'form_name' => $trigger_form->getName(),
			'triggerid' => $data['triggerid'],
			'expression_popup_parameters' => $expression_popup_parameters,
			'recovery_popup_parameters' => $recovery_popup_parameters,
			'readonly' => $readonly
		]).');'))->setOnDocumentReady()
);

$output = [
	'header' => $data['triggerid'] === null ? _('New trigger') : _('Trigger'),
	'body' => $trigger_form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('trigger.edit.js.php')
];

echo json_encode($output);
