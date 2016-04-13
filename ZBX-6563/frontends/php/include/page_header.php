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


if (!isset($page['type'])) {
	$page['type'] = PAGE_TYPE_HTML;
}
if (!isset($page['file'])) {
	$page['file'] = basename($_SERVER['PHP_SELF']);
}
$_REQUEST['fullscreen'] = get_request('fullscreen', 0);
if ($_REQUEST['fullscreen'] === '1') {
	if (!defined('ZBX_PAGE_NO_MENU')) {
		define('ZBX_PAGE_NO_MENU', 1);
	}
	define('ZBX_PAGE_FULLSCREEN', 1);
}

require_once dirname(__FILE__).'/menu.inc.php';

zbx_define_menu_restrictions($page, $ZBX_MENU);

if (!defined('ZBX_PAGE_NO_THEME')) {
	define('ZBX_PAGE_NO_THEME', false);
}

switch ($page['type']) {
	case PAGE_TYPE_IMAGE:
		set_image_header();
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_XML:
		header('Content-Type: text/xml');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_JS:
		header('Content-Type: application/javascript; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_JSON:
		header('Content-Type: application/json');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_JSON_RPC:
		header('Content-Type: application/json-rpc');
		if(!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_CSS:
		header('Content-Type: text/css; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_TEXT:
	case PAGE_TYPE_TEXT_RETURN_JSON:
	case PAGE_TYPE_HTML_BLOCK:
		header('Content-Type: text/plain; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_TEXT_FILE:
		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_CSV:
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', 1);
		}
		break;
	case PAGE_TYPE_HTML:
	default:
		header('Content-Type: text/html; charset=UTF-8');

		// page title
		$pageTitle = '';
		if (isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)) {
			$pageTitle = $ZBX_SERVER_NAME.NAME_DELIMITER;
		}
		$pageTitle .= isset($page['title']) ? $page['title'] : _('Zabbix');

		if (ZBX_DISTRIBUTED) {
			if (isset($ZBX_VIEWED_NODES) && $ZBX_VIEWED_NODES['selected'] == 0) { // all selected
				$pageTitle .= ' ('._('All nodes').') ';
			}
			elseif (!empty($ZBX_NODES)) {
				$pageTitle .= ' ('.$ZBX_NODES[$ZBX_CURRENT_NODEID]['name'].')';
			}
		}

		if ((defined('ZBX_PAGE_DO_REFRESH') || defined('ZBX_PAGE_DO_JS_REFRESH')) && CWebUser::$data['refresh']) {
			$pageTitle .= ' ['._s('refreshed every %1$s sec.', CWebUser::$data['refresh']).']';
		}
		break;
}

// construct menu
$main_menu = array();
$sub_menus = array();

$denied_page_requested = zbx_construct_menu($main_menu, $sub_menus, $page);

// render the "Deny access" page
if ($denied_page_requested) {
	access_deny(ACCESS_DENY_PAGE);
}

if ($page['type'] == PAGE_TYPE_HTML) {
	$pageHeader = new CPageHeader($pageTitle);
	$pageHeader->addCssInit();

	$css = ZBX_DEFAULT_THEME;
	if (!ZBX_PAGE_NO_THEME) {
		if (!empty($DB['DB'])) {
			$config = select_config();
			$css = getUserTheme(CWebUser::$data);

			$severityCss = <<<CSS
.disaster { background: #{$config['severity_color_5']} !important; }
.high { background: #{$config['severity_color_4']} !important; }
.average { background: #{$config['severity_color_3']} !important; }
.warning { background: #{$config['severity_color_2']} !important; }
.information { background: #{$config['severity_color_1']} !important; }
.not_classified { background: #{$config['severity_color_0']} !important; }
CSS;
			$pageHeader->addStyle($severityCss);

			// perform Zabbix server check only for standard pages
			if ((!defined('ZBX_PAGE_NO_MENU') || defined('ZBX_PAGE_FULLSCREEN')) && $config['server_check_interval']
					&& !empty($ZBX_SERVER) && !empty($ZBX_SERVER_PORT)) {
				$page['scripts'][] = 'servercheck.js';
			}
		}
	}
	$css = CHtml::encode($css);
	$pageHeader->addCssFile('styles/themes/'.$css.'/main.css');

	if ($page['file'] == 'sysmap.php') {
		$pageHeader->addCssFile('imgstore.php?css=1&output=css');
	}
	$pageHeader->addJsFile('js/browsers.js');
	$pageHeader->addJsBeforeScripts('var PHP_TZ_OFFSET = '.date('Z').';');

	// show GUI messages in pages with menus and in fullscreen mode
	$showGuiMessaging = (!defined('ZBX_PAGE_NO_MENU') || $_REQUEST['fullscreen'] == 1) ? 1 : 0;
	$path = 'jsLoader.php?ver='.ZABBIX_VERSION.'&amp;lang='.CWebUser::$data['lang'].'&showGuiMessaging='.$showGuiMessaging;
	$pageHeader->addJsFile($path);

	if (!empty($page['scripts']) && is_array($page['scripts'])) {
		foreach ($page['scripts'] as $script) {
			$path .= '&amp;files[]='.$script;
		}
		$pageHeader->addJsFile($path);
	}

	$pageHeader->display();
?>
<body class="<?php echo $css; ?>">
<div id="message-global-wrap"><div id="message-global"></div></div>
<?php
}

define('PAGE_HEADER_LOADED', 1);

if (defined('ZBX_PAGE_NO_HEADER')) {
	return null;
}

if (!defined('ZBX_PAGE_NO_MENU')) {
	$help = new CLink(_('Help'), 'http://www.zabbix.com/documentation/', 'small_font', null, 'nosid');
	$help->setTarget('_blank');
	$support = new CLink(_('Get support'), 'http://www.zabbix.com/support.php', 'small_font', null, 'nosid');
	$support->setTarget('_blank');

	$printview = new CLink(_('Print'), '', 'small_font print-link', null, 'nosid');

	$page_header_r_col = array($help, '|', $support, '|', $printview, '|');

	if (!CWebUser::isGuest()) {
		array_push($page_header_r_col, new CLink(_('Profile'), 'profile.php', 'small_font', null, 'nosid'), '|');
	}

	if (isset(CWebUser::$data['debug_mode']) && CWebUser::$data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
		$debug = new CLink(_('Debug'), '#debug', 'small_font', null, 'nosid');
		$d_script = " if (!isset('state', this)) { this.state = 'none'; }".
			" if (this.state == 'none') { this.state = 'block'; }".
			" else { this.state = 'none'; }".
			" showHideByName('zbx_debug_info', this.state);";
		$debug->setAttribute('onclick', 'javascript: '.$d_script);
		array_push($page_header_r_col, $debug, '|');
	}

	if (CWebUser::isGuest()) {
		$page_header_r_col[] = array(new CLink(_('Login'), 'index.php?reconnect=1', 'small_font', null, null));
	}
	else {
		// it is not possible to logout from HTTP authentication
		$chck = $page['file'] == 'authentication.php' && isset($_REQUEST['save'], $_REQUEST['config']);
		if ($chck && $_REQUEST['config'] == ZBX_AUTH_HTTP || !$chck && isset($config) && $config['authentication_type'] == ZBX_AUTH_HTTP) {
			$logout =  new CLink(_('Logout'), '', 'small_font', null, 'nosid');
			$logout->setHint(_s('It is not possible to logout from HTTP authentication.'), null, null, false);
		}
		else {
			$logout =  new CLink(_('Logout'), 'index.php?reconnect=1', 'small_font', null, null);
		}
		array_push($page_header_r_col, $logout);
	}

	$logo = new CLink(new CDiv(SPACE, 'zabbix_logo'), 'http://www.zabbix.com/', 'image', null, 'nosid');
	$logo->setTarget('_blank');

	$top_page_row = array(
		new CCol($logo, 'page_header_l'),
		new CCol($page_header_r_col, 'maxwidth page_header_r')
	);

	unset($logo, $page_header_r_col, $help, $support);

	$table = new CTable(null, 'maxwidth page_header');
	$table->setCellSpacing(0);
	$table->setCellPadding(5);
	$table->addRow($top_page_row);
	$table->show();

	$menu_table = new CTable(null, 'menu pointer');
	$menu_table->setCellSpacing(0);
	$menu_table->setCellPadding(5);
	$menu_table->addRow($main_menu);

	$node_form = null;
	if (ZBX_DISTRIBUTED && !defined('ZBX_HIDE_NODE_SELECTION')) {
		insert_js_function('check_all');

		$available_nodes = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ, PERM_RES_DATA_ARRAY);
		$available_nodes = get_tree_by_parentid($ZBX_LOCALNODEID, $available_nodes, 'masterid'); // remove parent nodes
		if (empty($available_nodes[0])) {
			unset($available_nodes[0]);
		}

		if (!empty($available_nodes)) {
			$node_form = new CForm('get');
			$node_form->cleanItems();
			$node_form->setAttribute('id', 'node_form');

			// create ComboBox with selected nodes
			$nodesComboBox = null;
			if (count($ZBX_VIEWED_NODES['nodes']) > 0) {
				$nodesComboBox = new CComboBox('switch_node', $ZBX_VIEWED_NODES['selected'], 'submit()');

				foreach ($ZBX_VIEWED_NODES['nodes'] as $nodeid => $nodedata) {
					$nodesComboBox->addItem($nodeid, $nodedata['name']);
				}
			}

			$jscript = 'javascript: '.
				" var pos = getPosition('button_show_tree');".
				" showHide('div_node_tree', 'table');".
				' pos.top += 20;'.
				" \$('div_node_tree').setStyle({top: pos.top + 'px'});";
			$button_show_tree = new CButton('show_node_tree', _('Select Nodes'), $jscript);
			$button_show_tree->setAttribute('id', 'button_show_tree');

			// create node tree
			$node_tree = array();
			$node_tree[0] = array(
				'id' => 0,
				'caption' => _('All'),
				'combo_select_node' => new CCheckbox('check_all_nodes', null, "javascript : check_all('node_form', this.checked);"),
				'parentid' => 0 // master
			);

			foreach ($available_nodes as $node) {
				$checked = isset($ZBX_VIEWED_NODES['nodeids'][$node['nodeid']]);
				$combo_select_node = new CCheckbox('selected_nodes['.$node['nodeid'].']', $checked, null, $node['nodeid']);
				$combo_select_node->setAttribute('style', 'margin: 1px 4px 2px 4px;');

				// if no parent for node, link it to root (0)
				if (!isset($available_nodes[$node['masterid']])) {
					$node['masterid'] = 0;
				}

				$node_tree[$node['nodeid']] = array(
					'id' => $node['nodeid'],
					'caption' => $node['name'],
					'combo_select_node' => $combo_select_node,
					'parentid' => $node['masterid']
				);
			}
			unset($node);

			$node_tree = new CTree('nodes', $node_tree, array('caption' => bold(_('Node')), 'combo_select_node' => SPACE));

			$div_node_tree = new CDiv();
			$div_node_tree->addItem($node_tree->getHTML());
			$div_node_tree->addItem(new CSubmit('select_nodes', _('Select'), "\$('div_node_tree').setStyle({display: 'none'});"));
			$div_node_tree->setAttribute('id', 'div_node_tree');
			$div_node_tree->addStyle('display: none');

			if (!is_null($nodesComboBox)) {
				$node_form->addItem(array(new CSpan(_('Current node').SPACE, 'textcolorstyles'), $nodesComboBox));
			}
			$node_form->addItem($button_show_tree);
			$node_form->addItem($div_node_tree);
			unset($nodesComboBox);
		}
	}

	if (isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)) {
		$table = new CTable();
		$table->addStyle('width: 100%;');

		$tableColumn = new CCol(new CSpan($ZBX_SERVER_NAME, 'textcolorstyles'));
		if (is_null($node_form)) {
			$tableColumn->addStyle('padding-right: 5px;');
		}
		else {
			$tableColumn->addStyle('padding-right: 20px; padding-bottom: 2px;');
		}
		$table->addRow(array($tableColumn, $node_form));
		$node_form = $table;
	}

	// 1st level menu
	$table = new CTable(null, 'maxwidth');
	$r_col = new CCol($node_form, 'right');
	$r_col->setAttribute('style', 'line-height: 1.8em;');
	$table->addRow(array($menu_table, $r_col));

	$page_menu = new CDiv(null, 'textwhite');
	$page_menu->setAttribute('id', 'mmenu');
	$page_menu->addItem($table);

	// 2nd level menu
	$sub_menu_table = new CTable(null, 'sub_menu maxwidth ui-widget-header');
	$menu_divs = array();
	$menu_selected = false;
	foreach ($sub_menus as $label => $sub_menu) {
		$sub_menu_row = array();
		foreach ($sub_menu as $id => $sub_page) {
			if (empty($sub_page['menu_text'])) {
				$sub_page['menu_text'] = SPACE;
			}

			$sub_page['menu_url'] .= '?ddreset=1';

			$sub_menu_item = new CLink($sub_page['menu_text'], $sub_page['menu_url'], $sub_page['class'].' nowrap');
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

		if (isset($page['menu']) && $page['menu'] == $label) {
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

	if ($page['file'] != 'index.php' && CWebUser::$data['userid'] > 0) {
		$searchForm = new CView('general.search');
		$search_div = $searchForm->render();
	}

	$sub_menu_table->addRow(array($menu_divs, $search_div));
	$page_menu->addItem($sub_menu_table);
	$page_menu->show();
}

// create history
if (isset($page['hist_arg']) && CWebUser::$data['alias'] != ZBX_GUEST_USER && $page['type'] == PAGE_TYPE_HTML && !defined('ZBX_PAGE_NO_MENU')) {
	$table = new CTable(null, 'history left');
	$table->addRow(new CRow(array(
		new CCol(_('History').':', 'caption'),
		get_user_history()
	)));
	$table->show();
}
elseif ($page['type'] == PAGE_TYPE_HTML && !defined('ZBX_PAGE_NO_MENU')) {
	echo SBR;
}

// unset multiple variables
unset($ZBX_MENU, $table, $top_page_row, $menu_table, $node_form, $main_menu_row, $db_nodes, $node_data, $sub_menu_table, $sub_menu_rows);

if ($page['type'] == PAGE_TYPE_HTML && $showGuiMessaging) {
	zbx_add_post_js('var msglistid = initMessages({});');
}

// if a user logs in after several unsuccessful attempts, display a warning
if ($failedAttempts = CProfile::get('web.login.attempt.failed', 0)) {
	$attempip = CProfile::get('web.login.attempt.ip', '');
	$attempdate = CProfile::get('web.login.attempt.clock', 0);

	$error_msg = _n('%4$s failed login attempt logged. Last failed attempt was from %1$s on %2$s at %3$s.',
		'%4$s failed login attempts logged. Last failed attempt was from %1$s on %2$s at %3$s.',
		$attempip,
		zbx_date2str(_('d M Y'), $attempdate),
		zbx_date2str(_('H:i'), $attempdate),
		$failedAttempts
	);
	error($error_msg);

	CProfile::update('web.login.attempt.failed', 0, PROFILE_TYPE_INT);
}
show_messages();
