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


require_once dirname(__FILE__).'/include/classes/core/Z.php';

try {
	Z::getInstance()->run(ZBase::EXEC_MODE_SETUP);
}
catch (Exception $e) {
	$warningView = new CView('general.warning', array('message' => 'Configuration file error: '.$e->getMessage()));
	$warningView->render();
	exit;
}

require_once dirname(__FILE__).'/include/setup.inc.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'type' =>				array(T_ZBX_STR, O_OPT, null,	IN('"'.ZBX_DB_MYSQL.'","'.ZBX_DB_POSTGRESQL.'","'.ZBX_DB_ORACLE.'","'.ZBX_DB_DB2.'","'.ZBX_DB_SQLITE3.'"'), null),
	'server' =>				array(T_ZBX_STR, O_OPT, null,	null,				null),
	'port' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null, _('Database port')),
	'database' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,			null, _('Database name')),
	'user' =>				array(T_ZBX_STR, O_OPT, null,	null,				null),
	'password' =>			array(T_ZBX_STR, O_OPT, null,	null, 				null),
	'schema' =>				array(T_ZBX_STR, O_OPT, null,	null, 				null),
	'zbx_server' =>			array(T_ZBX_STR, O_OPT, null,	null,				null),
	'zbx_server_name' =>	array(T_ZBX_STR, O_OPT, null,	null,				null),
	'zbx_server_port' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null, _('Port')),
	'message' =>			array(T_ZBX_STR, O_OPT, null,	null,				null),
	// actions
	'save_config' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'retry' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'finish' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'next' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'back' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,				null)
);

// config
$ZBX_CONFIG = get_cookie('ZBX_CONFIG', null);
$ZBX_CONFIG = isset($ZBX_CONFIG) ? unserialize($ZBX_CONFIG) : array();
$ZBX_CONFIG['check_fields_result'] = check_fields($fields, false);
if (!isset($ZBX_CONFIG['step'])) {
	$ZBX_CONFIG['step'] = 0;
}

// if a guest or a non-super admin user is logged in
if (CWebUser::$data && CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
	// on the last step of the setup we always have a guest user logged in;
	// when he presses the "Finish" button he must be redirected to the login screen
	if (CWebUser::isGuest() && $ZBX_CONFIG['step'] == 5 && hasRequest('finish')) {
		zbx_unsetcookie('ZBX_CONFIG');
		redirect('index.php');
	}
	// the guest user can also view the last step of the setup
	// all other user types must not have access to the setup
	elseif (!(CWebUser::isGuest() && $ZBX_CONFIG['step'] == 5)) {
		access_deny(ACCESS_DENY_PAGE);
	}
}
// if a super admin or a non-logged in user presses the "Finish" or "Login" button - redirect him to the login screen
elseif (hasRequest('cancel') || hasRequest('finish')) {
	zbx_unsetcookie('ZBX_CONFIG');
	redirect('index.php');
}

/*
 * Setup wizard
 */
$ZBX_SETUP_WIZARD = new CSetupWizard($ZBX_CONFIG);

zbx_setcookie('ZBX_CONFIG', serialize($ZBX_CONFIG));

// page title
$pageTitle = '';
if (isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)) {
	$pageTitle = $ZBX_SERVER_NAME.NAME_DELIMITER;
}
$pageTitle .= _('Installation');

$pageHeader = new CPageHeader($pageTitle);
$pageHeader->addCssInit();
$pageHeader->addCssFile('styles/themes/originalblue/main.css');
$pageHeader->addJsFile('js/jquery/jquery.js');
$pageHeader->addJsFile('js/jquery/jquery-ui.js');
$pageHeader->addJsFile('js/functions.js');

// if init fails due to missing configuration, set user as guest with default en_GB language
if (!CWebUser::$data) {
	CWebUser::setDefault();
}

$path = 'jsLoader.php?ver='.ZABBIX_VERSION.'&amp;lang='.CWebUser::$data['lang'].'&amp;files[]=common.js&amp;files[]=main.js';
$pageHeader->addJsFile($path);

$pageHeader->display();
?>
<body class="originalblue">

<?php $ZBX_SETUP_WIZARD->show(); ?>
<script>
	jQuery(function($) {
		$(':submit').button();
	});
</script>
</body>
</html>
