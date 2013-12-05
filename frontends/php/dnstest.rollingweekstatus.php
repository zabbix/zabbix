<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

$page['title'] = _('TLD Rolling week status');
$page['file'] = 'dnstest.rollingweekstatus.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// filter
	'filter_set' =>			array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'filter_search' =>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_dns' =>			array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_dnssec' =>		array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_rdds' =>		array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_epp' =>			array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_slv' =>			array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_status' =>		array(T_ZBX_INT, O_OPT,  null,	null,		null),
	// ajax
	'favobj' =>				array(T_ZBX_STR, O_OPT, P_ACT,	NULL,		NULL),
	'favref' =>				array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>			array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);

validate_sort_and_sortorder('name', ZBX_SORT_UP);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.dnstest.rollingweekstatus.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();

/*
 * Filter
 */
if (isset($_REQUEST['filter_set'])) {
	$data['filter_search'] = get_request('filter_search');
	$data['filter_dns'] = get_request('filter_dns');
	$data['filter_dnssec'] = get_request('filter_dnssec');
	$data['filter_rdds'] = get_request('filter_rdds');
	$data['filter_epp'] = get_request('filter_epp');
	$data['filter_slv'] = get_request('filter_slv');
	$data['filter_status'] = get_request('filter_status');

	CProfile::update('web.dnstest.rollingweekstatus.filter_search', get_request('filter_search'), PROFILE_TYPE_STR);
	CProfile::update('web.dnstest.rollingweekstatus.filter_dns', get_request('filter_dns', 0), PROFILE_TYPE_INT);
	CProfile::update('web.dnstest.rollingweekstatus.filter_dnssec', get_request('filter_dnssec', 0), PROFILE_TYPE_INT);
	CProfile::update('web.dnstest.rollingweekstatus.filter_rdds', get_request('filter_rdds', 0), PROFILE_TYPE_INT);
	CProfile::update('web.dnstest.rollingweekstatus.filter_epp', get_request('filter_epp', 0), PROFILE_TYPE_INT);
	CProfile::update('web.dnstest.rollingweekstatus.filter_slv', get_request('filter_slv', 0), PROFILE_TYPE_INT);
	CProfile::update('web.dnstest.rollingweekstatus.filter_status', get_request('filter_status', 0), PROFILE_TYPE_INT);
}
else {
	$data['filter_search'] = CProfile::get('web.dnstest.rollingweekstatus.filter_search');
	$data['filter_dns'] = CProfile::get('web.dnstest.rollingweekstatus.filter_dns');
	$data['filter_dnssec'] = CProfile::get('web.dnstest.rollingweekstatus.filter_dnssec');
	$data['filter_rdds'] = CProfile::get('web.dnstest.rollingweekstatus.filter_rdds');
	$data['filter_epp'] = CProfile::get('web.dnstest.rollingweekstatus.filter_epp');
	$data['filter_slv'] = CProfile::get('web.dnstest.rollingweekstatus.filter_slv');
	$data['filter_status'] = CProfile::get('web.dnstest.rollingweekstatus.filter_status');
}

$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => DNSTEST_PAGE_SLV
	)
));

$data['slv'] = reset($macro);

$options = array(
	'output' => array('hostid', 'name', 'host'),
	'tlds' => true,
	'preservekeys' => true
);

if (get_request('filter_search')) {
	$options['search'] = array('name' => get_request('filter_search'));
	$data['filter_search'] = get_request('filter_search');
}

if (get_request('filter_slv', 0) > 0
		&& (get_request('filter_dns') || get_request('filter_dnssec') || get_request('filter_rdds')
			|| get_request('filter_epp'))) {

	$slvValues = explode(',', $data['slv']['value']);
	if (!in_array(get_request('filter_slv'), $slvValues)) {
		show_error_message(_('Not allowed value for "Exceeding or equal to" field'));
	}
	else {
		$itemsOptions = array();
		$itemCount = 0;

		if (get_request('filter_dns')) {
			$items['key_'][] = DNSTEST_SLV_DNS_ROLLWEEK;
			$itemCount++;
		}
		if (get_request('filter_dnssec')) {
			$items['key_'][] = DNSTEST_SLV_DNSSEC_ROLLWEEK;
			$itemCount++;
		}
		if (get_request('filter_rdds')) {
			$items['key_'][] = DNSTEST_SLV_RDDS_ROLLWEEK;
			$itemCount++;
		}
		if (get_request('filter_epp')) {
			$items['key_'][] = DNSTEST_SLV_EPP_ROLLWEEK;
			$itemCount++;
		}

		$itemsHostids = DBselect(
			'SELECT i.hostid,COUNT(itemid)'.
			' FROM items i'.
			' WHERE i.lastvalue>='.get_request('filter_slv').
				' AND '.dbConditionString('i.key_', $items['key_']).
			' GROUP BY i.hostid'.
			' HAVING COUNT(i.itemid)>='.$itemCount
		);

		while ($hostId = DBfetch($itemsHostids)) {
			$options['hostids'][] = $hostId['hostid'];
		}
		if (!isset($options['hostids'])) {
			$options['hostids'] = 0;
		}
	}
}

