<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = hasRequest('parent_discoveryid') ? _('Configuration of graph prototypes') : _('Configuration of graphs');
$page['file'] = 'graphs.php';
$page['scripts'] = ['colorpicker.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>	[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			null],
	'copy_type' =>			[T_ZBX_INT, O_OPT, P_SYS,
								IN([COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE]),
								'isset({copy})'
							],
	'copy_mode' =>			[T_ZBX_INT, O_OPT, P_SYS,		IN('0'),		null],
	'graphid' =>			[T_ZBX_INT, O_OPT, P_SYS,		DB_ID,			'isset({form}) && {form} == "update"'],
	'name' =>				[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')],
	'width' =>				[T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535), 'isset({add}) || isset({update})', _('Width')],
	'height' =>				[T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535), 'isset({add}) || isset({update})', _('Height')],
	'graphtype' =>			[T_ZBX_INT, O_OPT, P_SYS,		IN('0,1,2,3'),	'isset({add}) || isset({update})'],
	'show_3d' =>			[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),		null],
	'show_legend' =>		[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),		null],
	'ymin_type' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),	null],
	'ymax_type' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),	null],
	'yaxismin' =>			[T_ZBX_DBL, O_OPT, null,		null,			'(isset({add}) || isset({update})) && isset({graphtype}) && ({graphtype} == '.GRAPH_TYPE_NORMAL.' || {graphtype} == '.GRAPH_TYPE_STACKED.')'],
	'yaxismax' =>			[T_ZBX_DBL, O_OPT, null,		null,			'(isset({add}) || isset({update})) && isset({graphtype}) && ({graphtype} == '.GRAPH_TYPE_NORMAL.' || {graphtype} == '.GRAPH_TYPE_STACKED.')'],
	'ymin_itemid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			'(isset({add}) || isset({update})) && isset({ymin_type}) && {ymin_type} == '.GRAPH_YAXIS_TYPE_ITEM_VALUE],
	'ymax_itemid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			'(isset({add}) || isset({update})) && isset({ymax_type}) && {ymax_type} == '.GRAPH_YAXIS_TYPE_ITEM_VALUE],
	'percent_left' =>		[T_ZBX_DBL, O_OPT, null,		BETWEEN_DBL(0, 100, 4), null, _('Percentile line (left)')],
	'percent_right' =>		[T_ZBX_DBL, O_OPT, null,		BETWEEN_DBL(0, 100, 4), null, _('Percentile line (right)')],
	'visible' =>			[T_ZBX_INT, O_OPT, null,		BETWEEN(0, 1),	null],
	'items' =>				[T_ZBX_STR, O_OPT, null,		null,			null],
	'discover' =>			[T_ZBX_INT, O_OPT, null,		IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'show_work_period' =>	[T_ZBX_INT, O_OPT, null,		IN('1'),		null],
	'show_triggers' =>		[T_ZBX_INT, O_OPT, null,		IN('1'),		null],
	'group_graphid' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			null],
	'copy_targetids' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,			null],
	'context' =>			[T_ZBX_STR, O_MAND, P_SYS,		IN('"host", "template"'),	null],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"graph.masscopyto","graph.massdelete","graph.updatediscover"'),	null],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'copy' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,			null],
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,			null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,		null,			null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_groupids' =>	[T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'filter_hostids' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"graphtype","name","discover"'),					null],
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

$gitems = getRequest('items', []);

$_REQUEST['show_3d'] = getRequest('show_3d', 0);
$_REQUEST['show_legend'] = getRequest('show_legend', 0);

/*
 * Permissions
 */
