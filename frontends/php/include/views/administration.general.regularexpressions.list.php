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

$form = new CForm();
$form->setName('regularExpressionsForm');

$regExpTable = new CTableInfo(_('No regular expressions found.'));
$regExpTable->setHeader(array(
	new CCheckBox('all_regexps', null, "checkAll('regularExpressionsForm', 'all_regexps', 'regexpids');"),
	_('Name'),
	_('Expressions')
));

$regExpWidget = new CWidget();
$regExpWidget->setTitle(_('Regular expressions'));

$generalComboBox = new CComboBox('configDropDown', 'adm.regexps.php', 'redirect(this.options[this.selectedIndex].value);');
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

$controls = new CList();
$controls->addItem($generalComboBox);
if (!isset($_REQUEST['form'])) {
	$controls->addItem(new CSubmit('form', _('New regular expression')));
}

$regExpForm = new CForm();
$regExpForm->cleanItems();

$regExpForm->addItem($controls);
$regExpWidget->setControls($regExpForm);

$expressions = array();
$values = array();
foreach($data['db_exps'] as $exp) {
	if (!isset($expressions[$exp['regexpid']])) {
		$values[$exp['regexpid']] = 1;
	}
	else {
		$values[$exp['regexpid']]++;
	}

	if (!isset($expressions[$exp['regexpid']])) {
		$expressions[$exp['regexpid']] = new CTable();
	}

	$expressions[$exp['regexpid']]->addRow(array(
		new CCol($values[$exp['regexpid']], 'top'),
		new CCol(' &raquo; ', 'top'),
		new CCol($exp['expression'], 'pre-wrap  break-lines'),
		new CCol(' ['.expression_type2str($exp['expression_type']).']', 'top')
	));
}
foreach($data['regexps'] as $regexpid => $regexp) {
	$regExpTable->addRow(array(
		new CCheckBox('regexpids['.$regexp['regexpid'].']', null, null, $regexp['regexpid']),
		new CLink($regexp['name'], 'adm.regexps.php?form=update'.'&regexpid='.$regexp['regexpid']),
		isset($expressions[$regexpid]) ? $expressions[$regexpid] : '-'
	));
}

// append table to form
$form->addItem(array($regExpTable, get_table_header(new CActionButtonList('action', 'regexpids', array(
	'regexp.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected regular expressions?'))
)))));

// append form to widget
$regExpWidget->addItem($form);

return $regExpWidget;
