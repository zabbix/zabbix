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
require_once dirname(__FILE__).'/include/incidentdetails.inc.php';

$page['title'] = _('Details of particular test');
$page['file'] = 'dnstest.particulartests.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>					array(T_ZBX_STR, O_MAND,	P_SYS,	null,			NULL),
	'type' =>					array(T_ZBX_INT, O_MAND,	null,	IN('0,1,2'),	null),
	'time' =>					array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,			NULL),
	'slvItemId' =>				array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,			NULL),
	// ajax
	'favobj'=>					array(T_ZBX_STR, O_OPT,	P_ACT,	NULL,			NULL),
	'favref'=>					array(T_ZBX_STR, O_OPT,	P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate'=>				array(T_ZBX_INT, O_OPT,	P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.dnstest.incidents.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();
$data['probes'] = array();
$data['host'] = get_request('host');
$data['time'] = get_request('time');
$data['slvItemId'] = get_request('slvItemId');
$data['type'] = get_request('type');

// check
if (!$data['slvItemId'] || !$data['host'] || !$data['time'] || $data['type'] === null) {
	access_deny();
}

$testTimeFrom = mktime(
	date('H', $data['time']),
	date('i', $data['time']),
	0,
	date('n', $data['time']),
	date('j', $data['time']),
	date('Y', $data['time'])
);

// get TLD
$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $data['host']
	)
));

$data['tld'] = reset($tld);

// get slv item
$slvItems = API::Item()->get(array(
	'itemids' => $data['slvItemId'],
	'output' => array('name', 'key_', 'lastvalue')
));

$data['slvItem'] = reset($slvItems);

// get "Probes" groupId
$groups = API::HostGroup()->get(array(
	'filter' => array(
		'name' => 'Probes'
	),
	'output' => array('groupid')
));

$group = reset($groups);

// get probes
$hosts = API::Host()->get(array(
	'groupids' => $group['groupid'],
	'output' => array('hostid', 'host', 'name'),
	'preservekeys' => true
));

$hostIds = array();
foreach ($hosts as $host) {
	$hostIds[] = $host['hostid'];
}

// get probes items
$probeItems = API::Item()->get(array(
	'hostids' => $hostIds,
	'output' => array('itemid', 'key_', 'hostid'),
	'preservekeys' => true
));

foreach ($probeItems as $probeItem) {
	if ($probeItem['key_'] != NUMBER_OF_ONLINE_AND_TOTAL_PROBES && $probeItem['key_'] != NUMBER_OF_ONLINE_PROBES
			&& $probeItem['key_'] != NUMBER_OF_TOTAL_PROBES ) {
		// manual items
		if ($probeItem['key_'] != PROBE_STATUS_MANUAL) {
			$manualItemIds[] = $probeItem['itemid'];
		}
		// automatic items
		if ($probeItem['key_'] != PROBE_STATUS_MANUAL) {
			$automaticItemIds[$probeItem['itemid']] = $probeItem['hostid'];
		}
	}
	elseif (isset($hosts[$probeItem['hostid']])) {
		unset($hosts[$probeItem['hostid']]);
	}
}

// probe main data generation
foreach ($hosts as $host) {
	$data['probes'][$host['hostid']] = array(
		'host' => $host['host'],
		'name' => $host['name']
	);
}

// get manual data
foreach ($manualItemIds as $itemId) {
	$itemValue = DBfetch(DBselect(
		'SELECT h.value'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock>='.$data['time']
	));

	if ($itemValue && $itemValue['value'] == PROBE_DOWN) {
		$data['probes'][$probeItems[$itemId]['hostid']]['status'] = PROBE_DOWN;
		$ignoredHostIds[] = $probeItems[$itemId]['hostid'];
	}
}

// get automatic data
foreach ($automaticItemIds as $itemId => $hostId) {
	if (!in_array($hostId, $ignoredHostIds)) {
		$itemValue = DBfetch(DBselect(DBaddLimit(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.$itemId.
				' AND h.clock>='.$data['time'],
			1
		)));

		if ($itemValue && $itemValue['value'] == PROBE_DOWN) {
			$data['probes'][$hostId]['status'] = PROBE_DOWN;
		}
	}
}

// get probes data hosts
foreach ($data['probes'] as $hostId => $probe) {
	if (!isset($probe['status'])) {
		$hostNames[] = $data['tld']['host'].' '.$probe['host'];
	}
}

$hosts = API::Host()->get(array(
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $hostNames
	),
	'preservekeys' => true
));

$hostIds = array();
foreach ($hosts as $host) {
	$hostIds[] = $host['hostid'];
}

if ($data['type'] == 0 || $data['type'] == 1) {
	$macro[] = DNSTEST_DNSSEC_DELAY;
}
else {
	$macro[] = DNSTEST_RDDS_DELAY;
}