$hostid = getRequest('hostid', 0);

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

	$hostid = $discoveryRule['hostid'];

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
elseif ($hostid && !isWritableHostTemplates([$hostid])) {
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
	// remove passing "gitemid" to API if new items added via pop-up
	foreach ($gitems as &$item) {
		if (array_key_exists('gitemid', $item) && !$item['gitemid']) {
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
		'gitems' => $gitems
	];

	DBstart();

	// create and update graph prototypes
	if (hasRequest('parent_discoveryid')) {
		$graph['discover'] = getRequest('discover', DB::getDefault('graphs', 'discover'));

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

		$cookieId = $hostid;
	}

	if ($result) {
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
			uncheckTableRows($hostid);
		}
		show_messages($result, _('Graph deleted'), _('Cannot delete graph'));
	}

	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (getRequest('graphid', '') && getRequest('action', '') === 'graph.updatediscover') {
	$result = API::GraphPrototype()->update([
		'graphid' => getRequest('graphid'),
		'discover' => getRequest('discover', DB::getDefault('graphs', 'discover'))
	]);

	show_messages($result, _('Graph updated'), _('Cannot update graph'));
}
elseif (hasRequest('action') && getRequest('action') === 'graph.massdelete' && hasRequest('group_graphid')) {
	$graphIds = getRequest('group_graphid');

	if (hasRequest('parent_discoveryid')) {
		$result = API::GraphPrototype()->delete($graphIds);

		if ($result) {
			uncheckTableRows(getRequest('parent_discoveryid'));
		}
		else {
			$graphs = API::GraphPrototype()->get([
				'graphids' => $graphIds,
				'output' => [],
				'editable' => true
			]);

			uncheckTableRows(getRequest('parent_discoveryid'), zbx_objectValues($graphs, 'graphid'));
		}
		show_messages($result, _('Graph prototypes deleted'), _('Cannot delete graph prototypes'));
	}
	else {
		$result = API::Graph()->delete($graphIds);

		if ($result) {
			uncheckTableRows($hostid);
		}
		else {
			$graphs = API::Graph()->get([
				'graphids' => $graphIds,
				'output' => [],
				'editable' => true
			]);

			uncheckTableRows($hostid, zbx_objectValues($graphs, 'graphid'));
		}
		show_messages($result, _('Graphs deleted'), _('Cannot delete graphs'));
	}
}
elseif (hasRequest('action') && getRequest('action') === 'graph.masscopyto' && hasRequest('copy')
		&& hasRequest('group_graphid')) {
	if (getRequest('copy_targetids', []) && hasRequest('copy_type')) {
		$result = true;

		$options = [
			'output' => ['hostid'],
			'editable' => true,
			'templated_hosts' => true
		];

		// hosts or templates
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$options['hostids'] = getRequest('copy_targetids');
		}
		// host groups
		else {
			$groupids = getRequest('copy_targetids');
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
				if (!copyGraphToHost($graphid, $host['hostid'])) {
					$result = false;
				}
			}
		}
		$result = DBend($result);

		$graphs_count = count(getRequest('group_graphid'));

		if ($result) {
			uncheckTableRows(
				(getRequest('parent_discoveryid') == 0) ? $hostid : getRequest('parent_discoveryid')
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

$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';

/**
 * Update profile keys.
 */
$sort_field = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
$sort_order = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update($prefix.$page['file'].'.sort', $sort_field, PROFILE_TYPE_STR);
CProfile::update($prefix.$page['file'].'.sortorder', $sort_order, PROFILE_TYPE_STR);

if (hasRequest('filter_set')) {
	CProfile::updateArray($prefix.'graphs.filter_groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
	CProfile::updateArray($prefix.'graphs.filter_hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
}
elseif (hasRequest('filter_rst')) {
	CProfile::deleteIdx($prefix.'graphs.filter_groupids');

	$filter_hostids = getRequest('filter_hostids', CProfile::getArray($prefix.'graphs.filter_hostids', []));
	if (count($filter_hostids) != 1) {
		CProfile::deleteIdx($prefix.'graphs.filter_hostids');
	}
}

/*
 * Display
 */
if (hasRequest('parent_discoveryid')) {
	// Argument parent_discoveryid is considered as alternative filter.
	$filter = [
		'groups' => null,
		'hosts' => null
	];
}
else {
	$filter = [
		'groups' => CProfile::getArray($prefix.'graphs.filter_groupids', null),
		'hosts' => CProfile::getArray($prefix.'graphs.filter_hostids', null)
	];
}

// Get host groups.
$filter['groups'] = $filter['groups']
	? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $filter['groups'],
		'editable' => true,
		'preservekeys' => true
	]), ['groupid' => 'id'])
	: [];

$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;
if ($filter_groupids) {
	$filter_groupids = getSubGroups($filter_groupids);
}

// Get hosts.
if (getRequest('context') === 'host') {
	$filter['hosts'] = $filter['hosts']
		? CArrayHelper::renameObjectsKeys(API::Host()->get([
			'output' => ['hostid', 'name'],
			'hostids' => $filter['hosts'],
			'editable' => true,
			'preservekeys' => true
		]), ['hostid' => 'id'])
		: [];
}
else {
	$filter['hosts'] = $filter['hosts']
		? CArrayHelper::renameObjectsKeys(API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $filter['hosts'],
			'editable' => true,
			'preservekeys' => true
		]), ['templateid' => 'id'])
		: [];
}

