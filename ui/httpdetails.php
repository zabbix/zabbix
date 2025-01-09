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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Details of web scenario');
$page['file'] = 'httpdetails.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js', 'layout.mode.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'from' =>		[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'to' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'reset' =>		[T_ZBX_STR,			O_OPT, P_SYS|P_ACT, null,	null],
	'httptestid' =>	[T_ZBX_INT,			O_MAND, P_SYS,	DB_ID,		null]
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Collect data
 */
$httptest = API::HttpTest()->get([
	'output' => ['httptestid', 'name', 'hostid'],
	'httptestids' => getRequest('httptestid'),
	'preservekeys' => true
]);
$httptest = reset($httptest);
if (!$httptest) {
	access_deny();
}

$timeselector_options = [
	'profileIdx' => 'web.httpdetails.filter',
	'profileIdx2' => $httptest['httptestid'],
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

$timeline = getTimeSelectorPeriod($timeselector_options);

$http_test_name = CMacrosResolverHelper::resolveHttpTestName($httptest['hostid'], $httptest['name']);

$details_screen = CScreenBuilder::getScreen([
	'resourcetype' => SCREEN_RESOURCE_HTTPTEST_DETAILS,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'httptest_details'
] + $timeline);

$graphs = [];

// dims
$graph_dims = getGraphDims();
$graph_dims['width'] = -50;
$graph_dims['graphHeight'] = 151;

/*
 * Graph in
 */
$graph_in = new CScreenBase([
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_in'
] + $timeline);

$items = DBfetchArray(DBselect(
	'SELECT i.itemid,i.value_type,i.history,i.trends,i.hostid'.
	' FROM items i,httpstepitem hi,httpstep hs'.
	' WHERE i.itemid=hi.itemid'.
		' AND hi.httpstepid=hs.httpstepid'.
		' AND hs.httptestid='.zbx_dbstr($httptest['httptestid'])
));

$url = (new CUrl('chart3.php'))
	->setArgument('height', 150)
	->setArgument('name', $http_test_name.': '._('Speed'))
	->setArgument('http_item_type', HTTPSTEP_ITEM_TYPE_IN)
	->setArgument('httptestid', $httptest['httptestid'])
	->setArgument('graphtype', GRAPH_TYPE_STACKED)
	->setArgument('from', $graph_in->timeline['from'])
	->setArgument('to', $graph_in->timeline['to'])
	->setArgument('profileIdx', $graph_in->profileIdx)
	->setArgument('profileIdx2', $graph_in->profileIdx2)
	->getUrl();

$graphs[] = (new CDiv((new CDiv())
		->setId('graph_in_container')
		->addClass(ZBX_STYLE_CENTER)
	))
	->addClass('flickerfreescreen')
	->setId('flickerfreescreen_graph_in')
	->setAttribute('data-timestamp', time());

$time_control_data = [
	'id' => 'graph_in',
	'containerid' => 'graph_in_container',
	'src' => $url,
	'objDims' => $graph_dims,
	'loadSBox' => 1,
	'loadImage' => 1
];
zbx_add_post_js('timeControl.addObject("graph_in", '.zbx_jsvalue($graph_in->timeline).', '.
	zbx_jsvalue($time_control_data).');'
);
$graph_in->insertFlickerfreeJs();

/*
 * Graph time
 */
$graph_time = new CScreenBase([
	'resourcetype' => SCREEN_RESOURCE_GRAPH,
	'mode' => SCREEN_MODE_PREVIEW,
	'dataId' => 'graph_time'
] + $timeline);

$url = (new CUrl('chart3.php'))
	->setArgument('height', 150)
	->setArgument('name', $http_test_name.': '._('Response time'))
	->setArgument('http_item_type', HTTPSTEP_ITEM_TYPE_TIME)
	->setArgument('httptestid', $httptest['httptestid'])
	->setArgument('graphtype', GRAPH_TYPE_STACKED)
	->setArgument('from', $graph_time->timeline['from'])
	->setArgument('to', $graph_time->timeline['to'])
	->setArgument('profileIdx', $graph_time->profileIdx)
	->setArgument('profileIdx2', $graph_time->profileIdx2)
	->getUrl();

$graphs[] = (new CDiv(((new CDiv())
		->setId('graph_time_container')
		->addClass(ZBX_STYLE_CENTER)
	)))
	->addClass('flickerfreescreen')
	->setId('flickerfreescreen_graph_time')
	->setAttribute('data-timestamp', time());

$time_control_data = [
	'id' => 'graph_time',
	'containerid' => 'graph_time_container',
	'src' => $url,
	'objDims' => $graph_dims,
	'loadSBox' => 1,
	'loadImage' => 1
];
zbx_add_post_js('timeControl.addObject("graph_time", '.zbx_jsvalue($graph_in->timeline).', '.
	zbx_jsvalue($time_control_data).');'
);
$graph_time->insertFlickerfreeJs();

// scroll
CScreenBuilder::insertScreenStandardJs($graph_in->timeline);

// Create graphs widget.
(new CHtmlPage())
	->setTitle(_('Details of web scenario').': '.$http_test_name)
	->setWebLayoutMode($page['web_layout_mode'])
	->setControls((new CTag('nav', true,
		(new CForm())
			->addItem((new CList())->addItem(get_icon('kioskmode', ['mode' => $page['web_layout_mode']])))
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($details_screen->get())
	->addItem(new CTag('br'))
	->addItem(
		(new CFilter())
			->setProfile($timeline['profileIdx'], $timeline['profileIdx2'])
			->setActiveTab(CProfile::get($timeline['profileIdx'].'.active', 1))
			->addTimeSelector($timeline['from'], $timeline['to'], $page['web_layout_mode'] != ZBX_LAYOUT_KIOSKMODE)
	)
	->addItem((new CDiv($graphs))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER))
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
