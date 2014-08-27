<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$this->data['cnf_wdgt']->addHeader(_('Regular expressions'));

$regExpForm = new CForm();
$regExpForm->setName('regularExpressionsForm');
$regExpForm->addItem(BR());

$regExpTable = new CTableInfo(_('No regular expressions found.'));
$regExpTable->setHeader(array(
	new CCheckBox('all_regexps', null, "checkAll('regularExpressionsForm', 'all_regexps', 'regexpids');"),
	_('Name'),
	_('Expressions')
));

$expressions = array();
$values = array();
foreach($this->data['db_exps'] as $exp) {
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
foreach($this->data['regexps'] as $regexpid => $regexp) {
	$regExpTable->addRow(array(
		new CCheckBox('regexpids['.$regexp['regexpid'].']', null, null, $regexp['regexpid']),
		new CLink($regexp['name'], 'adm.regexps.php?form=update'.'&regexpid='.$regexp['regexpid']),
		isset($expressions[$regexpid]) ? $expressions[$regexpid] : '-'
	));
}

$goBox = new CComboBox('action');

$goOption = new CComboItem('regexp.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected regular expressions?'));
$goBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "regexpids";');

$regExpTable->setFooter(new CCol(array($goBox, $goButton)));

$regExpForm->addItem($regExpTable);

return $regExpForm;
