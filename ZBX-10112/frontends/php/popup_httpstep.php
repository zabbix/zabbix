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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Step of scenario');
$page['file'] = 'popup_httpstep.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>			array(T_ZBX_STR, O_MAND, P_SYS,	NOT_EMPTY,			null),
	'stepid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0,65535),	null),
	'list_name' =>		array(T_ZBX_STR, O_OPT, P_SYS,	NOT_EMPTY,			'(isset({add}) || isset({update})) && isset({stepid})'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY.KEY_PARAM(), 'isset({add}) || isset({update})', _('Name')),
	'url' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,			'isset({add}) || isset({update})', _('URL')),
	'posts' =>			array(T_ZBX_STR, O_OPT, null,	null,				null),
	'variables' =>		array(T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'),
	'headers' =>		array(T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'),
	'retrieve_mode' =>	array(T_ZBX_STR, O_OPT, null,	null,				null),
	'follow_redirects' => array(T_ZBX_STR, O_OPT, null,	null,				null),
	'timeout' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,65535),	'isset({add}) || isset({update})', _('Timeout')),
	'required' =>		array(T_ZBX_STR, O_OPT, null,	null,				null),
	'status_codes' =>	array(T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'),
	'templated' =>		array(T_ZBX_STR, O_OPT, null, 	null, null),
	'old_name'=>		array(T_ZBX_STR, O_OPT, null, 	null, null),
	'steps_names'=>		array(T_ZBX_STR, O_OPT, null, 	null, null),
	// actions
	'add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'update' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,				null)
);
check_fields($fields);


// render view
$httpPopupView = new CView('configuration.httpconf.popup');
$httpPopupView->render();
$httpPopupView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
