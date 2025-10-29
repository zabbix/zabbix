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

global $page;

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

		if (strcasecmp(CSettingsHelper::getPublic(CSettingsHelper::X_FRAME_OPTIONS), 'null') != 0) {
			$x_frame_options = CSettingsHelper::getPublic(CSettingsHelper::X_FRAME_OPTIONS);

			if (strcasecmp($x_frame_options, 'SAMEORIGIN') == 0) {
				header('X-Frame-Options: SAMEORIGIN');
			}
			elseif (strcasecmp($x_frame_options, 'DENY') == 0) {
				header('X-Frame-Options: DENY');
			}
			else {
				header('Content-Security-Policy: frame-ancestors '.$x_frame_options);
			}
		}
}

if ($page['type'] == PAGE_TYPE_HTML) {
	global $ZBX_SERVER_NAME;

	// page title
	$page_title = '';
	if (isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '') {
		$page_title = $ZBX_SERVER_NAME.NAME_DELIMITER;
	}
	$page_title .= isset($page['title']) ? $page['title'] : _('Zabbix');

	if (defined('ZBX_PAGE_DO_JS_REFRESH') && CWebUser::getRefresh() != 0) {
		$page_title .= ' ['._s('refreshed every %1$s sec.', CWebUser::getRefresh()).']';
	}

	$page_header = new CHtmlPageHeader($page_title, CWebUser::getLang());
	$is_standard_page = (!defined('ZBX_PAGE_NO_MENU') || $page['web_layout_mode'] == ZBX_LAYOUT_KIOSKMODE);

	if (!ZBX_PAGE_NO_THEME) {
		global $DB;

		if (!empty($DB['DB'])) {
			$page_header
				->setTheme(getUserTheme(CWebUser::$data))
				->addStyle(getTriggerSeverityCss())
				->addStyle(getTriggerStatusCss());

			if ($is_standard_page) {
				if (CSettingsHelper::getPublic(CSettingsHelper::SERVER_CHECK_INTERVAL)) {
					$page['scripts'][] = 'servercheck.js';
				}

				if (CSettingsHelper::isSoftwareUpdateCheckEnabled()) {
					$page['scripts'][] = 'class.software-version-check.js';
				}
			}
		}
	}

	$page_header->addCssFile('assets/styles/'.$page_header->getTheme().'.css');

	foreach (APP::ModuleManager()->getAssets() as $module_id => $assets) {
		$module = APP::ModuleManager()->getModule($module_id);
		$relative_path = $module->getRelativePath().'/assets/css';

		foreach ($assets['css'] as $css_file) {
			$page_header->addCssFile((new CUrl($relative_path.'/'.$css_file))->getUrl());
		}
	}

	if ($page['file'] == 'sysmap.php') {
		$page_header->addCssFile('imgstore.php?css=1&output=css');
	}

	$tz_offsets = array_column((new DateTime())->getTimezone()->getTransitions(0, ZBX_MAX_DATE), 'offset', 'ts');

	$page_header
		->addJavaScript('
			const PHP_ZBX_FULL_DATE_TIME = "'.DATE_TIME_FORMAT_SECONDS.'";
			const PHP_TZ_OFFSETS = '.json_encode($tz_offsets).';
		')
		->addJsFile((new CUrl('js/browsers.js'))->getUrl());

	// Show GUI messages in pages with menus and in fullscreen mode.
	if (!defined('ZBX_PAGE_NO_JSLOADER')) {
		$page_header->addJsFile(
			(new CUrl('jsLoader.php'))
				->setArgument('ver', ZABBIX_VERSION)
				->setArgument('lang', CWebUser::$data['lang'])
				->setArgument('showGuiMessaging', ($is_standard_page && !CWebUser::isGuest()) ? 1 : null)
				->getUrl()
		);

		if (array_key_exists('scripts', $page) && $page['scripts']) {
			$page_header->addJsFile(
				(new CUrl('jsLoader.php'))
					->setArgument('ver', ZABBIX_VERSION)
					->setArgument('lang', CWebUser::$data['lang'])
					->setArgument('files', $page['scripts'])
					->getUrl()
			);
		}

		foreach (APP::ModuleManager()->getAssets() as $module_id => $assets) {
			$module = APP::ModuleManager()->getModule($module_id);
			$relative_path = $module->getRelativePath().'/assets/js';
			$translation_strings = $module->getTranslationStrings();

			foreach ($assets['js'] as $js_file) {
				$page_header->addJsFile((new CUrl($relative_path.'/'.$js_file))->getUrl());

				if (array_key_exists($js_file, $translation_strings)) {
					$page_header->addJsTranslationStrings($translation_strings[$js_file]);
				}
			}
		}
	}

	$page_header->show();

	echo '<body>';
}

define('PAGE_HEADER_LOADED', 1);

if ($page['type'] != PAGE_TYPE_HTML || defined('ZBX_PAGE_NO_HEADER')) {
	return null;
}

if (!defined('ZBX_PAGE_NO_MENU') && $page['web_layout_mode'] == ZBX_LAYOUT_NORMAL && CWebUser::isLoggedIn()) {
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
