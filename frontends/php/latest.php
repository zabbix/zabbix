<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Latest data');
$page['file'] = 'latest.php';
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['scripts'] = ['multiselect.js'];

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR						TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupids' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostids' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'fullscreen' =>			[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	'select' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'show_without_data' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'show_details' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'application' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"host","lastclock","name"'),				null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupids') && !isReadableHostGroups(getRequest('groupids'))) {
	access_deny();
}
if (getRequest('hostids') && !isReadableHosts(getRequest('hostids'))) {
	access_deny();
}

if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']){
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

require_once dirname(__FILE__).'/include/views/js/monitoring.latest.js.php';

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.latest.filter.select', getRequest('select', ''), PROFILE_TYPE_STR);
	CProfile::update('web.latest.filter.show_without_data', getRequest('show_without_data', 0), PROFILE_TYPE_INT);
	CProfile::update('web.latest.filter.show_details', getRequest('show_details', 0), PROFILE_TYPE_INT);
	CProfile::update('web.latest.filter.application', getRequest('application', ''), PROFILE_TYPE_STR);
	CProfile::updateArray('web.latest.filter.groupids', getRequest('groupids', []), PROFILE_TYPE_STR);
	CProfile::updateArray('web.latest.filter.hostids', getRequest('hostids', []), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.latest.filter.select');
	CProfile::delete('web.latest.filter.show_without_data');
	CProfile::delete('web.latest.filter.show_details');
	CProfile::delete('web.latest.filter.application');
	CProfile::deleteIdx('web.latest.filter.groupids');
	CProfile::deleteIdx('web.latest.filter.hostids');
	DBend();
}

$filter = [
	'select' => CProfile::get('web.latest.filter.select', ''),
	'showWithoutData' => CProfile::get('web.latest.filter.show_without_data', 1),
	'showDetails' => CProfile::get('web.latest.filter.show_details'),
	'application' => CProfile::get('web.latest.filter.application', ''),
	'groupids' => CProfile::getArray('web.latest.filter.groupids'),
	'hostids' => CProfile::getArray('web.latest.filter.hostids')
];

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

$applications = [];
$items = [];
$hostScripts = [];
$child_groups = [];

// multiselect host groups
$multiselect_hostgroup_data = [];
if ($filter['groupids'] !== null) {
	$filterGroups = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $filter['groupids'],
		'preservekeys' => true
	]);

	if ($filterGroups) {
		foreach ($filterGroups as $group) {
			$multiselect_hostgroup_data[] = [
				'id' => $group['groupid'],
				'name' => $group['name']
			];

			$child_groups[] = $group['name'].'/';
		}
	}
	else {
		$filter['groupids'] = [];
	}
}

// we'll only display the values if the filter is set
$filterSet = ($filter['select'] !== '' || $filter['application'] !== '' || $filter['groupids'] || $filter['hostids']);
if ($filterSet) {
	$groupids = null;
	if ($child_groups) {
		$groups = $filterGroups;
		foreach ($child_groups as $child_group) {
			$child_groups = API::HostGroup()->get([
				'output' => ['groupid'],
				'search' => ['name' => $child_group],
				'startSearch' => true,
				'preservekeys' => true
			]);
			$groups = array_replace($groups, $child_groups);
		}
		$groupids = array_keys($groups);
	}

	$hosts = API::Host()->get([
		'output' => ['name', 'hostid', 'status'],
		'hostids' => $filter['hostids'],
		'groupids' => $groupids,
		'selectGraphs' => API_OUTPUT_COUNT,
		'with_monitored_items' => true,
		'preservekeys' => true
	]);
}
else {
	$hosts = [];
}

