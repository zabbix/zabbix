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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
$this->data['cnf_wdgt']->addHeader(_('Regular expressions'));

$oRegExpForm = new CForm();
$oRegExpForm->setName('regularExpressionsForm');
$oRegExpForm->addVar('config', get_request('config', 10));
$oRegExpForm->addItem(BR());

$oRegExpTable = new CTableInfo();
$oRegExpTable->setHeader(array(new CCheckBox('all_regexps', null, "checkAll('regularExpressionsForm','all_regexps','regexpids');"), _('Name'), _('Expressions')));
$oRegExpTable->setFooter(new CCol(array(new CButtonQMessage('delete', _('Delete selected'), _('Delete selected regular expressions?')))));

$aCount = array();
$aExpressions = array();
foreach($this->data['db_exps'] as $aExp) {
	if (!isset($aExpressions[$aExp['regexpid']])) {
		$aCount[$aExp['regexpid']] = 1;
	}
	else {
		$aCount[$aExp['regexpid']]++;
	}
	if (!isset($aExpressions[$aExp['regexpid']])) {
		$aExpressions[$aExp['regexpid']] = new CTable();
	}
	$aExpressions[$aExp['regexpid']]->addRow(array($aCount[$aExp['regexpid']], ' &raquo; ', $aExp['expression'], ' ['.expression_type2str($aExp['expression_type']).']'));
}
foreach($this->data['regexps'] as $iRegexpid => $aRegexp) {
	$oRegExpTable->addRow(array(
		new CCheckBox('regexpids['.$aRegexp['regexpid'].']', null, null, $aRegexp['regexpid']),
		new CLink($aRegexp['name'], 'config.php?form=update'.url_param('config').'&regexpid='.$aRegexp['regexpid'].'#form'),
		isset($aExpressions[$iRegexpid]) ? $aExpressions[$iRegexpid] : '-'
	));
}

$oRegExpForm->addItem($oRegExpTable);

return $oRegExpForm;
?>
