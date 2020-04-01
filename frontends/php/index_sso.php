<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


require_once __DIR__.'/include/config.inc.php';

$config = select_config();
$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

if ($config['saml_auth_enabled'] == ZBX_AUTH_SAML_DISABLED) {
	redirect($redirect_to->toString());

	exit;
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => array_column(clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))->onClick('document.location = '.json_encode($redirect_to->getUrl()).';')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();
