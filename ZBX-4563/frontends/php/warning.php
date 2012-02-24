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

$page['title'] = _('Warning');
$page['file'] = 'warning.php';

define('ZBX_PAGE_DO_REFRESH', 1);
if (!defined('PAGE_HEADER_LOADED')) {
	define('ZBX_PAGE_NO_MENU', 1);
}

$refresh_rate = 30; // seconds
?>
<?php
//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'warning_msg' =>	array(T_ZBX_STR, O_OPT, null,		 null, null),
	'message' =>		array(T_ZBX_STR, O_OPT, null,		 null, null),
	'retry' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null)
);
check_fields($fields, false);
?>
<?php
if (isset($_REQUEST['cancel'])) {
	zbx_unsetcookie('ZBX_CONFIG');
	redirect('index.php');
}
// clear_messages();
CWebUser::$data['refresh'] = $refresh_rate;

require_once('include/page_header.php');

unset($USER_DETAILS);

$table = new CTable(null, 'warningTable');
$table->setAlign('center');
$table->setAttribute('style', 'width: 480px; margin-top: 100px;');
$table->setHeader(array(new CCol(_('Zabbix').SPACE.ZABBIX_VERSION, 'left')),'header');
$table->addRow(SPACE);

$img = new CImg('./images/general/warning16r.gif', 'warning', 16, 16, 'img');
$img->setAttribute('style', 'border-width: 0px; vertical-align: bottom;');

$msg = new CSpan(bold(SPACE.(isset($_REQUEST['warning_msg']) ? $_REQUEST['warning_msg'] : _('Zabbix is temporarily unavailable').'!')));
$msg->setAttribute('style', 'line-height: 20px; vertical-align: top;');

$table->addRow(new CCol(array($img, $msg), 'center'));
$table->addRow(SPACE);
$table->setFooter(new CCol(new CButton('retry', _('Retry'), 'javascript: document.location.reload();'), 'left'), 'footer');
$table->show();

zbx_add_post_js('setTimeout("document.location.reload();",'.($refresh_rate * 1000).');');
echo SBR;

require_once('include/page_footer.php');
?>
