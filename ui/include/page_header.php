<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

if (!array_key_exists('web_layout_mode', $page)) {
	$page['web_layout_mode'] = ZBX_LAYOUT_NORMAL;
}

if (!defined('ZBX_PAGE_NO_MENU') && $page['web_layout_mode'] == ZBX_LAYOUT_KIOSKMODE) {
	define('ZBX_PAGE_NO_MENU', true);
}

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
	case PAGE_TYPE_HTML:
	default:
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		header('X-XSS-Protection: 1; mode=block');

		if (CSettingsHelper::getGlobal(CSettingsHelper::X_FRAME_OPTIONS) !== '') {
			if (strcasecmp(CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS), 'SAMEORIGIN') == 0
					|| strcasecmp(CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS), 'DENY') == 0) {
				$x_frame_options = CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS);
			}
			else {
				$x_frame_options = 'SAMEORIGIN';
				$allowed_urls = explode(',', CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS));
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

if ($page['type'] == PAGE_TYPE_HTML) {
	global $ZBX_SERVER_NAME;

	// page title
	$pageTitle = '';
	if (isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '') {
		$pageTitle = $ZBX_SERVER_NAME.NAME_DELIMITER;
	}
	$pageTitle .= isset($page['title']) ? $page['title'] : _('Zabbix');

	if (defined('ZBX_PAGE_DO_JS_REFRESH') && CWebUser::getRefresh() != 0) {
		$pageTitle .= ' ['._s('refreshed every %1$s sec.', CWebUser::getRefresh()).']';
	}

	$pageHeader = new CPageHeader($pageTitle, CWebUser::getLang());
	$is_standard_page = (!defined('ZBX_PAGE_NO_MENU') || $page['web_layout_mode'] == ZBX_LAYOUT_KIOSKMODE);

	$theme = ZBX_DEFAULT_THEME;
	if (!ZBX_PAGE_NO_THEME) {
		global $DB;

		if (!empty($DB['DB'])) {
			$theme = getUserTheme(CWebUser::$data);

			$pageHeader->addStyle(getTriggerSeverityCss());
			$pageHeader->addStyle(getTriggerStatusCss());

			// perform Zabbix server check only for standard pages
			if ($is_standard_page && CSettingsHelper::get(CSettingsHelper::SERVER_CHECK_INTERVAL)) {
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
	if (!defined('ZBX_PAGE_NO_JSLOADER')) {
		$pageHeader->addJsFile((new CUrl('jsLoader.php'))
			->setArgument('ver', ZABBIX_VERSION)
			->setArgument('lang', CWebUser::$data['lang'])
			->setArgument('showGuiMessaging', ($is_standard_page && !CWebUser::isGuest()) ? 1 : null)
			->getUrl()
		);

		if (array_key_exists('scripts', $page) && $page['scripts']) {
			$pageHeader->addJsFile((new CUrl('jsLoader.php'))
				->setArgument('ver', ZABBIX_VERSION)
				->setArgument('lang', CWebUser::$data['lang'])
				->setArgument('files', $page['scripts'])
				->getUrl()
			);
		}
	}

	$pageHeader->display();

	echo '<body>';
}

define('PAGE_HEADER_LOADED', 1);

if ($page['type'] != PAGE_TYPE_HTML || defined('ZBX_PAGE_NO_HEADER')) {
	return null;
}

if (!defined('ZBX_PAGE_NO_MENU') && $page['web_layout_mode'] == ZBX_LAYOUT_NORMAL) {
	echo (new CPartial('layout.htmlpage.aside', [
		'server_name' => isset($ZBX_SERVER_NAME) ? $ZBX_SERVER_NAME : ''
	]))->getOutput();
}

echo '<div class="'.ZBX_STYLE_LAYOUT_WRAPPER.
	($page['web_layout_mode'] == ZBX_LAYOUT_KIOSKMODE ? ' '.ZBX_STYLE_LAYOUT_KIOSKMODE : '').'">'."\n";

// Display unexpected messages (if any) generated by the layout.
if (CMessageHelper::getType() === CMessageHelper::MESSAGE_TYPE_ERROR) {
	echo get_prepared_messages(['with_current_messages' => true]);
}
