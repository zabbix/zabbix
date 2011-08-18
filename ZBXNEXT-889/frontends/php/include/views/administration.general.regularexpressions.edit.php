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

$oRegExpForm = new CForm();
$oRegExpForm->setName('regularExpressionsForm');
$oRegExpForm->addVar('form', $this->data['form']);
$oRegExpForm->addVar('form_refresh', $this->data['form_refresh']);
$oRegExpForm->addVar('config', get_request('config', 10));
$oRegExpForm->addVar('regexpid', get_request('regexpid'));

$oRegExpLeftTable = new CTable();
$oRegExpLeftTable->addRow(create_hat(_('Regular expression'), get_regexp_form(), null, 'hat_regexp'));

$oRegExpRightTable = new CTable();
$oRegExpRightTable->addRow(create_hat(_('Expressions'), get_expressions_tab(), null, 'hat_expressions'));

if (isset($_REQUEST['new_expression'])) {
	$oHatTable = create_hat(_('New expression'), get_expression_form(), null, 'hat_new_expression');
	$oHatTable->setAttribute('style', 'margin-top: 3px;');
	$oRegExpRightTable->addRow($oHatTable);
}

$oRegExpLeftColumn = new CCol($oRegExpLeftTable);
$oRegExpLeftColumn->setAttribute('valign','top');

$oRegExpRightColumn = new CCol($oRegExpRightTable);
$oRegExpRightColumn->setAttribute('valign','top');

$oRegExpOuterTable = new CTable();
$oRegExpOuterTable->addRow(array($oRegExpLeftColumn, new CCol('&nbsp;'), $oRegExpRightColumn));

$oRegExpForm->addItem($oRegExpOuterTable);

show_messages();

return $oRegExpForm;
?>