if ($data['type'] == 0) {
	$macro[] = DNSTEST_MIN_DNS_COUNT;
	$macro[] = DNSTEST_DNS_UDP_RTT;
}

// get global macros
$macros = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => $macro
	)
));

//$testTimeTill = $testTimeFrom + 59;
if ($data['type'] == 0) {
	foreach ($macros as $macro) {
		if ($macro['macro'] == DNSTEST_MIN_DNS_COUNT) {
			$minDnsCount = $macro['value'];
		}
		elseif ($macro['macro'] == DNSTEST_DNS_UDP_RTT) {
			$udpRtt = $macro['value'];
		}
		else {
			$macroTime = $macro['value'] - 1;
		}
	}
}
else {
	$macro = reset($macros);
	$macroTime = $macro['value'] - 1;
}

// time calculation
$timeFrom = $macroTime - 59;
$testTimeTill = $testTimeFrom + 59;
$testTimeFrom -= $timeFrom;

// get only used items
if ($data['type'] == 0 || $data['type'] == 1) {
	$probeItems = API::Item()->get(array(
		'hostids' => $hostIds,
		'output' => array('itemid', 'key_', 'hostid'),
		'search' => array(
			'key_' => PROBE_DNS_UDP_ITEM
		),
		'startSearch' => true,
		'preservekeys' => true
	));
}
else {
	$probeItems = API::Item()->get(array(
		'hostids' => $hostIds,
		'output' => array('itemid', 'key_', 'hostid'),
		'search' => array(
			'key_' => PROBE_RDDS_ITEM
		),
		'startSearch' => true,
		'preservekeys' => true
	));
}

// get items value
foreach ($probeItems as $probeItem) {
	if ($data['type'] == 0 || $data['type'] == 1) {
		$itemValue = DBfetch(DBselect(DBaddLimit(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.$probeItem['itemid'].
				' AND h.clock>='.$testTimeFrom.
				' AND h.clock<='.$testTimeTill.
			' ORDER BY h.clock DESC',
			1
		)));
	}
	if ($data['type'] == 0) {
		preg_match('/^[^\[]+\[([^\]]+)]$/', $probeItem['key_'], $matches);
		$nsValues = explode(',', $matches[1]);

		if (!$itemValue) {
			$nsArray[$probeItems[$probeItem['itemid']]['hostid']][$nsValues[1]]['value'][] = null;
		}
		elseif ($itemValue['value'] < $udpRtt * 5 && $itemValue['value'] > DNSTEST_NO_REPLY_ERROR_CODE) {
			$nsArray[$probeItems[$probeItem['itemid']]['hostid']][$nsValues[1]]['value'][] = true;
		}
		else {
			$nsArray[$probeItems[$probeItem['itemid']]['hostid']][$nsValues[1]]['value'][] = false;
		}
	}
	elseif ($data['type'] == 1) {
		if (!isset($hosts[$probeItems[$probeItem['itemid']]['hostid']]['value'])) {
			$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['ok'] = 0;
			$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['fail'] = 0;
			$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['total'] = 0;
			$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['noResult'] = 0;
		}

		if ($itemValue) {
			if ($itemValue['value'] != DNSSEC_FAIL_ERROR_CODE) {
				$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['ok']++;
			}
			else {
				$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['fail']++;
			}
		}
		else {
			$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['noResult']++;
		}

		$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value']['total']++;
	}
	elseif ($data['type'] == 2) {
		$itemValue = DBfetch(DBselect(DBaddLimit(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.$probeItem['itemid'].
				' AND h.clock>='.$testTimeFrom.
				' AND h.clock<='.$testTimeTill.
			' ORDER BY h.clock DESC',
			1
		)));

		if (!$itemValue) {
			$itemValue['value'] = null;
		}
		$hosts[$probeItems[$probeItem['itemid']]['hostid']]['value'] = $itemValue['value'];
	}
}

if ($data['type'] == 0) {
	foreach ($nsArray as $hostId => $nss) {
		$failNs = 0;

		foreach ($nss as $nsName => $nsValue) {
			if (in_array(false, $nsValue)) {
				$failNs++;
			}
		}

		if (count($nss) - $failNs >= $minDnsCount) {
			$hosts[$hostId]['value'] = true;
		}
		else {
			$hosts[$hostId]['value'] = false;
		}
	}
}

foreach ($hosts as $host) {
	foreach ($data['probes'] as $hostId => $probe) {
		if (zbx_strtoupper($host['host']) == zbx_strtoupper($data['tld']['host'].' '.$probe['host'])) {
			$data['probes'][$hostId]['value'] = $host['value'];
			break;
		}
	}
}

if ($data['tld'] && $data['slvItem']) {
	$data['slv'] = $data['slvItem']['lastvalue'];
}
else {
	access_deny();
}

CArrayHelper::sort($data['probes'], array('name'));

$dnsTestView = new CView('dnstest.particulartests.list', $data);

$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
