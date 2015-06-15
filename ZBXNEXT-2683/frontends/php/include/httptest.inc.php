<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	$authentication_types = [
		HTTPTEST_AUTH_NONE => _('None'),
		HTTPTEST_AUTH_BASIC => _('Basic'),
		HTTPTEST_AUTH_NTLM => _('NTLM')
	];

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
	$statuses = [
		HTTPTEST_STATUS_ACTIVE => _('Enabled'),
		HTTPTEST_STATUS_DISABLED => _('Disabled')
	];

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
	$statuses = [
		HTTPTEST_STATUS_ACTIVE => ZBX_STYLE_GREEN,
		HTTPTEST_STATUS_DISABLED => ZBX_STYLE_RED
	];

	if (isset($statuses[$status])) {
		return $statuses[$status];
	}
	else {
		return ZBX_STYLE_GREY;
	}
}

/**
 * Delete web scenario item and web scenario step item history and trends by given web scenario IDs.
 *
 * @param array $httpTestIds
 *
 * @return bool
 */
function deleteHistoryByHttpTestIds(array $httpTestIds) {
	$itemIds = [];

	$dbItems = DBselect(
		'SELECT hti.itemid'.
		' FROM httptestitem hti'.
		' WHERE '.dbConditionInt('httptestid', $httpTestIds).
		' UNION ALL '.
		'SELECT hsi.itemid'.
		' FROM httpstep hs,httpstepitem hsi'.
		' WHERE hs.httpstepid=hsi.httpstepid'.
			' AND '.dbConditionInt('httptestid', $httpTestIds)
	);

	while ($dbItem = DBfetch($dbItems)) {
		$itemIds[] = $dbItem['itemid'];
	}

	if ($itemIds) {
		return deleteHistoryByItemIds($itemIds);
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
	$result = [];
	$template2testMap = [];

	foreach ($httpTests as $httpTest) {
		if (!empty($httpTest['templateid'])){
			$result[$httpTest['httptestid']] = [];
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
				$result[$testId] = ['name' => $dbHttpTest['name'], 'id' => $dbHttpTest['hostid']];

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
	$names = [];

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
	$names = $macrosResolver->resolve([
		'config' => 'httpTestName',
		'data' => $names
	]);

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
	$httpTests = API::HttpTest()->get([
		'output' => ['name', 'applicationid', 'delay', 'status', 'variables', 'agent', 'authentication',
			'http_user', 'http_password', 'http_proxy', 'retries', 'ssl_cert_file', 'ssl_key_file',
			'ssl_key_password', 'verify_peer', 'verify_host', 'headers'
		],
		'hostids' => $srcHostId,
		'selectSteps' => ['name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes', 'variables',
			'follow_redirects', 'retrieve_mode', 'headers'
		],
		'inherited' => false
	]);

	if (!$httpTests) {
		return true;
	}

	// get destination application IDs
	$srcApplicationIds = [];
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

/**
 * Construct and return a multidimensional array of user gents sorted in groups.
 *
 * @see http://www.useragentstring.com
 *
 * @return array
 */
function userAgents() {
	return [
		_('Internet Explorer') => [
			'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0)' => 'Internet Explorer 11.0',
			'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)' => 'Internet Explorer 10.0',
			'Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 9.0; Trident/5.0)' => 'Internet Explorer 9.0',
			'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)' => 'Internet Explorer 8.0',
			'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0)' => 'Internet Explorer 7.0',
			'Mozilla/5.0 (compatible; MSIE 6.0; Windows NT 5.1)' => 'Internet Explorer 6.0'
		],
		_('Mozilla Firefox') => [
			'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:33.0) Gecko/20100101 Firefox/33.0' => 'Firefox 33.0 (Windows)',
			'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:33.0) Gecko/20100101 Firefox/33.0' => 'Firefox 33.0 (Linux)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:33.0) Gecko/20100101 Firefox/33.0' => 'Firefox 33.0 (Mac)'
		],
		_('Google Chrome') => [
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.104 Safari/537.36' => 'Chrome 38.0 (Windows)',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.104 Safari/537.36' => 'Chrome 38.0 (Linux)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.104 Safari/537.36' => 'Chrome 38.0 (Mac)',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/37.0.2062.120 Chrome/37.0.2062.120 Safari/537.36' => 'Chromium 37.0 (Linux)'
		],
		_('Opera') => [
			'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36 OPR/25.0.1614.50' => 'Opera 25.0 (Windows)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.101 Safari/537.36 OPR/25.0.1614.50' => 'Opera 25.0 (Mac)',
			'Opera/9.80 (X11; Linux x86_64) Presto/2.12.388 Version/12.16' => 'Opera 12.16 (Linux)',
			'Opera/12.02 (Android 4.1; Linux; Opera Mobi/ADR-1111101157; U; en-US) Presto/2.9.201 Version/12.02' => 'Opera Mobile 12.02',
			'Opera/9.80 (J2ME/MIDP; Opera Mini/9.80 (S60; SymbOS; Opera Mobi/23.348; U; en) Presto/2.5.25 Version/10.54' => 'Opera Mini 9.80'
		],
		_('Safari') => [
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2' => 'Safari 7.0.6 (Mac)',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.57.2 (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2' => 'Safari 5.1.7 (Windows)',
			'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25' => 'Safari 6.0 (iPad)',
			'Mozilla/5.0 (iPod; U; CPU iPhone OS 4_3_3 like Mac OS X) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5' => 'Safari 5.0.2 (iPhone)'
		],
		_('Others') => [
			ZBX_DEFAULT_AGENT => 'Zabbix',
			'Mozilla/5.0 (X11; Linux x86_64) konqueror/4.14.2' => 'Konqueror 4.14.2',
			'Lynx/2.8.8rel.2 libwww-FM/2.14 SSL-MM/1.4.1' => 'Lynx 2.8.8rel.2',
			'Links (2.8; Linux 3.13.0-36-generic x86_64; GNU C 4.8.2; text)' => 'Links 2.8',
			'Mozilla/5.0 (Linux; Android 4.4.4; Nexus 5 Build/KTU84P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.114 Mobile Safari/537.36' => 'Android Webkit Browser 4.4.4',
			'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' => 'Googlebot 2.1'
		]
	];
}
