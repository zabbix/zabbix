<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = isset($_REQUEST['parent_discoveryid']) ? _('Configuration of graph prototypes') : _('Configuration of graphs');
$page['file'] = 'graphs.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>	[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			null],
	'groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			null],
	'copy_type' => [T_ZBX_INT, O_OPT, P_SYS, IN([COPY_TYPE_TO_HOST, COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_TEMPLATE]), 'isset({copy})'],
	'copy_mode' =>			[T_ZBX_INT, O_OPT, P_SYS,		IN('0'),		null],
	'graphid' =>			[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			'isset({form}) && {form} == "update"'],
	'name' =>				[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')],
	'width' =>				[T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535), 'isset({add}) || isset({update})', _('Width')],
	'height' =>				[T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535), 'isset({add}) || isset({update})', _('Height')],
	'graphtype' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1,2,3'),	'isset({add}) || isset({update})'],
	'show_3d' =>			[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),		null],
	'show_legend' =>		[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),		null],
	'ymin_type' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),	null],
	'ymax_type' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),	null],
	'yaxismin' =>			[T_ZBX_DBL, O_OPT, null,		null,			'(isset({add}) || isset({update})) && isset({graphtype}) && ({graphtype} == '.GRAPH_TYPE_NORMAL.' || {graphtype} == '.GRAPH_TYPE_STACKED.')'],
	'yaxismax' =>			[T_ZBX_DBL, O_OPT, null,		null,			'(isset({add}) || isset({update})) && isset({graphtype}) && ({graphtype} == '.GRAPH_TYPE_NORMAL.' || {graphtype} == '.GRAPH_TYPE_STACKED.')'],
	'ymin_itemid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			'(isset({add}) || isset({update})) && isset({ymin_type}) && {ymin_type} == '.GRAPH_YAXIS_TYPE_ITEM_VALUE],
	'ymax_itemid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			'(isset({add}) || isset({update})) && isset({ymax_type}) && {ymax_type} == '.GRAPH_YAXIS_TYPE_ITEM_VALUE],
	'percent_left' =>		[T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100), null, _('Percentile line (left)')],
	'percent_right' =>		[T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100), null, _('Percentile line (right)')],
	'visible' =>			[T_ZBX_INT, O_OPT, null,		BETWEEN(0, 1),	null],
	'items' =>				[T_ZBX_STR, O_OPT, null,		null,			null],
	'show_work_period' =>	[T_ZBX_INT, O_OPT, null,		IN('1'),		null],
	'show_triggers' =>		[T_ZBX_INT, O_OPT, null,		IN('1'),		null],
	'group_graphid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			null],
	'copy_targetid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			null],
	'copy_groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			'isset({copy}) && isset({copy_type}) && {copy_type} == 0'],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"graph.masscopyto","graph.massdelete"'),	null],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'copy' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,			null],
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,			null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,		null,			null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"graphtype","name"'),					null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
$percentVisible = getRequest('visible');
if (!isset($percentVisible['percent_left'])) {
	unset($_REQUEST['percent_left']);
}
if (!isset($percentVisible['percent_right'])) {
	unset($_REQUEST['percent_right']);
}
if (isset($_REQUEST['yaxismin']) && zbx_empty($_REQUEST['yaxismin'])) {
	unset($_REQUEST['yaxismin']);
}
if (isset($_REQUEST['yaxismax']) && zbx_empty($_REQUEST['yaxismax'])) {
	unset($_REQUEST['yaxismax']);
}
check_fields($fields);

$_REQUEST['items'] = getRequest('items', []);
$_REQUEST['show_3d'] = getRequest('show_3d', 0);
$_REQUEST['show_legend'] = getRequest('show_legend', 0);

/*
 * Permissions
 */
$groupId = getRequest('groupid');
if ($groupId && !isWritableHostGroups([$groupId])) {
	access_deny();
}

$hostId = getRequest('hostid', 0);

