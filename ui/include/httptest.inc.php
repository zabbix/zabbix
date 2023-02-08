<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
		ZBX_HTTP_AUTH_NONE => _('None'),
		ZBX_HTTP_AUTH_BASIC => _('Basic'),
		ZBX_HTTP_AUTH_NTLM => _('NTLM'),
		ZBX_HTTP_AUTH_KERBEROS => _('Kerberos'),
		ZBX_HTTP_AUTH_DIGEST => _('Digest')
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
 * @param array $httptestids
 *
 * @return bool
 */
function deleteHistoryByHttpTestIds(array $httptestids): bool {
	DBstart();

	$itemids = [];

	$db_items = DBselect(
		'SELECT hti.itemid'.
		' FROM httptestitem hti'.
		' WHERE '.dbConditionInt('httptestid', $httptestids).
		' UNION ALL '.
		'SELECT hsi.itemid'.
		' FROM httpstep hs,httpstepitem hsi'.
		' WHERE hs.httpstepid=hsi.httpstepid'.
			' AND '.dbConditionInt('httptestid', $httptestids)
	);

	while ($db_item = DBfetch($db_items)) {
		$itemids[] = $db_item['itemid'];
	}

	$result = true;

	if ($itemids) {
		$result = (bool) API::History()->clear($itemids);
	}

	return DBend($result);
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
 * Get data for displaying parent web scenario of given web scenarios.
 *
 * @param array $httptests
 * @param bool  $allowed_ui_conf_templates
 *
 * @return array
 */
function getParentHttpTests(array $httptests, bool $allowed_ui_conf_templates) {
	$parent_httptests = [];

	foreach ($httptests as $httptest) {
		if ($httptest['templateid'] != 0) {
			$parent_httptests[$httptest['templateid']] = true;
		}
	}

	if (!$parent_httptests) {
		return [];
	}

	$db_httptests = API::HttpTest()->get([
		'output' => [],
		'selectHosts' => ['name', 'hostid'],
		'httptestids' => array_keys($parent_httptests),
		'preservekeys' => true
	]);

	if ($allowed_ui_conf_templates && $db_httptests) {
		$editable_httptests = API::HttpTest()->get([
			'output' => [],
			'httptestids' => array_keys($parent_httptests),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	foreach ($parent_httptests as $httptestid => &$parent_httptest) {
		if (array_key_exists($httptestid, $db_httptests)) {
			$parent_httptest = [
				'editable' => $allowed_ui_conf_templates && array_key_exists($httptestid, $editable_httptests),
				'template_name' => $db_httptests[$httptestid]['hosts'][0]['name'],
				'templateid' => $db_httptests[$httptestid]['hosts'][0]['hostid']
			];
		}
		else {
			$parent_httptest = [
				'editable' => false,
				'template_name' => _('Inaccessible template')
			];
		}
	}
	unset($parent_httptest);

	return $parent_httptests;
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
		'output' => ['name', 'delay', 'status', 'variables', 'agent', 'authentication',
			'http_user', 'http_password', 'http_proxy', 'retries', 'ssl_cert_file', 'ssl_key_file',
			'ssl_key_password', 'verify_peer', 'verify_host', 'headers'
		],
		'hostids' => $srcHostId,
		'selectTags' => ['tag', 'value'],
		'selectSteps' => ['name', 'no', 'url', 'query_fields', 'timeout', 'posts', 'required', 'status_codes',
			'variables', 'follow_redirects', 'retrieve_mode', 'headers'
		],
		'inherited' => false
	]);

	if (!$httpTests) {
		return true;
	}

	foreach ($httpTests as &$httpTest) {
		$httpTest['hostid'] = $dstHostId;

		unset($httpTest['httptestid']);
	}
	unset($httpTest);

	return (bool) API::HttpTest()->create($httpTests);
}

/**
 * Construct and return a multidimensional array of user agents sorted in groups.
 *
 * @see http://www.useragentstring.com
 *      https://developers.whatismybrowser.com
 *
 * @return array
 */
function userAgents() {
	return [
		_('Microsoft Edge') => [
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 Edge/80.0.361.66' => 'Microsoft Edge 80',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36 Edge/18.18362' => 'Microsoft Edge 44'
		],
		_('Internet Explorer') => [
			'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0)' => 'Internet Explorer 11',
			'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)' => 'Internet Explorer 10',
			'Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 9.0; Trident/5.0)' => 'Internet Explorer 9',
			'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)' => 'Internet Explorer 8'
		],
		_('Mozilla Firefox') => [
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (Windows)',
			'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (Linux)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (macOS)'
		],
		_('Google Chrome') => [
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (Windows)',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (Linux)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (macOS)',
			'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/80.0.3987.95 Mobile/15E148 Safari/605.1' => 'Chrome 80 (iOS)',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.87 Chrome/80.0.3987.87 Safari/537.36' => 'Chromium 80 (Linux)'
		],
		_('Opera') => [
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 OPR/67.0.3575.79' => 'Opera 67 (Windows)',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 OPR/67.0.3575.79' => 'Opera 67 (Linux)',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 OPR/67.0.3575.79' => 'Opera 67 (macOS)'
		],
		_('Safari') => [
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15' => 'Safari 13 (macOS)',
			'Mozilla/5.0 (Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Mobile/15E148 Safari/604.1' => 'Safari 13 (iPhone)',
			'Mozilla/5.0 (iPad; CPU OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Mobile/15E148 Safari/604.1' => 'Safari 13 (iPad)',
			'Mozilla/5.0 (iPod Touch; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Mobile/15E148 Safari/604.1' => 'Safari 13 (iPod Touch)'
		],
		_('Others') => [
			ZBX_DEFAULT_AGENT => 'Zabbix',
			'Lynx/2.8.8rel.2 libwww-FM/2.14 SSL-MM/1.4.1' => 'Lynx 2.8.8rel.2',
			'Links (2.8; Linux 3.13.0-36-generic x86_64; GNU C 4.8.2; text)' => 'Links 2.8',
			'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' => 'Googlebot 2.1'
		]
	];
}
