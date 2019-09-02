<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


CSession::start();

if (!isset($page['type'])) {
	$page['type'] = PAGE_TYPE_HTML;
}
if (!isset($page['file'])) {
	$page['file'] = basename($_SERVER['PHP_SELF']);
}

if (!array_key_exists('web_layout_mode', $page)) {
	$page['web_layout_mode'] = ZBX_LAYOUT_NORMAL;
}

if (!defined('ZBX_PAGE_NO_MENU') && in_array($page['web_layout_mode'], [ZBX_LAYOUT_FULLSCREEN, ZBX_LAYOUT_KIOSKMODE])) {
	define('ZBX_PAGE_NO_MENU', true);
}

require_once dirname(__FILE__).'/menu.inc.php';

if (!defined('ZBX_PAGE_NO_THEME')) {
	define('ZBX_PAGE_NO_THEME', false);
}

switch ($page['type']) {
	case PAGE_TYPE_IMAGE:
		set_image_header();
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_XML:
		header('Content-Type: text/xml');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_JS:
		header('Content-Type: application/javascript; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_JSON:
		header('Content-Type: application/json');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_JSON_RPC:
		header('Content-Type: application/json-rpc');
		if(!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_CSS:
		header('Content-Type: text/css; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_TEXT:
	case PAGE_TYPE_TEXT_RETURN_JSON:
	case PAGE_TYPE_HTML_BLOCK:
		header('Content-Type: text/plain; charset=UTF-8');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_TEXT_FILE:
		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_CSV:
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$page['file'].'"');
		if (!defined('ZBX_PAGE_NO_MENU')) {
			define('ZBX_PAGE_NO_MENU', true);
		}
		break;
	case PAGE_TYPE_HTML:
	default:
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		header('X-XSS-Protection: 1; mode=block');

		if (X_FRAME_OPTIONS !== null) {
			if (strcasecmp(X_FRAME_OPTIONS, 'SAMEORIGIN') == 0 || strcasecmp(X_FRAME_OPTIONS, 'DENY') == 0) {
				$x_frame_options = X_FRAME_OPTIONS;
			}
			else {
				$x_frame_options = 'SAMEORIGIN';
				$allowed_urls = explode(',', X_FRAME_OPTIONS);
				$url_to_check = array_key_exists('HTTP_REFERER', $_SERVER)
					? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST)
					: null;

				if ($url_to_check) {
					foreach ($allowed_urls as $allowed_url) {
						if (strcasecmp(trim($allowed_url), $url_to_check) == 0) {
							$x_frame_options = 'ALLOW-FROM '.$allowed_url;
							break;
						}
					}
				}
			}

			header('X-Frame-Options: '.$x_frame_options);
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
	global $ZBX_SERVER_NAME;

	// page title
	$pageTitle = '';
	if (isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '') {
		$pageTitle = $ZBX_SERVER_NAME.NAME_DELIMITER;
	}
	$pageTitle .= isset($page['title']) ? $page['title'] : _('Zabbix');

	if ((defined('ZBX_PAGE_DO_REFRESH') || defined('ZBX_PAGE_DO_JS_REFRESH')) && CWebUser::getRefresh() != 0) {
		$pageTitle .= ' ['._s('refreshed every %1$s sec.', CWebUser::getRefresh()).']';
	}

	$pageHeader = new CPageHeader($pageTitle);
	$is_standard_page = (!defined('ZBX_PAGE_NO_MENU')
		|| in_array($page['web_layout_mode'], [ZBX_LAYOUT_FULLSCREEN, ZBX_LAYOUT_KIOSKMODE]));

	$theme = ZBX_DEFAULT_THEME;
	if (!ZBX_PAGE_NO_THEME) {
		global $DB;

		if (!empty($DB['DB'])) {
			$config = select_config();
			$theme = getUserTheme(CWebUser::$data);

			$pageHeader->addStyle(getTriggerSeverityCss($config));
			$pageHeader->addStyle(getTriggerStatusCss($config));

			// perform Zabbix server check only for standard pages
			if ($is_standard_page && $config['server_check_interval'] && !empty($ZBX_SERVER) && !empty($ZBX_SERVER_PORT)) {
				$page['scripts'][] = 'servercheck.js';
			}
		}
	}
	$pageHeader->addCssFile('assets/styles/'.CHtml::encode($theme).'.css');

	if ($page['file'] == 'sysmap.php') {
		$pageHeader->addCssFile('imgstore.php?css=1&output=css');
	}

	$pageHeader
		->addJsFile((new CUrl('js/browsers.js'))->getUrl())
		->addJsBeforeScripts(
			'var PHP_TZ_OFFSET = '.date('Z').','.
				'PHP_ZBX_FULL_DATE_TIME = "'.ZBX_FULL_DATE_TIME.'";'
		);

	// Show GUI messages in pages with menus and in fullscreen mode.
	if (CView::$js_loader_disabled !== true) {
		$pageHeader->addJsFile((new CUrl('jsLoader.php'))
			->setArgument('ver', ZABBIX_VERSION)
			->setArgument('lang', CWebUser::$data['lang'])
			->setArgument('showGuiMessaging', ($is_standard_page && !CWebUser::isGuest()) ? 1 : null)
			->getUrl()
		);

		if ($page['scripts']) {
			$pageHeader->addJsFile((new CUrl('jsLoader.php'))
				->setArgument('ver', ZABBIX_VERSION)
				->setArgument('lang', CWebUser::$data['lang'])
				->setArgument('files', $page['scripts'])
				->getUrl()
			);
		}
	}

	$pageHeader->display();
?>
<body lang="<?= CWebUser::getLang() ?>">
<output class="<?= ZBX_STYLE_MSG_GLOBAL_FOOTER.' '.ZBX_STYLE_MSG_WARNING ?>" id="msg-global-footer"></output>
<?php
}

define('PAGE_HEADER_LOADED', 1);

if (defined('ZBX_PAGE_NO_HEADER')) {
	return null;
}

// checking messages from MVC pages
$message_good = null;
$message_ok = null;
$message_error = null;
$messages = [];

// this code show messages generated by MVC pages
if (CSession::keyExists('messageOk') || CSession::keyExists('messageError')) {
	if (CSession::keyExists('messages')) {
		$messages = CSession::getValue('messages');
		CSession::unsetValue(['messages']);
	}

	if (CSession::keyExists('messageOk')) {
		$message_good = true;
		$message_ok = CSession::getValue('messageOk');
	}
	else {
		$message_good = false;
		$message_error = CSession::getValue('messageError');
	}

	CSession::unsetValue(['messageOk', 'messageError']);
}

if (!defined('ZBX_PAGE_NO_MENU') && $page['web_layout_mode'] === ZBX_LAYOUT_NORMAL) {
	$pageMenu = new CView('layout.htmlpage.menu', [
		'server_name' => isset($ZBX_SERVER_NAME) ? $ZBX_SERVER_NAME : '',
		'menu' => [
			'main_menu' => $main_menu,
			'sub_menus' => $sub_menus,
			'selected' => $page['menu']
		],
		'user' => [
			'is_guest' => CWebUser::isGuest(),
			'alias' => CWebUser::$data['alias'],
			'name' => CWebUser::$data['name'],
			'surname' => CWebUser::$data['surname']
		],
		'support_url' => getSupportUrl(CWebUser::getLang())
	]);
	echo $pageMenu->getOutput();
}

if ($page['type'] == PAGE_TYPE_HTML) {
	echo '<main>';
}

// unset multiple variables
unset($table, $top_page_row, $menu_table, $main_menu_row, $sub_menu_table, $sub_menu_rows);

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

// this code show messages generated by MVC pages
if ($message_good !== null) {
	global $ZBX_MESSAGES;

	$ZBX_MESSAGES = $messages;
	show_messages($message_good, $message_ok, $message_error);
}
