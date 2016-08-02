<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$page['title'] = _('Configuration of housekeeping');
$page['file'] = 'adm.housekeeper.php';

require_once dirname(__FILE__).'/include/page_header.php';

$fields = [
	'hk_events_mode' =>		[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_events_trigger' => 	[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Trigger event and alert data storage period')
	],
	'hk_events_internal' => [T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Internal event and alert data storage period')
	],
	'hk_events_discovery' =>[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Network discovery event and alert data storage period')
	],
	'hk_events_autoreg' => 	[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Auto-registration event and alert data storage period')
	],
	'hk_services_mode' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_services' => 		[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_services_mode})', _('IT service data storage period')
	],
	'hk_audit_mode' =>		[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_audit' => 			[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_audit_mode})', _('Audit data storage period')
	],
	'hk_sessions_mode' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_sessions' => 		[T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_sessions_mode})', _('User session data storage period')],
	'hk_history_mode' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_history_global' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Override item history period')],
	'hk_history' => 		[T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999),
		'isset({update}) && isset({hk_history_global})', _('History data storage period')
	],
	'hk_trends_mode' =>		[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')],
	'hk_trends_global' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Override item history period')],
	'hk_trends' => 			[T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999),
		'isset({update}) && isset({hk_trends_global})', _('Trend data storage period')
	],
	// actions
	'update' =>				[T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT,	null, null, null]
];
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	$config = [
		'hk_events_mode' => getRequest('hk_events_mode', 0),
		'hk_services_mode' => getRequest('hk_services_mode', 0),
		'hk_audit_mode' => getRequest('hk_audit_mode', 0),
		'hk_sessions_mode' => getRequest('hk_sessions_mode', 0),
		'hk_history_mode' => getRequest('hk_history_mode', 0),
		'hk_history_global' => getRequest('hk_history_global', 0),
		'hk_trends_mode' => getRequest('hk_trends_mode', 0),
		'hk_trends_global' => getRequest('hk_trends_global', 0)
	];

	if ($config['hk_events_mode'] == 1) {
		$config['hk_events_trigger'] = getRequest('hk_events_trigger');
		$config['hk_events_internal'] = getRequest('hk_events_internal');
		$config['hk_events_discovery'] = getRequest('hk_events_discovery');
		$config['hk_events_autoreg'] = getRequest('hk_events_autoreg');
	}

	if ($config['hk_services_mode'] == 1) {
		$config['hk_services'] = getRequest('hk_services');
	}

	if ($config['hk_audit_mode'] == 1) {
		$config['hk_audit'] = getRequest('hk_audit');
	}

	if ($config['hk_sessions_mode'] == 1) {
		$config['hk_sessions'] = getRequest('hk_sessions');
	}

	if ($config['hk_history_global'] == 1) {
		$config['hk_history'] = getRequest('hk_history');
	}

	if ($config['hk_trends_global'] == 1) {
		$config['hk_trends'] = getRequest('hk_trends');
	}

	DBstart();
	$result = update_config($config);
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = [
		'hk_events_mode' => getRequest('hk_events_mode', 0),
		'hk_events_trigger' => getRequest('hk_events_trigger', $config['hk_events_trigger']),
		'hk_events_internal' => getRequest('hk_events_internal', $config['hk_events_internal']),
		'hk_events_discovery' => getRequest('hk_events_discovery', $config['hk_events_discovery']),
		'hk_events_autoreg' => getRequest('hk_events_autoreg', $config['hk_events_autoreg']),
		'hk_services_mode' => getRequest('hk_services_mode', 0),
		'hk_services' => getRequest('hk_services', $config['hk_services']),
		'hk_audit_mode' => getRequest('hk_audit_mode', 0),
		'hk_audit' => getRequest('hk_audit', $config['hk_audit']),
		'hk_sessions_mode' => getRequest('hk_sessions_mode', 0),
		'hk_sessions' => getRequest('hk_sessions', $config['hk_sessions']),
		'hk_history_mode' => getRequest('hk_history_mode', 0),
		'hk_history_global' => getRequest('hk_history_global', 0),
		'hk_history' => getRequest('hk_history', $config['hk_history']),
		'hk_trends_mode' => getRequest('hk_trends_mode', 0),
		'hk_trends_global' => getRequest('hk_trends_global', 0),
		'hk_trends' => getRequest('hk_trends', $config['hk_trends'])
	];
}
else {
	$data = [
		'hk_events_mode' => $config['hk_events_mode'],
		'hk_events_trigger' => $config['hk_events_trigger'],
		'hk_events_internal' => $config['hk_events_internal'],
		'hk_events_discovery' => $config['hk_events_discovery'],
		'hk_events_autoreg' => $config['hk_events_autoreg'],
		'hk_services_mode' => $config['hk_services_mode'],
		'hk_services' => $config['hk_services'],
		'hk_audit_mode' => $config['hk_audit_mode'],
		'hk_audit' => $config['hk_audit'],
		'hk_sessions_mode' => $config['hk_sessions_mode'],
		'hk_sessions' => $config['hk_sessions'],
		'hk_history_mode' => $config['hk_history_mode'],
		'hk_history_global' => $config['hk_history_global'],
		'hk_history' => $config['hk_history'],
		'hk_trends_mode' => $config['hk_trends_mode'],
		'hk_trends_global' => $config['hk_trends_global'],
		'hk_trends' => $config['hk_trends']
	];
}

$view = new CView('administration.general.housekeeper.edit', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