// get TLD
$tlds = API::Host()->get($options);

$hostIds = array();
$data['tld'] = array();

if ($tlds) {
	foreach ($tlds as $tld) {
		$data['tld'][$tld['hostid']] = array(
			'hostid' => $tld['hostid'],
			'host' => $tld['host'],
			'name' => $tld['name'],
			'status' => false
		);
		$hostIds[] = $tld['hostid'];
	}

	// get items
	$items = API::Item()->get(array(
		'hostids' => $hostIds,
		'filter' => array(
			'key_' => array(DNSTEST_SLV_DNS_ROLLWEEK, DNSTEST_SLV_DNSSEC_ROLLWEEK, DNSTEST_SLV_RDDS_ROLLWEEK,
				DNSTEST_SLV_EPP_ROLLWEEK)
		),
		'output' => array('itemid', 'hostid', 'key_', 'lastvalue'),
		'preservekeys' => true
	));

	if ($items) {
		foreach ($items as $item) {
			switch ($item['key_']) {
				case DNSTEST_SLV_DNS_ROLLWEEK:
					$data['tld'][$item['hostid']]['dns']['itemid'] = $item['itemid'];
					$data['tld'][$item['hostid']]['dns']['lastvalue'] = $item['lastvalue'];
					$data['tld'][$item['hostid']]['dns']['trigger'] = false;
					break;
				case DNSTEST_SLV_DNSSEC_ROLLWEEK:
					$data['tld'][$item['hostid']]['dnssec']['itemid'] = $item['itemid'];
					$data['tld'][$item['hostid']]['dnssec']['lastvalue'] = $item['lastvalue'];
					$data['tld'][$item['hostid']]['dnssec']['trigger'] = false;
					break;
				case DNSTEST_SLV_RDDS_ROLLWEEK:
					$data['tld'][$item['hostid']]['rdds']['itemid'] = $item['itemid'];
					$data['tld'][$item['hostid']]['rdds']['lastvalue'] = $item['lastvalue'];
					$data['tld'][$item['hostid']]['rdds']['trigger'] = false;
					break;
			}

			$itemIds[] = $item['itemid'];
		}

		// get triggers
		$triggers = API::Trigger()->get(array(
			'itemids' => $itemIds,
			'output' => array('triggerids', 'value')
		));

		foreach ($triggers as $trigger) {
			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				if ($items[$trigger['itemid']]['key_'] == DNSTEST_SLV_DNS_ROLLWEEK) {
					$data['tld'][$items[$trigger['itemid']]['hostid']]['dns']['trigger'] = true;
				}
				if ($items[$trigger['itemid']]['key_'] == DNSTEST_SLV_DNSSEC_ROLLWEEK) {
					$data['tld'][$items[$trigger['itemid']]['hostid']]['dnssec']['trigger'] = true;
				}
				if ($items[$trigger['itemid']]['key_'] == DNSTEST_SLV_RDDS_ROLLWEEK) {
					$data['tld'][$items[$trigger['itemid']]['hostid']]['rdds']['trigger'] = true;
				}

				if ($data['filter_status']) {
					$data['tld'][$items[$trigger['itemid']]['hostid']]['status'] = true;
				}
			}
		}

		if ($data['filter_status']) {
			foreach ($data['tld'] as $tld) {
				if ($tld['status'] != true) {
					unset($data['tld'][$tld['hostid']]);
				}
			}
		}
	}
}
$data['paging'] = getPagingLine($data['tld']);

$dnsTestView = new CView('dnstest.rollingweekstatus.list', $data);
$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
