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
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of graphs');
$page['file'] = 'graphs.php';
$page['hist_arg'] = array();
$page['scripts'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				null),
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				null),
	'copy_type' =>		array(T_ZBX_INT, O_OPT, P_SYS,		IN('0,1'),			'isset({copy})'),
	'copy_mode' =>		array(T_ZBX_INT, O_OPT, P_SYS,		IN('0'),			null),
	'graphid' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				'(isset({form})&&({form}=="update"))'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,			'isset({save})||isset({preview})', _('Name')),
	'width' =>			array(T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535),	'isset({save})||isset({preview})', _('Width').' (min:20, max:65535)'),
	'height' =>			array(T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535),	'isset({save})||isset({preview})', _('Height').' (min:20, max:65535)'),
	'ymin_type' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null),
	'ymax_type' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null),
	'graphtype' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,2,3'),		'isset({save})||isset({preview})'),
	'yaxismin' =>		array(T_ZBX_DBL, O_OPT, null,		null,				'(isset({save})||isset({preview}))&&(({graphtype}==0)||({graphtype}==1))'),
	'yaxismax' =>		array(T_ZBX_DBL, O_OPT, null,		null,				'(isset({save})||isset({preview}))&&(({graphtype}==0)||({graphtype}==1))'),
	'graph3d' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null),
	'legend' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null),
	'ymin_itemid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				'(isset({save})||isset({preview}))&&isset({ymin_type})&&({ymin_type}==3)'),
	'ymax_itemid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				'(isset({save})||isset({preview}))&&isset({ymax_type})&&({ymax_type}==3)'),
	'percent_left' =>	array(T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null),
	'percent_right' =>	array(T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null),
	'visible' =>		array(T_ZBX_INT, O_OPT, null,		BETWEEN(0, 1),		null),
	'items' =>			array(T_ZBX_STR, O_OPT, null,		null,				null),
	'showworkperiod' =>	array(T_ZBX_INT, O_OPT, null,		IN('1'),			null),
	'showtriggers' =>	array(T_ZBX_INT, O_OPT, null,		IN('1'),			null),
	'group_graphid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				null),
	'copy_targetid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				null),
	'filter_groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,				'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'add_item' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'preview' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'copy' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,				null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,				null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,				null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,		null,				null)
);
$isDataValid = check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['items'] = get_request('items', array());
$_REQUEST['graph3d'] = get_request('graph3d', 0);
$_REQUEST['legend'] = get_request('legend', 0);

/*
 * Permissions
 */
