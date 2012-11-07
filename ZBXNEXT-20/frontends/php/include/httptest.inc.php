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
		HTTPTEST_AUTH_BASIC => _('Basic authentication'),
		HTTPTEST_AUTH_NTLM => _('NTLM authentication')
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

function activate_httptest($httptestid) {
	$result = DBexecute('UPDATE httptest SET status='.HTTPTEST_STATUS_ACTIVE.' WHERE httptestid='.$httptestid);

	$itemids = array();
	$items_db = DBselect('SELECT hti.itemid FROM httptestitem hti WHERE hti.httptestid='.$httptestid);
	while ($itemid = Dbfetch($items_db)) {
		$itemids[] = $itemid['itemid'];
	}

	$items_db = DBselect(
		'SELECT hsi.itemid'.
		' FROM httpstep hs,httpstepitem hsi'.
		' WHERE hs.httpstepid=hsi.httpstepid'.
			' AND hs.httptestid='.$httptestid
	);
	while ($itemid = Dbfetch($items_db)) {
		$itemids[] = $itemid['itemid'];
	}

	$result &= DBexecute('UPDATE items SET status='.ITEM_STATUS_ACTIVE.' WHERE '.DBcondition('itemid', $itemids));

	return $result;
}

function disable_httptest($httptestid) {
	$result = DBexecute('UPDATE httptest SET status='.HTTPTEST_STATUS_DISABLED.' WHERE httptestid='.$httptestid);

	$itemids = array();
	$items_db = DBselect('SELECT hti.itemid FROM httptestitem hti WHERE hti.httptestid='.$httptestid);
	while ($itemid = Dbfetch($items_db)) {
		$itemids[] = $itemid['itemid'];
	}

	$items_db = DBselect(
		'SELECT hsi.itemid'.
		' FROM httpstep hs,httpstepitem hsi'.
		' WHERE hs.httpstepid=hsi.httpstepid'.
			' AND hs.httptestid='.$httptestid
	);
	while ($itemid = Dbfetch($items_db)) {
		$itemids[] = $itemid['itemid'];
	}

	$result &= DBexecute('UPDATE items SET status='.ITEM_STATUS_DISABLED.' WHERE '.DBcondition('itemid', $itemids));

	return $result;
}

function delete_history_by_httptestid($httptestid) {
	$db_items = DBselect(
		'SELECT DISTINCT i.itemid'.
		' FROM items i,httpstepitem si,httpstep s'.
		' WHERE i.itemid=si.itemid'.
			' AND si.httpstepid=s.httpstepid'.
			' AND s.httptestid='.$httptestid
	);
	while ($item_data = DBfetch($db_items)) {
		if (!delete_history_by_itemid($item_data['itemid'])) {
			return false;
		}
	}

	return true;
}

function get_httptest_by_httptestid($httptestid) {
	return DBfetch(DBselect('SELECT ht.* FROM httptest ht WHERE ht.httptestid='.$httptestid));
}

function get_httpstep_by_no($httptestid, $no) {
	return DBfetch(DBselect('SELECT hs.* FROM httpstep hs WHERE hs.httptestid='.$httptestid.' AND hs.no='.$no));
}

function get_httptests_by_hostid($hostids) {
	zbx_value2array($hostids);

	return DBselect(
		'SELECT DISTINCT ht.*'.
		' FROM httptest ht,applications ap'.
		' WHERE ht.applicationid=ap.applicationid'.
			' AND '.DBcondition('ap.hostid', $hostids)
	);
}

/**
 * Cheks for duplicates in HTTP steps.
 *
 * @param type $steps
 *
 * @return boolean return true if duplicate found
 */
function validateHttpDuplicateSteps($steps) {
	$isDuplicateStepFound = false;

	$set = array();
	foreach ($steps as $step) {
		if (isset($set[$step['name']])) {
			error(_s('Step with name "%s" already exists.', $step['name']));
			$isDuplicateStepFound = true;
		}
		$set[$step['name']] = 1;
	}

	return $isDuplicateStepFound;
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
			$template2testMap[$httpTest['templateid']] = $httpTest['httptestid'];
		}
	}

	do {
		$dbHttpTests = DBselect('SELECT ht.httptestid,ht.templateid,ht.hostid,h.name'.
				' FROM httptest ht'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' WHERE'.DBcondition('ht.httptestid', array_keys($template2testMap)));
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			$testId = $template2testMap[$dbHttpTest['httptestid']];
			unset($template2testMap[$dbHttpTest['httptestid']]);
			$result[$testId] = array('name' => $dbHttpTest['name'], 'id' => $dbHttpTest['hostid']);

			if (!empty($dbHttpTest['templateid'])) {
				$template2testMap[$dbHttpTest['templateid']] = $testId;
			}
		}
	} while (!empty($template2testMap));

	return $result;
}

/**
 * Resolve http tests macros.
 *
 * @param array $httpTests
 * @param bool  $resolveTest
 * @param bool  $esolveSteps
 *
 * @return array
 */
function resolveHttpTestMacros(array $httpTests, $resolveTest = true, $esolveSteps = true) {
	$resolver = new CMacrosResolver();
	$names = array();

	$i = 0;
	foreach ($httpTests as $test) {
		if ($resolveTest) {
			$names[$test['hostid']][$i++] = $test['name'];
		}

		if ($esolveSteps) {
			foreach ($test['steps'] as $step) {
				$names[$test['hostid']][$i++] = $step['name'];
			}
		}
	}

	$names = $resolver->resolveMacrosInTextBatch($names);

	$i = 0;
	foreach ($httpTests as $tnum => $test) {
		if ($resolveTest) {
			$httpTests[$tnum]['name'] = $names[$test['hostid']][$i++];
		}

		if ($esolveSteps) {
			foreach ($httpTests[$tnum]['steps'] as $snum => $step) {
				$httpTests[$tnum]['steps'][$snum]['name'] = $names[$test['hostid']][$i++];
			}
		}
	}

	return $httpTests;
}
