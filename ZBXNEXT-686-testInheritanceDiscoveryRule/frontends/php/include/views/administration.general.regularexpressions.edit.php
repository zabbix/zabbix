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
$nameTextBox = new CTextBox('name', $this->get('name'), ZBX_TEXTBOX_STANDARD_SIZE, null, 128);
$nameTextBox->attr('autofocus', 'autofocus');
$exprTab->addRow(_('Name'), $nameTextBox);

$exprTable = new CTable(null, 'formElementTable formWideTable');
$exprTable->attr('id', 'exprTable');

$exprTable->setHeader(array(
	_('Expression'),
	_('Expression type'),
	_('Case sensitive'),
	SPACE
));
$exprTable->setFooter(new CButton('add', _('Add'), null, 'link_menu exprAdd'));

zbx_add_post_js('zabbixRegExp.addExpressions('.CJs::encodeJson(array_values($this->get('expressions'))).')');

$exprTab->addRow(_('Expressions'), new CDiv($exprTable, 'inlineblock border_dotted objectgroup'));


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
$preloaderDiv = new CDiv(null, 'preloader', 'testPreloader');
$preloaderDiv->addStyle('display:none');
$testTab->addRow(SPACE, array(new CButton('testExpression', _('Test expressions')), $preloaderDiv));

$tabExp = new CTableInfo(null);
$tabExp->attr('id', 'testResultTable');
$tabExp->setHeader(array(_('Expression'), _('Expression type'), _('Result')));
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
		new CButtonDelete(_('Delete regular expression?'), url_param('form').url_param('regexpid').url_param('delete', false, 'go'))
	);
}
$form->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), $secondaryActions));

return $form;
