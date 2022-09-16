<?php
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
 */

require_once dirname(__FILE__).'/js/configuration.triggers.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Triggers'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_TRIGGERS_EDIT));

// Append host summary to widget header.
if ($data['hostid'] != 0) {
	$widget->setNavigation(getHostNavigation('triggers', $data['hostid']));
}

$url = (new CUrl('triggers.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// Create form.
$triggersForm = (new CForm('post', $url))
	->setid('triggers-form')
	->setName('triggersForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid'])
	->addVar('expression_constructor', $data['expression_constructor'])
	->addVar('recovery_expression_constructor', $data['recovery_expression_constructor'])
	->addVar('toggle_expression_constructor', '')
	->addVar('toggle_recovery_expression_constructor', '')
	->addVar('remove_expression', '')
	->addVar('remove_recovery_expression', '')
	->addVar('backurl', $data['backurl']);

$discovered_trigger = false;

if ($data['triggerid'] !== null) {
	$triggersForm->addVar('triggerid', $data['triggerid']);

	if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$discovered_trigger = true;
	}
}

$readonly = ($data['limited'] || $discovered_trigger);

if ($readonly) {
	$triggersForm
		->addItem((new CVar('opdata', $data['opdata']))->removeId())
		->addItem((new CVar('recovery_mode', $data['recovery_mode']))->removeId())
		->addItem((new CVar('type', $data['type']))->removeId())
		->addItem((new CVar('correlation_mode', $data['correlation_mode']))->removeId())
		->addItem((new CVar('manual_close', $data['manual_close']))->removeId());
}

// Create form list.
$triggersFormList = new CFormList('triggersFormList');
if (!empty($data['templates'])) {
	$triggersFormList->addRow(_('Parent triggers'), $data['templates']);
}

