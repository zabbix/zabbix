<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
			$logout->setHint(_s('It is not possible to logout from HTTP authentication.'), null, false);
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

	$serverName = (isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME))
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
unset($ZBX_MENU, $table, $top_page_row, $menu_table, $main_menu_row, $sub_menu_table, $sub_menu_rows);

if ($page['type'] == PAGE_TYPE_HTML && $showGuiMessaging) {
	zbx_add_post_js('initMessages({});');
}

// if a user logs in after several unsuccessful attempts, display a warning
if ($failedAttempts = CProfile::get('web.login.attempt.failed', 0)) {
	$attempip = CProfile::get('web.login.attempt.ip', '');
	$attempdate = CProfile::get('web.login.attempt.clock', 0);

	$error_msg = _n('%4$s failed login attempt logged. Last failed attempt was from %1$s on %2$s at %3$s.',
		'%4$s failed login attempts logged. Last failed attempt was from %1$s on %2$s at %3$s.',
		$attempip,
		zbx_date2str(DATE_FORMAT, $attempdate),
		zbx_date2str(TIME_FORMAT, $attempdate),
		$failedAttempts
	);
	error($error_msg);

	CProfile::update('web.login.attempt.failed', 0, PROFILE_TYPE_INT);
}
show_messages();
