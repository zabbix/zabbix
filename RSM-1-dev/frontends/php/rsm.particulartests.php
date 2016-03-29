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
$page['file'] = 'rsm.particulartests.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,			null),
	'type' =>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1,2,3'),	null),
	'time' =>		array(T_ZBX_INT, O_OPT,	null,	null,			null),
	'slvItemId' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			null)
);
check_fields($fields);

$data['probes'] = array();
$data['host'] = null;
$data['time'] = null;
$data['slvItemId'] = null;
$data['type'] = null;

if (get_request('host') && get_request('time') && get_request('slvItemId') && get_request('type') !== null) {
	$data['host'] = get_request('host');
	$data['time'] = get_request('time');
	$data['slvItemId'] = get_request('slvItemId');
	$data['type'] = get_request('type');
	CProfile::update('web.rsm.particulartests.host', $data['host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.particulartests.time', $data['time'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particulartests.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particulartests.type', $data['type'], PROFILE_TYPE_ID);
}
elseif (!get_request('host') && !get_request('time') && !get_request('slvItemId') && get_request('type') === null) {
	$data['host'] = CProfile::get('web.rsm.particulartests.host');
	$data['time'] = CProfile::get('web.rsm.particulartests.time');
	$data['slvItemId'] = CProfile::get('web.rsm.particulartests.slvItemId');
	$data['type'] = CProfile::get('web.rsm.particulartests.type');
}

// check
if ($data['host'] && $data['time'] && $data['slvItemId'] && $data['type'] !== null) {
	$testTimeFrom = mktime(
		date('H', $data['time']),
		date('i', $data['time']),
		0,
		date('n', $data['time']),
		date('j', $data['time']),
		date('Y', $data['time'])
	);

	$data['totalProbes'] = 0;

	// macro
	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_DELAY;

		if ($data['type'] == RSM_DNS) {
			$data['downProbes'] = 0;
		}
		else {
			$data['totalTests'] = 0;
		}
	}
	elseif ($data['type'] == RSM_RDDS) {
		$calculatedItemKey[] = CALCULATED_ITEM_RDDS_DELAY;
	}
	else {
		$calculatedItemKey[] = CALCULATED_ITEM_EPP_DELAY;
	}

	if ($data['type'] == RSM_DNS) {
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_AVAIL_MINNS;
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_UDP_RTT_HIGH;
	}

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

	// get macros old value
	$macroItems = API::Item()->get(array(
		'hostids' => $rsm['hostid'],
		'output' => array('itemid', 'key_', 'value_type'),
		'filter' => array(
			'key_' => $calculatedItemKey
		)
	));

	foreach ($macroItems as $macroItem) {
		$macroItemValue = API::History()->get(array(
			'itemids' => $macroItem['itemid'],
			'time_from' => $testTimeFrom,
			'history' => $macroItem['value_type'],
			'output' => API_OUTPUT_EXTEND,
			'limit' => 1
		));

		$macroItemValue = reset($macroItemValue);

		if ($data['type'] == RSM_DNS) {
			if ($macroItem['key_'] == CALCULATED_ITEM_DNS_AVAIL_MINNS) {
				$minDnsCount = $macroItemValue['value'];
			}
			elseif ($macroItem['key_'] == CALCULATED_ITEM_DNS_UDP_RTT_HIGH) {
				$udpRtt = $macroItemValue['value'];
			}
			else {
				$macroTime = $macroItemValue['value'] - 1;
			}
		}
		else {
			$macroTime = $macroItemValue['value'] - 1;
		}
	}

	// time calculation
	$testTimeTill = $testTimeFrom + 59;
	$timeFrom = $macroTime - 59;
	$testTimeFrom -= $timeFrom;

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
		show_error_message(_('No permissions to referred TLD or it does not exist!'));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
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
		show_error_message(_('No permissions to referred SLV item or it does not exist!'));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get test resut
	if ($data['type'] == RSM_DNS) {
		$key = RSM_SLV_DNS_AVAIL;
	}
	elseif ($data['type'] == RSM_DNSSEC) {
		$key = RSM_SLV_DNSSEC_AVAIL;
	}
	elseif ($data['type'] == RSM_RDDS) {
		$key = RSM_SLV_RDDS_AVAIL;
	}
	else {
		$key = RSM_SLV_EPP_AVAIL;
	}

	// get items
	$availItems = API::Item()->get(array(
		'hostids' => $data['tld']['hostid'],
		'filter' => array(
			'key_' => $key
		),
		'output' => array('itemid', 'value_type'),
		'preservekeys' => true
	));

	if ($availItems) {
		$availItem = reset($availItems);
		$testResults = API::History()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $availItem['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $availItem['value_type'],
			'limit' => 1
		));
		$testResult = reset($testResults);
		$data['testResult'] = $testResult['value'];
	}
	else {
		show_error_message(_s('Item with key "%1$s" not exist on TLD!', $key));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

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

	$data['totalProbes'] = count($hostIds);

	// get probes items
	$probeItems = API::Item()->get(array(
		'output' => array('itemid', 'key_', 'hostid'),
		'hostids' => $hostIds,
		'filter' => array(
			'key_' => array(PROBE_STATUS_MANUAL, PROBE_STATUS_AUTOMATIC)
		),
		'monitored' => true,
		'preservekeys' => true
	));

	foreach ($probeItems as $probeItem) {
		// manual items
		if ($probeItem['key_'] == PROBE_STATUS_MANUAL) {
			$manualItemIds[] = $probeItem['itemid'];
		}
		// automatic items
		if ($probeItem['key_'] == PROBE_STATUS_AUTOMATIC) {
			$automaticItemIds[$probeItem['itemid']] = $probeItem['hostid'];
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
	$ignoredHostIds = array();

	foreach ($manualItemIds as $itemId) {
		$itemValue = DBfetch(DBselect(DBaddLimit(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.$itemId.
				' AND h.clock<='.$testTimeTill.
			' ORDER BY h.clock DESC',
			1
		)));

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
					' AND h.clock>='.$testTimeFrom.
					' AND h.clock<='.$testTimeTill,
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

	// get only used items
	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_DNS_UDP_ITEM_RTT.'%').') OR i.key_='.zbx_dbstr(PROBE_DNS_UDP_ITEM).')';
	}
	elseif ($data['type'] == RSM_RDDS) {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_RDDS_ITEM.'%').')'.
		' OR '.dbConditionString('i.key_',
			array(PROBE_RDDS43_IP, PROBE_RDDS43_RTT, PROBE_RDDS43_UPD, PROBE_RDDS80_IP, PROBE_RDDS80_RTT)).
		')';
	}
	else {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_EPP_RESULT.'%').')'.
		' OR '.dbConditionString('i.key_', array(PROBE_EPP_IP, PROBE_EPP_UPDATE, PROBE_EPP_INFO, PROBE_EPP_LOGIN)).')';
	}

	// get items
	$items = DBselect(
		'SELECT i.itemid,i.key_,i.hostid,i.value_type,i.valuemapid,i.units'.
		' FROM items i'.
		' WHERE '.dbConditionInt('i.hostid', $hostIds).
			$probeItemKey
	);

	$nsArray = array();

	// get items value
	while ($item = DBfetch($items)) {
		$itemValue = API::History()->get(array(
			'itemids' => $item['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $item['value_type'],
			'output' => API_OUTPUT_EXTEND
		));

		$itemValue = reset($itemValue);

		if ($data['type'] == RSM_DNS && $item['key_'] === PROBE_DNS_UDP_ITEM) {
			$hosts[$item['hostid']]['result'] = $itemValue ? $itemValue['value'] : null;
		}
		elseif ($data['type'] == RSM_DNS && zbx_substring($item['key_'], 0, 16) == PROBE_DNS_UDP_ITEM_RTT) {
			preg_match('/^[^\[]+\[([^\]]+)]$/', $item['key_'], $matches);
			$nsValues = explode(',', $matches[1]);

			if (!$itemValue) {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_NO_RESULT;
			}
			elseif ($itemValue['value'] < $udpRtt && $itemValue['value'] > DNS_NO_REPLY_ERROR_CODE) {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_UP;
			}
			else {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_DOWN;
			}
		}
		elseif ($data['type'] == RSM_DNSSEC && zbx_substring($item['key_'], 0, 16) == PROBE_DNS_UDP_ITEM_RTT) {
			if (!isset($hosts[$item['hostid']]['value'])) {
				$hosts[$item['hostid']]['value']['ok'] = 0;
				$hosts[$item['hostid']]['value']['fail'] = 0;
				$hosts[$item['hostid']]['value']['total'] = 0;
				$hosts[$item['hostid']]['value']['noResult'] = 0;
			}

			if ($itemValue) {
				if ($itemValue['value'] != DNSSEC_FAIL_ERROR_CODE) {
					$hosts[$item['hostid']]['value']['ok']++;
				}
				else {
					$hosts[$item['hostid']]['value']['fail']++;
				}
			}
			else {
				$hosts[$item['hostid']]['value']['noResult']++;
			}

			$hosts[$item['hostid']]['value']['total']++;
		}
		elseif ($data['type'] == RSM_RDDS) {
			if ($item['key_'] == PROBE_RDDS43_IP) {
				$hosts[$item['hostid']]['rdds43']['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_RDDS43_RTT) {
				$hosts[$item['hostid']]['rdds43']['rtt'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			elseif ($item['key_'] == PROBE_RDDS43_UPD) {
				$hosts[$item['hostid']]['rdds43']['upd'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			elseif ($item['key_'] == PROBE_RDDS80_IP) {
				$hosts[$item['hostid']]['rdds80']['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_RDDS80_RTT) {
				$hosts[$item['hostid']]['rdds80']['rtt'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			else {
				$hosts[$item['hostid']]['value'] = $itemValue['value'];
			}
		}
		elseif ($data['type'] == RSM_EPP) {
			if ($item['key_'] == PROBE_EPP_IP) {
				$hosts[$item['hostid']]['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_EPP_UPDATE) {
				$hosts[$item['hostid']]['update'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			elseif ($item['key_'] == PROBE_EPP_INFO) {
				$hosts[$item['hostid']]['info'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			elseif ($item['key_'] == PROBE_EPP_LOGIN) {
				$hosts[$item['hostid']]['login'] = $itemValue['value']
					? applyValueMap(convert_units($itemValue['value'], $item['units']), $item['valuemapid']) : null;
			}
			else {
				$hosts[$item['hostid']]['value'] = $itemValue['value'];
			}
		}
	}

	if ($data['type'] == RSM_DNS) {
		foreach ($nsArray as $hostId => $nss) {
			$hosts[$hostId]['value']['fail'] = 0;

			foreach ($nss as $nsName => $nsValue) {
				if (in_array(NS_DOWN, $nsValue['value'])) {
					$hosts[$hostId]['value']['fail']++;
				}
			}

			// calculate Down probes
			if (count($nss) - $hosts[$hostId]['value']['fail'] < $minDnsCount) {
				$data['downProbes']++;
				$hosts[$hostId]['class'] = 'red';
			}
			else {
				$hosts[$hostId]['class'] = 'green';
			}
		}
	}
	elseif ($data['type'] == RSM_DNSSEC) {
		// get tests items
		$testItems = API::Item()->get(array(
			'output' => array('itemid', 'value_type'),
			'hostids' => $hostIds,
			'search' => array(
				'key_' => PROBE_DNS_UDP_ITEM_RTT
			),
			'startSearch' => true,
			'monitored' => true
		));

		$data['totalTests'] = count($testItems);
	}

	foreach ($hosts as $host) {
		foreach ($data['probes'] as $hostId => $probe) {
			if (zbx_strtoupper($host['host']) == zbx_strtoupper($data['tld']['host'].' '.$probe['host'])
					&& isset($host['value'])) {
				$data['probes'][$hostId] = $host;
				$data['probes'][$hostId]['name'] = $probe['host'];
				break;
			}
		}
	}

	CArrayHelper::sort($data['probes'], array('name'));
}
else {
	access_deny();
}

$rsmView = new CView('rsm.particulartests.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
