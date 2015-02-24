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

$page['title'] = _('Configuration of proxies');
$page['file'] = 'proxies.php';
$page['hist_arg'] = array('');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'proxyid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'),
	'host' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Proxy name')),
	'status' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(HOST_STATUS_PROXY_ACTIVE,HOST_STATUS_PROXY_PASSIVE), 'isset({add}) || isset({update})'),
	'interface' =>		array(T_ZBX_STR, O_OPT, null,	null,		'(isset({add}) || isset({update})) && {status} == '.HOST_STATUS_PROXY_PASSIVE),
	'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'description' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	// actions
	'action' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"proxy.massenable","proxy.massdisable","proxy.massdelete"'),
							null
						),
	'add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	// sort and sortorder
	'sort' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"host"'),								null),
	'sortorder' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['proxyid'])) {
	$dbProxy = API::Proxy()->get(array(
		'proxyids' => getRequest('proxyid'),
		'selectHosts' => array('hostid', 'host'),
		'selectInterface' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	));

	if (!$dbProxy) {
		access_deny();
	}
}
if (isset($_REQUEST['action'])) {
	if (!isset($_REQUEST['hosts']) || !is_array($_REQUEST['hosts'])) {
		access_deny();
	}
	else {
		$dbProxyChk = API::Proxy()->get(array(
			'proxyids' => $_REQUEST['hosts'],
			'selectHosts' => array('hostid', 'host'),
			'selectInterface' => API_OUTPUT_EXTEND,
			'countOutput' => true
		));
		if ($dbProxyChk != count($_REQUEST['hosts'])) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$proxy = array(
		'host' => getRequest('host'),
		'status' => getRequest('status'),
		'interface' => getRequest('interface'),
		'description' => getRequest('description')
	);

	DBstart();

	// skip discovered hosts
	$proxy['hosts'] = API::Host()->get(array(
		'hostids' => getRequest('hosts', array()),
		'output' => array('hostid'),
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
	));

	if (hasRequest('update')) {
		$proxy['proxyid'] = getRequest('proxyid');
		$result = API::Proxy()->update($proxy);

		$messageSuccess = _('Proxy updated');
		$messageFailed = _('Cannot update proxy');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = API::Proxy()->create($proxy);

		$messageSuccess = _('Proxy added');
		$messageFailed = _('Cannot add proxy');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_PROXY, '['.$_REQUEST['host'].'] ['.reset($result['proxyids']).']');
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete'])) {
	$result = API::Proxy()->delete(array($_REQUEST['proxyid']));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['proxyid']);
		uncheckTableRows();
	}
	show_messages($result, _('Proxy deleted'), _('Cannot delete proxy'));

	unset($_REQUEST['delete']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['proxyid'])) {
	unset($_REQUEST['proxyid'], $_REQUEST['hosts']);
	$_REQUEST['form'] = 'clone';
}
elseif (str_in_array(getRequest('action'), array('proxy.massenable', 'proxy.massdisable')) && hasRequest('hosts')) {
	$result = true;
	$enable =(getRequest('action') == 'proxy.massenable');
	$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$hosts = getRequest('hosts');

	DBstart();

	$updated = 0;
	foreach ($hosts as $hostId) {
		$dbHosts = DBselect(
			'SELECT h.hostid,h.status FROM hosts h WHERE h.proxy_hostid='.zbx_dbstr($hostId)
		);

		while ($dbHost = DBfetch($dbHosts)) {
			$oldStatus = $dbHost['status'];
			$updated++;

			if ($oldStatus == $status) {
				continue;
			}

			$result &= updateHostStatus($dbHost['hostid'], $status);
			if (!$result) {
				continue;
			}
		}
	}

	$result = DBend($result && $hosts);

	if ($result) {
		uncheckTableRows();
	}

	$messageSuccess = $enable
		? _n('Host enabled', 'Hosts enabled', $updated)
		: _n('Host disabled', 'Hosts disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable host', 'Cannot enable hosts', $updated)
		: _n('Cannot disable host', 'Cannot disable hosts', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'proxy.massdelete' && hasRequest('hosts')) {
	DBstart();

	$result = API::Proxy()->delete(getRequest('hosts'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Proxy deleted'), _('Cannot delete proxy'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => getRequest('form', 1),
		'form_refresh' => getRequest('form_refresh', 0) + 1,
		'proxyid' => getRequest('proxyid', 0),
		'name' => getRequest('host', ''),
		'status' => getRequest('status', HOST_STATUS_PROXY_ACTIVE),
		'hosts' => getRequest('hosts', array()),
		'interface' => getRequest('interface', array()),
		'proxy' => array(),
		'description' => getRequest('description', '')
	);

	// proxy
	if ($data['proxyid']) {
		$dbProxy = reset($dbProxy);

		if (!isset($_REQUEST['form_refresh'])) {
			$data['name'] = $dbProxy['host'];
			$data['status'] = $dbProxy['status'];
			$data['interface'] = $dbProxy['interface'];
			$data['hosts'] = zbx_objectValues($dbProxy['hosts'], 'hostid');
			$data['description'] = $dbProxy['description'];
		}
	}

	// interface
	if ($data['status'] == HOST_STATUS_PROXY_PASSIVE && !$data['interface']) {
		$data['interface'] = array(
			'dns' => 'localhost',
			'ip' => '127.0.0.1',
			'useip' => 1,
			'port' => '10051'
		);
	}

	// fetch available hosts, skip host prototypes
	$data['dbHosts'] = DBfetchArray(DBselect(
		'SELECT h.hostid,h.proxy_hostid,h.name,h.flags'.
		' FROM hosts h'.
		' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
			' AND h.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE
	));
	order_result($data['dbHosts'], 'name');

	// render view
	$proxyView = new CView('administration.proxy.edit', $data);
	$proxyView->render();
	$proxyView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'host'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = array(
		'config' => select_config(),
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	$data['proxies'] = API::Proxy()->get(array(
		'editable' => true,
		'selectHosts' => array('hostid', 'host', 'name', 'status'),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	));
	$data['proxies'] = zbx_toHash($data['proxies'], 'proxyid');

	$proxyIds = array_keys($data['proxies']);

	// sorting & paging
	order_result($data['proxies'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['proxies']);

	// calculate performance
	$dbPerformance = DBselect(
		'SELECT h.proxy_hostid,SUM(CAST(1.0/i.delay AS DECIMAL(20,10))) AS qps'.
		' FROM items i,hosts h'.
		' WHERE i.status='.ITEM_STATUS_ACTIVE.
			' AND i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND i.delay<>0'.
			' AND i.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE.
			' AND '.dbConditionInt('h.proxy_hostid', $proxyIds).
		' GROUP BY h.proxy_hostid'
	);
	while ($performance = DBfetch($dbPerformance)) {
		if (isset($data['proxies'][$performance['proxy_hostid']])) {
			$data['proxies'][$performance['proxy_hostid']]['perf'] = round($performance['qps'], 2);
		}
	}

	// get items
	$items = API::Item()->get(array(
		'proxyids' => $proxyIds,
		'groupCount' => true,
		'countOutput' => true,
		'webitems' => true,
		'monitored' => true
	));
	foreach ($items as $item) {
		if (isset($data['proxies'][$item['proxy_hostid']])) {
			if (!isset($data['proxies'][$item['proxy_hostid']]['item_count'])) {
				$data['proxies'][$item['proxy_hostid']]['item_count'] = 0;
			}

			$data['proxies'][$item['proxy_hostid']]['item_count'] += $item['rowscount'];
		}
	}

	// render view
	$proxyView = new CView('administration.proxy.list', $data);
	$proxyView->render();
	$proxyView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
