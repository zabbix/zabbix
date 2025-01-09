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


/**
 * @var CPartial $this
 * @var array    $data
 */

global $DB, $ZBX_SERVER_NAME;

$scripts = $data['javascript']['files'];
$page_title = $data['page']['title'];

if (isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '') {
	$page_title = $page_title !== '' ? $ZBX_SERVER_NAME.NAME_DELIMITER.$page_title : $ZBX_SERVER_NAME;
}

$page_header = new CHtmlPageHeader($page_title, CWebUser::getLang());

if (!empty($DB['DB'])) {
	$page_header
		->setTheme(getUserTheme($data['user']))
		->addStyle(getTriggerSeverityCss())
		->addStyle(getTriggerStatusCss());

	// Perform Zabbix server check only for standard pages.
	if ($data['config']['server_check_interval']) {
		$scripts[] = 'servercheck.js';
	}

	if (CSettingsHelper::isSoftwareUpdateCheckEnabled()) {
		$scripts[] = 'class.software-version-check.js';

		$check_data = CSettingsHelper::getSoftwareUpdateCheckData() + ['nextcheck' => 0];
		$now = time();
		$delay = $check_data['nextcheck'] > $now ? $check_data['nextcheck'] - $now : 0;

		$page_header->addJavaScript('const ZBX_SOFTWARE_VERSION_CHECK_DELAY = '.$delay.';');
	}
}

// Show GUI messages in pages with menus and in kiosk mode.
$show_gui_messaging = !defined('ZBX_PAGE_NO_MENU') || $data['web_layout_mode'] == ZBX_LAYOUT_KIOSKMODE
	? (int)!CWebUser::isGuest()
	: null;

$modules_assets = APP::ModuleManager()->getAssets();

$tz_offsets = array_column((new DateTime())->getTimezone()->getTransitions(0, ZBX_MAX_DATE), 'offset', 'ts');

$page_header->addCssFile('assets/styles/'.$page_header->getTheme().'.css');

foreach ($modules_assets as $module_id => $assets) {
	$module = APP::ModuleManager()->getModule($module_id);
	$relative_path = $module->getRelativePath().'/assets/css';

	foreach ($assets['css'] as $css_file) {
		$page_header->addCssFile((new CUrl($relative_path.'/'.$css_file))->getUrl());
	}
}

$page_header
	->addJavaScript('
		const PHP_TZ_OFFSETS = '.json_encode($tz_offsets).';
		const PHP_ZBX_FULL_DATE_TIME = "'.DATE_TIME_FORMAT_SECONDS.'";
	')
	->addJsFile((new CUrl('js/browsers.js'))->getUrl())
	->addJsFile((new CUrl('jsLoader.php'))
		->setArgument('lang', $data['user']['lang'])
		->setArgument('ver', ZABBIX_VERSION)
		->setArgument('showGuiMessaging', $show_gui_messaging)
		->getUrl()
	);

foreach ($data['stylesheet']['files'] as $css_file) {
	$page_header->addCssFile($css_file);
}

if ($scripts) {
	$page_header->addJsFile(
		(new CUrl('jsLoader.php'))
			->setArgument('ver', ZABBIX_VERSION)
			->setArgument('lang', $data['user']['lang'])
			->setArgument('files', $scripts)
			->getUrl()
	);

	$page_header->addJavaScript('if (locale === undefined) { var locale = {}; }');

	foreach ($modules_assets as $module_id => $assets) {
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
