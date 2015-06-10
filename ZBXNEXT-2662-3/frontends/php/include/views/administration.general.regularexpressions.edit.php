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


require_once dirname(__FILE__).'/js/adm.regexprs.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Regular expressions'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.regexps.php')))
	);

$form = new CForm();
$form->setId('zabbixRegExpForm');
$form->addVar('form', 1);
$form->addVar('regexpid', $data['regexpid']);

zbx_add_post_js('zabbixRegExp.addExpressions('.CJs::encodeJson(array_values($data['expressions'])).');');

/*
 * Expressions tab
 */
$exprTab = new CFormList('exprTab');
$nameTextBox = new CTextBox('name', $data['name'], ZBX_TEXTBOX_STANDARD_SIZE, false, 128);
$nameTextBox->setAttribute('autofocus', 'autofocus');
$exprTab->addRow(_('Name'), $nameTextBox);

$exprTable = (new CTable())
	->addClass('formElementTable')
	->addClass('formWideTable')
	->setId('exprTable')
	->setHeader([
		_('Expression'),
		(new CCol(_('Expression type')))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol(_('Case sensitive')))->addClass(ZBX_STYLE_NOWRAP),
		SPACE
	])
	->setFooter(
		(new CButton('add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('exprAdd')
	);
$exprTab->addRow(
	_('Expressions'),
	(new CDiv($exprTable))
		->addClass('inlineblock')
		->addClass('border_dotted')
		->addClass('objectgroup')
);

$exprForm = (new CTable())
	->addClass('formElementTable')
	->addRow([_('Expression'), new CTextBox('expressionNew', null, ZBX_TEXTBOX_STANDARD_SIZE)])
	->addRow([_('Expression type'), new CComboBox('typeNew', null, null, expression_type2str())])
	->addRow([_('Delimiter'), new CComboBox('delimiterNew', null, null, expressionDelimiters())], null, 'delimiterNewRow')
	->addRow([_('Case sensitive'), new CCheckBox('case_sensitiveNew')]);

$exprFormFooter = [
	(new CButton('saveExpression', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
	SPACE,
	(new CButton('cancelExpression', _('Cancel')))->addClass(ZBX_STYLE_BTN_LINK)
];
$exprTab->addRow(
	null,
	(new CDiv([$exprForm, $exprFormFooter]))
		->addClass('objectgroup')
		->addClass('inlineblock')
		->addClass('border_dotted'),
	true,
	'exprForm'
);

/*
 * Test tab
 */
$testTab = new CFormList('testTab');
$testTab->addRow(_('Test string'), new CTextArea('test_string', $data['test_string']));
$preloaderDiv = (new CDiv())
	->addClass('preloader')
	->setId('testPreloader')
	->addStyle('display: none');
$testTab->addRow(SPACE, [new CButton('testExpression', _('Test expressions')), $preloaderDiv]);

$tabExp = (new CTableInfo())
	->setId('testResultTable')
	->setHeader([_('Expression'), _('Expression type'), _('Result')]);
$testTab->addRow(_('Result'), $tabExp);

$regExpView = new CTabView();
if (!$data['form_refresh']) {
	$regExpView->setSelected(0);
}
$regExpView->addTab('expr', _('Expressions'), $exprTab);
$regExpView->addTab('test', _('Test'), $testTab);

// footer
if (isset($data['regexpid'])) {
	$regExpView->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete regular expression?'),
				url_param('regexpid').url_param('regexp.massdelete', false, 'action')
			),
			new CButtonCancel()
		]
	));
}
else {
	$regExpView->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($regExpView);

$widget->addItem($form);

return $widget;
