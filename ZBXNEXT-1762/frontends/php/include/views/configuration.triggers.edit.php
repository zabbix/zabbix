<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('triggers', $this->data['hostid']));
}

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('input_method', $this->data['input_method'])
	->addVar('toggle_input_method', '')
	->addVar('remove_expression', '');

if ($data['triggerid'] !== null) {
	$triggersForm->addVar('triggerid', $this->data['triggerid']);
}

// create form list
$triggersFormList = new CFormList('triggersFormList');
if (!empty($this->data['templates'])) {
	$triggersFormList->addRow(_('Parent triggers'), $this->data['templates']);
}
$triggersFormList->addRow(_('Name'),
	(new CTextBox('description', $this->data['description'], $this->data['limited']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
);

// append expression to form list
if ($this->data['expression_field_readonly']) {
	$triggersForm->addVar('expression', $this->data['expression']);
}

$addExpressionButton = (new CButton('insert', ($this->data['input_method'] == IM_TREE) ? _('Edit') : _('Add')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->onClick(
		'return PopUp("popup_trexpr.php?dstfrm='.$triggersForm->getName().
			'&dstfld1='.$this->data['expression_field_name'].'&srctbl=expression&srcfld1=expression'.
			'&expression=" + encodeURIComponent(jQuery(\'[name="'.$this->data['expression_field_name'].'"]\').val()));'
	);
if ($this->data['limited']) {
	$addExpressionButton->setAttribute('disabled', 'disabled');
}
$expressionRow = [
	(new CTextArea(
		$this->data['expression_field_name'],
		$this->data['expression_field_value'],
		[
			'readonly' => $this->data['expression_field_readonly']
		]
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	$addExpressionButton
];

if ($this->data['input_method'] == IM_TREE) {
	// insert macro button
	$insertMacroButton = (new CButton('insert_macro', _('Insert expression')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->setMenuPopup(CMenuPopupHelper::getTriggerMacro());
	if ($this->data['limited']) {
		$insertMacroButton->setAttribute('disabled', 'disabled');
	}
	$expressionRow[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$expressionRow[] = $insertMacroButton;

	array_push($expressionRow, BR());
	if (empty($this->data['outline'])) {
		// add button
		$addExpressionButton = (new CSubmit('add_expression', _('Add')))->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['limited']) {
			$addExpressionButton->setAttribute('disabled', 'disabled');
		}
		$expressionRow[] = $addExpressionButton;
	}
	else {
		// add button
		$addExpressionButton = (new CSubmit('and_expression', _('And')))->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['limited']) {
			$addExpressionButton->setAttribute('disabled', 'disabled');
		}
		$expressionRow[] = $addExpressionButton;

		// or button
		$orExpressionButton = (new CSubmit('or_expression', _('Or')))->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['limited']) {
			$orExpressionButton->setAttribute('disabled', 'disabled');
		}
		$expressionRow[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expressionRow[] = $orExpressionButton;

		// replace button
		$replaceExpressionButton = (new CSubmit('replace_expression', _('Replace')))->addClass(ZBX_STYLE_BTN_GREY);
		if ($this->data['limited']) {
			$replaceExpressionButton->setAttribute('disabled', 'disabled');
		}
		$expressionRow[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expressionRow[] = $replaceExpressionButton;
	}
}
elseif ($this->data['input_method'] != IM_FORCED) {
	$inputMethodToggle = (new CButton(null, _('Expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_input_method").value=1;'.
			'document.getElementById("input_method").value='.(($this->data['input_method'] == IM_TREE) ? IM_ESTABLISHED : IM_TREE).';'.
			'document.forms["'.$triggersForm->getName().'"].submit();');
	$expressionRow[] = [BR(), $inputMethodToggle];
}
$triggersFormList->addRow(_('Expression'), $expressionRow);

// append expression table to form list
if ($this->data['input_method'] == IM_TREE) {
	$expressionTable = (new CTable())
		->setAttribute('style', 'min-width: 500px;')
		->setId('exp_list')
		->setHeader([
			$this->data['limited'] ? null : _('Target'),
			_('Expression'),
			_('Error'),
			$this->data['limited'] ? null : _('Action')
		]);

	$allowedTesting = true;
	if (!empty($this->data['eHTMLTree'])) {
		foreach ($this->data['eHTMLTree'] as $i => $e) {
			if (!$this->data['limited']) {
				$deleteUrl = (new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('javascript:'.
						' if (confirm('.CJs::encodeJson(_('Delete expression?')).')) {'.
							' delete_expression("'.$e['id'] .'");'.
							' document.forms["'.$triggersForm->getName().'"].submit();'.
						' }'
					);
				$triggerCheckbox = (new CCheckBox('expr_target_single', $e['id']))
					->setChecked($i == 0)
					->onClick('check_target(this);');
			}
			else {
				$triggerCheckbox = null;
			}

			if (!isset($e['expression']['levelErrors'])) {
				$errorImg = (new CImg('images/general/ok_icon.png', 'expression_no_errors'))
					->setHint(_('No errors found.'));
			}
			else {
				$allowedTesting = false;
				$errorImg = new CImg('images/general/error2.png', 'expression_errors');
				$errorTexts = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if (count($errorTexts) > 0) {
							array_push($errorTexts, BR());
						}
						array_push($errorTexts, $expVal, ':', $errTxt);
					}
				}

				$errorImg->setHint($errorTexts, 'left');
			}

			$errorColumn = (new CCol($errorImg))->addClass('center');

			// templated trigger
			if ($this->data['limited']) {
				// make all links inside inactive
				$listSize = count($e['list']);
				for ($i = 0; $i < $listSize; $i++) {
					if (gettype($e['list'][$i]) == 'object' && get_class($e['list'][$i]) == 'CSpan' && $e['list'][$i]->getAttribute('class') == 'link') {
						$e['list'][$i]->removeAttribute('class');
						$e['list'][$i]->onClick('');
					}
				}
			}

			$row = new CRow([$triggerCheckbox, $e['list'], $errorColumn, isset($deleteUrl) ? $deleteUrl : null]);
			$expressionTable->addRow($row);
		}
	}
	else {
		$allowedTesting = false;
		$this->data['outline'] = '';
	}

	$testButton = (new CButton('test_expression', _('Test')))
		->onClick('openWinCentered("tr_testexpr.php?expression=" + encodeURIComponent(this.form.elements["expression"].value),'.
		'"ExpressionTest", 850, 400, "titlebar=no, resizable=yes, scrollbars=yes"); return false;')
		->addClass(ZBX_STYLE_BTN_LINK);
	if (!$allowedTesting) {
		$testButton->setAttribute('disabled', 'disabled');
	}
	if (empty($this->data['outline'])) {
		$testButton->setAttribute('disabled', 'disabled');
	}

	$wrapOutline = new CSpan([$this->data['outline']]);
	$triggersFormList->addRow(SPACE, [
		$wrapOutline,
		BR(),
		BR(),
		(new CDiv([$expressionTable, $testButton]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	]);

	$inputMethodToggle = (new CButton(null, _('Close expression constructor')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->onClick('javascript: '.
			'document.getElementById("toggle_input_method").value=1;'.
			'document.getElementById("input_method").value='.IM_ESTABLISHED.';'.
			'document.forms["'.$triggersForm->getName().'"].submit();');
	$triggersFormList->addRow(SPACE, [$inputMethodToggle, BR()]);
}

$triggersFormList
	->addRow(_('Multiple PROBLEM events generation'),
		(new CCheckBox('type'))->setChecked($this->data['type'] == TRIGGER_MULT_EVENT_ENABLED)
	)
	->addRow(_('Description'),
		(new CTextArea('comments', $this->data['comments']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('URL'), (new CTextBox('url', $this->data['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Severity'), new CSeverity(['name' => 'priority', 'value' => $this->data['priority']]));

// append status to form list
if (empty($this->data['triggerid']) && empty($this->data['form_refresh'])) {
	$status = true;
}
else {
	$status = ($this->data['status'] == 0);
}
$triggersFormList->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked($status));

// append tabs to form
$triggersTab = new CTabView();
if (!$this->data['form_refresh']) {
	$triggersTab->setSelected(0);
}
$triggersTab->addTab('triggersTab', _('Trigger'), $triggersFormList);

/*
 * Dependencies tab
 */
$dependenciesFormList = new CFormList('dependenciesFormList');
$dependenciesTable = (new CTable())
	->setNoDataMessage(_('No dependencies defined.'))
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setHeader([_('Name'), _('Action')]);

foreach ($this->data['db_dependencies'] as $dependency) {
	$triggersForm->addVar('dependencies[]', $dependency['triggerid'], 'dependencies_'.$dependency['triggerid']);

	$depTriggerDescription = CHtml::encode(
		implode(', ', zbx_objectValues($dependency['hosts'], 'name')).NAME_DELIMITER.$dependency['description']
	);

	if ($dependency['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$description = (new CLink($depTriggerDescription, 'triggers.php?form=update&triggerid='.$dependency['triggerid']))
			->setAttribute('target', '_blank');
	}
	else {
		$description = $depTriggerDescription;
	}

	$row = new CRow([$description,
		(new CButton('remove', _('Remove')))
			->onClick('javascript: removeDependency("'.$dependency['triggerid'].'");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

	$row->setId('dependency_'.$dependency['triggerid']);
	$dependenciesTable->addRow($row);
}

$dependenciesFormList->addRow(
	_('Dependencies'),
	(new CDiv(
		[
			$dependenciesTable,
			(new CButton('bnt1', _('Add')))
				->onClick('return PopUp("popup.php?srctbl=triggers&srcfld1=triggerid&reference=deptrigger&multiselect=1'.
					'&with_triggers=1&noempty=1");')
				->addClass(ZBX_STYLE_BTN_LINK)
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);
$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormList);

// append buttons to form
if (!empty($this->data['triggerid'])) {
	$deleteButton = new CButtonDelete(_('Delete trigger?'), url_params(['form', 'hostid', 'triggerid']));
	if ($this->data['limited']) {
		$deleteButton->setAttribute('disabled', 'disabled');
	}

	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CSubmit('clone', _('Clone')),
			$deleteButton,
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

// append tabs to form
$triggersForm->addItem($triggersTab);

$widget->addItem($triggersForm);

return $widget;