if (hasRequest('parent_discoveryid')) {
	// check whether discovery rule is editable by user
	$discoveryRule = API::DiscoveryRule()->get([
		'output' => ['itemid', 'hostid'],
		'itemids' => getRequest('parent_discoveryid'),
		'editable' => true
	]);
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule) {
		access_deny();
	}

	$hostId = $discoveryRule['hostid'];

	// check whether graph prototype is editable by user
	if (hasRequest('graphid')) {
		$graphPrototype = (bool) API::GraphPrototype()->get([
			'output' => [],
			'graphids' => getRequest('graphid'),
			'editable' => true
		]);
		if (!$graphPrototype) {
			access_deny();
		}
	}
}
elseif (hasRequest('graphid')) {
	// check whether graph is normal and editable by user
	$graph = (bool) API::Graph()->get([
		'output' => [],
		'graphids' => getRequest('graphid'),
		'editable' => true
	]);
	if (!$graph) {
		access_deny();
	}
}
elseif ($hostId && !isWritableHostTemplates([$hostId])) {
	access_deny();
}

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['graphid'])) {
	// graph
	$options = [
		'graphids' => $_REQUEST['graphid'],
		'output' => API_OUTPUT_EXTEND
	];
	$graph = empty($_REQUEST['parent_discoveryid'])
		? API::Graph()->get($options)
		: API::GraphPrototype()->get($options);
	$graph = reset($graph);

	$graph['items'] = API::GraphItem()->get([
		'graphids' => $_REQUEST['graphid'],
		'sortfield' => 'gitemid',
		'output' => API_OUTPUT_EXTEND
	]);

	if ($graph['templateid'] || $graph['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$_REQUEST = array_merge($_REQUEST, $graph);
	}
	else {
		$graph = array_merge($graph, $_REQUEST);
	}

	unset($_REQUEST['graphid']);

	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	$items = getRequest('items', []);

	// remove passing "gitemid" to API if new items added via pop-up
	foreach ($items as &$item) {
		if (!$item['gitemid']) {
			unset($item['gitemid']);
		}
	}
	unset($item);

	$graph = [
		'name' => getRequest('name'),
		'width' => getRequest('width'),
		'height' => getRequest('height'),
		'ymin_type' => getRequest('ymin_type', 0),
		'ymax_type' => getRequest('ymax_type', 0),
		'yaxismin' => getRequest('yaxismin', 0),
		'yaxismax' => getRequest('yaxismax', 0),
		'ymin_itemid' => getRequest('ymin_itemid'),
		'ymax_itemid' => getRequest('ymax_itemid'),
		'show_work_period' => getRequest('show_work_period', 0),
		'show_triggers' => getRequest('show_triggers', 0),
		'graphtype' => getRequest('graphtype'),
		'show_legend' => getRequest('show_legend', 1),
		'show_3d' => getRequest('show_3d', 0),
		'percent_left' => getRequest('percent_left', 0),
		'percent_right' => getRequest('percent_right', 0),
		'gitems' => $items
	];

	DBstart();

	// create and update graph prototypes
	if (hasRequest('parent_discoveryid')) {
		if (hasRequest('graphid')) {
			$graph['graphid'] = getRequest('graphid');
			$result = API::GraphPrototype()->update($graph);

			$messageSuccess = _('Graph prototype updated');
			$messageFailed = _('Cannot update graph prototype');
		}
		else {
			$result = API::GraphPrototype()->create($graph);

			$messageSuccess = _('Graph prototype added');
			$messageFailed = _('Cannot add graph prototype');
		}

		$cookieId = getRequest('parent_discoveryid');
	}
	// create and update graphs
	else {
		if (hasRequest('graphid')) {
			$graph['graphid'] = getRequest('graphid');
			$result = API::Graph()->update($graph);

			$messageSuccess = _('Graph updated');
			$messageFailed = _('Cannot update graph');
		}
		else {
			$result = API::Graph()->create($graph);

			$messageSuccess = _('Graph added');
			$messageFailed = _('Cannot add graph');
		}

		$cookieId = $hostId;
	}

	if ($result) {
		if (hasRequest('graphid')) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_GRAPH,
				'Graph ID ['.$graph['graphid'].'] Graph ['.getRequest('name').']'
			);
		}
		else {
			add_audit(AUDIT_ACTION_ADD, AUDIT_RESOURCE_GRAPH, 'Graph ['.getRequest('name').']');
		}

		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows($cookieId);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('delete') && hasRequest('graphid')) {
	$graphId = getRequest('graphid');

	if (hasRequest('parent_discoveryid')) {
		$result = API::GraphPrototype()->delete([$graphId]);

		if ($result) {
			uncheckTableRows(getRequest('parent_discoveryid'));
		}
		show_messages($result, _('Graph prototype deleted'), _('Cannot delete graph prototype'));
	}
	else {
		$result = API::Graph()->delete([$graphId]);

		if ($result) {
			uncheckTableRows($hostId);
		}
		show_messages($result, _('Graph deleted'), _('Cannot delete graph'));
	}

	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (hasRequest('action') && getRequest('action') === 'graph.massdelete' && hasRequest('group_graphid')) {
	$graphIds = getRequest('group_graphid');

	if (hasRequest('parent_discoveryid')) {
		$result = API::GraphPrototype()->delete($graphIds);

		if ($result) {
			uncheckTableRows(getRequest('parent_discoveryid'));
		}
		show_messages($result, _('Graph prototypes deleted'), _('Cannot delete graph prototypes'));
	}
	else {
		$result = API::Graph()->delete($graphIds);

		if ($result) {
			uncheckTableRows($hostId);
		}
		show_messages($result, _('Graphs deleted'), _('Cannot delete graphs'));
	}
}
elseif (hasRequest('action') && getRequest('action') === 'graph.masscopyto' && hasRequest('copy')
		&& hasRequest('group_graphid')) {
	if (getRequest('copy_targetid') != 0 && hasRequest('copy_type')) {
		$result = true;

		$options = [
			'output' => ['hostid'],
			'editable' => true,
			'templated_hosts' => true
		];

		// hosts or templates
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$options['hostids'] = getRequest('copy_targetid');
		}
		// host groups
		else {
			$groupids = getRequest('copy_targetid');
			zbx_value2array($groupids);

			$dbGroups = API::HostGroup()->get([
				'output' => ['groupid'],
				'groupids' => $groupids,
				'editable' => true
			]);
			$dbGroups = zbx_toHash($dbGroups, 'groupid');

			foreach ($groupids as $groupid) {
				if (!isset($dbGroups[$groupid])) {
					access_deny();
				}
			}

			$options['groupids'] = $groupids;
		}

		$dbHosts = API::Host()->get($options);

		DBstart();
		foreach (getRequest('group_graphid') as $graphid) {
			foreach ($dbHosts as $host) {
				$result &= (bool) copyGraphToHost($graphid, $host['hostid']);
			}
		}
		$result = DBend($result);

		$graphs_count = count(getRequest('group_graphid'));

		if ($result) {
			uncheckTableRows(
				(getRequest('parent_discoveryid') == 0) ? $hostId : getRequest('parent_discoveryid')
			);
			unset($_REQUEST['group_graphid']);
		}
		show_messages($result,
			_n('Graph copied', 'Graphs copied', $graphs_count),
			_n('Cannot copy graph', 'Cannot copy graphs', $graphs_count)
		);
	}
	else {
		error(_('No target selected.'));
	}
	show_messages();
}

