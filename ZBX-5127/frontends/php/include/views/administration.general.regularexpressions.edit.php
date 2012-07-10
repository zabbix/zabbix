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


require_once dirname(__FILE__).'/js/adm.regexprs.edit.js.php';

$form = new CForm();
$form->attr('id', 'zabbixRegExpForm');
$form->addVar('form', 1);
$form->addVar('regexpid', $this->data['regexpid']);


// Expressions tab
$exprTab = new CFormList('exprTab');
$exprTab->addRow(_('Name'), new CTextBox('name', $this->get('name'), ZBX_TEXTBOX_STANDARD_SIZE, null, 128));

$exprTable = new CTable(null, 'formElementTable');
$exprTable->attr('id', 'exprTable');
$exprTable->addStyle('min-width: 600px');

$exprTable->setHeader(array(
	_('Expression'),
	_('Expected result'),
	_('Case sensitive'),
	SPACE
));
$exprTable->setFooter(new CButton('add', _('Add'), null, 'link_menu exprAdd'));

zbx_add_post_js('zabbixRegExp.addExpressions('.CJs::encodeJson(array_values($this->get('expressions'))).')');

$exprTab->addRow(_('Expressions'), new CDiv($exprTable, 'inlineblock border_dotted'));


$exprForm = new CTable(null, 'formElementTable');

$exprForm->addRow(array(_('Expression'), new CTextBox('expressionNew', null, ZBX_TEXTBOX_STANDARD_SIZE)));
$exprForm->addRow(array(_('Expression type'), new CComboBox('typeNew', null, null, expression_type2str())));
$exprForm->addRow(array(_('Delimiter'), new CComboBox('delimiterNew', null, null, expressionDelimiters())), null, 'delimiterNewRow');
$exprForm->addRow(array(_('Case sensitive'), new CCheckBox('case_sensitiveNew')));
$exprFormFooter = array(
	new Cbutton('saveExpression', _('Add'), null, 'link_menu'),
	SPACE,
	new Cbutton('cancelExpression', _('Cancel'), null, 'link_menu')
);
$exprTabDiv = new CDiv(array($exprForm, $exprFormFooter), 'objectgroup inlineblock border_dotted', 'exprForm');
$exprTabDiv->addStyle('display: none;');
$exprTab->addRow(SPACE, $exprTabDiv);

// Test tab
$testTab = new CFormList('testTab');
$testTab->addRow(_('Test string'), new CTextArea('test_string', $this->get('test_string')));
$testTab->addRow(SPACE, new CButton('testExpr', _('Test regular expressions'), null, 'link_menu'));

$tabExp = new CTableInfo();
$tabExp->setHeader(array(_('Expression'), _('Result'), _('Expected result'), _('Final result')));




$final_result = !empty($test_string);
foreach($this->get('expressions') as $id => $expression){
	$results = array();
	$paterns = array($expression['expression']);

	if(!empty($test_string)){
		if($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED){
			$paterns = explode($expression['exp_delimiter'],$expression['expression']);
		}

		if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_TRUE,EXPRESSION_TYPE_FALSE))){
			if($expression['case_sensitive'])
				$results[$id] = preg_match('/'.$paterns[0].'/',$test_string);
			else
				$results[$id] = preg_match('/'.$paterns[0].'/i',$test_string);

			if($expression['expression_type'] == EXPRESSION_TYPE_TRUE)
				$final_result &= $results[$id];
			else
				$final_result &= !$results[$id];
		}
		else{
			$results[$id] = true;

			$tmp_result = false;
			if($expression['case_sensitive']){
				foreach($paterns as $pid => $patern){
					$tmp_result |= (zbx_strstr($test_string,$patern) !== false);
				}
			}
			else{
				foreach($paterns as $pid => $patern){
					$tmp_result |= (zbx_stristr($test_string,$patern) !== false);
				}
			}

			if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED)))
				$results[$id] &= $tmp_result;
			else if($expression['expression_type'] == EXPRESSION_TYPE_NOT_INCLUDED){
				$results[$id] &= !$tmp_result;
			}
			$final_result &= $results[$id];
		}
	}

	if(isset($results[$id]) && $results[$id])
		$exp_res = new CSpan(_('TRUE'), 'green bold');
	else
		$exp_res = new CSpan(_('FALSE'), 'red bold');

	$expec_result = expression_type2str($expression['expression_type']);
	if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
		$expec_result.=' ('._('Delimiter')."='".$expression['exp_delimiter']."')";

	$tabExp->addRow(array(
		$expression['expression'],
		$exp_res,
		$expec_result,
		'asd'
	));

}

$td = new CCol(_('Combined result'), 'bold');
$td->setColSpan(3);

if ($final_result) {
	$final_result = new CSpan(_('TRUE'), 'green bold');
}
else {
	$final_result = new CSpan(_('FALSE'), 'red bold');
}

$tabExp->addRow(array(
	$td,
	$final_result
));

$testTab->addRow(_('Result'), $tabExp);



$regExpView = new CTabView();
$regExpView->addTab('expr', _('Expressions'), $exprTab);
$regExpView->addTab('test', _('Test'), $testTab);
$form->addItem($regExpView);


// footer
$secondaryActions = array(new CButtonCancel());
if (isset($this->data['regexpid'])) {
	array_unshift($secondaryActions,
		new CSubmit('clone', _('Clone')),
		new CButtonDelete(_('Delete regular expression?'), url_param('form').url_param('regexpid'))
	);
}
$form->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), $secondaryActions));

return $form;
