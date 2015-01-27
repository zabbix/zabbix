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


require_once dirname(__FILE__).'/js/configuration.triggers.expression.js.php';

$expressionWidget = new CWidget(null, 'trigger-popup');

// create form
$expressionForm = new CForm();
$expressionForm->setName('expression');
$expressionForm->addVar('dstfrm', $this->data['dstfrm']);
$expressionForm->addVar('dstfld1', $this->data['dstfld1']);
$expressionForm->addVar('itemid', $this->data['itemid']);

if (!empty($this->data['parent_discoveryid'])) {
	$expressionForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create form list
$expressionFormList = new CFormList('expressionFormList');

// append item to form list
$item = array(
	new CTextBox('description', $this->data['description'], ZBX_TEXTBOX_STANDARD_SIZE, true),
	new CButton('select', _('Select'), 'return PopUp(\'popup.php?writeonly=1&dstfrm='.$expressionForm->getName().
		'&dstfld1=itemid&dstfld2=description&submitParent=1'.(!empty($this->data['parent_discoveryid']) ? '&normal_only=1' : '').
		'&srctbl=items&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');',
		'button-form'
	)
);
if (!empty($this->data['parent_discoveryid'])) {
	$item[] = new CButton('select', _('Select prototype'), 'return PopUp(\'popup.php?dstfrm='.$expressionForm->getName().
		'&dstfld1=itemid&dstfld2=description&submitParent=1'.url_param('parent_discoveryid', true).
		'&srctbl=item_prototypes&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');',
		'button-form'
	);
}

$expressionFormList->addRow(_('Item'), $item);

$functionComboBox = new CComboBox('expr_type', $this->data['expr_type'], 'submit()');
foreach ($this->data['functions'] as $id => $f) {
	$functionComboBox->addItem($id, $f['description']);
}
$expressionFormList->addRow(_('Function'), $functionComboBox);

if (isset($this->data['functions'][$this->data['selectedFunction']]['params'])) {
	foreach ($this->data['functions'][$this->data['selectedFunction']]['params'] as $paramId => $paramFunction) {
		$paramValue = isset($this->data['params'][$paramId]) ? $this->data['params'][$paramId] : null;

		if ($paramFunction['T'] == T_ZBX_INT) {
			$paramTypeElement = null;

			if ($paramId == 0
				|| ($paramId == 1
					&& (substr($this->data['expr_type'], 0, 6) == 'regexp'
						|| substr($this->data['expr_type'], 0, 7) == 'iregexp'
						|| (substr($this->data['expr_type'], 0, 3) == 'str' && substr($this->data['expr_type'], 0, 6) != 'strlen')))) {
				if (isset($paramFunction['M'])) {
					$paramTypeElement = new CComboBox('paramtype', $this->data['paramtype']);

					foreach ($paramFunction['M'] as $mid => $caption) {
						$paramTypeElement->addItem($mid, $caption);
					}
				}
				else {
					$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
					$paramTypeElement = SPACE._('Time');
				}
			}

			if ($paramId == 1
					&& (substr($this->data['expr_type'], 0, 3) != 'str' || substr($this->data['expr_type'], 0, 6) == 'strlen')
					&& substr($this->data['expr_type'], 0, 6) != 'regexp'
					&& substr($this->data['expr_type'], 0, 7) != 'iregexp') {
				$paramTypeElement = SPACE._('Time');
				$paramField = new CTextBox('params['.$paramId.']', $paramValue, 10);
			}
			else {
				$paramField = ($this->data['paramtype'] == PARAM_TYPE_COUNTS)
					? new CNumericBox('params['.$paramId.']', (int) $paramValue, 10)
					: new CTextBox('params['.$paramId.']', $paramValue, 10);
			}

			$expressionFormList->addRow($paramFunction['C'], array($paramField, $paramTypeElement));
		}
		else {
			$expressionFormList->addRow($paramFunction['C'],
				new CTextBox('params['.$paramId.']', $paramValue, $paramFunction['T'] == T_ZBX_DBL ? 10 : 30
			));
			$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
		}
	}
}
else {
	$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
}

$expressionFormList->addRow('N', new CTextBox('value', $this->data['value'], 10));

// append tabs to form
$expressionTab = new CTabView();
$expressionTab->addTab('expressionTab', _('Trigger expression condition'), $expressionFormList);
$expressionForm->addItem($expressionTab);

// append buttons to form
$expressionForm->addItem(makeFormFooter(
	new CSubmit('insert', _('Insert')),
	array(new CButtonCancel(url_params(array('parent_discoveryid', 'dstfrm', 'dstfld1')))
)));

$expressionWidget->addItem($expressionForm);

return $expressionWidget;
