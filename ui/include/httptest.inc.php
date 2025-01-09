<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array|null
 */
function makeHttpTestTemplatePrefix($httptestid, array $parent_templates, bool $provide_links) {
	if (!array_key_exists($httptestid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$httptestid]['httptestid'], $parent_templates['links'])) {
		$httptestid = $parent_templates['links'][$httptestid]['httptestid'];
	}

	$template = $parent_templates['templates'][$parent_templates['links'][$httptestid]['hostid']];

	if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
		$name = (new CLink($template['name'],
			(new CUrl('httpconf.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$template['hostid']])
				->setArgument('context', 'template')
		))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan($template['name']);
	}

	return [$name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
}

/**
 * Returns a list of web scenario templates.
 *
 * @param string $httptestid
 * @param array  $parent_templates  The list of the templates, prepared by getHttpTestParentTemplates() function.
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array
 */
function makeHttpTestTemplatesHtml($httptestid, array $parent_templates, bool $provide_links) {
	$list = [];

	while (array_key_exists($httptestid, $parent_templates['links'])) {
		$template = $parent_templates['templates'][$parent_templates['links'][$httptestid]['hostid']];

		if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
			$name = new CLink($template['name'],
				(new CUrl('httpconf.php'))
					->setArgument('form', 'update')
					->setArgument('hostid', $template['hostid'])
					->setArgument('httptestid', $parent_templates['links'][$httptestid]['httptestid'])
					->setArgument('context', 'template')
			);
		}
		else {
			$name = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, [NBSP(), RARR(), NBSP()]);

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

/**
 * Get direct or inherited tags for web scenario edit form.
 *
 * @param array  $data
 * @param string $data['templates'][<templateid>]['hostid']
 * @param string $data['templates'][<templateid>]['name']
 * @param int    $data['templates'][<templateid>]['permission']
 * @param string $data['hostid']
 * @param array  $data['tags']
 * @param string $data['tags'][]['tag']
 * @param string $data['tags'][]['value']
 * @param int    $data['show_inherited_tags']
 *
 * @return array
 */
function getHttpTestTags(array $data): array {
	$tags = array_key_exists('tags', $data) ? $data['tags'] : [];

	if ($data['show_inherited_tags']) {
		$db_templates = $data['templates']
			? API::Template()->get([
				'output' => ['templateid'],
				'selectTags' => ['tag', 'value'],
				'templateids' => array_keys($data['templates']),
				'preservekeys' => true
			])
			: [];

		$inherited_tags = [];

		// Make list of template tags.
		foreach ($data['templates'] as $templateid => $template) {
			if (array_key_exists($templateid, $db_templates)) {
				foreach ($db_templates[$templateid]['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $inherited_tags)
							&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
						$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
							$templateid => $template
						];
					}
					else {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
							'parent_templates' => [$templateid => $template],
							'type' => ZBX_PROPERTY_INHERITED
						];
					}
				}
			}
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['hostid'],
			'templated_hosts' => true
		]);

		// Overwrite and attach host level tags.
		if ($db_hosts) {
			foreach ($db_hosts[0]['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag;
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
			}
		}

		// Overwrite and attach http test's own tags.
		foreach ($data['tags'] as $tag) {
			if (array_key_exists($tag['tag'], $inherited_tags)
					&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
			}
			else {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
			}
		}

		$tags = [];
		foreach ($inherited_tags as $tag) {
			foreach ($tag as $value) {
				$tags[] = $value;
			}
		}
	}

	return $tags;
}
