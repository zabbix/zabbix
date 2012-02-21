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
require_once('include/config.inc.php');
require_once('include/forms.inc.php');

$page['title'] = _('Installation');
$page['file'] = 'instal.php';

require_once('include/page_header.php');
require_once('setup.php');
require_once('include/page_footer.php');

/*******************************/
/* THIS POINT NEVER BE REACHED */
/*******************************/
// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'install' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'update' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null)
);
check_fields($fields);

if (isset($_REQUEST['install'])) {
	jsRedirect('setup.php');
	exit();
}
elseif (isset($_REQUEST['update'])) {
	error('*UNDER CONSTRUCTION*');
}

$form = new CFormTable(_('Installation/Update'));
$form->setHelp('install_source_web.php');
$form->addRow(
	array(bold(_('NEW INSTALLATION')), BR(), BR(), bold(_('Description')), BR(), 'Not implemented yet!', BR(),BR(),BR()),
	new CSubmit('install', _('New installation'))
);
$form->addRow(
	array(bold(_('UPDATE')), BR(), BR(), bold(_('Description')), BR(), 'Not implemented yet!', BR(), BR(), BR()),
	new CSubmit('update', _('Update'))
);
$form->show();

require_once('include/page_footer.php');
?>