if ($hosts) {

	foreach ($hosts as &$host) {
		$host['item_cnt'] = 0;
	}
	unset($host);

	$sortFields = ($sortField === 'host') ? [['field' => 'name', 'order' => $sortOrder]] : ['name'];
	CArrayHelper::sort($hosts, $sortFields);

	$hostIds = array_keys($hosts);

	$applications = null;

	// if an application filter is set, fetch the applications and then use them to filter items
	if ($filter['application'] !== '') {
		$applications = API::Application()->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $hostIds,
			'search' => ['name' => $filter['application']],
			'preservekeys' => true
		]);
	}

	$items = API::Item()->get([
		'hostids' => array_keys($hosts),
		'output' => ['itemid', 'name', 'type', 'value_type', 'units', 'hostid', 'state', 'valuemapid', 'status',
			'error', 'trends', 'history', 'delay', 'key_', 'flags'],
		'selectApplications' => ['applicationid'],
		'selectItemDiscovery' => ['ts_delete'],
		'applicationids' => ($applications !== null) ? zbx_objectValues($applications, 'applicationid') : null,
		'webitems' => true,
		'filter' => [
			'status' => [ITEM_STATUS_ACTIVE]
		],
		'preservekeys' => true
	]);

	// if the applications haven't been loaded when filtering, load them based on the retrieved items to avoid
	// fetching applications from hosts that may not be displayed
	if ($applications === null) {
		$applications = API::Application()->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => array_keys(array_flip(zbx_objectValues($items, 'hostid'))),
			'search' => ['name' => $filter['application']],
			'preservekeys' => true
		]);
	}
}

if ($items) {
	// macros
	$items = CMacrosResolverHelper::resolveItemKeys($items);
	$items = CMacrosResolverHelper::resolveItemNames($items);
	$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

	// filter items by name
	foreach ($items as $key => $item) {
		if (($filter['select'] !== '')) {
			$haystack = mb_strtolower($item['name_expanded']);
			$needle = mb_strtolower($filter['select']);

			if (mb_strpos($haystack, $needle) === false) {
				unset($items[$key]);
			}
		}
	}

	if ($items) {
		// get history
		$history = Manager::History()->getLastValues($items, 2, ZBX_HISTORY_PERIOD);

		// filter items without history
		if (!$filter['showWithoutData']) {
			foreach ($items as $key => $item) {
				if (!isset($history[$item['itemid']])) {
					unset($items[$key]);
				}
			}
		}
	}

	if ($items) {
		// add item last update date for sorting
		foreach ($items as &$item) {
			if (isset($history[$item['itemid']])) {
				$item['lastclock'] = $history[$item['itemid']][0]['clock'];
			}
		}
		unset($item);

		// sort
		if ($sortField === 'name') {
			$sortFields = [['field' => 'name_expanded', 'order' => $sortOrder], 'itemid'];
		}
		elseif ($sortField === 'lastclock') {
			$sortFields = [['field' => 'lastclock', 'order' => $sortOrder], 'name_expanded', 'itemid'];
		}
		else {
			$sortFields = ['name_expanded', 'itemid'];
		}
		CArrayHelper::sort($items, $sortFields);

		if ($applications) {
			foreach ($applications as &$application) {
				$application['hostname'] = $hosts[$application['hostid']]['name'];
				$application['item_cnt'] = 0;
			}
			unset($application);

			// by default order by application name and application id
			$sortFields = ($sortField === 'host') ? [['field' => 'hostname', 'order' => $sortOrder]] : [];
			array_push($sortFields, 'name', 'applicationid');
			CArrayHelper::sort($applications, $sortFields);
		}

		// get host scripts
		$hostScripts = API::Script()->getScriptsByHosts($hostIds);

		// get templates screen count
		$screens = API::TemplateScreen()->get([
			'hostids' => $hostIds,
			'countOutput' => true,
			'groupCount' => true
		]);
		$screens = zbx_toHash($screens, 'hostid');
		foreach ($hosts as &$host) {
			$host['screens'] = isset($screens[$host['hostid']]);
		}
		unset($host);
	}
}

