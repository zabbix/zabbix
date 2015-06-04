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

$regExpTable = new CTableInfo();
$regExpTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_regexps', null, "checkAll('regularExpressionsForm', 'all_regexps', 'regexpids');")))->
		addClass('cell-width'),
	_('Name'),
	_('Expressions')
]);

$regExpWidget = (new CWidget())->setTitle(_('Regular expressions'));

$controls = new CList();
$controls->addItem(new CComboBox('configDropDown', 'adm.regexps.php',
	'redirect(this.options[this.selectedIndex].value);',
	[
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
	]
));
$controls->addItem(new CSubmit('form', _('New regular expression')));

$regExpForm = new CForm();
$regExpForm->cleanItems();

$regExpForm->addItem($controls);
$regExpWidget->setControls($regExpForm);

$expressions = [];
$values = [];
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

	$expressions[$exp['regexpid']]->addRow([
		new CCol($values[$exp['regexpid']]),
		new CCol(' &raquo; '),
		new CCol($exp['expression']),
		new CCol(' ['.expression_type2str($exp['expression_type']).']')
	]);
}
foreach($data['regexps'] as $regexpid => $regexp) {
	$regExpTable->addRow([
		new CCheckBox('regexpids['.$regexp['regexpid'].']', null, null, $regexp['regexpid']),
		new CLink($regexp['name'], 'adm.regexps.php?form=update'.'&regexpid='.$regexp['regexpid']),
		isset($expressions[$regexpid]) ? $expressions[$regexpid] : ''
	]);
}

// append table to form
$form->addItem([
	$regExpTable,
	new CActionButtonList('action', 'regexpids', [
		'regexp.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected regular expressions?')]
	])
]);

// append form to widget
$regExpWidget->addItem($form);

return $regExpWidget;
