<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.triggers.edit.js.php';

$widget = (new CWidget())->setTitle(_('Triggers'));

// append host summary to widget header
if (!empty($data['hostid'])) {
	$widget->addItem(get_header_host_table('triggers', $data['hostid']));
}

// Create form.
$triggersForm = (new CForm())
	->setName('triggersForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid'])
	->addVar('expression_constructor', $data['expression_constructor'])
	->addVar('recovery_expression_constructor', $data['recovery_expression_constructor'])
	->addVar('toggle_expression_constructor', '')
	->addVar('toggle_recovery_expression_constructor', '')
	->addVar('remove_expression', '')
	->addVar('remove_recovery_expression', '');

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
		->addVar('recovery_mode', $data['recovery_mode'])
		->addVar('type', $data['type'])
		->addVar('correlation_mode', $data['correlation_mode']);

	if ($data['config']['event_ack_enable']) {
		$triggersForm->addVar('manual_close', $data['manual_close']);
	}
}

// Create form list.
$triggersFormList = new CFormList('triggersFormList');
if (!empty($data['templates'])) {
	$triggersFormList->addRow(_('Parent triggers'), $data['templates']);
}

if ($discovered_trigger) {
	$triggersFormList->addRow(_('Discovered by'), new CLink($data['discoveryRule']['name'],
		'trigger_prototypes.php?parent_discoveryid='.$data['discoveryRule']['itemid']
	));
}

$triggersFormList->addRow(_('Name'),
	(new CTextBox('description', $data['description'], $readonly))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
);

if ($discovered_trigger) {
	$triggersFormList->addVar('priority', (int) $data['priority']);
	$severity = new CSeverity(['name' => 'priority_names', 'value' => (int) $data['priority']], false);
}
else {
	$severity = new CSeverity(['name' => 'priority', 'value' => (int) $data['priority']]);
}

$triggersFormList->addRow(_('Severity'), $severity);

// Append expression to form list.
if ($data['expression_field_readonly']) {
	$triggersForm->addVar('expression', $data['expression']);
}

if ($data['recovery_expression_field_readonly']) {
	$triggersForm->addVar('recovery_expression', $data['recovery_expression']);
}

$popup_options = [
	'srctbl' => $data['expression_field_name'],
	'srcfld1' => $data['expression_field_name'],
	'dstfrm' => $triggersForm->getName(),
	'dstfld1' => $data['expression_field_name']
];

if ($data['groupid'] && $data['hostid']) {
	$popup_options['groupid'] = $data['groupid'];
	$popup_options['hostid'] = $data['hostid'];
}

