<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		HTTPTEST_AUTH_NTLM => _('NTLM'),
		HTTPTEST_AUTH_KERBEROS => _('Kerberos')
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
 * @param array $http_testids
 *
 * @return bool
 */
function deleteHistoryByHttpTestIds(array $http_testids) {
	$itemids = [];

	$db_items = DBselect(
		'SELECT hti.itemid'.
		' FROM httptestitem hti'.
		' WHERE '.dbConditionInt('httptestid', $http_testids).
		' UNION ALL '.
		'SELECT hsi.itemid'.
		' FROM httpstep hs,httpstepitem hsi'.
		' WHERE hs.httpstepid=hsi.httpstepid'.
			' AND '.dbConditionInt('httptestid', $http_testids)
	);

	while ($db_item = DBfetch($db_items)) {
		$itemids[] = $db_item['itemid'];
	}

	if ($itemids) {
		return Manager::History()->deleteHistory($itemids);
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
 * Get parent templates for each given web scenario.
 *
 * @param array  $httptests                  An array of web scenarios.
 * @param string $httptests[]['httptestid']  ID of a web scenario.
 * @param string $httptests[]['templateid']  ID of parent template web scenario.
 *
 * @return array
 */
function getHttpTestParentTemplates(array $httptests) {
	$parent_httptestids = [];
	$data = [
		'links' => [],
		'templates' => []
	];

	foreach ($httptests as $httptest) {
		if ($httptest['templateid'] != 0) {
			$parent_httptestids[$httptest['templateid']] = true;
			$data['links'][$httptest['httptestid']] = ['httptestid' => $httptest['templateid']];
		}
	}

	if (!$parent_httptestids) {
		return $data;
	}

	$all_parent_httptestids = [];
	$hostids = [];

	do {
		$db_httptests = API::HttpTest()->get([
			'output' => ['httptestid', 'hostid', 'templateid'],
			'httptestids' => array_keys($parent_httptestids)
		]);

		$all_parent_httptestids += $parent_httptestids;
		$parent_httptestids = [];

		foreach ($db_httptests as $db_httptest) {
			$data['templates'][$db_httptest['hostid']] = [];
			$hostids[$db_httptest['httptestid']] = $db_httptest['hostid'];

			if ($db_httptest['templateid'] != 0) {
				if (!array_key_exists($db_httptest['templateid'], $all_parent_httptestids)) {
					$parent_httptestids[$db_httptest['templateid']] = true;
				}

				$data['links'][$db_httptest['httptestid']] = ['httptestid' => $db_httptest['templateid']];
			}
		}
	}
	while ($parent_httptestids);

	foreach ($data['links'] as &$parent_httptest) {
		$parent_httptest['hostid'] = array_key_exists($parent_httptest['httptestid'], $hostids)
			? $hostids[$parent_httptest['httptestid']]
			: 0;
	}
	unset($parent_httptest);

	$db_templates = $data['templates']
		? API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($data['templates']),
			'preservekeys' => true
		])
		: [];

	$rw_templates = $db_templates
		? API::Template()->get([
			'output' => [],
			'templateids' => array_keys($db_templates),
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['templates'][0] = [];

	foreach ($data['templates'] as $hostid => &$template) {
		$template = array_key_exists($hostid, $db_templates)
			? [
				'hostid' => $hostid,
				'name' => $db_templates[$hostid]['name'],
				'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
			]
			: [
				'hostid' => $hostid,
				'name' => _('Inaccessible template'),
				'permission' => PERM_DENY
			];
	}
	unset($template);

	return $data;
}

/**
 * Returns a template prefix for selected web scenario.
 *
 * @param string $httptestid
 * @param array  $parent_templates  The list of the templates, prepared by getHttpTestParentTemplates() function.
 *
 * @return array|null
 */
function makeHttpTestTemplatePrefix($httptestid, array $parent_templates) {
	if (!array_key_exists($httptestid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$httptestid]['httptestid'], $parent_templates['links'])) {
		$httptestid = $parent_templates['links'][$httptestid]['httptestid'];
	}

	$template = $parent_templates['templates'][$parent_templates['links'][$httptestid]['hostid']];

	if ($template['permission'] == PERM_READ_WRITE) {
		$name = (new CLink(CHtml::encode($template['name']),
			(new CUrl('httpconf.php'))
				->setArgument('hostid', $template['hostid'])
				->setArgument('filter_set', 1)
		))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan(CHtml::encode($template['name']));
	}

	return [$name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
}

/**
 * Returns a list of web scenario templates.
 *
 * @param string $httptestid
 * @param array  $parent_templates  The list of the templates, prepared by getHttpTestParentTemplates() function.
 *
 * @return array
 */
function makeHttpTestTemplatesHtml($httptestid, array $parent_templates) {
	$list = [];

	while (array_key_exists($httptestid, $parent_templates['links'])) {
		$template = $parent_templates['templates'][$parent_templates['links'][$httptestid]['hostid']];

		if ($template['permission'] == PERM_READ_WRITE) {
			$name = new CLink(CHtml::encode($template['name']),
				(new CUrl('httpconf.php'))
					->setArgument('form', 'update')
					->setArgument('hostid', $template['hostid'])
					->setArgument('httptestid', $parent_templates['links'][$httptestid]['httptestid'])
			);
		}
		else {
			$name = (new CSpan(CHtml::encode($template['name'])))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, '&nbsp;&rArr;&nbsp;');

		$httptestid = $parent_templates['links'][$httptestid]['httptestid'];
	}

	if ($list) {
		array_pop($list);
	}

	return $list;
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
		'selectSteps' => ['name', 'no', 'url', 'query_fields', 'timeout', 'posts', 'required', 'status_codes',
			'variables', 'follow_redirects', 'retrieve_mode', 'headers'
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
