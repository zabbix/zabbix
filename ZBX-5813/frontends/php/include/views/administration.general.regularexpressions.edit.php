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

$regExpWidget = new CWidget();

$generalComboBox = new CComboBox('configDropDown', 'adm.regexps.php',
	'redirect(this.options[this.selectedIndex].value);'
);
$generalComboBox->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeping'),
	'adm.images.php' => _('Images'),
	'adm.iconmapping.php' => _('Icon mapping'),
	'adm.regexps.php' => _('Regular expressions'),
	'adm.macros.php' => _('Macros'),
	'adm.valuemapping.php' => _('Value mapping'),
	'adm.workingtime.php' => _('Working time'),
	'adm.triggerseverities.php' => _('Trigger severities'),
	'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));

$headerForm = new CForm();
$headerForm->cleanItems();
$headerForm->addItem($generalComboBox);

$regExpWidget->addPageHeader(_('CONFIGURATION OF REGULAR EXPRESSIONS'), $headerForm);

$form = new CForm();
$form->attr('id', 'zabbixRegExpForm');
$form->addVar('form', 1);
$form->addVar('regexpid', $data['regexpid']);

zbx_add_post_js('zabbixRegExp.addExpressions('.CJs::encodeJson(array_values($data['expressions'])).');');

/*
 * Expressions tab
 */
$exprTab = new CFormList('exprTab');
$nameTextBox = new CTextBox('name', $data['name'], ZBX_TEXTBOX_STANDARD_SIZE, false, 128);
$nameTextBox->attr('autofocus', 'autofocus');
$exprTab->addRow(_('Name'), $nameTextBox);

$exprTable = new CTable(null, 'formElementTable formWideTable');
$exprTable->attr('id', 'exprTable');
$exprTable->setHeader(array(
	_('Expression'),
	new CCol(_('Expression type'), 'nowrap'),
	new CCol(_('Case sensitive'), 'nowrap'),
	SPACE
));
$exprTable->setFooter(new CButton('add', _('Add'), null, 'link_menu exprAdd'));
$exprTab->addRow(_('Expressions'), new CDiv($exprTable, 'inlineblock border_dotted objectgroup'));

$exprForm = new CTable(null, 'formElementTable');
$exprForm->addRow(array(_('Expression'), new CTextBox('expressionNew', null, ZBX_TEXTBOX_STANDARD_SIZE)));
$exprForm->addRow(array(_('Expression type'), new CComboBox('typeNew', null, null, expression_type2str())));
$exprForm->addRow(array(_('Delimiter'), new CComboBox('delimiterNew', null, null, expressionDelimiters())), null, 'delimiterNewRow');
$exprForm->addRow(array(_('Case sensitive'), new CCheckBox('case_sensitiveNew')));
$exprFormFooter = array(
	new CButton('saveExpression', _('Add'), null, 'link_menu'),
	SPACE,
	new CButton('cancelExpression', _('Cancel'), null, 'link_menu')
);
$exprTab->addRow(null, new CDiv(array($exprForm, $exprFormFooter), 'objectgroup inlineblock border_dotted'), true, 'exprForm');

/*
 * Test tab
 */
$testTab = new CFormList('testTab');
$testTab->addRow(_('Test string'), new CTextArea('test_string', $data['test_string']));
$preloaderDiv = new CDiv(null, 'preloader', 'testPreloader');
$preloaderDiv->addStyle('display: none');
$testTab->addRow(SPACE, array(new CButton('testExpression', _('Test expressions')), $preloaderDiv));

$tabExp = new CTableInfo(null);
$tabExp->attr('id', 'testResultTable');
$tabExp->setHeader(array(_('Expression'), _('Expression type'), _('Result')));
$testTab->addRow(_('Result'), $tabExp);

$regExpView = new CTabView();
if (!$data['form_refresh']) {
	$regExpView->setSelected(0);
}
$regExpView->addTab('expr', _('Expressions'), $exprTab);
$regExpView->addTab('test', _('Test'), $testTab);
$form->addItem($regExpView);

// footer
if (isset($data['regexpid'])) {
	$form->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete regular expression?'),
				url_param('regexpid').url_param('regexp.massdelete', false, 'action')
			),
			new CButtonCancel()
		)
	));
}
else {
	$form->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		array(new CButtonCancel())
	));
}

// append form to widget
$regExpWidget->addItem($form);

return $regExpWidget;
