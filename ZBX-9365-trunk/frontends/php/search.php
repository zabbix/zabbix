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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Search');
$page['file'] = 'search.php';
$page['hist_arg'] = array();
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		null),
	'search'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	// ajax
	'widgetName' =>	array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
	'widgetState'=>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,		null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('widgetName')) {
	CProfile::update('web.search.hats.'.getRequest('widgetName').'.state', getRequest('widgetState'), PROFILE_TYPE_INT);
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$admin = in_array(CWebUser::$data['type'], array(
	USER_TYPE_ZABBIX_ADMIN,
	USER_TYPE_SUPER_ADMIN
));
$rows_per_page = CWebUser::$data['rows_per_page'];

$searchWidget = new CWidget('search_wdgt');

$search = getRequest('search', '');

// Header
if (zbx_empty($search)) {
	$search = _('Search pattern is empty');
}
$searchWidget->setClass('header');
$searchWidget->addHeader(array(
	_('SEARCH').NAME_DELIMITER,
	bold($search)
), SPACE);

// FIND Hosts
$params = array(
	'search' => array(
		'host' => $search,
		'name' => $search,
		'dns' => $search,
		'ip' => $search
	),
	'limit' => $rows_per_page,
	'selectGroups' => API_OUTPUT_EXTEND,
	'selectInterfaces' => API_OUTPUT_EXTEND,
	'selectItems' => API_OUTPUT_COUNT,
	'selectTriggers' => API_OUTPUT_COUNT,
	'selectGraphs' => API_OUTPUT_COUNT,
	'selectApplications' => API_OUTPUT_COUNT,
	'selectScreens' => API_OUTPUT_COUNT,
	'selectHttpTests' => API_OUTPUT_COUNT,
	'selectDiscoveries' => API_OUTPUT_COUNT,
	'output' => array('name', 'status', 'host'),
	'searchByAny' => true
);
$db_hosts = API::Host()->get($params);

order_result($db_hosts, 'name');

// bump the hosts whose name exactly match the pattern to the top
$hosts = selectByPattern($db_hosts, 'name', $search, $rows_per_page);

$hostids = zbx_objectValues($hosts, 'hostid');

$rw_hosts = API::Host()->get(array(
	'output' => array('hostid'),
	'hostids' => $hostids,
	'editable' => 1
));
$rw_hosts = zbx_toHash($rw_hosts, 'hostid');

$params = array(
	'search' => array(
		'host' => $search,
		'name' => $search,
		'dns' => $search,
		'ip' => $search
	),
	'countOutput' => 1,
	'searchByAny' => true
);

$overalCount = API::Host()->get($params);
$viewCount = count($hosts);

$table = new CTableInfo(_('No hosts found.'));
$table->setHeader(array(
	new CCol(_('Host')),
	new CCol(_('IP')),
	new CCol(_('DNS')),
	new CCol(_('Latest data')),
	new CCol(_('Triggers')),
	new CCol(_('Events')),
	new CCol(_('Graphs')),
	new CCol(_('Screens')),
	new CCol(_('Web')),
	new CCol(_('Applications')),
	new CCol(_('Items')),
	new CCol(_('Triggers')),
	new CCol(_('Graphs')),
	new CCol(_('Discovery')),
	new CCol(_('Web'))
));

foreach ($hosts as $hnum => $host) {
	$hostid = $host['hostid'];

	$interface = reset($host['interfaces']);
	$host['ip'] = $interface['ip'];
	$host['dns'] = $interface['dns'];
	$host['port'] = $interface['port'];

	$style = $host['status'] == HOST_STATUS_NOT_MONITORED ? 'on' : null;

	$group = reset($host['groups']);
	$link = 'groupid='.$group['groupid'].'&hostid='.$hostid;

	// highlight visible name
	$visibleName = make_decoration($host['name'], $search);

	if ($admin && isset($rw_hosts[$hostid])) {
		// host
		$hostCell = array(new CLink($visibleName, 'hosts.php?form=update&'.$link, $style));

		$applications_link = array(
			new CLink(_('Applications'), 'applications.php?'.$link),
			' ('.$host['applications'].')'
		);
		$items_link = array(
			new CLink(_('Items'), 'items.php?filter_set=1&'.$link),
			' ('.$host['items'].')'
		);
		$triggers_link = array(
			new CLink(_('Triggers'), 'triggers.php?'.$link),
			' ('.$host['triggers'].')'
		);
		$graphs_link = array(
			new CLink(_('Graphs'), 'graphs.php?'.$link),
			' ('.$host['graphs'].')'
		);
		$discoveryLink = array(
			new CLink(_('Discovery'), 'host_discovery.php?'.$link),
			' ('.$host['discoveries'].')'
		);
		$httpTestsLink = array(
			new CLink(_('Web'), 'httpconf.php?'.$link),
			' ('.$host['httpTests'].')'
		);
	}
	else {
		// host
		$hostCell = array(new CSpan($visibleName, $style));

		$applications_link = _('Applications').' ('.$host['applications'].')';
		$items_link = _('Items').' ('.$host['items'].')';
		$triggers_link = _('Triggers').' ('.$host['triggers'].')';
		$graphs_link = _('Graphs').' ('.$host['graphs'].')';
		$discoveryLink = _('Discovery').' ('.$host['discoveries'].')';
		$httpTestsLink = _('Web').' ('.$host['httpTests'].')';
	}

	// display the host name only if it matches the search string and is different from the visible name
	if ($host['host'] !== $host['name'] && stripos($host['host'], $search) !== false) {
		$hostCell[] = BR();
		$hostCell[] = '(';
		$hostCell[] = make_decoration($host['host'], $search);
		$hostCell[] = ')';
	}

	$hostip = make_decoration($host['ip'], $search);
	$hostdns = make_decoration($host['dns'], $search);

	$table->addRow(array(
		$hostCell,
		$hostip,
		$hostdns,
		new CLink(_('Latest data'), 'latest.php?filter_set=1&groupids[]='.$group['groupid'].'&hostids[]='.$hostid),
		new CLink(_('Triggers'), 'tr_status.php?'.$link),
		new CLink(_('Events'), 'events.php?source='.EVENT_SOURCE_TRIGGERS.'&'.$link),
		new CLink(_('Graphs'), 'charts.php?'.$link),
		new CLink(_('Screens'), 'host_screen.php?hostid='.$hostid),
		new CLink(_('Web'), 'httpmon.php?'.$link),
		$applications_link,
		$items_link,
		$triggers_link,
		$graphs_link,
		$discoveryLink,
		$httpTestsLink
	));
}

$searchHostWidget = new CCollapsibleUiWidget('search_hosts', $table);
$searchHostWidget->open = (bool) CProfile::get('web.search.hats.search_hosts.state', true);
$searchHostWidget->setHeader(_('Hosts'));
$searchHostWidget->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));

