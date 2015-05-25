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


if (!isset($page['type'])) {
	$page['type'] = PAGE_TYPE_HTML;
}
if (!isset($page['file'])) {
	$page['file'] = basename($_SERVER['PHP_SELF']);
}
$_REQUEST['fullscreen'] = getRequest('fullscreen', 0);
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
$main_menu = [];
$sub_menus = [];

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
//	$pageHeader->addCssFile('styles/themes/'.$css.'/main.css');

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
<body>
<div class="msg-bad-global" id="msg-bad-global"></div>
<?php
}

define('PAGE_HEADER_LOADED', 1);

if (defined('ZBX_PAGE_NO_HEADER')) {
	return null;
}

if (!defined('ZBX_PAGE_NO_MENU')) {
	$pageMenu = new CView('layout.htmlpage.menu', [
		'menu' => [
			'main_menu' => $main_menu,
			'sub_menus' => $sub_menus,
			'selected' => $page['menu']
		]
	]);
	echo $pageMenu->getOutput();
}

if ($page['type'] == PAGE_TYPE_HTML) {
	echo '<div class="'.ZBX_STYLE_ARTICLE.'">';
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
