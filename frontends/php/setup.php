<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/include/classes/core/Z.php';

$page['file'] = 'setup.php';

try {
	Z::getInstance()->run(ZBase::EXEC_MODE_SETUP);
}
catch (Exception $e) {
	(new CView('general.warning', [
		'header' => $e->getMessage(),
		'messages' => [],
		'theme' => ZBX_DEFAULT_THEME
	]))->render();

	exit;
}

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'type' =>				[T_ZBX_STR, O_OPT, null,	IN('"'.ZBX_DB_MYSQL.'","'.ZBX_DB_POSTGRESQL.'","'.ZBX_DB_ORACLE.'","'.ZBX_DB_DB2.'"'), null],
	'server' =>				[T_ZBX_STR, O_OPT, null,	null,				null],
	'port' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null, _('Database port')],
	'database' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,			null, _('Database name')],
	'user' =>				[T_ZBX_STR, O_OPT, null,	null,				null],
	'password' =>			[T_ZBX_STR, O_OPT, null,	null, 				null],
	'schema' =>				[T_ZBX_STR, O_OPT, null,	null, 				null],
	'zbx_server' =>			[T_ZBX_STR, O_OPT, null,	null,				null],
	'zbx_server_name' =>	[T_ZBX_STR, O_OPT, null,	null,				null],
	'zbx_server_port' =>	[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null, _('Port')],
	// actions
	'save_config' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'retry' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'finish' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'next' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'back' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,				null],
];

CSession::start();
CSession::setValue('check_fields_result', check_fields($fields, false));
if (!CSession::keyExists('step')) {
	CSession::setValue('step', 0);
}

// if a guest or a non-super admin user is logged in
if (CWebUser::$data && CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
	// on the last step of the setup we always have a guest user logged in;
	// when he presses the "Finish" button he must be redirected to the login screen
	if (CWebUser::isGuest() && CSession::getValue('step') == 5 && hasRequest('finish')) {
		CSession::clear();
		redirect('index.php');
	}
	// the guest user can also view the last step of the setup
	// all other user types must not have access to the setup
	elseif (!(CWebUser::isGuest() && CSession::getValue('step') == 5)) {
		access_deny(ACCESS_DENY_PAGE);
	}
}
// if a super admin or a non-logged in user presses the "Finish" or "Login" button - redirect him to the login screen
elseif (hasRequest('cancel') || hasRequest('finish')) {
	CSession::clear();
	redirect('index.php');
}

$theme = CWebUser::$data ? getUserTheme(CWebUser::$data) : ZBX_DEFAULT_THEME;

DBclose();

/*
 * Setup wizard
 */
$ZBX_SETUP_WIZARD = new CSetupWizard();

// if init fails due to missing configuration, set user as guest with default en_GB language
if (!CWebUser::$data) {
	CWebUser::setDefault();
}

// page title
(new CPageHeader(_('Installation')))
	->addCssFile('styles/'.CHtml::encode($theme).'.css')
	->addJsFile('js/browsers.js')
	->addJsFile('jsLoader.php?ver='.ZABBIX_VERSION.'&amp;lang='.CWebUser::$data['lang'])
	->display();

/*
 * Displaying
 */
$link = (new CLink('GPL v2', 'http://www.zabbix.com/license.php'))
	->setTarget('_blank')
	->addClass(ZBX_STYLE_GREY)
	->addClass(ZBX_STYLE_LINK_ALT);
$sub_footer = (new CDiv(['Licensed under ', $link]))->addClass(ZBX_STYLE_SIGNIN_LINKS);

(new CTag('body', true, [(new CTag('main', true, [$ZBX_SETUP_WIZARD, $sub_footer])), makePageFooter()]))
	->setAttribute('lang', CWebUser::getLang())
	->show();
?>
</html>