// Get hostid.
if ($hostid == 0 && count($filter['hosts']) == 1) {
	$hostid = reset($filter['hosts'])['id'];
}

if (hasRequest('action') && getRequest('action') === 'graph.masscopyto' && hasRequest('group_graphid')) {
	$data = getCopyElementsFormData('group_graphid', _('Graphs'));
	$data['action'] = 'graph.masscopyto';

	// render view
	echo (new CView('configuration.copy.elements', $data))->getOutput();
}
elseif (isset($_REQUEST['form'])) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'graphid' => getRequest('graphid', 0),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'group_gid' => getRequest('group_gid', []),
		'hostid' => $hostid,
		'normal_only' => getRequest('normal_only'),
		'context' => getRequest('context')
	];

	if (!empty($data['graphid']) && !isset($_REQUEST['form_refresh'])) {
		$options = [
			'graphids' => $data['graphid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid']
		];

		if ($data['parent_discoveryid'] === null) {
			$options += [
				'selectDiscoveryRule'	=> ['itemid', 'name'],
				'selectGraphDiscovery'	=> ['parent_graphid']
			];
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
		$data['yaxismin'] = sprintf('%.'.ZBX_FLOAT_DIG.'G', $graph['yaxismin']);
		$data['yaxismax'] = sprintf('%.'.ZBX_FLOAT_DIG.'G', $graph['yaxismax']);
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
			$data['graphDiscovery'] = $graph['graphDiscovery'];
		}
		else {
			$data['discover'] = $graph['discover'];
		}

		// if no host has been selected for the navigation panel, use the first graph host
		if ($data['hostid'] == 0) {
			$host = reset($graph['hosts']);
			$data['hostid'] = $host['hostid'];
		}

		// templates
		$flag = ($data['parent_discoveryid'] === null) ? ZBX_FLAG_DISCOVERY_NORMAL : ZBX_FLAG_DISCOVERY_PROTOTYPE;
		$data['templates'] = makeGraphTemplatesHtml($graph['graphid'], getGraphParentTemplates([$graph], $flag),
			$flag, CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);

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
		$data['items'] = $gitems;
		$data['discover'] = getRequest('discover', DB::getDefault('graphs', 'discover'));
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
			'output' => ['itemid', 'hostid', 'name', 'flags'],
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
			$item['flags'] = $items[$item['itemid']]['flags'];
		}
		unset($item);
	}

	// Set ymin_item_name.
	$data['ymin_item_name'] = '';
	$data['ymax_item_name'] = '';

	if ($data['ymin_itemid'] != 0 || $data['ymax_itemid'] != 0) {
		$items = API::Item()->get([
			'output' => ['itemid', 'name'],
			'selectHosts' => ['name'],
			'itemids' => array_filter([$data['ymin_itemid'], $data['ymax_itemid']]),
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
			],
			'webitems' => true,
			'preservekeys' => true
		]);

		if ($data['ymin_itemid'] != 0 && array_key_exists($data['ymin_itemid'], $items)) {
			$item = $items[$data['ymin_itemid']];
			$data['ymin_item_name'] = $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
		}

		if ($data['ymax_itemid'] != 0 && array_key_exists($data['ymax_itemid'], $items)) {
			$item = $items[$data['ymax_itemid']];
			$data['ymax_item_name'] = $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
		}
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
	CArrayHelper::sort($data['items'], ['sortorder']);
	$data['items'] = array_values($data['items']);

	// is template
	$data['is_template'] = ($data['hostid'] == 0) ? false : isTemplate($data['hostid']);

	// render view
	echo (new CView('configuration.graph.edit', $data))->getOutput();
}
else {
	$data = [
		'filter' => $filter,
		'hostid' => $hostid,
		'parent_discoveryid' => hasRequest('parent_discoveryid') ? $discoveryRule['itemid'] : null,
		'graphs' => [],
		'sort' => $sort_field,
		'sortorder' => $sort_order,
		'profileIdx' => $prefix.'graphs.filter',
		'active_tab' => CProfile::get($prefix.'graphs.filter.active', 1),
		'context' => getRequest('context')
	];

	// Select graphs.
	$options = [
		'output' => ['graphid', 'name', 'graphtype'],
		'hostids' => $filter['hosts'] ? array_keys($filter['hosts']) : null,
		'groupids' => $filter_groupids,
		'discoveryids' => hasRequest('parent_discoveryid') ? $discoveryRule['itemid'] : null,
		'templated' => ($data['context'] === 'template'),
		'editable' => true,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
	];

	$data['graphs'] = hasRequest('parent_discoveryid')
		? API::GraphPrototype()->get($options)
		: API::Graph()->get($options);

	if ($sort_field === 'graphtype') {
		foreach ($data['graphs'] as $gnum => $graph) {
			$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
		}
	}

	order_result($data['graphs'], $sort_field, $sort_order);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$url = (new CUrl('graphs.php'))->setArgument('context', $data['context']);

	if (hasRequest('parent_discoveryid')) {
		$url->setArgument('parent_discoveryid', $data['parent_discoveryid']);
	}

	$data['paging'] = CPagerHelper::paginate($page_num, $data['graphs'], $sort_order, $url);

	// Get graphs after paging.
	$options = [
		'output' => ['graphid', 'name', 'templateid', 'graphtype', 'width', 'height'],
		'selectDiscoveryRule' => ['itemid', 'name'],
		'selectHosts' => ($data['hostid'] == 0) ? ['name'] : null,
		'selectTemplates' => ($data['hostid'] == 0) ? ['name'] : null,
		'graphids' => zbx_objectValues($data['graphs'], 'graphid'),
		'preservekeys' => true
	];

	if (hasRequest('parent_discoveryid')) {
		$options['output'][] = 'discover';
		$data['graphs'] = API::GraphPrototype()->get($options);
	}
	else {
		$data['graphs'] = API::Graph()->get($options + ['selectGraphDiscovery' => ['ts_delete']]);
	}

	foreach ($data['graphs'] as $gnum => $graph) {
		$data['graphs'][$gnum]['graphtype'] = graphType($graph['graphtype']);
	}

	if (!hasRequest('parent_discoveryid')) {
		$items = API::Item()->get([
			'output' => ['itemid'],
			'selectGraphs' => ['graphid'],
			'selectItemDiscovery' => ['ts_delete'],
			'graphids' => array_keys($data['graphs']),
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED]
		]);

		foreach ($items as $item) {
			$ts_delete = $item['itemDiscovery']['ts_delete'];

			if ($ts_delete == 0) {
				continue;
			}

			foreach (array_column($item['graphs'], 'graphid') as $graphid) {
				if (!array_key_exists('ts_delete', $data['graphs'][$graphid]['graphDiscovery'])) {
					$data['graphs'][$graphid]['graphDiscovery']['ts_delete'] = $ts_delete;
				}
				else {
					$graph_ts_delete = $data['graphs'][$graphid]['graphDiscovery']['ts_delete'];
					$data['graphs'][$graphid]['graphDiscovery']['ts_delete'] = ($graph_ts_delete > 0)
						? min($ts_delete, $graph_ts_delete)
						: $ts_delete;
				}
			}
		}
	}

	order_result($data['graphs'], $sort_field, $sort_order);

	$data['parent_templates'] = getGraphParentTemplates($data['graphs'], ($data['parent_discoveryid'] === null)
		? ZBX_FLAG_DISCOVERY_NORMAL
		: ZBX_FLAG_DISCOVERY_PROTOTYPE
	);

	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	// render view
	echo (new CView('configuration.graph.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
