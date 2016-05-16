<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$page['title'] = _('Screens');
$page['file'] = 'rsm.screens.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'export' =>			array(T_ZBX_INT, O_OPT,		P_ACT,	null,			null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'tld' =>			array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'filter_year' =>	array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'filter_month' =>	array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'item_key' =>		array(T_ZBX_STR, O_OPT,		P_SYS,	DB_ID,			null),
	'type' =>			array(T_ZBX_INT, O_OPT,		null,	IN('0,1,2'),	null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'favref' =>			array(T_ZBX_STR, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.screens.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Filter
 */
if (!array_key_exists('tld', $_REQUEST) || !array_key_exists('filter_year', $_REQUEST)
		|| !array_key_exists('filter_month', $_REQUEST) || !array_key_exists('type', $_REQUEST)
		|| !array_key_exists('item_key', $_REQUEST)) {
	show_error_message(_('Incorrect input parameters.'));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$year = date('Y', time());
$month = date('m', time());

if ($month == 1) {
	$year--;
	$month = 12;
}
else {
	$month--;
}

$data = array(
	'filter_year' => get_request('filter_year'),
	'filter_month' => get_request('filter_month'),
	'type' => get_request('type'),
	'item_key' => get_request('item_key')
);

if ($year < $data['filter_year'] || ($year == $data['filter_year'] && $month < $data['filter_month'])) {
	show_error_message(_('Incorrect report period.'));
}

$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'name' => get_request('tld')
	)
));

if (!$tld) {
	show_error_message(_s('No permissions to referred TLD "%1$s" or it does not exist!', get_request('tld')));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$data['tld'] = reset($tld);

// Item validation.
$item = API::Item()->get(array(
	'output' => array('key_'),
	'hostids' => $data['tld']['hostid'],
	'filter' => array(
		'key_' => get_request('item_key')
	)
));

if (!$item) {
	show_error_message(_s('Item with key "%1$s" not exist on TLD!', get_request('item_key')));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

// Get data by item key and type
$rsmView = new CView('rsm.screens.view', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