// multiselect hosts
$multiselect_host_data = [];
if ($filter['hostids']) {
	$filterHosts = API::Host()->get([
		'output' => ['hostid', 'name'],
		'hostids' => $filter['hostids']
	]);

	foreach ($filterHosts as $host) {
		$multiselect_host_data[] = [
			'id' => $host['hostid'],
			'name' => $host['name']
		];
	}
}

/*
 * Display
 */
$widget = (new CWidget())
	->setTitle(_('Latest data'))
	->setControls((new CList())
		->addItem(get_icon('fullscreen', ['fullscreen' => getRequest('fullscreen')]))
	);

// Filter
$filterForm = (new CFilter('web.latest.filter.state'))
	->addVar('fullscreen', getRequest('fullscreen'));

$filterColumn1 = (new CFormList())
	->addRow(_('Host groups'),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $multiselect_hostgroup_data,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Hosts'),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $multiselect_host_data,
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Application'), [
		(new CTextBox('application', $filter['application']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('application_name', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'applications',
					'srcfld1' => 'name',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'application',
					'real_hosts' => '1',
					'with_applications' => '1'
				]).', null, this);'
			)
	]);

$filterColumn2 = (new CFormList())
	->addRow(_('Name'), (new CTextBox('select', $filter['select']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
	->addRow(_('Show items without data'),
		(new CCheckBox('show_without_data'))->setChecked($filter['showWithoutData'] == 1)
	)
	->addRow(_('Show details'), (new CCheckBox('show_details'))->setChecked($filter['showDetails'] == 1));

$filterForm
	->addColumn($filterColumn1)
	->addColumn($filterColumn2);

$widget->addItem($filterForm);
// End of Filter

$form = (new CForm('GET', 'history.php'))
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH));
// table
$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
if (!$filterSet) {
	$table->setNoDataMessage(_('Specify some filter condition to see the values.'));
}

$toggle_all = (new CColHeader(
	(new CSimpleButton())
		->addClass(ZBX_STYLE_TREEVIEW)
		->addClass('app-list-toggle-all')
		->addItem(new CSpan())
))->addStyle('width: 18px');

$check_all = (new CColHeader(
	(new CCheckBox('all_items'))->onClick("checkAll('".$form->getName()."', 'all_items', 'itemids');")
))->addStyle('width: 15px');

if ($filter['showDetails']) {
	$table->setHeader([
		$toggle_all,
		$check_all,
		make_sorting_header(_('Host'), 'host', $sortField, $sortOrder)->addStyle('width: 13%'),
		make_sorting_header(_('Name'), 'name', $sortField, $sortOrder)->addStyle('width: 21%'),
		(new CColHeader(_('Interval')))->addStyle('width: 5%'),
		(new CColHeader(_('History')))->addStyle('width: 5%'),
		(new CColHeader(_('Trends')))->addStyle('width: 5%'),
		(new CColHeader(_('Type')))->addStyle('width: 8%'),
		make_sorting_header(_('Last check'), 'lastclock', $sortField, $sortOrder)->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}
else {
	$table->setHeader([
		$toggle_all,
		$check_all,
		make_sorting_header(_('Host'), 'host', $sortField, $sortOrder)->addStyle('width: 17%'),
		make_sorting_header(_('Name'), 'name', $sortField, $sortOrder)->addStyle('width: 40%'),
		make_sorting_header(_('Last check'), 'lastclock', $sortField, $sortOrder)->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%')
	]);
}

$tab_rows = [];

$config = select_config();

// Resolve delay, history and trend macros.
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
$simple_interval_parser = new CSimpleIntervalParser();

foreach ($items as &$item) {
	if ($item['type'] == ITEM_TYPE_SNMPTRAP || $item['type'] == ITEM_TYPE_TRAPPER
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();

		if ($item['delay'][0] === '{') {
			$item['delay'] = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$item['delay'] = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
	}

	if ($config['hk_history_global']) {
		$keep_history = timeUnitToSeconds($config['hk_history']);
		$item['history'] = $config['hk_history'];
	}
	elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
		$keep_history = timeUnitToSeconds($item['history']);
	}
	else {
		$keep_history = 0;
		$item['history'] = (new CSpan($item['history']))->addClass(ZBX_STYLE_RED);
	}

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		if ($config['hk_trends_global']) {
			$keep_trends = timeUnitToSeconds($config['hk_trends']);
			$item['trends'] = $config['hk_trends'];
		}
		elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
			$keep_trends = timeUnitToSeconds($item['trends']);
		}
		else {
			$keep_trends = 0;
			$item['trends'] = (new CSpan($item['trends']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$keep_trends = 0;
		$item['trends'] = '';
	}

	$item['show_link'] = ($keep_history != 0 || $keep_trends != 0);
}
unset($item);

foreach ($items as $key => $item) {
	if (!$item['applications']) {
		continue;
	}

	$lastHistory = isset($history[$item['itemid']][0]) ? $history[$item['itemid']][0] : null;
	$prevHistory = isset($history[$item['itemid']][1]) ? $history[$item['itemid']][1] : null;

	if (strpos($item['units'], ',') !== false) {
		list($item['units'], $item['unitsLong']) = explode(',', $item['units']);
	}
	else {
		$item['unitsLong'] = '';
	}

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// change
	$digits = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if ($lastHistory && $prevHistory
			&& ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units([
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $item['units'] == 'unixtime' ? 'uptime' : $item['units']
		]);
		$change = nbsp($change);
	}
	else {
		$change = UNKNOWN_VALUE;
	}


	$checkbox = (new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']));

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		$actions = $item['show_link']
			? new CLink(_('Graph'), 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
	}
	else {
		$actions = $item['show_link']
			? new CLink(_('History'), 'history.php?action='.HISTORY_VALUES.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
		$checkbox->setEnabled(false);
	}

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	if ($filter['showDetails']) {
		// item key
		$itemKey = ($item['type'] == ITEM_TYPE_HTTPTEST)
			? (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN)
			: (new CLink($item['key_expanded'], 'items.php?form=update&itemid='.$item['itemid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);

		$info_icons = [];
		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol([$item['name_expanded'], BR(), $itemKey]))->addClass($state_css),
			(new CCol($item['delay']))->addClass($state_css),
			(new CCol($item['history']))->addClass($state_css),
			(new CCol($item['trends']))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions,
			makeInformationList($info_icons)
		]);
	}
	else {
		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol($item['name_expanded']))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions
		]);
	}

	// add the item row to each application tab
	foreach ($item['applications'] as $itemApplication) {
		$applicationId = $itemApplication['applicationid'];

		if (isset($applications[$applicationId])) {
			$applications[$applicationId]['item_cnt']++;
			// objects may have different properties, so it's better to use a copy of it
			$tab_rows[$applicationId][] = clone $row;
		}
	}

	// remove items with applications from the collection
	unset($items[$key]);
}

foreach ($applications as $appid => $dbApp) {
	$host = $hosts[$dbApp['hostid']];

	if(!isset($tab_rows[$appid])) continue;

	$appRows = $tab_rows[$appid];

	$open_state = CProfile::get('web.latest.toggle', null, $dbApp['applicationid']);

	$hostName = (new CLinkAction($host['name']))
		->setMenuPopup(CMenuPopupHelper::getHost($host, $hostScripts[$host['hostid']]));
	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	// add toggle row
	$table->addRow([
		(new CSimpleButton())
			->addClass(ZBX_STYLE_TREEVIEW)
			->addClass('app-list-toggle')
			->setAttribute('data-app-id', $dbApp['applicationid'])
			->setAttribute('data-open-state', $open_state)
			->addItem(new CSpan()),
		'',
		$hostName,
		(new CCol([bold($dbApp['name']), ' ('._n('%1$s Item', '%1$s Items', $dbApp['item_cnt']).')']))
			->setColSpan($filter['showDetails'] ? 10 : 5)
	]);

	// add toggle sub rows
	foreach ($appRows as $row) {
		$row->setAttribute('parent_app_id', $dbApp['applicationid']);
		$table->addRow($row);
	}
}

/**
 * Display OTHER ITEMS (which are not linked to application)
 */
$tab_rows = [];
foreach ($items as $item) {
	$lastHistory = isset($history[$item['itemid']][0]) ? $history[$item['itemid']][0] : null;
	$prevHistory = isset($history[$item['itemid']][1]) ? $history[$item['itemid']][1] : null;

	if (strpos($item['units'], ',') !== false)
		list($item['units'], $item['unitsLong']) = explode(',', $item['units']);
	else
		$item['unitsLong'] = '';

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// column "change"
	$digits = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 2 : 0;
	if (isset($lastHistory['value']) && isset($prevHistory['value'])
			&& ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units([
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $item['units'] == 'unixtime' ? 'uptime' : $item['units']
		]);
		$change = nbsp($change);
	}
	else {
		$change = UNKNOWN_VALUE;
	}

	$checkbox = (new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']));

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		$actions = $item['show_link']
			? new CLink(_('Graph'), 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
	}
	else {
		$actions = $item['show_link']
			? new CLink(_('History'), 'history.php?action='.HISTORY_VALUES.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
		$checkbox->setEnabled(false);
	}

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	$host = $hosts[$item['hostid']];
	if ($filter['showDetails']) {
		// item key
		$itemKey = ($item['type'] == ITEM_TYPE_HTTPTEST)
			? (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN)
			: (new CLink($item['key_expanded'], 'items.php?form=update&itemid='.$item['itemid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);

		$info_icons = [];
		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol([$item['name_expanded'], BR(), $itemKey]))->addClass($state_css),
			(new CCol($item['delay']))->addClass($state_css),
			(new CCol($item['history']))->addClass($state_css),
			(new CCol($item['trends']))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions,
			makeInformationList($info_icons)
		]);
	}
	else {
		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol($item['name_expanded']))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions
		]);
	}

	$hosts[$item['hostid']]['item_cnt']++;
	$tab_rows[$item['hostid']][] = $row;
}

foreach ($hosts as $hostId => $dbHost) {
	$host = $hosts[$dbHost['hostid']];

	if(!isset($tab_rows[$hostId])) {
		continue;
	}
	$appRows = $tab_rows[$hostId];

	$open_state = CProfile::get('web.latest.toggle_other', null, $host['hostid']);

	$hostName = (new CLinkAction($host['name']))
		->setMenuPopup(CMenuPopupHelper::getHost($host, $hostScripts[$host['hostid']]));
	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	// add toggle row
	$table->addRow([
		(new CSimpleButton())
			->addClass(ZBX_STYLE_TREEVIEW)
			->addClass('app-list-toggle')
			->setAttribute('data-host-id', $host['hostid'])
			->setAttribute('data-open-state', $open_state)
			->addItem(new CSpan()),
		'',
		$hostName,
		(new CCol([bold('- '.('other').' -'), ' ('._n('%1$s Item', '%1$s Items', $dbHost['item_cnt']).')']))
			->setColSpan($filter['showDetails'] ? 10 : 5)
	]);

	// add toggle sub rows
	foreach($appRows as $row) {
		$row->setAttribute('parent_host_id', $host['hostid']);
		$table->addRow($row);
	}
}

$form->addItem([
	$table,
	new CActionButtonList('graphtype', 'itemids', [
		GRAPH_TYPE_STACKED => ['name' => _('Display stacked graph')],
		GRAPH_TYPE_NORMAL => ['name' => _('Display graph')]
	])
]);

$widget->addItem($form)->show();

require_once dirname(__FILE__).'/include/page_footer.php';
