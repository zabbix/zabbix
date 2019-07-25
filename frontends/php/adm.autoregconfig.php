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


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Auto registration');
$page['file'] = 'adm.autoregconfig.php';

require_once dirname(__FILE__).'/include/page_header.php';

$fields = [
	'tls_accept' =>			[T_ZBX_INT, O_OPT, null, BETWEEN(HOST_ENCRYPTION_NONE,
								(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)), null
							],
	'tls_psk_identity' =>	[T_ZBX_STR, O_OPT, null, null, null],
	'tls_psk' =>			[T_ZBX_STR, O_OPT, null, null, null],

	// actions
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null, null, null]
];
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();

	if (hasRequest('update')) {
		$result = API::Autoregistration()->update([
			'tls_accept' => getRequest('tls_accept'),
			'tls_psk_identity' => getRequest('tls_psk_identity'),
			'tls_psk' => getRequest('tls_psk')
		]);
	}

	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$autoreg = API::Autoregistration()->get(['output' => API_OUTPUT_EXTEND]);

if (hasRequest('form_refresh')) {
	$data = [
		'tls_accept'		=> getRequest('tls_accept', $autoreg['tls_accept']),
		'tls_psk_identity'	=> getRequest('tls_psk_identity', $autoreg['tls_psk_identity']),
		'tls_psk'			=> getRequest('tls_psk', $autoreg['tls_psk'])
	];
}
else {
	$data = [
		'tls_accept'		=> $autoreg['tls_accept'] == null ? HOST_ENCRYPTION_NONE : $autoreg['tls_accept'],
		'tls_psk_identity'	=> $autoreg['tls_psk_identity'],
		'tls_psk'			=> $autoreg['tls_psk']
	];
}

$view = new CView('administration.general.autoregconfig.edit', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
