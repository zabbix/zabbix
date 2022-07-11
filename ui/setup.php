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


require_once __DIR__.'/include/classes/core/APP.php';

$page['file'] = 'setup.php';

try {
	APP::getInstance()->run(APP::EXEC_MODE_SETUP);
}
catch (Exception $e) {
	echo (new CView('general.warning', [
		'header' => $e->getMessage(),
		'messages' => [],
		'theme' => ZBX_DEFAULT_THEME
	]))->getOutput();

	exit();
}

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'default_lang' =>		[T_ZBX_STR, O_OPT, null,	null,				null],
	'type' =>				[T_ZBX_STR, O_OPT, null,	IN('"'.ZBX_DB_MYSQL.'","'.ZBX_DB_POSTGRESQL.'","'.ZBX_DB_ORACLE.'"'), null],
	'server' =>				[T_ZBX_STR, O_OPT, null,	null,				null],
	'port' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null, _('Database port')],
	'database' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,			'(isset({type}) && {type} !== "'.ZBX_DB_ORACLE.'")', _('Database name')],
	'user' =>				[T_ZBX_STR, O_OPT, null,	null,				null],
	'password' =>			[T_ZBX_STR, O_OPT, null,	null, 				null],
	'schema' =>				[T_ZBX_STR, O_OPT, null,	null, 				null],
	'tls_encryption' =>		[T_ZBX_INT, O_OPT, null,	IN([0,1]),			null],
	'verify_certificate' =>	[T_ZBX_INT, O_OPT, null,	IN([0,1]),			null],
	'verify_host' =>		[T_ZBX_INT, O_OPT, null,	IN([0,1]),			null],
	'key_file' =>			[T_ZBX_STR, O_OPT, null,	null, 				null],
	'cert_file' =>			[T_ZBX_STR, O_OPT, null,	null, 				null],
	'ca_file' =>			[T_ZBX_STR, O_OPT, null,	null, 				null],
	'cipher_list' =>		[T_ZBX_STR, O_OPT, null,	null, 				null],
	'creds_storage' =>		[T_ZBX_INT, O_OPT, null,	IN([DB_STORE_CREDS_CONFIG, DB_STORE_CREDS_VAULT]),			null],
	'vault_url' =>			[T_ZBX_STR, O_OPT, null,	null,				null],
	'vault_db_path' =>		[T_ZBX_STR, O_OPT, null,	null,				null],
	'vault_token' =>		[T_ZBX_STR, O_OPT, null,	null,				null],
	'zbx_server_name' =>	[T_ZBX_STR, O_OPT, null,	null,				null],
	'default_timezone' =>	[T_ZBX_STR, O_OPT, null,	null,				null],
	'default_theme' =>		[T_ZBX_STR, O_OPT, null,	null,				null],
	// actions
	'save_config' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'retry' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'finish' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'next' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'back' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null]
];

$check_fields_result = check_fields($fields, false);

if (hasRequest('cancel') || hasRequest('finish')) {
	redirect('index.php');
}

if (CWebUser::$data && CWebUser::getType() < USER_TYPE_SUPER_ADMIN
		&& CSessionHelper::get('step') != CSetupWizard::STAGE_INSTALL) {
	access_deny(ACCESS_DENY_PAGE);
}

CSessionHelper::set('check_fields_result', $check_fields_result);

// Set default language.
$default_lang = ZBX_DEFAULT_LANG;

if (CSessionHelper::has('default_lang')) {
	$default_lang = CSessionHelper::get('default_lang');
}
elseif (CWebUser::$data) {
	$default_lang = CWebUser::$data['lang'];
}

$available_locales = [];

foreach (getLocales() as $localeid => $locale) {
	if ($locale['display'] && setlocale(LC_MONETARY, zbx_locale_variants($localeid)) !== false) {
		$available_locales[] = $localeid;
	}
}

// Restoring original locale.
setlocale(LC_MONETARY, zbx_locale_variants($default_lang));

$default_lang = getRequest('default_lang', $default_lang);

if (!in_array($default_lang, $available_locales)) {
	$default_lang = ZBX_DEFAULT_LANG;
}

CSessionHelper::set('default_lang', $default_lang);
APP::getInstance()->initLocales($default_lang);

// Set default time zone.
$default_timezone = ZBX_DEFAULT_TIMEZONE;

if (CSessionHelper::has('default_timezone')) {
	$default_timezone = CSessionHelper::get('default_timezone');
}
elseif (CWebUser::$data) {
	$default_timezone = CWebUser::$data['timezone'];
}

$default_timezone = getRequest('default_timezone', $default_timezone);

if ($default_timezone !== ZBX_DEFAULT_TIMEZONE && !CTimezoneHelper::isSupported($default_timezone)) {
	$default_timezone = ZBX_DEFAULT_TIMEZONE;
}

CSessionHelper::set('default_timezone', $default_timezone);

// Set default theme.
$default_theme = ZBX_DEFAULT_THEME;

if (CSessionHelper::has('default_theme')) {
	$default_theme = CSessionHelper::get('default_theme');
}
elseif (CWebUser::$data) {
	$default_theme = getUserTheme(CWebUser::$data);
}

$default_theme = getRequest('default_theme', $default_theme);

if (!array_key_exists($default_theme, APP::getThemes())) {
	$default_theme = ZBX_DEFAULT_THEME;
}

CSessionHelper::set('default_theme', $default_theme);

DBclose();

/*
 * Setup wizard
 */
$setup_wizard = new CSetupWizard();

// page title
(new CPageHeader(_('Installation'), substr($default_lang, 0, strpos($default_lang, '_'))))
	->addCssFile('assets/styles/'.CHtml::encode($default_theme).'.css')
	->addJsFile((new CUrl('js/browsers.js'))->getUrl())
	->addJsFile((new CUrl('jsLoader.php'))
		->setArgument('ver', ZABBIX_VERSION)
		->setArgument('lang', $default_lang)
		->getUrl()
	)
	->addJsFile((new CUrl('jsLoader.php'))
		->setArgument('ver', ZABBIX_VERSION)
		->setArgument('lang', $default_lang)
		->setArgument('files', ['setup.js'])
		->getUrl()
	)
	->display();

/*
 * Displaying
 */
$link = (new CLink('GPL v2', 'https://www.zabbix.com/license'))
	->setTarget('_blank')
	->addClass(ZBX_STYLE_GREY)
	->addClass(ZBX_STYLE_LINK_ALT);
$sub_footer = (new CDiv([_('Licensed under'), ' ', $link]))->addClass(ZBX_STYLE_SIGNIN_LINKS);

(new CTag('body', true,
	(new CDiv([
		(new CTag('main', true, [$setup_wizard, $sub_footer])), makePageFooter()])
	)->addClass(ZBX_STYLE_LAYOUT_WRAPPER)
))->show();
?>
</html>

<?php
session_write_close();