$searchWidget->addItem(new CDiv($searchHostWidget));
//----------------


// Find Host groups
$params = array(
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_COUNT,
	'selectTemplates' => API_OUTPUT_COUNT,
	'search' => array('name' => $search),
	'limit' => $rows_per_page
);
$db_hostGroups = API::HostGroup()->get($params);
order_result($db_hostGroups, 'name');

$hostGroups = selectByPattern($db_hostGroups, 'name', $search, $rows_per_page);
$groupids = zbx_objectValues($hostGroups, 'groupid');

$rw_hostGroups = API::HostGroup()->get(array(
	'output' => array('groupid'),
	'groupids' => $groupids,
	'editable' => true
));
$rw_hostGroups = zbx_toHash($rw_hostGroups, 'groupid');

$params = array(
	'search' => array('name' => $search),
	'countOutput' => 1
);
$overalCount = API::HostGroup()->get($params);
$viewCount = count($hostGroups);

$header = array(
	new CCol(_('Host group')),
	new CCol(_('Latest data')),
	new CCol(_('Triggers')),
	new CCol(_('Events')),
	new CCol(_('Graphs')),
	new CCol(_('Web')),
	$admin ? new CCol(_('Hosts')) : null,
	$admin ? new CCol(_('Templates')) : null,
);

$table = new CTableInfo(_('No host groups found.'));
$table->setHeader($header);

foreach ($hostGroups as $hnum => $group) {
	$hostgroupid = $group['groupid'];

	$caption = make_decoration($group['name'], $search);
	$link = 'groupid='.$hostgroupid.'&hostid=0';

	$hostsLink = null;
	$templatesLink = null;
	$hgroup_link = new CSpan($caption);
	if ($admin) {
		if (isset($rw_hostGroups[$hostgroupid])) {
			if ($group['hosts']) {
				$hostsLink = array(
					new CLink(_('Hosts'), 'hosts.php?groupid='.$hostgroupid),
					' ('.$group['hosts'].')'
				);
			}
			else {
				$hostsLink = _('Hosts').' (0)';
			}

			if ($group['templates']) {
				$templatesLink = array(
					new CLink(_('Templates'), 'templates.php?groupid='.$hostgroupid),
					' ('.$group['templates'].')'
				);
			}
			else {
				$templatesLink = _('Templates').' (0)';
			}

			$hgroup_link = new CLink($caption, 'hostgroups.php?form=update&'.$link);
		}
		else {
			$hostsLink = _('Hosts');
			$templatesLink = _('Templates');
		}
	}

	$table->addRow(array(
		$hgroup_link,
		new CLink(_('Latest data'), 'latest.php?filter_set=1&groupids[]='.$hostgroupid),
		new CLink(_('Triggers'), 'tr_status.php?'.$link),
		new CLink(_('Events'), 'events.php?source='.EVENT_SOURCE_TRIGGERS.'&'.$link),
		new CLink(_('Graphs'), 'charts.php?'.$link),
		new CLink(_('Web'), 'httpmon.php?'.$link),
		$hostsLink,
		$templatesLink
	));
}