if ($discovered_trigger) {
	$triggersFormList->addRow(_('Discovered by'), new CLink($data['discoveryRule']['name'],
		(new CUrl('trigger_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
			->setArgument('triggerid', $data['triggerDiscovery']['parent_triggerid'])
			->setArgument('context', $data['context'])
	));
}

$triggersFormList
	->addRow(
		(new CLabel(_('Name'), 'description'))->setAsteriskMark(),
		(new CTextBox('description', $data['description'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		(new CLabel(_('Event name'), 'event_name')),
		(new CTextAreaFlexible('event_name', $data['event_name']))
			->setReadonly($readonly)
			->setMaxlength(DB::getFieldLength('triggers', 'event_name'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(
		new CLabel(_('Operational data'), 'opdata'),
		(new CTextBox('opdata', $data['opdata'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

if ($discovered_trigger) {
	$triggersFormList->addVar('priority', (int) $data['priority']);
	$severity = new CSeverity('priority_names', (int) $data['priority'], false);
}
else {
	$severity = new CSeverity('priority', (int) $data['priority']);
}

$triggersFormList->addRow(_('Severity'), $severity);

// Append expression to form list.
if ($data['expression_field_readonly']) {
	$triggersForm->addItem((new CVar('expression', $data['expression']))->removeId());
}

if ($data['recovery_expression_field_readonly']) {
	$triggersForm->addItem((new CVar('recovery_expression', $data['recovery_expression']))->removeId());
}

$popup_parameters = [
	'dstfrm' => $triggersForm->getName(),
	'dstfld1' => $data['expression_field_name'],
	'context' => $data['context']
];

if ($data['hostid']) {
	$popup_parameters['hostid'] = $data['hostid'];
}

$expression_row = [
	(new CTextArea(
		$data['expression_field_name'],
		$data['expression_field_value'],
		['readonly' => $data['expression_field_readonly']]
	))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', $data['expression_constructor'] == IM_TREE ? _('Edit') : _('Add')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->setAttribute('data-parameters', json_encode($popup_parameters))
		->onClick('
			PopUp("popup.triggerexpr", {
				...JSON.parse(this.dataset.parameters),
				expression: document.querySelector("[name='.$data['expression_field_name'].']").value
			}, {dialogue_class: "modal-popup-generic"});
		')
		->setEnabled(!$readonly)
		->removeId()
];

if ($data['expression_constructor'] == IM_TREE) {
	// Append "Insert expression" button.
	$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$expression_row[] = (new CButton('insert_macro', _('Insert expression')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->setMenuPopup(CMenuPopupHelper::getTriggerMacro())
		->setEnabled(!$readonly);
	$expression_row[] = BR();

	if ($data['expression_formula'] === '') {
		// Append "Add" button.
		$expression_row[] = (new CSimpleButton(_('Add')))
			->onClick('submitFormWithParam("'.$triggersForm->getName().'", "add_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
	else {
		// Append "And" button.
		$expression_row[] = (new CSimpleButton(_('And')))
			->onClick('submitFormWithParam("'.$triggersForm->getName().'", "and_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Or" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Or')))
			->onClick('submitFormWithParam("'.$triggersForm->getName().'", "or_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Replace" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Replace')))
			->onClick('submitFormWithParam("'.$triggersForm->getName().'", "replace_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
}
elseif ($data['expression_constructor'] != IM_FORCED) {
	$input_method_toggle = (new CSimpleButton(_('Expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick(
			'document.getElementById("toggle_expression_constructor").value=1;'.
			'document.getElementById("expression_constructor").value='.
				(($data['expression_constructor'] == IM_TREE) ? IM_ESTABLISHED : IM_TREE).';'.
			'document.forms["'.$triggersForm->getName().'"].submit();');
	$expression_row[] = [BR(), $input_method_toggle];
}

$triggersFormList->addRow(
	(new CLabel(_('Expression'), $data['expression_field_name']))->setAsteriskMark(),
	$expression_row,
	'expression_row'
);

// Append expression table to form list.
if ($data['expression_constructor'] == IM_TREE) {
	$expression_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([
			$readonly ? null : _('Target'),
			_('Expression'),
			$readonly ? null : _('Action'),
			_('Info')
		]);

	$allowed_testing = true;
	if ($data['expression_tree']) {
		foreach ($data['expression_tree'] as $i => $e) {
			$info_icons = [];
			if (isset($e['expression']['levelErrors'])) {
				$allowed_testing = false;
				$errors = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if ($errors) {
							$errors[] = BR();
						}
						$errors[] = $expVal.':'.$errTxt;
					}
				}

				$info_icons[] = makeErrorIcon($errors);
			}

			// Templated or discovered trigger.
			if ($readonly) {
				// Make all links inside inactive.
				foreach ($e['list'] as &$obj) {
					if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj = new CSpan($obj->items);
					}
				}
				unset($obj);
			}

			$expression_table->addRow(
				new CRow([
					!$readonly
						? (new CCheckBox('expr_target_single', $e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this, '.TRIGGER_EXPRESSION.');')
							->removeId()
						: null,
					(new CDiv($e['list']))->addClass(ZBX_STYLE_WORDWRAP),
					!$readonly
						? (new CCol(
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->setAttribute('data-id', $e['id'])
								->onClick('
									if (confirm('.json_encode(_('Delete expression?')).')) {
										delete_expression(this.dataset.id, '.TRIGGER_EXPRESSION.');
										document.forms["'.$triggersForm->getName().'"].submit();
									}
								')
						))->addClass(ZBX_STYLE_NOWRAP)
						: null,
					makeInformationList($info_icons)
				])
			);
		}
	}
	else {
		$allowed_testing = false;
		$data['expression_formula'] = '';
	}

	$testButton = (new CButton('test_expression', _('Test')))
		->onClick(
			'return PopUp("popup.testtriggerexpr", {expression: this.form.elements["expression"].value}, {
				dialogue_class: "modal-popup-generic"
			});'
		)
		->addClass(ZBX_STYLE_BTN_LINK)
		->removeId();

	if (!$allowed_testing) {
		$testButton->setEnabled(false);
	}

	if ($data['expression_formula'] === '') {
		$testButton->setEnabled(false);
	}

	$wrapOutline = new CSpan([$data['expression_formula']]);
	$triggersFormList->addRow(null, [
		$wrapOutline,
		BR(),
		BR(),
		(new CDiv([$expression_table, $testButton]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);

	$input_method_toggle = (new CSimpleButton(_('Close expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_expression_constructor").value=1;'.
			'document.getElementById("expression_constructor").value='.IM_ESTABLISHED.';'.
			'document.forms["'.$triggersForm->getName().'"].submit();');
	$triggersFormList->addRow(null, [$input_method_toggle, BR()]);
}

$triggersFormList->addRow(_('OK event generation'),
	(new CRadioButtonList('recovery_mode', (int) $data['recovery_mode']))
		->addValue(_('Expression'), ZBX_RECOVERY_MODE_EXPRESSION)
		->addValue(_('Recovery expression'), ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION)
		->addValue(_('None'), ZBX_RECOVERY_MODE_NONE)
		->setModern(true)
		->setEnabled(!$readonly)
);

$popup_parameters = [
	'dstfrm' => $triggersForm->getName(),
	'dstfld1' => $data['recovery_expression_field_name'],
	'context' => $data['context']
];

if ($data['hostid']) {
	$popup_parameters['hostid'] = $data['hostid'];
}

$recovery_expression_row = [
	(new CTextArea(
		$data['recovery_expression_field_name'],
		$data['recovery_expression_field_value'],
		['readonly' => $data['recovery_expression_field_readonly']]
	))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', $data['recovery_expression_constructor'] == IM_TREE ? _('Edit') : _('Add')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->setAttribute('data-parameters', json_encode($popup_parameters))
		->onClick('
			PopUp("popup.triggerexpr", {
				...JSON.parse(this.dataset.parameters),
				expression: document.querySelector("[name='.$data['recovery_expression_field_name'].']").value
			}, {dialogue_class: "modal-popup-generic"});
		')
		->setEnabled(!$readonly)
		->removeId()
];

if ($data['recovery_expression_constructor'] == IM_TREE) {
	$recovery_expression_row[] = BR();

	if ($data['recovery_expression_formula'] === '') {
		// Append "Add" button.
		$recovery_expression_row[] = (new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "add_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
	else {
		// Append "And" button.
		$recovery_expression_row[] = (new CSimpleButton(_('And')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "and_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Or" button.
		$recovery_expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$recovery_expression_row[] = (new CSimpleButton(_('Or')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "or_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Replace" button.
		$recovery_expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$recovery_expression_row[] = (new CSimpleButton(_('Replace')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "replace_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
}
elseif ($data['recovery_expression_constructor'] != IM_FORCED) {
	$input_method_toggle = (new CSimpleButton(_('Expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_recovery_expression_constructor").value=1;'.
			'document.getElementById("recovery_expression_constructor").value='.
				(($data['recovery_expression_constructor'] == IM_TREE) ? IM_ESTABLISHED : IM_TREE).';'.
			'document.forms["'.$triggersForm->getName().'"].submit();'
		);
	$recovery_expression_row[] = [BR(), $input_method_toggle];
}

$triggersFormList->addRow(
	(new CLabel(_('Recovery expression'), $data['recovery_expression_field_name']))->setAsteriskMark(),
	$recovery_expression_row,
	null,
	'recovery_expression_constructor_row'
);

// Append expression table to form list.
if ($data['recovery_expression_constructor'] == IM_TREE) {
	$recovery_expression_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([
			$readonly ? null : _('Target'),
			_('Expression'),
			$readonly ? null : _('Action'),
			_('Info')
		]);

	$allowed_testing = true;

	if ($data['recovery_expression_tree']) {
		foreach ($data['recovery_expression_tree'] as $i => $e) {
			$info_icons = [];
			if (isset($e['expression']['levelErrors'])) {
				$allowed_testing = false;
				$errors = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if ($errors) {
							$errors[] = BR();
						}
						$errors[] = $expVal.':'.$errTxt;
					}
				}

				$info_icons[] = makeErrorIcon($errors);
			}

			// Templated or discovered trigger.
			if ($readonly) {
				// Make all links inside inactive.
				foreach ($e['list'] as &$obj) {
					if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj = new CSpan($obj->items);
					}
				}
				unset($obj);
			}

			$recovery_expression_table->addRow(
				new CRow([
					!$readonly
						? (new CCheckBox('recovery_expr_target_single', $e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this, '.TRIGGER_RECOVERY_EXPRESSION.');')
							->removeId()
						: null,
					(new CDiv($e['list']))->addClass(ZBX_STYLE_WORDWRAP),
					!$readonly
						? (new CCol(
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->setAttribute('data-id', $e['id'])
								->onClick('
									if (confirm('.json_encode(_('Delete expression?')).')) {
										delete_expression(this.dataset.id, '.TRIGGER_RECOVERY_EXPRESSION.');
										document.forms["'.$triggersForm->getName().'"].submit();
									}
								')
						))->addClass(ZBX_STYLE_NOWRAP)
						: null,
					makeInformationList($info_icons)
				])
			);
		}
	}
	else {
		$allowed_testing = false;
		$data['recovery_expression_formula'] = '';
	}

	$testButton = (new CButton('test_expression', _('Test')))
		->onClick(
			'return PopUp("popup.testtriggerexpr", {expression: this.form.elements["recovery_expression"].value}, {
				dialogue_class: "modal-popup-generic"
			});'
		)
		->addClass(ZBX_STYLE_BTN_LINK)
		->removeId();

	if (!$allowed_testing) {
		$testButton->setAttribute('disabled', 'disabled');
	}

	if ($data['recovery_expression_formula'] === '') {
		$testButton->setAttribute('disabled', 'disabled');
	}

	$wrapOutline = new CSpan([$data['recovery_expression_formula']]);
	$triggersFormList->addRow(null, [
		$wrapOutline,
		BR(),
		BR(),
		(new CDiv([$recovery_expression_table, $testButton]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	], null, 'recovery_expression_constructor_row');

	$input_method_toggle = (new CSimpleButton(_('Close expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_recovery_expression_constructor").value=1;'.
			'document.getElementById("recovery_expression_constructor").value='.IM_ESTABLISHED.';'.
			'document.forms["'.$triggersForm->getName().'"].submit();'
		);
	$triggersFormList->addRow(null, [$input_method_toggle, BR()], null, 'recovery_expression_constructor_row');
}

$triggersFormList
	->addRow(_('PROBLEM event generation mode'),
		(new CRadioButtonList('type', (int) $data['type']))
			->addValue(_('Single'), TRIGGER_MULT_EVENT_DISABLED)
			->addValue(_('Multiple'), TRIGGER_MULT_EVENT_ENABLED)
			->setModern(true)
			->setEnabled(!$readonly)
	)
	->addRow(_('OK event closes'),
		(new CRadioButtonList('correlation_mode', (int) $data['correlation_mode']))
			->addValue(_('All problems'), ZBX_TRIGGER_CORRELATION_NONE)
			->addValue(_('All problems if tag values match'), ZBX_TRIGGER_CORRELATION_TAG)
			->setModern(true)
			->setEnabled(!$readonly),
		'correlation_mode_row'
	)
	->addRow(
		(new CLabel(_('Tag for matching'), 'correlation_tag'))->setAsteriskMark(),
		(new CTextBox('correlation_tag', $data['correlation_tag'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'correlation_tag_row'
	)
	->addRow(_('Allow manual close'),
		(new CCheckBox('manual_close'))
			->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
			->setEnabled(!$readonly)
	);

// Append status to form list.
if (empty($data['triggerid']) && empty($data['form_refresh'])) {
	$status = true;
}
else {
	$status = ($data['status'] == TRIGGER_STATUS_ENABLED);
}

$triggersFormList
	->addRow(_('URL'), (new CTextBox('url', $data['url'], $discovered_trigger))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Description'),
		(new CTextArea('comments', $data['comments']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('triggers', 'comments'))
			->setReadonly($discovered_trigger)
	)
	->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked($status));

// Append tabs to form.
$triggersTab = new CTabView();
if (!$data['form_refresh']) {
	$triggersTab->setSelected(0);
}
$triggersTab->addTab('triggersTab', _('Trigger'), $triggersFormList);

// tags
$triggersTab->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'trigger',
		'tags' => $data['tags'],
		'show_inherited_tags' => $data['show_inherited_tags'],
		'readonly' => $discovered_trigger,
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]),
	TAB_INDICATOR_TAGS
);

/*
 * Dependencies tab
 */
$dependenciesFormList = new CFormList('dependenciesFormList');
$dependenciesTable = (new CTable())
	->setId('dependency-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), $discovered_trigger ? null : _('Action')]);

foreach ($data['db_dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$dep_trigger_description = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

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

$buttons = null;

if (!$discovered_trigger) {
	$buttons = $data['context'] === 'host'
		? (new CButton('add_dep_trigger', _('Add')))
			->setAttribute('data-hostid', $data['hostid'])
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

$dependenciesFormList->addRow(_('Dependencies'),
	(new CDiv([$dependenciesTable, $buttons]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormList, TAB_INDICATOR_DEPENDENCY);

$cancelButton = $data['backurl'] !== null
	? (new CRedirectButton(_('Cancel'), $data['backurl']))->setId('cancel')
	: new CButtonCancel(url_param('context'));

// Append buttons to form list.
if (!empty($data['triggerid'])) {
	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			(new CButtonDelete(
				_('Delete trigger?'),
				url_params(['form', 'hostid', 'triggerid', 'context', 'backurl']),
				'context'
			))->setEnabled(!$data['limited']),
			$cancelButton
		]
	));
}
else {
	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[$cancelButton]
	));
}

// Append tabs to form.
$triggersForm->addItem($triggersTab);

$widget->addItem($triggersForm);

$widget->show();

(new CScriptTag('
	view.init('.json_encode([
		'form_name' => $triggersForm->getName()
	]).');
'))
	->setOnDocumentReady()
	->show();