$expression_row = [
	(new CTextArea(
		$data['expression_field_name'],
		$data['expression_field_value'],
		['readonly' => $data['expression_field_readonly']]
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', ($data['expression_constructor'] == IM_TREE) ? _('Edit') : _('Add')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.triggerexpr",jQuery.extend('.CJs::encodeJson($popup_options).
			',{expression: jQuery(\'[name="'.$data['expression_field_name'].'"]\').val()}));'
		)
		->setEnabled(!$readonly)
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
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "add_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
	else {
		// Append "And" button.
		$expression_row[] = (new CSimpleButton(_('And')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "and_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Or" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Or')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "or_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);

		// Append "Replace" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Replace')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "replace_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$readonly);
	}
}
elseif ($data['expression_constructor'] != IM_FORCED) {
	$input_method_toggle = (new CSimpleButton(_('Expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_expression_constructor").value=1;'.
			'document.getElementById("expression_constructor").value='.
				(($data['expression_constructor'] == IM_TREE) ? IM_ESTABLISHED : IM_TREE).';'.
			'document.forms["'.$triggersForm->getName().'"].submit();');
	$expression_row[] = [BR(), $input_method_toggle];
}

$triggersFormList->addRow(_('Expression'), $expression_row, 'expression_row');

// Append expression table to form list.
if ($data['expression_constructor'] == IM_TREE) {
	$expressionTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setId('exp_list')
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
					if (gettype($obj) === 'object' && get_class($obj) === 'CSpan'
							&& $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj->removeAttribute('class');
						$obj->onClick(null);
					}
				}
				unset($obj);
			}

			$expressionTable->addRow(
				new CRow([
					!$readonly
						? (new CCheckBox('expr_target_single', $e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this, '.TRIGGER_EXPRESSION.');')
						: null,
					$e['list'],
					!$readonly
						? (new CCol(
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->onClick('javascript:'.
									' if (confirm('.CJs::encodeJson(_('Delete expression?')).')) {'.
										' delete_expression("'.$e['id'] .'", '.TRIGGER_EXPRESSION.');'.
										' document.forms["'.$triggersForm->getName().'"].submit();'.
									' }'
								)
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
		->onClick('return PopUp("popup.testtriggerexpr",{expression: this.form.elements["expression"].value});')
		->addClass(ZBX_STYLE_BTN_LINK);

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
		(new CDiv([$expressionTable, $testButton]))
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

$popup_options = [
	'srctbl' => $data['recovery_expression_field_name'],
	'srcfld1' => $data['recovery_expression_field_name'],
	'dstfrm' => $triggersForm->getName(),
	'dstfld1' => $data['recovery_expression_field_name']
];
if ($data['groupid'] && $data['hostid']) {
	$popup_options['groupid'] = $data['groupid'];
	$popup_options['hostid'] = $data['hostid'];
}

$recovery_expression_row = [
	(new CTextArea(
		$data['recovery_expression_field_name'],
		$data['recovery_expression_field_value'],
		['readonly' => $data['recovery_expression_field_readonly']]
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('insert', ($data['recovery_expression_constructor'] == IM_TREE) ? _('Edit') : _('Add')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.triggerexpr",jQuery.extend('.
			CJs::encodeJson($popup_options).
				',{expression: jQuery(\'[name="'.$data['recovery_expression_field_name'].'"]\').val()}));'
		)
		->setEnabled(!$readonly)
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

$triggersFormList->addRow(_('Recovery expression'), $recovery_expression_row, null,
	'recovery_expression_constructor_row'
);

// Append expression table to form list.
if ($data['recovery_expression_constructor'] == IM_TREE) {
	$recovery_expression_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setId('exp_list')
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
					if (gettype($obj) === 'object' && get_class($obj) === 'CSpan'
							&& $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj->removeAttribute('class');
						$obj->onClick(null);
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
						: null,
					$e['list'],
					!$readonly
						? (new CCol(
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->onClick('javascript:'.
									' if (confirm('.CJs::encodeJson(_('Delete expression?')).')) {'.
										' delete_expression("'.$e['id'] .'", '.TRIGGER_RECOVERY_EXPRESSION.');'.
										' document.forms["'.$triggersForm->getName().'"].submit();'.
									' }'
								)
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
		->onClick('return PopUp("popup.testtriggerexpr",'.
			'{expression: this.form.elements["recovery_expression"].value});')
		->addClass(ZBX_STYLE_BTN_LINK);

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
	->addRow(_('Tag for matching'),
		(new CTextBox('correlation_tag', $data['correlation_tag'], $readonly))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'correlation_tag_row'
	);

// Append tags to form list.
$tags_table = (new CTable())->setId('tbl_tags');

foreach ($data['tags'] as $tag_key => $tag) {
	$tags = [
		(new CTextBox('tags['.$tag_key.'][tag]', $tag['tag'], $discovered_trigger, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('tags['.$tag_key.'][value]', $tag['value'], $discovered_trigger, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value'))
	];

	if (!$discovered_trigger) {
		$tags[] = (new CCol(
			(new CButton('tags['.$tag_key.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP);
	}

	$tags_table->addRow($tags, 'form_row');
}

if (!$discovered_trigger) {
	$tags_table->setFooter(new CCol(
		(new CButton('tag_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	));
}

$triggersFormList->addRow(_('Tags'),
	(new CDiv($tags_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
);

if ($data['config']['event_ack_enable']) {
	$triggersFormList->addRow(_('Allow manual close'),
		(new CCheckBox('manual_close'))
			->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
			->setEnabled(!$readonly)
	);
}

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
			->setReadonly($discovered_trigger)
	)
	->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked($status));

// Append tabs to form.
$triggersTab = new CTabView();
if (!$data['form_refresh']) {
	$triggersTab->setSelected(0);
}
$triggersTab->addTab('triggersTab', _('Trigger'), $triggersFormList);

/*
 * Dependencies tab
 */
$dependenciesFormList = new CFormList('dependenciesFormList');
$dependenciesTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), $discovered_trigger ? null : _('Action')]);

foreach ($data['db_dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$dep_trigger_description = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	$dependenciesTable->addRow(
		(new CRow([
			(new CLink($dep_trigger_description, 'triggers.php?form=update&triggerid='.$dependency['triggerid']))
				->setAttribute('target', '_blank'),
			(new CCol(
				$discovered_trigger
					? null
					: (new CButton('remove', _('Remove')))
						->onClick('javascript: removeDependency("'.$dependency['triggerid'].'");')
						->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('dependency_'.$dependency['triggerid'])
	);
}

$dependenciesFormList->addRow(_('Dependencies'),
	(new CDiv([
		$dependenciesTable,
		$discovered_trigger
			? null
			: (new CButton('bnt1', _('Add')))
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'triggers',
						'srcfld1' => 'triggerid',
						'reference' => 'deptrigger',
						'hostid' => $data['hostid'],
						'groupid' => $data['groupid'],
						'multiselect' => '1',
						'with_triggers' => '1',
						'noempty' => '1'
					]).');'
				)
				->addClass(ZBX_STYLE_BTN_LINK)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormList);

// Append buttons to form list.
if (!empty($data['triggerid'])) {
	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			(new CButtonDelete(_('Delete trigger?'), url_params(['form', 'hostid', 'triggerid'])))
				->setEnabled(!$data['limited']),
			new CButtonCancel(url_param('hostid'))
		]
	));
}
else {
	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('hostid'))]
	));
}

// Append tabs to form.
$triggersForm->addItem($triggersTab);

$widget->addItem($triggersForm);

return $widget;
