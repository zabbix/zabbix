<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('form', $this->data['form'])
	->addVar('parent_discoveryid', $this->data['parent_discoveryid'])
	->addVar('input_method', $this->data['input_method'])
	->addVar('toggle_input_method', '')
	->addVar('remove_expression', '');

if ($this->data['triggerid'] !== null) {
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
$expressionTextBox = (new CTextArea(
	$this->data['expression_field_name'],
	$this->data['expression_field_value'],
	[
		'readonly' => $this->data['expression_field_readonly']
	]
))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if ($this->data['expression_field_readonly']) {
	$triggersForm->addVar('expression', $this->data['expression']);
}

$addExpressionButton = (new CButton('insert', ($this->data['input_method'] == IM_TREE) ? _('Edit') : _('Add')))
	->addClass(ZBX_STYLE_BTN_GREY)
	->onClick(
		'return PopUp("popup_trexpr.php?dstfrm='.$triggersForm->getName().
			'&dstfld1='.$this->data['expression_field_name'].'&srctbl=expression'.url_param('parent_discoveryid').
			'&srcfld1=expression'.
			'&expression=" + encodeURIComponent(jQuery(\'[name="'.$this->data['expression_field_name'].'"]\').val()));'
	);
if ($this->data['limited']) {
	$addExpressionButton->setAttribute('disabled', 'disabled');
}
$expressionRow = [$expressionTextBox, (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), $addExpressionButton];

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
		// Append "Add" button.
		$expressionRow[] = (new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "add_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
	}
	else {
		// Append "And" button.
		$expressionRow[] = (new CSimpleButton(_('And')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "and_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Or" button.
		$expressionRow[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expressionRow[] = (new CSimpleButton(_('Or')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "or_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);

		// Append "Replace" button.
		$expressionRow[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
		$expressionRow[] = (new CSimpleButton(_('Replace')))
			->onClick('javascript: submitFormWithParam("'.$triggersForm->getName().'", "replace_expression", "1");')
			->addClass(ZBX_STYLE_BTN_GREY)
			->setEnabled(!$data['limited']);
	}
}
elseif ($this->data['input_method'] != IM_FORCED) {
	$inputMethodToggle = (new CSimpleButton(_('Expression constructor')))
		->onClick('javascript: '.
			'document.getElementById("toggle_input_method").value=1;'.
			'document.getElementById("input_method").value='.(($this->data['input_method'] == IM_TREE) ? IM_ESTABLISHED : IM_TREE).';'.
			'document.forms["'.$triggersForm->getName().'"].submit();')
		->addClass(ZBX_STYLE_BTN_LINK);
	$expressionRow[] = [BR(), $inputMethodToggle];
}
$triggersFormList->addRow(_('Expression'), $expressionRow);

// append expression table to form list
if ($this->data['input_method'] == IM_TREE) {
	$expressionTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setId('exp_list')
		->setHeader([
			$this->data['limited'] ? null : _('Target'),
			_('Expression'),
			$this->data['limited'] ? null : _('Action'),
			_('Info')
		]);

	$allowedTesting = true;
	if (!empty($this->data['eHTMLTree'])) {
		foreach ($this->data['eHTMLTree'] as $i => $e) {
			if (!isset($e['expression']['levelErrors'])) {
				$errorImg = '';
			}
			else {
				$allowedTesting = false;
				$errors = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if ($errors) {
							$errors[] = BR();
						}
						$errors[] = $expVal.':'.$errTxt;
					}
				}

				$errorImg = makeErrorIcon($errors);
			}

			// templated trigger
			if ($this->data['limited']) {
				// make all links inside inactive
				foreach ($e['list'] as &$obj) {
					if (gettype($obj) == 'object' && get_class($obj) == 'CSpan'
							&& $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
						$obj->removeAttribute('class');
						$obj->onClick(null);
					}
				}
				unset($obj);
			}

			$expressionTable->addRow(
				new CRow([
					!$this->data['limited']
						? (new CCheckBox('expr_target_single',$e['id']))
							->setChecked($i == 0)
							->onClick('check_target(this);')
						: null,
					$e['list'],
					!$this->data['limited']
						? (new CCol(
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->onClick('javascript:'.
									' if (confirm('.CJs::encodeJson(_('Delete expression?')).')) {'.
										' delete_expression("'.$e['id'] .'");'.
										' document.forms["'.$triggersForm->getName().'"].submit();'.
									' }'
								)
						))->addClass(ZBX_STYLE_NOWRAP)
						: null,
					$errorImg
				])
			);
		}
	}
	else {
		$allowedTesting = false;
		$this->data['outline'] = '';
	}

	$testButton = (new CButton('test_expression', _('Test')))
		->onClick('openWinCentered("tr_testexpr.php?expression=" + encodeURIComponent(this.form.elements["expression"].value),'.
			'"ExpressionTest", 950, 650, "titlebar=no, resizable=yes, scrollbars=yes"); return false;')
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
		(new CDiv([$expressionTable, $testButton]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	]);

	$inputMethodToggle = (new CSimpleButton(_('Close expression constructor')))
		->onClick('javascript: '.
			'document.getElementById("toggle_input_method").value=1;'.
			'document.getElementById("input_method").value='.IM_ESTABLISHED.';'.
			'document.forms["'.$triggersForm->getName().'"].submit();')
		->addClass(ZBX_STYLE_BTN_LINK);
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
	->addRow(_('Severity'), new CSeverity(['name' => 'priority', 'value' => (int) $this->data['priority']]));

// append status to form list
if (empty($this->data['triggerid']) && empty($this->data['form_refresh'])) {
	$status = true;
}
else {
	$status = ($this->data['status'] == 0);
}
$triggersFormList->addRow(_('Enabled'),
	(new CCheckBox('status'))->setChecked($status)
);

// append tabs to form
$triggersTab = new CTabView();
if (!$this->data['form_refresh']) {
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

foreach ($this->data['db_dependencies'] as $dependency) {
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
				->onClick('return PopUp("popup.php?srctbl=triggers&srcfld1=triggerid&reference=deptrigger'.
					'&multiselect=1&with_triggers=1&normal_only=1&noempty=1");')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_trigger_prototype', _('Add prototype')))
				->onClick('return PopUp("popup.php?srctbl=trigger_prototypes&srcfld1=triggerid&reference=deptrigger'.
					url_param('parent_discoveryid').'&multiselect=1");')
				->addClass(ZBX_STYLE_BTN_LINK)
		])
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
$triggersTab->addTab('dependenciesTab', _('Dependencies'), $dependenciesFormList);

// append buttons to form
if (!empty($this->data['triggerid'])) {
	$deleteButton = new CButtonDelete(_('Delete trigger prototype?'),
		url_params(['form', 'triggerid', 'parent_discoveryid'])
	);

	if ($this->data['limited']) {
		$deleteButton->setAttribute('disabled', 'disabled');
	}

	$triggersTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
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