$searchHostGroupWidget = new CCollapsibleUiWidget('search_hostgroup', $table);
$searchHostGroupWidget->open = (bool) CProfile::get('web.search.hats.search_hostgroup.state', true);
$searchHostGroupWidget->setHeader(_('Host groups'));
$searchHostGroupWidget->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));

$searchWidget->addItem(new CDiv($searchHostGroupWidget));
//----------------

// FIND Templates
if ($admin) {
	$params = array(
		'output' => array('name', 'host'),
		'selectGroups' => array('groupid'),
		'sortfield' => 'name',
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'search' => array(
			'host' => $search,
			'name' => $search
		),
		'searchByAny' => true,
		'limit' => $rows_per_page
	);
	$db_templates = API::Template()->get($params);
	order_result($db_templates, 'name');

	// bump the templates whose name exactly match the pattern to the top
	$templates = selectByPattern($db_templates, 'name', $search, $rows_per_page);

	$templateids = zbx_objectValues($templates, 'templateid');

	$rw_templates = API::Template()->get(array(
		'output' => array('templateid'),
		'templateids' => $templateids,
		'editable' => 1
	));
	$rw_templates = zbx_toHash($rw_templates, 'templateid');

	$params = array(
		'search' => array(
			'host' => $search,
			'name' => $search
		),
		'countOutput' => 1,
		'searchByAny' => true
	);

	$overalCount = API::Template()->get($params);
	$viewCount = count($templates);

	$header = array(
		new CCol(_('Template')),
		new CCol(_('Applications')),
		new CCol(_('Items')),
		new CCol(_('Triggers')),
		new CCol(_('Graphs')),
		new CCol(_('Screens')),
		new CCol(_('Discovery')),
		new CCol(_('Web')),
	);

	$table = new CTableInfo(_('No templates found.'));
	$table->setHeader($header);

	foreach ($templates as $tnum => $template) {
		$templateid = $template['templateid'];

		$group = reset($template['groups']);
		$link = 'groupid='.$group['groupid'].'&hostid='.$templateid;

		// highlight visible name
		$templateVisibleName = make_decoration($template['name'], $search);

		if (isset($rw_templates[$templateid])) {
			// template
			$templateCell = array(new CLink($templateVisibleName,
				'templates.php?form=update&'.'&templateid='.$templateid
			));

			$applications_link = array(
				new CLink(_('Applications'), 'applications.php?'.$link),
				' ('.$template['applications'].')'
			);
			$items_link = array(
				new CLink(_('Items'), 'items.php?filter_set=1&'.$link),
				' ('.$template['items'].')'
			);
			$triggers_link = array(
				new CLink(_('Triggers'), 'triggers.php?'.$link),
				' ('.$template['triggers'].')'
			);
			$graphs_link = array(
				new CLink(_('Graphs'), 'graphs.php?'.$link),
				' ('.$template['graphs'].')'
			);
			$screensLink = array(
				new CLink(_('Screens'), 'screenconf.php?templateid='.$templateid),
				' ('.$template['screens'].')'
			);
			$discoveryLink = array(
				new CLink(_('Discovery'), 'host_discovery.php?'.$link),
				' ('.$template['discoveries'].')'
			);
			$httpTestsLink = array(
				new CLink(_('Web'), 'httpconf.php?'.$link),
				' ('.$template['httpTests'].')'
			);
		}
		else {
			// host
			$templateCell = array(new CSpan($templateVisibleName));

			$applications_link = _('Applications').' ('.$template['applications'].')';
			$items_link = _('Items').' ('.$template['items'].')';
			$triggers_link = _('Triggers').' ('.$template['triggers'].')';
			$graphs_link = _('Graphs').' ('.$template['graphs'].')';
			$screensLink = _('Screens').' ('.$template['screens'].')';
			$discoveryLink = _('Discovery').' ('.$template['discoveries'].')';
			$httpTestsLink = _('Web').' ('.$template['httpTests'].')';
		}

		// display the template host name only if it matches the search string and is different from the visible name
		if ($template['host'] !== $template['name'] && stripos($template['host'], $search) !== false) {
			$templateCell[] = BR();
			$templateCell[] = '(';
			$templateCell[] = make_decoration($template['host'], $search);
			$templateCell[] = ')';
		}

		$table->addRow(array(
			$templateCell,
			$applications_link,
			$items_link,
			$triggers_link,
			$graphs_link,
			$screensLink,
			$discoveryLink,
			$httpTestsLink
		));
	}

	$searchTemplateWidget = new CCollapsibleUiWidget('search_templates', $table);
	$searchTemplateWidget->open = (bool) CProfile::get('web.search.hats.search_templates.state', true);
	$searchTemplateWidget->setHeader(_('Templates'));
	$searchTemplateWidget->setFooter(_s('Displaying %1$s of %2$s found', $viewCount, $overalCount));

	$searchWidget->addItem(new CDiv($searchTemplateWidget));
}
//----------------

$searchWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
