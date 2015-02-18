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


$menu_table = new CTable(null, 'menu pointer');
$menu_table->addRow($data['menu']['main_menu']);

$serverName = (isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '')
	? new CCol($ZBX_SERVER_NAME, 'right textcolorstyles server-name')
	: null;

// 1st level menu
$table = new CTable(null, 'maxwidth');
$table->addRow(array($menu_table, $serverName));

$page_menu = new CDiv(null, 'textwhite');
$page_menu->setAttribute('id', 'mmenu');
$page_menu->addItem($table);

// 2nd level menu
$sub_menu_table = new CTable(null, 'sub_menu maxwidth ui-widget-header');
$menu_divs = array();
$menu_selected = false;
foreach ($data['menu']['sub_menus'] as $label => $sub_menu) {
	$sub_menu_row = array();
	foreach ($sub_menu as $id => $sub_page) {
		if (empty($sub_page['menu_text'])) {
			$sub_page['menu_text'] = SPACE;
		}

		$url = new CUrl($sub_page['menu_url']);
		if ($sub_page['menu_action'] !== null) {
			$url->setArgument('action', $sub_page['menu_action']);
		}
		else {
			$url->setArgument('ddreset', 1);
		}
		$url->removeArgument('sid');

		$sub_menu_item = new CLink($sub_page['menu_text'], $url->getUrl(), $sub_page['class'].' nowrap', null, false);
		if ($sub_page['selected']) {
			$sub_menu_item = new CSpan($sub_menu_item, 'active nowrap');
		}
		$sub_menu_row[] = $sub_menu_item;
		$sub_menu_row[] = new CSpan(SPACE.' | '.SPACE, 'divider');
	}
	array_pop($sub_menu_row);

	$sub_menu_div = new CDiv($sub_menu_row);
	$sub_menu_div->setAttribute('id', 'sub_'.$label);
	$sub_menu_div->addAction('onmouseover', 'javascript: MMenu.submenu_mouseOver();');

	$sub_menu_div->addAction('onmouseout', 'javascript: MMenu.mouseOut();');

	if ($data['menu']['selected'] == $label) {
		$menu_selected = true;
		$sub_menu_div->setAttribute('style', 'display: block;');
		insert_js('MMenu.def_label = '.zbx_jsvalue($label));
	}
	else {
		$sub_menu_div->setAttribute('style', 'display: none;');
	}
	$menu_divs[] = $sub_menu_div;
}

$sub_menu_div = new CDiv(SPACE);
$sub_menu_div->setAttribute('id', 'sub_empty');
$sub_menu_div->setAttribute('style', 'display: '.($menu_selected ? 'none;' : 'block;'));

$menu_divs[] = $sub_menu_div;
$search_div = null;

$searchForm = new CView('general.search');
$search_div = $searchForm->render();

$sub_menu_table->addRow(array($menu_divs, $search_div));
$page_menu->addItem($sub_menu_table);
//$page_menu->show();

echo '<header role="banner">
		<nav role="navigation">
			<div class="top-nav-container">
			<ul class="top-nav"><li class="selected" onMouseOver="menu_mouseover(\'menu-monitoring\')"><a href="zabbix.php?action=dashboard.view">Monitoring</a></li><li onMouseOver="menu_mouseover(\'menu-inventory\')"><a href="hostinventoriesoverview.php">Inventory</a></li><li onMouseOver="menu_mouseover(\'menu-reports\')"><a href="report1.php">Reports</a></li><li onMouseOver="menu_mouseover(\'menu-configuration\')"><a href="hosts.php">Configuration</a></li><li onMouseOver="menu_mouseover(\'menu-administration\')"><a href="proxies.php">Administration</a></li></ul><ul class="top-nav-icons"><li><form method="get" action="search.php" accept-charset="utf-8"><input class="input text search" id="search" name="search" value="za" size="20" maxlength="255" autocomplete="off" type="text"></form></li><li><a href="http://www.zabbix.com/documentation/" target="_blank" class="top-nav-help" title="Help"></a></li><li><a href="" class="top-nav-print" title="Print"></a></li><li><a href="profile.php" class="top-nav-profile" title="Profile"></a></li><li><a href="index.php?reconnect=1" class="top-nav-signout" title="Sign out"></a></li></ul>
			</div>
			<div class="top-subnav-container" id="menu-monitoring">
			<ul class="top-subnav"><li><a href="zabbix.php?action=dashboard.view" class="selected">Dashboard</a></li><li><a href="overview.php">Overview</a></li><li><a href="httpmon.php">Web</a></li><li><a href="latest.php">Latest data</a></li><li><a href="tr_status.php">Triggers</a></li><li><a href="events.php">Events</a></li><li><a href="charts.php">Graphs</a></li><li><a href="screens.php">Screens</a></li><li><a href="zabbix.php?action=map.view">Maps</a></li><li><a href="zabbix.php?action=discovery.view">Discovery</a></li><li><a href="srv_status.php">IT services</a></li></ul>
			</div>
			<div class="top-subnav-container" id="menu-reports" style="display:none">
			<ul class="top-subnav"><li><a href="report1.php">Status of Zabbix</a></li><li><a href="report2.php">Availability report</a></li><li><a href="toptriggers.php">Triggers top 100</a></li><li><a href="report6.php">Bar reports</a></li></ul>
			</div>
			<div class="top-subnav-container" id="menu-inventory" style="display:none">
			<ul class="top-subnav"><li><a href="hostinventoriesoverview.php">Overview</a></li><li><a href="hostinventories.php">Hosts</a></li></ul>
			</div>
			<div class="top-subnav-container" id="menu-configuration" style="display:none">
			<ul class="top-subnav"><li><a href="hostgroups.php">Host groups</a></li><li><a href="templates.php">Templates</a></li><li><a href="hosts.php">Hosts</a></li><li><a href="maintenance.php">Maintenance</a></li><li><a href="actionconf.php">Actions</a></li><li><a href="screenconf.php">Screens</a></li><li><a href="slideconf.php">Slide shows</a></li><li><a href="sysmaps.php">Maps</a></li><li><a href="discoveryconf.php">Discovery</a></li><li><a href="services.php">IT Services</a></li></ul>
			</div>
			<div class="top-subnav-container" id="menu-administration" style="display:none">
			<ul class="top-subnav"><li><a href="adm.gui.php">General</a></li><li><a href="zabbix.php?action=proxy.list">Proxies</a></li><li><a href="authentication.php">Authentication</a></li><li><a href="usergrps.php">Users</a></li><li><a href="zabbix.php?action=mediatype.list">Media types</a></li><li><a href="zabbix.php?action=script.list">Scripts</a></li><li><a href="auditlogs.php">Audit</a></li><li><a href="queue.php">Queue</a></li><li><a href="report4.php">Notifications</a></li><li><a href="services.setup.php">Installation</a></li></ul>
			</div>
		</nav>
	</header>
	<article>
<script type="text/javascript">
function menu_mouseover(submenu)
{
	jQuery("div[id^=menu]").filter(\'div[id!="\' + submenu + \'"]\').hide();
	jQuery("#" + submenu).show();
}

function menu_mouseout()
{
//	jQuery("#menu-*").show();

//	jQuery("#menu-*").hide();
}
</script>
';
