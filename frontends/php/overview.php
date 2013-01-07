<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Overview');
$page['file'] = 'overview.php';
$page['hist_arg'] = array('groupid', 'type');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS', 0);
define('SHOW_DATA', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid'     => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,     null),
	'view_style'  => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null),
	'type'        => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null),
	'application' => array(T_ZBX_STR, O_OPT, P_SYS, null,	   null),
	'fullscreen'  => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null)
);
check_fields($fields);

/*
 * Display
 */
$data = array(
	'fullscreen' => get_request('fullscreen')
);

$data['view_style'] = get_request('view_style', CProfile::get('web.overview.view.style', STYLE_TOP));
CProfile::update('web.overview.view.style', $data['view_style'], PROFILE_TYPE_INT);

$data['type'] = get_request('type', CProfile::get('web.overview.type', SHOW_TRIGGERS));
CProfile::update('web.overview.type', $data['type'], PROFILE_TYPE_INT);

$data['pageFilter'] = new CPageFilter(array(
	'groups' => array(
		($data['type'] == SHOW_TRIGGERS ? 'with_monitored_triggers' : 'with_monitored_items') => true
	),
	'hosts' => array(
		'monitored_hosts' => true,
		($data['type'] == SHOW_TRIGGERS ? 'with_monitored_triggers' : 'with_monitored_items') => true
	),
	'applications' => array('templated' => false),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null),
	'application' => get_request('application', null)
));

$data['groupid'] = $data['pageFilter']->groupid;

// render view
$overviewView = new CView('monitoring.overview', $data);
$overviewView->render();
$overviewView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