if (!empty($_REQUEST['graphid'])) {
	$options = array(
		'nodeids' => get_current_nodeid(true),
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'graphids' => $_REQUEST['graphid'],
		'editable' => true,
		'preservekeys' => true
	);
	$graphs = API::Graph()->get($options);
	if (empty($graphs)) {
		access_deny();
	}
}
elseif (!empty($_REQUEST['hostid'])) {
	$options = array(
		'hostids' => $_REQUEST['hostid'],
		'output' => API_OUTPUT_EXTEND,
		'templated_hosts' => true,
		'editable' => true,
		'preservekeys' => true
	);
	$hosts = API::Host()->get($options);
	if(empty($hosts)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['graphid'])) {
	unset($_REQUEST['graphid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$result = true;

	$items = get_request('items', array());
	$itemids = array();
	foreach ($items as $item) {
		if (!empty($item['itemid'])) {
			$itemids[$item['itemid']] = $item['itemid'];
		}
		else {
			$result = false;
		}
	}
	if (!$result) {
		info(_('Items required for graph.'));
	}

	if (!empty($itemids) && $result) {
		$dbItems = API::Item()->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $itemids,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
			'webitems' => true,
			'editable' => true
		));
		$dbItems = zbx_toHash($dbItems, 'itemid');

		foreach ($itemids as $itemid) {
			if (!isset($dbItems[$itemid])) {
				access_deny();
			}
		}

		if (!isset($_REQUEST['ymin_type'])) {
			$_REQUEST['ymin_type'] = 0;
		}
		if (!isset($_REQUEST['ymax_type'])) {
			$_REQUEST['ymax_type'] = 0;
		}
		if (!isset($_REQUEST['yaxismin'])) {
			$_REQUEST['yaxismin'] = 0;
		}
		if (!isset($_REQUEST['yaxismax'])) {
			$_REQUEST['yaxismax'] = 0;
		}

		$showworkperiod = isset($_REQUEST['showworkperiod']) ? 1 : 0;
		$showtriggers = isset($_REQUEST['showtriggers']) ? 1 : 0;

		$visible = get_request('visible');
		$percent_left = 0;
		$percent_right = 0;

		if (isset($visible['percent_left'])) {
			$percent_left = get_request('percent_left', 0);
		}
		if (isset($visible['percent_right'])) {
			$percent_right = get_request('percent_right', 0);
		}

		if ($_REQUEST['ymin_itemid'] != 0 && $_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$_REQUEST['yaxismin'] = 0;
		}
		if ($_REQUEST['ymax_itemid'] != 0 && $_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$_REQUEST['yaxismax'] = 0;
		}

		$graph = array(
			'name' => $_REQUEST['name'],
			'width' => $_REQUEST['width'],
			'height' => $_REQUEST['height'],
			'ymin_type' => $_REQUEST['ymin_type'],
			'ymax_type' => $_REQUEST['ymax_type'],
			'yaxismin' => $_REQUEST['yaxismin'],
			'yaxismax' => $_REQUEST['yaxismax'],
			'ymin_itemid' => $_REQUEST['ymin_itemid'],
			'ymax_itemid' => $_REQUEST['ymax_itemid'],
			'show_work_period' => get_request('showworkperiod', 0),
			'show_triggers' => get_request('showtriggers', 0),
			'graphtype' => $_REQUEST['graphtype'],
			'show_legend' => get_request('legend', 1),
			'show_3d' => get_request('graph3d', 0),
			'percent_left' => $percent_left,
			'percent_right' => $percent_right,
			'gitems' => $items
		);

		if (isset($_REQUEST['graphid'])) {
			$graph['graphid'] = $_REQUEST['graphid'];

			$result = API::Graph()->update($graph);
			if ($result) {
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_GRAPH, 'Graph ID ['.$_REQUEST['graphid'].'] Graph ['.$_REQUEST['name'].']');
			}
		}
		else {
			$result = API::Graph()->create($graph);
			if ($result) {
				add_audit(AUDIT_ACTION_ADD, AUDIT_RESOURCE_GRAPH, 'Graph ['.$_REQUEST['name'].']');
			}
		}

		if ($result) {
			unset($_REQUEST['form']);
		}
	}

	if (isset($_REQUEST['graphid'])) {
		show_messages($result, _('Graph updated'), _('Cannot update graph'));
	}
	else {
		show_messages($result, _('Graph added'), _('Cannot add graph'));
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['graphid'])) {
	$result = API::Graph()->delete($_REQUEST['graphid']);
	if ($result) {
		unset($_REQUEST['form']);
	}
	show_messages($result, _('Graph deleted'), _('Cannot delete graph'));
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_graphid'])) {
	$go_result = API::Graph()->delete($_REQUEST['group_graphid']);
	show_messages($go_result, _('Graphs deleted'), _('Cannot delete graphs'));
}
elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['copy']) && isset($_REQUEST['group_graphid'])) {
	if (!empty($_REQUEST['copy_targetid']) && isset($_REQUEST['copy_type'])) {
		$go_result = true;

		$options = array(
			'editable' => true,
			'nodes' => get_current_nodeid(true),
			'templated_hosts' => true
		);

		// hosts
		if ($_REQUEST['copy_type'] == 0) {
			$options['hostids'] = $_REQUEST['copy_targetid'];
		}
		// groups
		else {
			zbx_value2array($_REQUEST['copy_targetid']);

			$dbGroups = API::HostGroup()->get(array(
				'groupids' => $_REQUEST['copy_targetid'],
				'nodes' => get_current_nodeid(true),
				'editable' => true
			));
			$dbGroups = zbx_toHash($dbGroups, 'groupid');

			foreach ($_REQUEST['copy_targetid'] as $groupid) {
				if (!isset($dbGroups[$groupid])) {
					access_deny();
				}
			}

			$options['groupids'] = $_REQUEST['copy_targetid'];
		}

		$dbHosts = API::Host()->get($options);

		DBstart();
		foreach ($_REQUEST['group_graphid'] as $graphid) {
			foreach ($dbHosts as $host) {
				$go_result &= (bool) copy_graph_to_host($graphid, $host['hostid']);
			}
		}
		$go_result = DBend($go_result);

		show_messages($go_result, _('Graphs copied'), _('Cannot copy graphs'));
		$_REQUEST['go'] = 'none2';
	}
	else {
		error(_('No target selected.'));
	}
	show_messages();
}
if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
$pageFilter = new CPageFilter(array(
	'groups' => array(
		'not_proxy_hosts' => true,
		'editable' => true
	),
	'hosts' => array(
		'editable' => true,
		'templated_hosts' => true
	),
	'groupid' => get_request('groupid', null),
	'hostid' => get_request('hostid', null),
));
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

if ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['group_graphid'])) {
	$graphView = new CView('configuration.copy.elements', getCopyElementsFormData('group_graphid'));
	$graphView->render();
	$graphView->show();
}
elseif (isset($_REQUEST['form'])) {
	$data = getGraphFormData();
	$data['isDataValid'] = $isDataValid;

	$graphView = new CView('configuration.graph.edit', $data);
	$graphView->render();
	$graphView->show();
}
else {
	if (isset($_REQUEST['graphid']) && $_REQUEST['graphid'] == 0) {
		unset($_REQUEST['graphid']);
	}

	$data = array(
		'pageFilter' => $pageFilter,
		'hostid' => get_request('hostid'),
		'graphs' => array()
	);

	$sortfield = getPageSortField('name');

	if ($pageFilter->hostsSelected) {
		$options = array(
			'editable' => true,
			'output' => array('graphid', 'name', 'graphtype'),
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		);
		if ($pageFilter->hostid > 0) {
			$options['hostids'] = $pageFilter->hostid;
		}
		elseif ($pageFilter->groupid > 0) {
			$options['groupids'] = $pageFilter->groupid;
		}
		$data['graphs'] = API::Graph()->get($options);
	}

	if ($sortfield == 'graphtype') {
		foreach ($data['graphs'] as $gnum => $graph) {
			$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
		}
	}
	$data['paging'] = getPagingLine($data['graphs']);

	$data['graphs'] = API::Graph()->get(array(
		'graphids' => zbx_objectValues($data['graphs'], 'graphid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectTemplates' => API_OUTPUT_EXTEND,
		'selectDiscoveryRule' => API_OUTPUT_EXTEND
	));

	foreach ($data['graphs'] as $gnum => $graph) {
		$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
	}
	order_result($data['graphs'], $sortfield, getPageSortOrder());


	$graphView = new CView('configuration.graph.list', $data);
	$graphView->render();
	$graphView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
?>
