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

$page['title'] = _('Test result from particular proxy');
$page['file'] = 'dnstest.particularproxys.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>					array(T_ZBX_STR, O_MAND,	P_SYS,	null,			NULL),
	'probe' =>					array(T_ZBX_STR, O_MAND,	P_SYS,	null,			NULL),
	'time' =>					array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,			NULL),
	'slvItemId' =>				array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,			NULL),
	// ajax
	'favobj'=>					array(T_ZBX_STR, O_OPT,	P_ACT,	NULL,			NULL),
	'favref'=>					array(T_ZBX_STR, O_OPT,	P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate'=>				array(T_ZBX_INT, O_OPT,	P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

validate_sort_and_sortorder('name', ZBX_SORT_UP);

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
$data['proxys'] = array();
$data['host'] = get_request('host');
$data['time'] = get_request('time');
$data['slvItemId'] = get_request('slvItemId');
$data['probe'] = get_request('probe');

// check
if (!$data['slvItemId'] || !$data['host'] || !$data['time'] || !$data['probe']) {
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

$testTimeTill = $testTimeFrom + 59;

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
	'output' => array('name', 'lastvalue')
));

$data['slvItem'] = reset($slvItems);

// get probe
$probe = API::Host()->get(array(
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $data['probe']
	)
));

$data['probe'] = reset($probe);

// get probe host
$hostName = $data['tld']['host'].' '.$data['probe']['host'];

$host = API::Host()->get(array(
	'output' => array('hostid'),
	'filter' => array(
		'host' => $hostName
	)
));

$host = reset($host);

// get macros
$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => DNSTEST_DNS_UDP_RTT
	)
));

$macro = reset($macro);

// get items
$probeItems = API::Item()->get(array(
	'hostids' => $host['hostid'],
	'output' => array('itemid', 'key_', 'hostid', 'valuemapid', 'units'),
	'search' => array(
		'key_' => PROBE_RDDS_ITEM
	),
	'startSearch' => true,
	'preservekeys' => true
));

$totalNs = array();
$data['positiveNs'] = 0;
foreach ($probeItems as $probeItem) {
	preg_match('/^[^\[]+\[([^\]]+)]$/', $probeItem['key_'], $matches);
	$nsValues = explode(',', $matches[1]);

	// get NS values
	$itemValue = DBfetch(DBselect(DBaddLimit(
		'SELECT h.value'.
		' FROM history h'.
		' WHERE h.itemid='.$probeItem['itemid'].
			' AND h.clock>='.$testTimeFrom.
			' AND h.clock<='.$testTimeTill.
		' ORDER BY h.clock DESC',
		1
	)));

	$ms = convert_units($itemValue['value'], $probeItem['units']);
	$ms = $itemValue ? applyValueMap($ms, $probeItem['valuemapid']) : null;

	$data['proxys'][$probeItem['itemid']] = array(
		'ns' => $nsValues[1],
		'ip' => $nsValues[2],
		'ms' => $ms
	);

	$totalNs[$nsValues[1]] = true;

	if ($itemValue['value'] > 0 && $itemValue['value'] < $macro['value']) {
		$data['positiveNs']++;
	}
}

$data['totalNs'] = count($totalNs);

if ($data['tld'] && $data['slvItem'] && $data['probe']) {
	$data['slv'] = $data['slvItem']['lastvalue'];
}
else {
	access_deny();
}

$dnsTestView = new CView('dnstest.particularproxys.list', $data);

$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