/*
 * Display
 */
$pageFilter = new CPageFilter([
	'groups' => [
		'with_hosts_and_templates' => true,
		'editable' => true
	],
	'hosts' => [
		'editable' => true,
		'templated_hosts' => true
	],
	'groupid' => $groupId,
	'hostid' => $hostId
]);

if (empty($_REQUEST['parent_discoveryid'])) {
	if ($pageFilter->groupid > 0) {
		$groupId = $pageFilter->groupids;
	}
	if ($pageFilter->hostid > 0) {
		$hostId = $pageFilter->hostid;
	}
}

if (hasRequest('action') && getRequest('action') == 'graph.masscopyto' && hasRequest('group_graphid')) {
	// render view
	$data = getCopyElementsFormData('group_graphid');
	$data['action'] = 'graph.masscopyto';
	$graphView = new CView('configuration.copy.elements', $data);
	$graphView->render();
	$graphView->show();
}
elseif (isset($_REQUEST['form'])) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'graphid' => getRequest('graphid', 0),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'group_gid' => getRequest('group_gid', []),
		'hostid' => $hostId,
		'normal_only' => getRequest('normal_only')
	];

	if (!empty($data['graphid']) && !isset($_REQUEST['form_refresh'])) {
		$options = [
			'graphids' => $data['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid']
		];

		if ($data['parent_discoveryid'] === null) {
			$options['selectDiscoveryRule'] = ['itemid', 'name'];
			$graph = API::Graph()->get($options);
		}
		else {
			$graph = API::GraphPrototype()->get($options);
		}

		$graph = reset($graph);

		$data['name'] = $graph['name'];
		$data['width'] = $graph['width'];
		$data['height'] = $graph['height'];
		$data['ymin_type'] = $graph['ymin_type'];
		$data['ymax_type'] = $graph['ymax_type'];
		$data['yaxismin'] = $graph['yaxismin'];
		$data['yaxismax'] = $graph['yaxismax'];
		$data['ymin_itemid'] = $graph['ymin_itemid'];
		$data['ymax_itemid'] = $graph['ymax_itemid'];
		$data['show_work_period'] = $graph['show_work_period'];
		$data['show_triggers'] = $graph['show_triggers'];
		$data['graphtype'] = $graph['graphtype'];
		$data['show_legend'] = $graph['show_legend'];
		$data['show_3d'] = $graph['show_3d'];
		$data['percent_left'] = $graph['percent_left'];
		$data['percent_right'] = $graph['percent_right'];
		$data['templateid'] = $graph['templateid'];
		$data['templates'] = [];

		if ($data['parent_discoveryid'] === null) {
			$data['flags'] = $graph['flags'];
			$data['discoveryRule'] = $graph['discoveryRule'];
		}

		// if no host has been selected for the navigation panel, use the first graph host
		if ($data['hostid'] == 0) {
			$host = reset($graph['hosts']);
			$data['hostid'] = $host['hostid'];
		}

		// templates
		if (!empty($data['templateid'])) {
			$parentGraphid = $data['templateid'];
			do {
				$parentGraph = getGraphByGraphId($parentGraphid);

				// parent graph prototype link
				if (getRequest('parent_discoveryid')) {
					$parentGraphPrototype = API::GraphPrototype()->get([
						'output' => ['graphid'],
						'graphids' => $parentGraph['graphid'],
						'selectTemplates' => API_OUTPUT_EXTEND,
						'selectDiscoveryRule' => ['itemid']
					]);
					if ($parentGraphPrototype) {
						$parentGraphPrototype = reset($parentGraphPrototype);
						$parentTemplate = reset($parentGraphPrototype['templates']);

						$link = new CLink($parentTemplate['name'],
							'graphs.php?form=update&graphid='.$parentGraphPrototype['graphid'].'&hostid='.$parentTemplate['templateid'].'&parent_discoveryid='.$parentGraphPrototype['discoveryRule']['itemid']
						);
					}
				}
				// parent graph link
				else {
					$parentTemplate = get_hosts_by_graphid($parentGraph['graphid']);
					$parentTemplate = DBfetch($parentTemplate);

					$link = new CLink($parentTemplate['name'],
						'graphs.php?form=update&graphid='.$parentGraph['graphid'].'&hostid='.$parentTemplate['hostid']
					);
				}
				if (isset($link)) {
					$data['templates'][] = $link;
					$data['templates'][] = ' &rArr; ';
				}
				$parentGraphid = $parentGraph['templateid'];
			} while ($parentGraphid != 0);
			$data['templates'] = array_reverse($data['templates']);
			array_shift($data['templates']);
		}

		// items
		$data['items'] = API::GraphItem()->get([
			'output' => [
				'gitemid', 'graphid', 'itemid', 'type', 'drawtype', 'yaxisside', 'calc_fnc', 'color', 'sortorder'
			],
			'graphids' => $data['graphid'],
			'sortfield' => 'gitemid'
		]);
	}
	else {
		$data['name'] = getRequest('name', '');
		$data['graphtype'] = getRequest('graphtype', GRAPH_TYPE_NORMAL);

		if ($data['graphtype'] == GRAPH_TYPE_PIE || $data['graphtype'] == GRAPH_TYPE_EXPLODED) {
			$data['width'] = getRequest('width', 400);
			$data['height'] = getRequest('height', 300);
		}
		else {
			$data['width'] = getRequest('width', 900);
			$data['height'] = getRequest('height', 200);
		}

		$data['ymin_type'] = getRequest('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
		$data['ymax_type'] = getRequest('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);
		$data['yaxismin'] = getRequest('yaxismin', 0);
		$data['yaxismax'] = getRequest('yaxismax', 100);
		$data['ymin_itemid'] = getRequest('ymin_itemid', 0);
		$data['ymax_itemid'] = getRequest('ymax_itemid', 0);
		$data['show_work_period'] = getRequest('show_work_period', 0);
		$data['show_triggers'] = getRequest('show_triggers', 0);
		$data['show_legend'] = getRequest('show_legend', 0);
		$data['show_3d'] = getRequest('show_3d', 0);
		$data['visible'] = getRequest('visible');
		$data['percent_left'] = 0;
		$data['percent_right'] = 0;
		$data['visible'] = getRequest('visible');
		$data['items'] = getRequest('items', []);
		$data['templates'] = [];

		if (isset($data['visible']['percent_left'])) {
			$data['percent_left'] = getRequest('percent_left', 0);
		}
		if (isset($data['visible']['percent_right'])) {
			$data['percent_right'] = getRequest('percent_right', 0);
		}
	}

	if (empty($data['graphid']) && !isset($_REQUEST['form_refresh'])) {
		$data['show_legend'] = $_REQUEST['show_legend'] = 1;
		$data['show_work_period'] = $_REQUEST['show_work_period'] = 1;
		$data['show_triggers'] = $_REQUEST['show_triggers'] = 1;
	}

	// items
	if ($data['items']) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'itemids' => zbx_objectValues($data['items'], 'itemid'),
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
			],
			'webitems' => true,
			'preservekeys' => true
		]);

		foreach ($data['items'] as &$item) {
			$host = reset($items[$item['itemid']]['hosts']);

			$item['host'] = $host['name'];
			$item['hostid'] = $items[$item['itemid']]['hostid'];
			$item['name'] = $items[$item['itemid']]['name'];
			$item['key_'] = $items[$item['itemid']]['key_'];
			$item['flags'] = $items[$item['itemid']]['flags'];
		}
		unset($item);

		$data['items'] = CMacrosResolverHelper::resolveItemNames($data['items']);
	}

	$data['items'] = array_values($data['items']);
	$itemCount = count($data['items']);
	for ($i = 0; $i < $itemCount - 1;) {
		// check if we delete an item
		$next = $i + 1;
		while (!isset($data['items'][$next]) && $next < ($itemCount - 1)) {
			$next++;
		}

		if (isset($data['items'][$next]) && $data['items'][$i]['sortorder'] == $data['items'][$next]['sortorder']) {
			for ($j = $next; $j < $itemCount; $j++) {
				if ($data['items'][$j - 1]['sortorder'] >= $data['items'][$j]['sortorder']) {
					$data['items'][$j]['sortorder']++;
				}
			}
		}

		$i = $next;
	}
	asort_by_key($data['items'], 'sortorder');
	$data['items'] = array_values($data['items']);

	// is template
	$data['is_template'] = ($data['hostid'] == 0) ? false : isTemplate($data['hostid']);

	// render view
	$graphView = new CView('configuration.graph.edit', $data);
	$graphView->render();
	$graphView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'pageFilter' => $pageFilter,
		'hostid' => ($pageFilter->hostid > 0) ? $pageFilter->hostid : $hostId,
		'parent_discoveryid' => isset($discoveryRule) ? $discoveryRule['itemid'] : null,
		'graphs' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// get graphs
	$options = [
		'hostids' => ($data['hostid'] == 0) ? null : $data['hostid'],
		'groupids' => ($data['hostid'] == 0 && $pageFilter->groupid > 0) ? $pageFilter->groupids : null,
		'discoveryids' => isset($discoveryRule) ? $discoveryRule['itemid'] : null,
		'editable' => true,
		'output' => ['graphid', 'name', 'graphtype'],
		'limit' => $config['search_limit'] + 1
	];

	$data['graphs'] = isset($discoveryRule)
		? API::GraphPrototype()->get($options)
		: API::Graph()->get($options);

	if ($sortField == 'graphtype') {
		foreach ($data['graphs'] as $gnum => $graph) {
			$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
		}
	}

	order_result($data['graphs'], $sortField, $sortOrder);

	$url = (new CUrl('graphs.php'))
		->setArgument('groupid', $pageFilter->groupid)
		->setArgument('hostid', $data['hostid']);

	$data['paging'] = getPagingLine($data['graphs'], $sortOrder, $url);

	// get graphs after paging
	$options = [
		'graphids' => zbx_objectValues($data['graphs'], 'graphid'),
		'output' => ['graphid', 'name', 'templateid', 'graphtype', 'width', 'height'],
		'selectDiscoveryRule' => ['itemid', 'name'],
		'selectHosts' => ($data['hostid'] == 0) ? ['name'] : null,
		'selectTemplates' => ($data['hostid'] == 0) ? ['name'] : null
	];

	$data['graphs'] = empty($_REQUEST['parent_discoveryid'])
		? API::Graph()->get($options)
		: API::GraphPrototype()->get($options);

	foreach ($data['graphs'] as $gnum => $graph) {
		$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
	}

	order_result($data['graphs'], $sortField, $sortOrder);

	// render view
	$graphView = new CView('configuration.graph.list', $data);
	$graphView->render();
	$graphView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
