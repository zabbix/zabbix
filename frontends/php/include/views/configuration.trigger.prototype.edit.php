<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$triggersWidget = (new CWidget())
	->setTitle(_('Trigger prototypes'))
	->addItem(get_header_host_table('triggers', $data['hostid'], $data['parent_discoveryid']));

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('form', $data['form'])
	->addItem((new CVar('parent_discoveryid', $data['parent_discoveryid']))->removeId())
	->addVar('expression_constructor', $data['expression_constructor'])
	->addVar('recovery_expression_constructor', $data['recovery_expression_constructor'])
	->addVar('toggle_expression_constructor', '')
	->addVar('toggle_recovery_expression_constructor', '')
	->addVar('remove_expression', '')
	->addVar('remove_recovery_expression', '');

if ($data['triggerid'] !== null) {
	$triggersForm->addVar('triggerid', $data['triggerid']);
}

if ($data['limited']) {
	$triggersForm
		->addVar('recovery_mode', $data['recovery_mode'])
		->addVar('type', $data['type'])
		->addVar('correlation_mode', $data['correlation_mode']);

	if ($data['config']['event_ack_enable']) {
		$triggersForm->addVar('manual_close', $data['manual_close']);
	}
}

// create form list
$triggersFormList = new CFormList('triggersFormList');
if (!empty($data['templates'])) {
	$triggersFormList->addRow(_('Parent triggers'), $data['templates']);
}
$triggersFormList->addRow(
	(new CLabel(_('Name'), 'description'))->setAsteriskMark(),
	(new CTextBox('description', $data['description'], $data['limited']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Severity'), new CSeverity(['name' => 'priority', 'value' => (int) $data['priority']]));

// append expression to form list
if ($data['expression_field_readonly']) {
	$triggersForm->addVar('expression', $data['expression']);
}

if ($data['recovery_expression_field_readonly']) {
	$triggersForm->addVar('recovery_expression', $data['recovery_expression']);
}

$popup_options = [
	'srctbl' => 'expression',
	'srcfld1' => 'expression',
	'dstfrm' => $triggersForm->getName(),
	'dstfld1' => $data['expression_field_name'],
	'parent_discoveryid' => $data['parent_discoveryid']
];
if ($data['groupid'] && $data['hostid']) {
	$popup_options['groupid'] = $data['groupid'];
	$popup_options['hostid'] = $data['hostid'];
}
$add_expression_button = (new CButton('insert', ($data['expression_constructor'] == IM_TREE) ? _('Edit') : _('Add')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->onClick('return PopUp("popup.triggerexpr",jQuery.extend('.
		CJs::encodeJson($popup_options).
			',{expression: jQuery(\'[name="'.$data['expression_field_name'].'"]\').val()}), null, this);'
	)
	->removeId();
if ($data['limited']) {
	$add_expression_button->setAttribute('disabled', 'disabled');
}
$expression_row = [
	(new CTextArea(
		$data['expression_field_name'],
		$data['expression_field_value'],
		['readonly' => $data['expression_field_readonly']]
	))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	$add_expression_button
];

if ($data['expression_constructor'] == IM_TREE) {
	// insert macro button
	$insertMacroButton = (new CButton('insert_macro', _('Insert expression')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->setMenuPopup(CMenuPopupHelper::getTriggerMacro());
	if ($data['limited']) {
		$insertMacroButton->setAttribute('disabled', 'disabled');
	}
	$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$expression_row[] = $insertMacroButton;
	$expression_row[] = BR();

	if ($data['expression_formula'] === '') {
		// Append "Add" button.
		$expression_row[] = (new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "add_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
	}
	else {
		// Append "And" button.
		$expression_row[] = (new CSimpleButton(_('And')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "and_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Or" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Or')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "or_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Replace" button.
		$expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expression_row[] = (new CSimpleButton(_('Replace')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "replace_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
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

$triggersFormList->addRow(
	(new CLabel(_('Expression'), $data['expression_field_name']))->setAsteriskMark(),
	$expression_row,
	'expression_row'
);

// Append expression table to form list.
if ($data['expression_constructor'] == IM_TREE) {
	$expressionTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([
			$data['limited'] ? null : _('Target'),
			_('Expression'),
			$data['limited'] ? null : _('Action'),
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

			// templated trigger
			if ($data['limited']) {
				// make all links inside inactive
				foreach ($e['list'] as &$obj) {
					if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj = new CSpan($obj->items);
					}
				}
				unset($obj);
			}

			$expressionTable->addRow(
				new CRow([
					!$data['limited']
						? (new CCheckBox('expr_target_single', $e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this, '.TRIGGER_EXPRESSION.');')
						: null,
					$e['list'],
					!$data['limited']
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
		->onClick('return PopUp("popup.testtriggerexpr",{expression: this.form.elements["expression"].value}, null,'.
					'this);')
		->addClass(ZBX_STYLE_BTN_LINK)
		->removeId();
	if (!$allowed_testing) {
		$testButton->setAttribute('disabled', 'disabled');
	}
	if ($data['expression_formula'] === '') {
		$testButton->setAttribute('disabled', 'disabled');
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
		->setEnabled(!$data['limited'])
);

$add_recovery_expression_button = (new CButton('insert',
		($data['recovery_expression_constructor'] == IM_TREE) ? _('Edit') : _('Add'))
	)
	->addClass(ZBX_STYLE_BTN_GREY)
	->onClick('return PopUp("popup.triggerexpr",jQuery.extend('.
		CJs::encodeJson([
			'srctbl' => $data['recovery_expression_field_name'],
			'srcfld1' => $data['recovery_expression_field_name'],
			'dstfrm' => $triggersForm->getName(),
			'dstfld1' => $data['recovery_expression_field_name'],
			'parent_discoveryid' => $data['parent_discoveryid']
		]).
			',{expression: jQuery(\'[name="'.$data['recovery_expression_field_name'].'"]\').val()}), null, this);'
	);

if ($data['limited']) {
	$add_recovery_expression_button->setAttribute('disabled', 'disabled');
}

$recovery_expression_row = [
	(new CTextArea(
		$data['recovery_expression_field_name'],
		$data['recovery_expression_field_value'],
		['readonly' => $data['recovery_expression_field_readonly']]
	))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	$add_recovery_expression_button
];

if ($data['recovery_expression_constructor'] == IM_TREE) {
	$recovery_expression_row[] = BR();

	if ($data['recovery_expression_formula'] === '') {
		// Append "Add" button.
		$recovery_expression_row[] = (new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "add_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
	}
	else {
		// Append "And" button.
		$recovery_expression_row[] = (new CSimpleButton(_('And')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "and_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Or" button.
		$recovery_expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$recovery_expression_row[] = (new CSimpleButton(_('Or')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "or_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Replace" button.
		$recovery_expression_row[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$recovery_expression_row[] = (new CSimpleButton(_('Replace')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "replace_recovery_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
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
			$data['limited'] ? null : _('Target'),
			_('Expression'),
			$data['limited'] ? null : _('Action'),
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

			// templated trigger
			if ($data['limited']) {
				// make all links inside inactive
				foreach ($e['list'] as &$obj) {
					if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj = new CSpan($obj->items);
					}
				}
				unset($obj);
			}

			$recovery_expression_table->addRow(
				new CRow([
					!$data['limited']
						? (new CCheckBox('recovery_expr_target_single', $e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this, '.TRIGGER_RECOVERY_EXPRESSION.');')
						: null,
					$e['list'],
					!$data['limited']
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
			'{expression: this.form.elements["recovery_expression"].value}, null, this);')
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
			->setEnabled(!$data['limited'])
	)
	->addRow(_('OK event closes'),
		(new CRadioButtonList('correlation_mode', (int) $data['correlation_mode']))
			->addValue(_('All problems'), ZBX_TRIGGER_CORRELATION_NONE)
			->addValue(_('All problems if tag values match'), ZBX_TRIGGER_CORRELATION_TAG)
			->setModern(true)
			->setEnabled(!$data['limited']),
		'correlation_mode_row'
	)
	->addRow(
		(new CLabel(_('Tag for matching'), 'correlation_tag'))->setAsteriskMark(),
		(new CTextBox('correlation_tag', $data['correlation_tag'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'correlation_tag_row'
	);

// tags
$tags_table = (new CTable())->setId('tbl_tags');

foreach ($data['tags'] as $tag_key => $tag) {
	$tags_table->addRow([
		(new CTextBox('tags['.$tag_key.'][tag]', $tag['tag'], false, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('tags['.$tag_key.'][value]', $tag['value'], false, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value')),
		(new CCol(
			(new CButton('tags['.$tag_key.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}

$tags_table->setFooter(new CCol(
	(new CButton('tag_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));
$triggersFormList->addRow(_('Tags'),
	(new CDiv($tags_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
);

if ($data['config']['event_ack_enable']) {
	$triggersFormList->addRow(_('Allow manual close'),
		(new CCheckBox('manual_close'))
			->setChecked($data['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED)
			->setEnabled(!$data['limited'])
	);
}

// append status to form list
if (empty($data['triggerid']) && empty($data['form_refresh'])) {
	$status = true;
}
else {
	$status = ($data['status'] == TRIGGER_STATUS_ENABLED);
}

$triggersFormList
	->addRow(_('URL'), (new CTextBox('url', $data['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Description'),
		(new CTextArea('comments', $data['comments']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Create enabled'), (new CCheckBox('status'))->setChecked($status));

// append tabs to form
$triggersTab = new CTabView();
if (!$data['form_refresh']) {
	$triggersTab->setSelected(0);
}
$triggersTab->addTab('triggersTab',	_('Trigger prototype'), $triggersFormList);

/*
 * Dependencies tab
 */
$dependenciesFormList = new CFormList('dependenciesFormList');
$dependenciesTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('Action')]);

foreach ($data['db_dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$depTriggerDescription = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$description = (new CLink($depTriggerDescription,
			'trigger_prototypes.php?form=update'.url_param('parent_discoveryid').'&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}
	elseif ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = (new CLink($depTriggerDescription,
			'triggers.php?form=update&triggerid='.$dependency['triggerid']
		))->setAttribute('target', '_blank');
	}

	$row = new CRow([$description,
		(new CCol(
			(new CButton('remove', _('Remove')))
				->onClick('javascript: removeDependency("'.$dependency['triggerid'].'");')
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		))->addClass(ZBX_STYLE_NOWRAP)
	]);

	$row->setId('dependency_'.$dependency['triggerid']);
	$dependenciesTable->addRow($row);
}

$dependenciesFormList->addRow(_('Dependencies'),
	(new CDiv([
		$dependenciesTable,
		new CHorList([
			(new CButton('add_dep_trigger', _('Add')))
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'triggers',
						'srcfld1' => 'triggerid',
						'reference' => 'deptrigger',
						'multiselect' => '1',
						'with_triggers' => '1',
						'normal_only' => '1',
						'noempty' => '1',
						'hostid' => $data['hostid'],
						'groupid' => $data['groupid']
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_trigger_prototype', _('Add prototype')))
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'trigger_prototypes',
						'srcfld1' => 'triggerid',
						'reference' => 'deptrigger',
						'multiselect' => '1',
						'parent_discoveryid' => $data['parent_discoveryid']
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_LINK)
		])
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormList);

// append buttons to form
if (!empty($data['triggerid'])) {
	$deleteButton = new CButtonDelete(_('Delete trigger prototype?'),
		url_params(['form', 'triggerid', 'parent_discoveryid'])
	);

	if ($data['limited']) {
		$deleteButton->setAttribute('disabled', 'disabled');
	}

	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			$deleteButton,
			new CButtonCancel(url_param('parent_discoveryid'))
		]
	));
}
else {
	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('parent_discoveryid'))]
	));
}

// append tabs to form
$triggersForm->addItem($triggersTab);

$triggersWidget->addItem($triggersForm);

return $triggersWidget;
