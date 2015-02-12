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
$page_menu->show();
