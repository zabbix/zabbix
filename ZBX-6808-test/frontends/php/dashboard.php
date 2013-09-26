<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Dashboard');
$page['file'] = 'dashboard.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.pmaster.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'view_style' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'type' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'output' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'jsscriptid' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favcnt' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'pmasterid' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'favaction' =>	array(T_ZBX_STR, O_OPT, P_ACT,	IN("'add','remove','refresh','flop'"), null),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favaction})&&("flop"=={favaction})')
);
check_fields($fields);

/*
 * Filter
 */
$dashconf = array();
$dashconf['groupids'] = null;
$dashconf['maintenance'] = null;
$dashconf['severity'] = null;
$dashconf['extAck'] = 0;
$dashconf['filterEnable'] = CProfile::get('web.dashconf.filter.enable', 0);
if ($dashconf['filterEnable'] == 1) {
	// groups
	$dashconf['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);
	if ($dashconf['grpswitch'] == 0) {
		$dashconf['groupids'] = null; // null mean all groups
	}
	else {
		$dashconf['groupids'] = zbx_objectValues(CFavorite::get('web.dashconf.groups.groupids'), 'value');
		$hideGroupIds = zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value');

		if ($hideGroupIds) {
			// get all groups if no selected groups defined
			if (empty($dashconf['groupids'])) {
				$groups = API::HostGroup()->get(array(
					'nodeids' => get_current_nodeid(),
					'output' => array('groupid')
				));
				$dashconf['groupids'] = zbx_objectValues($groups, 'groupid');
			}

			$dashconf['groupids'] = array_diff($dashconf['groupids'], $hideGroupIds);

			// get available hosts
			$availableHosts = API::Host()->get(array(
				'groupids' => $dashconf['groupids'],
				'output' => array('hostid')
			));
			$availableHostIds = zbx_objectValues($availableHosts, 'hostid');

			$disabledHosts = API::Host()->get(array(
				'groupids' => $hideGroupIds,
				'output' => array('hostid')
			));
			$disabledHostIds = zbx_objectValues($disabledHosts, 'hostid');

			$dashconf['hostids'] = array_diff($availableHostIds, $disabledHostIds);
		}
		else {
			if (empty($dashconf['groupids'])) {
				$dashconf['groupids'] = null; // null mean all groups
			}
		}
	}

	// hosts
	$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
	$dashconf['maintenance'] = $maintenance == 0 ? 0 : null;

	// triggers
	$severity = CProfile::get('web.dashconf.triggers.severity', null);
	$dashconf['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
	$dashconf['severity'] = zbx_toHash($dashconf['severity']);

	$config = select_config();
	$dashconf['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
}

/*
 * Actions
 */
if (isset($_REQUEST['favobj'])) {
	$_REQUEST['pmasterid'] = get_request('pmasterid', 'mainpage');

	if ($_REQUEST['favobj'] == 'hat') {
		if ($_REQUEST['favaction'] == 'flop') {
			CProfile::update('web.dashboard.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
		elseif ($_REQUEST['favaction'] == 'refresh') {
			switch ($_REQUEST['favref']) {
				case 'hat_syssum':
					$syssum = make_system_status($dashconf);
					$syssum->show();
					break;
				case 'hat_hoststat':
					$hoststat = make_hoststat_summary($dashconf);
					$hoststat->show();
					break;
				case 'hat_stszbx':
					$stszbx = make_status_of_zbx();
					$stszbx->show();
					break;
				case 'hat_lastiss':
					$lastiss = make_latest_issues($dashconf);
					$lastiss->show();
					break;
				case 'hat_webovr':
					$webovr = make_webmon_overview($dashconf);
					$webovr->show();
					break;
				case 'hat_dscvry':
					$dscvry = make_discovery_status();
					$dscvry->show();
					break;
			}
		}
	}

	if ($_REQUEST['favobj'] == 'set_rf_rate') {
		if (str_in_array($_REQUEST['favref'], array('hat_syssum', 'hat_stszbx', 'hat_lastiss', 'hat_webovr', 'hat_dscvry', 'hat_hoststat'))) {
			CProfile::update('web.dashboard.rf_rate.'.$_REQUEST['favref'], $_REQUEST['favcnt'], PROFILE_TYPE_INT);
			$_REQUEST['favcnt'] = CProfile::get('web.dashboard.rf_rate.'.$_REQUEST['favref'], 60);

			echo get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $_REQUEST['favcnt'])
				.get_update_doll_script('mainpage', $_REQUEST['favref'], 'stopDoll')
				.get_update_doll_script('mainpage', $_REQUEST['favref'], 'startDoll');

			$menu = array();
			$submenu = array();
			make_refresh_menu('mainpage', $_REQUEST['favref'], $_REQUEST['favcnt'], null, $menu, $submenu);

			echo 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';';
		}
	}

	if (str_in_array($_REQUEST['favobj'], array('itemid', 'graphid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			zbx_value2array($_REQUEST['favid']);

			foreach ($_REQUEST['favid'] as $sourceid) {
				$result = CFavorite::add('web.favorite.graphids', $sourceid, $_REQUEST['favobj']);
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.graphids', $_REQUEST['favid'], $_REQUEST['favobj']);
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			$innerHTML = make_favorite_graphs();
			$innerHTML = $innerHTML->toString();
			echo '$("hat_favgrph").update('.zbx_jsvalue($innerHTML).');';

			$menu = array();
			$submenu = array();
			echo 'page_submenu["menu_graphs"] = '.zbx_jsvalue(make_graph_submenu()).';';
		}
	}

	if ($_REQUEST['favobj'] == 'sysmapid') {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			zbx_value2array($_REQUEST['favid']);
			foreach ($_REQUEST['favid'] as $sourceid) {
				$result = CFavorite::add('web.favorite.sysmapids', $sourceid, $_REQUEST['favobj']);
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.sysmapids', $_REQUEST['favid'], $_REQUEST['favobj']);
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			$innerHTML = make_favorite_maps();
			$innerHTML = $innerHTML->toString();
			echo '$("hat_favmap").update('.zbx_jsvalue($innerHTML).');';

			$menu = array();
			$submenu = array();
			echo 'page_submenu["menu_sysmaps"] = '.zbx_jsvalue(make_sysmap_submenu()).';';
		}
	}

	if (str_in_array($_REQUEST['favobj'], array('screenid', 'slideshowid'))) {
		$result = false;
		if ($_REQUEST['favaction'] == 'add') {
			zbx_value2array($_REQUEST['favid']);
			foreach ($_REQUEST['favid'] as $sourceid) {
				$result = CFavorite::add('web.favorite.screenids', $sourceid, $_REQUEST['favobj']);
			}
		}
		elseif ($_REQUEST['favaction'] == 'remove') {
			$result = CFavorite::remove('web.favorite.screenids', $_REQUEST['favid'], $_REQUEST['favobj']);
		}

		if ($page['type'] == PAGE_TYPE_JS && $result) {
			$innerHTML = make_favorite_screens();
			$innerHTML = $innerHTML->toString();
			echo '$("hat_favscr").update('.zbx_jsvalue($innerHTML).');';

			$menu = array();
			$submenu = array();
			echo 'page_submenu["menu_screens"] = '.zbx_jsvalue(make_screen_submenu()).';';
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
$dashboardWidget = new CWidget('dashboard_wdgt');
$dashboardWidget->setClass('header');
$dashboardWidget->addHeader(_('PERSONAL DASHBOARD'), array(
	new CIcon(
		_s('Configure (Filter %s)', $dashconf['filterEnable'] ? _('Enabled') : _('Disabled')),
		$dashconf['filterEnable'] ? 'iconconfig_hl' : 'iconconfig',
		"document.location = 'dashconf.php';"
	),
	SPACE,
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])))
);

// js menu arrays
$menu = array();
$submenu = array();
make_graph_menu($menu, $submenu);
make_sysmap_menu($menu, $submenu);
make_screen_menu($menu, $submenu);

make_refresh_menu('mainpage', 'hat_syssum', CProfile::get('web.dashboard.rf_rate.hat_syssum', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_hoststat', CProfile::get('web.dashboard.rf_rate.hat_hoststat', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_stszbx', CProfile::get('web.dashboard.rf_rate.hat_stszbx', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_lastiss', CProfile::get('web.dashboard.rf_rate.hat_lastiss', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_webovr', CProfile::get('web.dashboard.rf_rate.hat_webovr', 60), null, $menu, $submenu);
make_refresh_menu('mainpage', 'hat_dscvry', CProfile::get('web.dashboard.rf_rate.hat_dscvry', 60), null, $menu, $submenu);

insert_js('var page_menu='.zbx_jsvalue($menu).";\n".'var page_submenu='.zbx_jsvalue($submenu).";\n");

/*
 * Left column
 */
$leftColumn = array();

// favorite graphs
$graph_menu = get_icon('menu', array('menu' => 'graphs'));
$fav_grph = new CUIWidget('hat_favgrph', make_favorite_graphs(), CProfile::get('web.dashboard.hats.hat_favgrph.state', 1));
$fav_grph->setHeader(_('Favourite graphs'), array($graph_menu));
$fav_grph->setFooter(new CLink(_('Graphs').' &raquo;', 'charts.php', 'highlight'), true);
$leftColumn[] = $fav_grph;

// favorite screens
$screen_menu = get_icon('menu', array('menu' => 'screens'));
$fav_scr = new CUIWidget('hat_favscr', make_favorite_screens(), CProfile::get('web.dashboard.hats.hat_favscr.state', 1));
$fav_scr->setHeader(_('Favourite screens'), array($screen_menu));
$fav_scr->setFooter(new CLink(_('Screens').' &raquo;', 'screens.php', 'highlight'), true);
$leftColumn[] = $fav_scr;

// favorite sysmaps
$sysmap_menu = get_icon('menu', array('menu' => 'sysmaps'));
$fav_maps = new CUIWidget('hat_favmap', make_favorite_maps(), CProfile::get('web.dashboard.hats.hat_favmap.state', 1));
$fav_maps->setHeader(_('Favourite maps'), array($sysmap_menu));
$fav_maps->setFooter(new CLink(_('Maps').' &raquo;', 'maps.php', 'highlight'), true);
$leftColumn[] = $fav_maps;

// refresh tab
$refresh_tab = array(
	array('id' => 'hat_syssum', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_syssum', 120)),
	array('id' => 'hat_stszbx', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_stszbx', 120)),
	array('id' => 'hat_lastiss', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_lastiss', 60)),
	array('id' => 'hat_webovr', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_webovr', 60)),
	array('id' => 'hat_hoststat', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_hoststat', 60))
);

/*
 * Right column
 */
$rightColumn = array();

// status of zbx
if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
	$refresh_menu = get_icon('menu', array('menu' => 'hat_stszbx'));
	$zbxStatus = new CUIWidget('hat_stszbx', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_stszbx.state', 1));
	$zbxStatus->setHeader(_('Status of Zabbix'), array($refresh_menu));
	$zbxStatus->setFooter(new CDiv(SPACE, 'textwhite', 'hat_stszbx_footer'));
	$rightColumn[] = $zbxStatus;
}

// system status
$refresh_menu = new CIcon(_('Menu'), 'iconmenu', 'create_page_menu(event,"hat_syssum");');
$sys_stat = new CUIWidget('hat_syssum', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_syssum.state', 1));
$sys_stat->setHeader(_('System status'), array($refresh_menu));
$sys_stat->setFooter(new CDiv(SPACE, 'textwhite', 'hat_syssum_footer'));
$rightColumn[] = $sys_stat;

// host status
$refresh_menu = get_icon('menu', array('menu' => 'hat_hoststat'));
$hoststat = new CUIWidget('hat_hoststat', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_hoststat.state', 1));
$hoststat->setHeader(_('Host status'), array($refresh_menu));
$hoststat->setFooter(new CDiv(SPACE, 'textwhite', 'hat_hoststat_footer'));
$rightColumn[] = $hoststat;

// last issues
$refresh_menu = get_icon('menu', array('menu' => 'hat_lastiss'));
$lastiss = new CUIWidget('hat_lastiss', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_lastiss.state', 1));
$lastiss->setHeader(_n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT), array($refresh_menu));
$lastiss->setFooter(new CDiv(SPACE, 'textwhite', 'hat_lastiss_footer'));
$rightColumn[] = $lastiss;

// web monitoring
$refresh_menu = get_icon('menu', array('menu' => 'hat_webovr'));
$web_mon = new CUIWidget('hat_webovr', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_webovr.state', 1));
$web_mon->setHeader(_('Web monitoring'), array($refresh_menu));
$web_mon->setFooter(new CDiv(SPACE, 'textwhite', 'hat_webovr_footer'));
$rightColumn[] = $web_mon;

// discovery info
$drules = DBfetch(DBselect(
		'SELECT COUNT(d.druleid) AS cnt'.
		' FROM drules d'.
		' WHERE d.status='.DRULE_STATUS_ACTIVE.
			andDbNode('d.druleid')
));
if ($drules['cnt'] > 0 && check_right_on_discovery(PERM_READ)) {
	$refresh_tab[] = array('id' => 'hat_dscvry', 'frequency' => CProfile::get('web.dashboard.rf_rate.hat_dscvry', 60));
	$refresh_menu = get_icon('menu', array('menu' => 'hat_dscvry'));
	$dcvr_mon = new CUIWidget('hat_dscvry', new CSpan(_('Loading...'), 'textcolorstyles'), CProfile::get('web.dashboard.hats.hat_dscvry.state', 1));
	$dcvr_mon->setHeader(_('Discovery status'), array($refresh_menu));
	$dcvr_mon->setFooter(new CDiv(SPACE, 'textwhite', 'hat_dscvry_footer'));
	$rightColumn[] = $dcvr_mon;
}

add_doll_objects($refresh_tab);

$dashboardTable = new CTable();
$dashboardTable->addRow(array(new CDiv($leftColumn, 'column'), new CDiv($rightColumn, 'column'), new CDiv(null, 'column')), 'top');

$dashboardWidget->addItem($dashboardTable);
$dashboardWidget->show();

// activating blinking
zbx_add_post_js('jqBlink.blink();');
?>
<script type="text/javascript">
	//<!--<![CDATA[
	function addPopupValues(list) {
		if (!isset('object', list)) {
			throw("Error hash attribute 'list' doesn't contain 'object' index");
			return false;
		}
		if ('undefined' == typeof(Ajax)) {
			throw('Prototype.js lib is required!');
			return false;
		}

		var favorites = {graphid: 1, itemid: 1, screenid: 1, slideshowid: 1, sysmapid: 1};

		if (isset(list.object, favorites)) {
			var favid = [];
			for (var i = 0; i < list.values.length; i++) {
				favid.push(list.values[i][list.object]);
			}

			var params = {
				'favobj': list.object,
				'favid[]': favid,
				'favaction': 'add'
			};
			send_params(params);
		}
	}
	//]]> -->
</script>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
