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


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Warning');
$page['file'] = 'warning.php';

define('ZBX_PAGE_DO_REFRESH', 1);
if (!defined('PAGE_HEADER_LOADED') && !defined('ZBX_PAGE_NO_MENU')) {
	define('ZBX_PAGE_NO_MENU', 1);
}

$refresh_rate = 30; // seconds

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'warning_msg' =>	array(T_ZBX_STR, O_OPT, null,			null, null),
	'retry' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null, null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null, null)
);
check_fields($fields, false);

if (isset($_REQUEST['cancel'])) {
	zbx_unsetcookie('ZBX_CONFIG');
	redirect('index.php');
}

CWebUser::$data['refresh'] = $refresh_rate;

require_once dirname(__FILE__).'/include/page_header.php';

unset($USER_DETAILS);

$msg = isset($_REQUEST['warning_msg']) ? $_REQUEST['warning_msg'] : _('Zabbix is temporarily unavailable!');

$warning = new CWarning(_('Zabbix').SPACE.ZABBIX_VERSION, $msg);
$warning->setAlignment('center');
$warning->setAttribute('style', 'margin-top: 100px;');
$warning->setPaddings(SPACE);
$warning->setButtons(new CButton('retry', _('Retry'), 'javascript: document.location.reload();', 'formlist'));
$warning->show();

zbx_add_post_js('setTimeout("document.location.reload();", '.($refresh_rate * 1000).');');
echo SBR;

require_once dirname(__FILE__).'/include/page_footer.php';
