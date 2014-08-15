<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


require_once dirname(__FILE__).'/defines.inc.php';
require_once dirname(__FILE__).'/items.inc.php';

function httptest_authentications($type = null) {
	$authentication_types = array(
		HTTPTEST_AUTH_NONE => _('None'),
		HTTPTEST_AUTH_BASIC => _('Basic'),
		HTTPTEST_AUTH_NTLM => _('NTLM')
	);

	if (is_null($type)) {
		return $authentication_types;
	}
	elseif (isset($authentication_types[$type])) {
		return $authentication_types[$type];
	}
	else {
		return _('Unknown');
	}
}

function httptest_status2str($status = null) {
	$statuses = array(
		HTTPTEST_STATUS_ACTIVE => _('Enabled'),
		HTTPTEST_STATUS_DISABLED => _('Disabled')
	);

	if (is_null($status)) {
		return $statuses;
	}
	elseif (isset($statuses[$status])) {
		return $statuses[$status];
	}
	else {
		return _('Unknown');
	}
}

function httptest_status2style($status) {
	$statuses = array(
		HTTPTEST_STATUS_ACTIVE => 'off',
		HTTPTEST_STATUS_DISABLED => 'on',
	);

	if (isset($statuses[$status])) {
		return $statuses[$status];
	}
	else {
		return 'unknown';
	}
}

function delete_history_by_httptestid($httptestid) {
	$db_items = DBselect(
		'SELECT DISTINCT i.itemid'.
		' FROM items i,httpstepitem si,httpstep s'.
		' WHERE i.itemid=si.itemid'.
			' AND si.httpstepid=s.httpstepid'.
			' AND s.httptestid='.zbx_dbstr($httptestid)
	);
	while ($item_data = DBfetch($db_items)) {
		if (!delete_history_by_itemid($item_data['itemid'])) {
			return false;
		}
	}

	return true;
}

function get_httptest_by_httptestid($httptestid) {
	return DBfetch(DBselect('SELECT ht.* FROM httptest ht WHERE ht.httptestid='.zbx_dbstr($httptestid)));
}

function get_httpstep_by_no($httptestid, $no) {
	return DBfetch(DBselect('SELECT hs.* FROM httpstep hs WHERE hs.httptestid='.zbx_dbstr($httptestid).' AND hs.no='.zbx_dbstr($no)));
}

function get_httptests_by_hostid($hostids) {
	zbx_value2array($hostids);

	return DBselect('SELECT DISTINCT ht.* FROM httptest ht WHERE '.dbConditionInt('ht.hostid', $hostids));
}

/**
 * Return parent templates for http tests.
 * Result structure:
 * array(
 *   'httptestid' => array(
 *     'name' => <template name>,
 *     'id' => <template id>
 *   ), ...
 * )
 *
 * @param array $httpTests must have httptestid and templateid fields
 *
 * @return array
 */
function getHttpTestsParentTemplates(array $httpTests) {
	$result = array();
	$template2testMap = array();

	foreach ($httpTests as $httpTest) {
		if (!empty($httpTest['templateid'])){
			$result[$httpTest['httptestid']] = array();
			$template2testMap[$httpTest['templateid']][$httpTest['httptestid']] = $httpTest['httptestid'];
		}
	}

	do {
		$dbHttpTests = DBselect('SELECT ht.httptestid,ht.templateid,ht.hostid,h.name'.
				' FROM httptest ht'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' WHERE '.dbConditionInt('ht.httptestid', array_keys($template2testMap)));
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			foreach ($template2testMap[$dbHttpTest['httptestid']] as $testId => $data) {
				$result[$testId] = array('name' => $dbHttpTest['name'], 'id' => $dbHttpTest['hostid']);

				if (!empty($dbHttpTest['templateid'])) {
					$template2testMap[$dbHttpTest['templateid']][$testId] = $testId;
				}
			}
			unset($template2testMap[$dbHttpTest['httptestid']]);
		}
	} while (!empty($template2testMap));

	return $result;
}

/**
 * Resolve http tests macros.
 *
 * @param array $httpTests
 * @param bool  $resolveName
 * @param bool  $resolveStepName
 *
 * @return array
 */
function resolveHttpTestMacros(array $httpTests, $resolveName = true, $resolveStepName = true) {
	$names = array();

	$i = 0;
	foreach ($httpTests as $test) {
		if ($resolveName) {
			$names[$test['hostid']][$i++] = $test['name'];
		}

		if ($resolveStepName) {
			foreach ($test['steps'] as $step) {
				$names[$test['hostid']][$i++] = $step['name'];
			}
		}
	}

	$macrosResolver = new CMacrosResolver();
	$names = $macrosResolver->resolve(array(
		'config' => 'httpTestName',
		'data' => $names
	));

	$i = 0;
	foreach ($httpTests as $tnum => $test) {
		if ($resolveName) {
			$httpTests[$tnum]['name'] = $names[$test['hostid']][$i++];
		}

		if ($resolveStepName) {
			foreach ($httpTests[$tnum]['steps'] as $snum => $step) {
				$httpTests[$tnum]['steps'][$snum]['name'] = $names[$test['hostid']][$i++];
			}
		}
	}

	return $httpTests;
}

/**
 * Copies web scenarios from given host ID to destination host.
 *
 * @param string $srcHostId		source host ID
 * @param string $dstHostId		destination host ID
 *
 * @return bool
 */
function copyHttpTests($srcHostId, $dstHostId) {
	$httpTests = API::HttpTest()->get(array(
		'output' => array('name', 'applicationid', 'delay', 'status', 'variables', 'agent', 'authentication',
			'http_user', 'http_password', 'http_proxy', 'retries', 'ssl_cert_file', 'ssl_key_file',
			'ssl_key_password', 'verify_peer', 'verify_host', 'headers'
		),
		'hostids' => $srcHostId,
		'selectSteps' => array('name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes', 'variables',
			'follow_redirects', 'retrieve_mode', 'headers'
		),
		'inherited' => false
	));

	if (!$httpTests) {
		return true;
	}

	// get destination application IDs
	$srcApplicationIds = array();
	foreach ($httpTests as $httpTest) {
		if ($httpTest['applicationid'] != 0) {
			$srcApplicationIds[] = $httpTest['applicationid'];
		}
	}

	if ($srcApplicationIds) {
		$dstApplicationIds = get_same_applications_for_host($srcApplicationIds, $dstHostId);
	}

	foreach ($httpTests as &$httpTest) {
		$httpTest['hostid'] = $dstHostId;

		if (isset($dstApplicationIds[$httpTest['applicationid']])) {
			$httpTest['applicationid'] = $dstApplicationIds[$httpTest['applicationid']];
		}
		else {
			unset($httpTest['applicationid']);
		}

		unset($httpTest['httptestid']);
	}
	unset($httpTest);

	return (bool) API::HttpTest()->create($httpTests);
}
