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
$page['file'] = 'rsm.particularproxys.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	null),
	'type' =>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
	'probe' =>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	null),
	'time' =>		array(T_ZBX_INT, O_OPT,	null,	null,	null),
	'slvItemId' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null)
);
check_fields($fields);

$data['proxys'] = array();
$data['host'] = null;
$data['time'] = null;
$data['slvItemId'] = null;
$data['type'] = null;
$data['probe'] = null;

if (get_request('host') && get_request('time') && get_request('slvItemId') && get_request('type') !== null
		&& get_request('probe')) {
	$data['host'] = get_request('host');
	$data['time'] = get_request('time');
	$data['slvItemId'] = get_request('slvItemId');
	$data['type'] = get_request('type');
	$data['probe'] = get_request('probe');
	CProfile::update('web.rsm.particularproxys.host', $data['host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.particularproxys.time', $data['time'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particularproxys.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particularproxys.type', $data['type'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particularproxys.probe', $data['probe'], PROFILE_TYPE_STR);
}
elseif (!get_request('host') && !get_request('time') && !get_request('slvItemId') && get_request('type') === null
		&& !get_request('probe')) {
	$data['host'] = CProfile::get('web.rsm.particularproxys.host');
	$data['time'] = CProfile::get('web.rsm.particularproxys.time');
	$data['slvItemId'] = CProfile::get('web.rsm.particularproxys.slvItemId');
	$data['type'] = CProfile::get('web.rsm.particularproxys.type');
	$data['probe'] = CProfile::get('web.rsm.particularproxys.probe');
}

// check
if ($data['host'] && $data['time'] && $data['slvItemId'] && $data['type'] !== null && $data['probe']) {
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

	if ($tld) {
		$data['tld'] = reset($tld);
	}
	else {
		access_deny();
	}

	// get slv item
	$slvItems = API::Item()->get(array(
		'itemids' => $data['slvItemId'],
		'output' => array('name')
	));

	if ($slvItems) {
		$data['slvItem'] = reset($slvItems);
	}
	else {
		access_deny();
	}

	// get probe
	$probe = API::Host()->get(array(
		'output' => array('hostid', 'host', 'name'),
		'filter' => array(
			'host' => $data['probe']
		)
	));

	if ($probe) {
		$data['probe'] = reset($probe);
	}
	else {
		access_deny();
	}

	// get probe host
	$hostName = $data['probe']['host'];

	$host = API::Host()->get(array(
		'output' => array('hostid'),
		'filter' => array(
			'host' => $hostName
		)
	));

	$host = reset($host);

	// get host with calculated items
	$rsm = API::Host()->get(array(
		'output' => array('hostid'),
		'filter' => array(
			'host' => RSM_HOST
		)
	));

	if ($rsm) {
		$rsm = reset($rsm);
	}
	else {
		show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	$macroItemKey[] = CALCULATED_ITEM_DNS_UDP_RTT_HIGH;

	if ($data['type'] == RSM_DNS) {
		$macroItemKey[] = CALCULATED_ITEM_DNS_DELAY;
		$macroItemKey[] = CALCULATED_ITEM_DNS_AVAIL_MINNS;
	}
	elseif ($data['type'] == RSM_DNSSEC) {
		$macroItemKey[] = CALCULATED_ITEM_DNS_DELAY;
	}
	elseif ($data['type'] == RSM_RDDS) {
		$macroItemKey[] = CALCULATED_ITEM_RDDS_DELAY;
	}
	else {
		$macroItemKey[] = CALCULATED_ITEM_EPP_DELAY;
	}

	// get macros old value
	$macroItems = API::Item()->get(array(
		'hostids' => $rsm['hostid'],
		'output' => array('itemid', 'value_type', 'key_'),
		'filter' => array(
			'key_' => $macroItemKey
		)
	));

	// check items
	if (count($macroItems) != count($macroItemKey)) {
		show_error_message(_s('Missing calculated items at host "%1$s"!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get time till
	foreach ($macroItems as $key => $macroItem) {
		if ($macroItem['key_'] == CALCULATED_ITEM_DNS_DELAY || $macroItem['key_'] == CALCULATED_ITEM_RDDS_DELAY
				|| $macroItem['key_'] == CALCULATED_ITEM_EPP_DELAY) {
			$macroItemValue = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $macroItem['itemid'],
				'time_from' => $testTimeFrom,
				'history' => $macroItem['value_type'],
				'limit' => 1
			));

			$macroItemValue = reset($macroItemValue);

			$testTimeTill = $testTimeFrom + $macroItemValue['value'] - 1;

			unset($macroItems[$key]);
		}
	}

	foreach ($macroItems as $macroItem) {
		$macroItemValue = API::History()->get(array(
			'itemids' => $macroItem['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $macroItem['value_type'],
			'output' => API_OUTPUT_EXTEND
		));

		$macroItemValue = reset($macroItemValue);

		if ($macroItem['key_'] == CALCULATED_ITEM_DNS_UDP_RTT_HIGH) {
			$dnsUdpRtt = $macroItemValue['value'];
		}
		else {
			$minNs = $macroItemValue['value'];
		}
	}

	// get test result for DNS service
	if ($data['type'] == RSM_DNS) {
		$probeResultItems = API::Item()->get(array(
			'hostids' => $data['probe']['hostid'],
			'output' => array('itemid', 'value_type', 'key_'),
			'filter' => array(
				'key_' => PROBE_DNS_UDP_ITEM
			)
		));

		$probeResultItem = reset($probeResultItems);

		$itemValue = API::History()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $probeResultItem['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $probeResultItem['value_type']

		));

		if ($itemValue) {
			$itemValue = reset($itemValue);
			$data['testResult'] = ($itemValue['value'] >= $minNs) ? true : false;
		}
		else {
			$data['testResult'] = null;
		}
	}

	// get items
	$probeItems = API::Item()->get(array(
		'hostids' => $host['hostid'],
		'output' => array('itemid', 'key_', 'hostid', 'valuemapid', 'units', 'value_type'),
		'search' => array(
			'key_' => PROBE_DNS_UDP_ITEM_RTT
		),
		'startSearch' => true,
		'preservekeys' => true
	));

	$totalNs = array();
	$negativeNs = array();
	foreach ($probeItems as $probeItem) {
		preg_match('/^[^\[]+\[([^\]]+)]$/', $probeItem['key_'], $matches);
		$nsValues = explode(',', $matches[1]);

		// get NS values
		$itemValue = API::History()->get(array(
			'itemids' => $probeItem['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $probeItem['value_type'],
			'output' => API_OUTPUT_EXTEND
		));

		$itemValue = reset($itemValue);

		$ms = convert_units($itemValue['value'], $probeItem['units']);
		$ms = $itemValue ? applyValueMap($ms, $probeItem['valuemapid']) : null;

		$data['proxys'][$probeItem['itemid']] = array(
			'ns' => $nsValues[1],
			'ip' => $nsValues[2],
			'ms' => $ms
		);

		$totalNs[$nsValues[1]] = true;

		if (($itemValue['value'] < 0 || $itemValue['value'] > $dnsUdpRtt) && $itemValue['value'] !== null) {
			$negativeNs[$nsValues[1]] = true;
		}
	}

	$data['totalNs'] = count($totalNs);
	$data['positiveNs'] = count($totalNs) - count($negativeNs);

	$data['minMs'] = $dnsUdpRtt;
}
else {
	access_deny();
}

$rsmView = new CView('rsm.particularproxys.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
