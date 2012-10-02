<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';

$page['title'] = _('Configuration of proxies');
$page['file'] = 'proxies.php';
$page['hist_arg'] = array('');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'proxyid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})&&({form}=="update")'),
	'host' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Proxy name')),
	'status' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(HOST_STATUS_PROXY_ACTIVE,HOST_STATUS_PROXY_PASSIVE), 'isset({save})'),
	'interfaces' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})&&({status}=='.HOST_STATUS_PROXY_PASSIVE.')'),
	'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_STR, O_OPT, null,	null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('host', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	if (!count(get_accessible_nodes_by_user(CWebUser::$data, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	$proxy = array(
		'host' => get_request('host'),
		'status' => get_request('status'),
		'interfaces' => get_request('interfaces'),
		'hosts' => get_request('hosts', array())
	);

	DBstart();
	if (isset($_REQUEST['proxyid'])) {
		$proxy['proxyid'] = $_REQUEST['proxyid'];
		$proxyids = API::Proxy()->update($proxy);

		$action = AUDIT_ACTION_UPDATE;
		$msg_ok = _('Proxy updated');
		$msg_fail = _('Cannot update proxy');
	}
	else {
		$proxyids = API::Proxy()->create($proxy);

		$action = AUDIT_ACTION_ADD;
		$msg_ok = _('Proxy added');
		$msg_fail = _('Cannot add proxy');
	}

	$result = DBend($proxyids);
	show_messages($result, $msg_ok, $msg_fail);

	if ($result) {
		add_audit($action, AUDIT_RESOURCE_PROXY, '['.$_REQUEST['host'].' ] ['.reset($proxyids['proxyids']).']');
		unset($_REQUEST['form']);
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete'])) {
	$result = false;

	if (isset($_REQUEST['proxyid'])) {
		$proxies = API::Proxy()->get(array(
			'proxyids' => $_REQUEST['proxyid'],
			'output' => API_OUTPUT_EXTEND
		));

		$result = API::Proxy()->delete(array('proxyid' => $_REQUEST['proxyid']));

		if ($result) {
			unset($_REQUEST['form'], $_REQUEST['proxyid']);
			$proxy = reset($proxies);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_PROXY, '['.$proxy['host'].' ] ['.$proxy['proxyid'].']');
		}

		show_messages($result, _('Proxy deleted'), _('Cannot delete proxy'));
	}
	unset($_REQUEST['delete']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['proxyid'])) {
	unset($_REQUEST['proxyid'], $_REQUEST['hosts']);
	$_REQUEST['form'] = 'clone';
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['hosts'])) {
	$go_result = true;

	$status = ($_REQUEST['go'] == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$hosts = get_request('hosts', array());

	DBstart();
	foreach ($hosts as $hostid) {
		$dbHosts = DBselect(
			'SELECT h.hostid,h.status'.
			' FROM hosts h'.
			' WHERE h.proxy_hostid='.$hostid.
				' AND '.DBin_node('h.hostid')
		);

		while ($dbHost = DBfetch($dbHosts)) {
			$oldStatus = $dbHost['status'];
			if ($oldStatus == $status) {
				continue;
			}

			$go_result &= updateHostStatus($dbHost['hostid'], $status);
			if (!$go_result) {
				continue;
			}
		}
	}

	$go_result = DBend($go_result && !empty($hosts));
	show_messages($go_result, _('Host status updated'), null);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['hosts'])) {
	$hosts = get_request('hosts', array());

	DBstart();
	$go_result = API::Proxy()->delete(zbx_toObject($hosts, 'proxyid'));
	$go_result = DBend($go_result);

	show_messages($go_result, _('Proxy deleted'), _('Cannot delete proxy'));
}

if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form', 1),
		'form_refresh' => get_request('form_refresh', 0) + 1,
		'proxyid' => get_request('proxyid', 0),
		'name' => get_request('host', ''),
		'status' => get_request('status', HOST_STATUS_PROXY_ACTIVE),
		'hosts' => get_request('hosts', array()),
		'interfaces' => get_request('interfaces', array()),
		'interface' => get_request('interface', array()),
		'proxy' => array()
	);

	// proxy
	if (!empty($data['proxyid'])) {
		$data['proxy'] = API::Proxy()->get(array(
			'proxyids' => $data['proxyid'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid', 'host'),
			'output' => API_OUTPUT_EXTEND
		));
		$data['proxy'] = reset($data['proxy']);

		if (!isset($_REQUEST['form_refresh'])) {
			$data['name'] = $data['proxy']['host'];
			$data['status'] = $data['proxy']['status'];
			$data['interfaces'] = $data['proxy']['interfaces'];
			$data['hosts'] = zbx_objectValues($data['proxy']['hosts'], 'hostid');
		}
	}

	// interfaces
	if ($data['status'] == HOST_STATUS_PROXY_PASSIVE) {
		if (!empty($data['interfaces'])) {
			$data['interface'] = reset($data['interfaces']);
		}
		else {
			$data['interface'] = array(
				'dns' => 'localhost',
				'ip' => '127.0.0.1',
				'useip' => 1,
				'port' => '10051'
			);
		}
	}

	// hosts
	$data['dbHosts'] = DBfetchArray(DBselect(
		'SELECT h.hostid,h.proxy_hostid,h.name'.
		' FROM hosts h'.
		' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
			' AND '.DBin_node('h.hostid')
	));
	order_result($data['dbHosts'], 'name');

	// render view
	$proxyView = new CView('administration.proxy.edit', $data);
	$proxyView->render();
	$proxyView->show();
}
else {
	$data = array();

	$sortfield = getPageSortField('host');

	$data['proxies'] = API::Proxy()->get(array(
		'editable' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));
	$data['proxies'] = zbx_toHash($data['proxies'], 'proxyid');
	$proxyids = array_keys($data['proxies']);

	order_result($data['proxies'], $sortfield, getPageSortOrder());

	// paging
	$data['paging'] = getPagingLine($data['proxies']);

	// calculate performance
	$dbPerformance = DBselect(
		'SELECT h.proxy_hostid,SUM(1.0/i.delay) AS qps'.
		' FROM items i,hosts h'.
		' WHERE i.status='.ITEM_STATUS_ACTIVE.
			' AND i.hostid=h.hostid '.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND i.delay<>0'.
			' AND '.DBcondition('h.proxy_hostid', $proxyids).
		' GROUP BY h.proxy_hostid'
	);
	while ($performance = DBfetch($dbPerformance)) {
		$data['proxies'][$performance['proxy_hostid']]['perf'] = round($performance['qps'], 2);
	}

	// get items
	$items = API::Item()->get(array(
		'groupCount' => 1,
		'countOutput' => 1,
		'proxyids' => $proxyids,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
		'webitems' => 1,
		'monitored' => 1
	));
	foreach ($items as $item) {
		if (!isset($data['proxies'][$item['proxy_hostid']]['item_count'])) {
			$data['proxies'][$item['proxy_hostid']]['item_count'] = 0;
		}
		$data['proxies'][$item['proxy_hostid']]['item_count'] += $item['rowscount'];
	}

	// render view
	$proxyView = new CView('administration.proxy.list', $data);
	$proxyView->render();
	$proxyView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
?>
