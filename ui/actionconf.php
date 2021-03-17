<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$page['scripts'] = [
	'flickerfreescreen.js',
	'gtlc.js',
	'class.dashboard.js',
	'class.dashboard.page.js',
//	'class.dashboard.widget.iterator.js',
	'class.dashboard.widget.placeholder.js',
	'class.widget.js',
	'class.widget.clock.js',
	'class.widget.graph.js',
	'class.widget.map.js',
	'class.widget.navtree.js',
	'class.widget.problems.js',
	'class.widget.problemsbysv.js',
	'class.widget.svggraph.js',
	'class.widget.trigerover.js',
	'class.calendar.js',
	'multiselect.js',
	'layout.mode.js',
	'class.coverride.js',
	'class.cverticalaccordion.js',
	'class.crangecontrol.js',
	'colorpicker.js',
	'class.csvggraph.js',
	'class.cnavtree.js',
	'class.svg.canvas.js',
	'class.svg.map.js',
	'class.tab-indicators.js',
	'class.sortable.js',
];

require_once dirname(__FILE__).'/include/page_header.php';

$dashboards = API::Dashboard()->get([
	'output' => ['dashboardid'],
	'sortfield' => 'name',
	'limit' => 1
]);

$dashboardid = $dashboards[0]['dashboardid'];

$widget = (new CWidget())
	->setTitle('TESTING')
	->setWebLayoutMode(ZBX_LAYOUT_NORMAL)
	->addItem(
		(new CFilter(new CUrl()))
			->setProfile('web.dashbrd.filter', $dashboardid)
			->setActiveTab(1)
			->addTimeSelector('now-1h', 'now', true)
	);

$widget->show();

$dashboards = API::Dashboard()->get([
	'output' => ['dashboardid', 'name', 'userid', 'display_period', 'auto_start'],
	'selectPages' => ['dashboard_pageid', 'name', 'display_period', 'widgets'],
	'dashboardids' => [$dashboardid],
	'preservekeys' => true
]);

CDashboardHelper::updateEditableFlag($dashboards);

$dashboard = array_shift($dashboards);
$dashboard['pages'] = CDashboardHelper::preparePagesForGrid($dashboard['pages'], null, true);

$widgets = $dashboard['pages'][0]['widgets'];
foreach ($widgets as &$widget) {
	$widget += [
		'dashboard_data' => [
			'templateid' => null,
			'dashboardid' => $dashboardid,
			'dynamic_hostid' => null,
		],
		'defaults' => CWidgetConfig::getDefaults(CWidgetConfig::CONTEXT_DASHBOARD)[$widget['type']],
		'uniqueid' => 'UNIQ'.uniqid(),
		'cell_width' => 100/24,
		'cell_height' => 70,
		'is_editable' => true,
		'index' => 0
	];
}
unset($widget);
?>

<main>
	<div>
		<button onclick="_activate();">Activate</button>
		<button onclick="_deactivate();">Deactivate</button>
	</div>

	<div id="test_stand" style="position: relative; margin: 20px 0; height: 500px;"></div>
</main>

<script>

let time_selector = {
	profileIdx: 'web.dashbrd.filter',
	profileIdx2: '<?= $dashboardid ?>',
	from: 'now-1h',
	to: 'now',
	from_ts: <?= strtotime('now-1hour') ?>,
	to_ts: <?= strtotime('now') ?>
};

jQuery.subscribe('timeselector.rangeupdate', (e, data) => {
	time_selector = {
		...time_selector,
		from: data.from,
		to: data.to,
		from_ts: data.from_ts,
		to_ts: data.to_ts
	};
});

if (!ZABBIX) {
	ZABBIX = {};
}
if (!ZABBIX.Dashboard) {
	ZABBIX.Dashboard = {};
}
ZABBIX.Dashboard.getTimeSelector = () => {
	return time_selector;
};

// =====================================================================================================================

var widgets = <?= json_encode($widgets); ?>;
var type_classes = <?= json_encode(CWidgetConfig::getTypeJSClasses()); ?>;

var classes = {};

for (var w of widgets) {
	classes[w.type] = eval(type_classes[w.type]);
}

let wi = [];

for (var w_data of widgets) {
	const w = new classes[w_data.type](w_data);
	wi.push(w);

	w.start();
	document.getElementById('test_stand').appendChild(w.getView());
}

function _activate() {
	for (var w of wi) {
		w.activate();
	}
}
function _deactivate() {
	for (var w of wi) {
		w.deactivate();
	}
}

let resize_timeout;
window.addEventListener('resize', () => {
	clearTimeout(resize_timeout);
	resize_timeout = setTimeout(function() {
		for (var w of wi) {
			w.resize();
		}
	}, 200);
});

</script>

<?php

require_once dirname(__FILE__).'/include/page_footer.php';
