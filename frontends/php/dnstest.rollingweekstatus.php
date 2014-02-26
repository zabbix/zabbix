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
require_once dirname(__FILE__).'/include/rollingweekstatus.inc.php';

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
	'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'filter_search' =>	array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_dns' =>		array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_dnssec' =>	array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_rdds' =>	array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_epp' =>		array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_slv' =>		array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_status' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
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
	$data['filter_slv'] = get_request('filter_slv', 0);
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

if (!$macro) {
	show_error_message(_s('Macros "%1$s" not exit.', DNSTEST_PAGE_SLV));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}
elseif (!isset($data['slv']['value']) || !$data['slv']['value']) {
	show_error_message(_s('Macros "%1$s" is empty.', DNSTEST_PAGE_SLV));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$options = array(
	'output' => array('hostid', 'name', 'host'),
	'tlds' => true,
	'preservekeys' => true
);

if ($data['filter_search']) {
	$options['search'] = array('name' => $data['filter_search']);
	$data['filter_search'] = $data['filter_search'];
}

if ($data['filter_slv'] > 0
		&& ($data['filter_dns'] || $data['filter_dnssec'] || $data['filter_rdds']
			|| $data['filter_epp'])) {

	$slvValues = explode(',', $data['slv']['value']);
	if (!in_array($data['filter_slv'], $slvValues)) {
		show_error_message(_('Not allowed value for "Exceeding or equal to" field'));
	}
	else {
		$itemCount = 0;

		if ($data['filter_dns']) {
			$items['key'][] = DNSTEST_SLV_DNS_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_dnssec']) {
			$items['key'][] = DNSTEST_SLV_DNSSEC_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_rdds']) {
			$items['key'][] = DNSTEST_SLV_RDDS_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_epp']) {
			$items['key'][] = DNSTEST_SLV_EPP_ROLLWEEK;
			$itemCount++;
		}

		$itemsHostids = DBselect(
			'SELECT DISTINCT i.hostid'.
			' FROM items i'.
			' WHERE i.lastvalue>='.$data['filter_slv'].
				' AND '.dbConditionString('i.key_', $items['key'])
		);

		while ($hostId = DBfetch($itemsHostids)) {
			$options['hostids'][] = $hostId['hostid'];
		}

		if (!isset($options['hostids'])) {
			$options['hostids'] = 0;
		}
	}
}

// disabled services
if ($data['filter_status'] == 2
		&& (!isset($options['hostids']) || (isset($options['hostids']) && $options['hostids'] != 0))) {
	if (isset($options['hostids'])) {
		$filtredHost = ' AND '.dbConditionInt('i.hostid', $options['hostids']);
		unset($options['hostids']);
	}
	else {
		$filtredHost = null;
	}

	$items['key'] = array(
		DNSTEST_SLV_DNS_ROLLWEEK,
		DNSTEST_SLV_DNSSEC_ROLLWEEK,
		DNSTEST_SLV_RDDS_ROLLWEEK,
		DNSTEST_SLV_EPP_ROLLWEEK
	);

	$itemsHostids = DBselect(
		'SELECT i.hostid,COUNT(itemid)'.
		' FROM items i'.
		' WHERE '.dbConditionString('i.key_', $items['key']).
			$filtredHost.
		' GROUP BY i.hostid'.
		' HAVING COUNT(i.itemid)<=2'
	);

	while ($hostId = DBfetch($itemsHostids)) {
		$options['hostids'][] = $hostId['hostid'];
	}

	if (!isset($options['hostids'])) {
		$options['hostids'] = 0;
	}
}

// get TLD
$tlds = API::Host()->get($options);

$data['tld'] = array();

if ($tlds) {
	$sortField = getPageSortField('name');
	$sortOrder = getPageSortOrder();
	$hostIds = array_keys($tlds);

	// get items
	$items = API::Item()->get(array(
		'hostids' => $hostIds,
		'filter' => array(
			'key_' => array(
				DNSTEST_SLV_DNS_ROLLWEEK, DNSTEST_SLV_DNSSEC_ROLLWEEK, DNSTEST_SLV_RDDS_ROLLWEEK,
				DNSTEST_SLV_EPP_ROLLWEEK, DNSTEST_SLV_DNS_AVAIL, DNSTEST_SLV_DNSSEC_AVAIL,
				DNSTEST_SLV_RDDS_AVAIL, DNSTEST_SLV_EPP_AVAIL
			)
		),
		'output' => array('itemid', 'hostid', 'key_', 'lastvalue'),
		'preservekeys' => true
	));

	if ($items) {
		if ($sortField != 'name') {
			$sortItems = array();
			foreach ($items as $item) {
				if (($item['key_'] == DNSTEST_SLV_DNS_ROLLWEEK && $sortField == 'dns')
						|| ($item['key_'] == DNSTEST_SLV_DNSSEC_ROLLWEEK && $sortField == 'dnssec')
						|| ($item['key_'] == DNSTEST_SLV_RDDS_ROLLWEEK && $sortField == 'rdds')
						|| ($item['key_'] == DNSTEST_SLV_EPP_ROLLWEEK && $sortField == 'epp')) {
					$sortItems[$item['itemid']]['lastvalue'] = $item['lastvalue'];
					$sortItems[$item['itemid']]['hostid'] = $item['hostid'];
					$itemIds[] = $item['itemid'];
				}
			}

			// sorting
			CArrayHelper::sort($sortItems, array(array('field' => 'lastvalue', 'order' => $sortOrder)));

			foreach ($sortItems as $itemId => $sortItem) {
				$data['tld'][$sortItem['hostid']][$sortField]['itemid'] = $itemId;
				$data['tld'][$sortItem['hostid']][$sortField]['lastvalue'] = sprintf(
					'%.3f',
					$sortItem['lastvalue']
				);
				$data['tld'][$sortItem['hostid']][$sortField]['trigger'] = false;
			}
		}

		foreach ($items as $item) {
			if ($item['key_'] == DNSTEST_SLV_DNS_ROLLWEEK && $sortField != 'dns') {
				$data['tld'][$item['hostid']]['dns']['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']]['dns']['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']]['dns']['trigger'] = false;
			}
			elseif ($item['key_'] == DNSTEST_SLV_DNSSEC_ROLLWEEK && $sortField != 'dnssec') {
				$data['tld'][$item['hostid']]['dnssec']['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']]['dnssec']['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']]['dnssec']['trigger'] = false;
			}
			elseif ($item['key_'] == DNSTEST_SLV_RDDS_ROLLWEEK && $sortField != 'rdds') {
				$data['tld'][$item['hostid']]['rdds']['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']]['rdds']['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']]['rdds']['trigger'] = false;
			}
			elseif ($item['key_'] == DNSTEST_SLV_EPP_ROLLWEEK && $sortField != 'epp') {
				$data['tld'][$item['hostid']]['epp']['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']]['epp']['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']]['epp']['trigger'] = false;
			}
			elseif ($item['key_'] == DNSTEST_SLV_DNS_AVAIL) {
				$data['tld'][$item['hostid']]['dns']['availItemId'] = $item['itemid'];
				$itemIds[] = $item['itemid'];
			}
			elseif ($item['key_'] == DNSTEST_SLV_DNSSEC_AVAIL) {
				$data['tld'][$item['hostid']]['dnssec']['availItemId'] = $item['itemid'];
				$itemIds[] = $item['itemid'];
			}
			elseif ($item['key_'] == DNSTEST_SLV_RDDS_AVAIL) {
				$data['tld'][$item['hostid']]['rdds']['availItemId'] = $item['itemid'];
				$itemIds[] = $item['itemid'];
			}
			elseif ($item['key_'] == DNSTEST_SLV_EPP_AVAIL) {
				$data['tld'][$item['hostid']]['epp']['availItemId'] = $item['itemid'];
				$itemIds[] = $item['itemid'];
			}
		}

		foreach ($tlds as $tld) {
			$data['tld'][$tld['hostid']]['hostid'] = $tld['hostid'];
			$data['tld'][$tld['hostid']]['host'] = $tld['host'];
			$data['tld'][$tld['hostid']]['name'] = $tld['name'];
			$data['tld'][$tld['hostid']]['status'] = false;
		}

		// get triggers
		$triggers = API::Trigger()->get(array(
			'output' => array('triggerid', 'value'),
			'itemids' => $itemIds
		));

		foreach ($triggers as $trigger) {
			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				$trItem = $trigger['itemid'];
				$problem = array();
				switch ($items[$trItem]['key_']) {
					case DNSTEST_SLV_DNS_AVAIL:
						$data['tld'][$items[$trItem]['hostid']]['dns']['trigger'] = true;
						$data['tld'][$items[$trItem]['hostid']]['dns']['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						break;
					case DNSTEST_SLV_DNSSEC_AVAIL:
						$data['tld'][$items[$trItem]['hostid']]['dnssec']['trigger'] = true;
						$data['tld'][$items[$trItem]['hostid']]['dnssec']['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						break;
					case DNSTEST_SLV_RDDS_AVAIL:
						$data['tld'][$items[$trItem]['hostid']]['rdds']['trigger'] = true;
						$data['tld'][$items[$trItem]['hostid']]['rdds']['incident'] = getLastEvent(
							$trigger['triggerid']);
						break;
					case DNSTEST_SLV_EPP_AVAIL:
						$data['tld'][$items[$trItem]['hostid']]['epp']['trigger'] = true;
						$data['tld'][$items[$trItem]['hostid']]['epp']['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						break;
				}

				if ($data['filter_status'] == 1) {
					$data['tld'][$items[$trItem]['hostid']]['status'] = true;
				}
			}
		}

		// fail services
		if ($data['filter_status'] == 1) {
			foreach ($data['tld'] as $tld) {
				if ($tld['status'] != true) {
					unset($data['tld'][$tld['hostid']]);
				}
			}
		}
	}

	if ($sortField == 'name') {
		order_result($data['tld'], 'name', $sortOrder);
	}
}

$data['paging'] = getPagingLine($data['tld']);

$dnsTestView = new CView('dnstest.rollingweekstatus.list', $data);
$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
