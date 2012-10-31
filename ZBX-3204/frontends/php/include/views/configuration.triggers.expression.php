<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/js/configuration.triggers.expression.js.php';

$expressionWidget = new CWidget();

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
	new CTextBox('description', $this->data['description'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
	new CButton('select', _('Select'), 'return PopUp(\'popup.php?dstfrm='.$expressionForm->getName().
		'&dstfld1=itemid&dstfld2=description&submitParent=1'.(!empty($this->data['parent_discoveryid']) ? '&normal_only=1' : '').
		'&srctbl=items&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');',
		'formlist'
	)
);
if (!empty($this->data['parent_discoveryid'])) {
	$item[] = new CButton('select', _('Select prototype'), 'return PopUp(\'popup.php?dstfrm='.$expressionForm->getName().
		'&dstfld1=itemid&dstfld2=description&submitParent=1'.url_param('parent_discoveryid', true).
		'&srctbl=prototypes&srcfld1=itemid&srcfld2=name\', 0, 0, \'zbx_popup_item\');',
		'formlist'
	);
}
$expressionFormList->addRow(_('Item'), $item);

// append function to form list
$functionComboBox = new CComboBox('expr_type', $this->data['expr_type'], 'submit()');
$functionComboBox->addStyle('width: 605px;');
foreach ($this->data['functions'] as $id => $f) {
	// if user has selected an item, we are filtering out the triggers that can't work with it
	if (empty($this->data['itemValueType']) || !empty($f['allowed_types'][$this->data['itemValueType']])) {
		$functionComboBox->addItem($id, $f['description']);
	}
}
$expressionFormList->addRow(_('Function'), $functionComboBox);
if (isset($this->data['functions'][$this->data['function'].'['.$this->data['operator'].']']['params'])) {
	foreach ($this->data['functions'][$this->data['function'].'['.$this->data['operator'].']']['params'] as $pid => $pf) {
		$paramIsReadonly = 'no';
		$paramTypeElement = null;
		$paramValue = isset($this->data['param'][$pid]) ? $this->data['param'][$pid] : null;

		if ($pf['T'] == T_ZBX_INT) {
			if ($pid == 0 || (substr($this->data['expr_type'], 0, 5) == 'count' && $pid == 1)) {
				if (isset($pf['M'])) {
					if (is_array($pf['M'])) {
						$paramTypeElement = new CComboBox('paramtype', $this->data['paramtype']);
						foreach ($pf['M'] as $mid => $caption) {
							$paramTypeElement->addItem($mid, $caption);
						}
						if (substr($this->data['expr_type'], 0, 4) == 'last' || substr($this->data['expr_type'], 0, 6) == 'strlen') {
							$paramIsReadonly = 'yes';
						}
					}
					elseif ($pf['M'] == PARAM_TYPE_SECONDS) {
						$expressionForm->addVar('paramtype', PARAM_TYPE_SECONDS);
						$paramTypeElement = SPACE._('Seconds');
					}
					elseif ($pf['M'] == PARAM_TYPE_COUNTS) {
						$expressionForm->addVar('paramtype', PARAM_TYPE_COUNTS);
						$paramTypeElement = SPACE._('Count');
					}
				}
				else {
					$expressionForm->addVar('paramtype', PARAM_TYPE_SECONDS);
					$paramTypeElement = SPACE._('Seconds');
				}
			}
			if ($pid == 1 && substr($this->data['expr_type'], 0, 5) != 'count') {
				$paramTypeElement = SPACE._('Seconds');
			}
			$expressionFormList->addRow($pf['C'].' ', array(new CNumericBox('param['.$pid.']', $paramValue, 10, $paramIsReadonly), $paramTypeElement));
		}
		else {
			$expressionFormList->addRow($pf['C'], new CTextBox('param['.$pid.']', $paramValue, 30));
			$expressionForm->addVar('paramtype', PARAM_TYPE_SECONDS);
		}
	}
}
else {
	$expressionForm->addVar('paramtype', PARAM_TYPE_SECONDS);
	$expressionForm->addVar('param', 0);
}
$expressionFormList->addRow('N', new CTextBox('value', $this->data['value'], 10));

// append tabs to form
$expressionTab = new CTabView();
$expressionTab->addTab('expressionTab', _('Trigger expression condition'), $expressionFormList);
$expressionForm->addItem($expressionTab);

// append buttons to form
$expressionForm->addItem(makeFormFooter(array(new CSubmit('insert', _('Insert'))), array(new CButtonCancel(url_param('parent_discoveryid').url_param('dstfrm').url_param('dstfld1')))));

$expressionWidget->addItem($expressionForm);
return $expressionWidget;
?>
