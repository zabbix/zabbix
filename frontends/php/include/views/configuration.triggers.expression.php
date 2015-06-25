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

$expressionWidget = new CWidget();

// create form
$expressionForm = (new CForm())
	->setName('expression')
	->addVar('dstfrm', $this->data['dstfrm'])
	->addVar('dstfld1', $this->data['dstfld1'])
	->addVar('itemid', $this->data['itemid']);

if (!empty($this->data['parent_discoveryid'])) {
	$expressionForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create form list
$expressionFormList = new CFormList();

// append item to form list
$item = [
	(new CTextBox('description', $this->data['description'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('select', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp(\'popup.php?writeonly=1&dstfrm='.$expressionForm->getName().
			'&dstfld1=itemid&dstfld2=description&submitParent=1'.(!empty($this->data['parent_discoveryid']) ? '&normal_only=1' : '').
			'&srctbl=items&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');')
];

if (!empty($this->data['parent_discoveryid'])) {
	$item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$item[] = (new CButton('select', _('Select prototype')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp(\'popup.php?dstfrm='.$expressionForm->getName().
			'&dstfld1=itemid&dstfld2=description&submitParent=1'.url_param('parent_discoveryid', true).
			'&srctbl=item_prototypes&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');');
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
					$paramTypeElement = new CComboBox('paramtype', $this->data['paramtype'], null, $paramFunction['M']);
				}
				else {
					$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
					$paramTypeElement = _('Time');
				}
			}

			if ($paramId == 1
					&& (substr($this->data['expr_type'], 0, 3) != 'str' || substr($this->data['expr_type'], 0, 6) == 'strlen')
					&& substr($this->data['expr_type'], 0, 6) != 'regexp'
					&& substr($this->data['expr_type'], 0, 7) != 'iregexp') {
				$paramTypeElement = _('Time');
				$paramField = (new CTextBox('params['.$paramId.']', $paramValue))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
			}
			else {
				$paramField = ($this->data['paramtype'] == PARAM_TYPE_COUNTS)
					? (new CNumericBox('params['.$paramId.']', (int) $paramValue, 10))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
					: (new CTextBox('params['.$paramId.']', $paramValue))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
			}

			$expressionFormList->addRow($paramFunction['C'], [
				$paramField,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$paramTypeElement
			]);
		}
		else {
			$expressionFormList->addRow($paramFunction['C'],
				(new CTextBox('params['.$paramId.']', $paramValue))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
			$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
		}
	}
}
else {
	$expressionForm->addVar('paramtype', PARAM_TYPE_TIME);
}

$expressionFormList->addRow('N', (new CTextBox('value', $this->data['value']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH));

// append tabs to form
$expressionTab = (new CTabView())->addTab('expressionTab', _('Trigger expression condition'), $expressionFormList);

// append buttons to form
$expressionTab->setFooter(makeFormFooter(
	new CSubmit('insert', _('Insert')),
	[new CButtonCancel(url_params(['parent_discoveryid', 'dstfrm', 'dstfld1']))
]));

$expressionForm->addItem($expressionTab);
$expressionWidget->addItem($expressionForm);

return $expressionWidget;
