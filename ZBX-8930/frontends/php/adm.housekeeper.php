<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'hk_events_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_events_trigger' => 	array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Trigger event and alert data storage period')
	),
	'hk_events_internal' => array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Internal event and alert data storage period')
	),
	'hk_events_discovery' =>array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Network discovery event and alert data storage period')
	),
	'hk_events_autoreg' => 	array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_events_mode})', _('Auto-registration event and alert data storage period')
	),
	'hk_services_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_services' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_services_mode})', _('IT service data storage period')
	),
	'hk_audit_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_audit' => 			array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_audit_mode})', _('Audit data storage period')
	),
	'hk_sessions_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_sessions' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999),
		'isset({update}) && isset({hk_sessions_mode})', _('User session data storage period')),
	'hk_history_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_history_global' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Override item history period')),
	'hk_history' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999),
		'isset({update}) && isset({hk_history_global})', _('History data storage period')
	),
	'hk_trends_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable internal housekeeping')),
	'hk_trends_global' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Override item history period')),
	'hk_trends' => 			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999),
		'isset({update}) && isset({hk_trends_global})', _('Trend data storage period')
	),
	// actions
	'update' =>				array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT,	null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	$config = array(
		'hk_events_mode' => getRequest('hk_events_mode', 0),
		'hk_services_mode' => getRequest('hk_services_mode', 0),
		'hk_audit_mode' => getRequest('hk_audit_mode', 0),
		'hk_sessions_mode' => getRequest('hk_sessions_mode', 0),
		'hk_history_mode' => getRequest('hk_history_mode', 0),
		'hk_history_global' => getRequest('hk_history_global', 0),
		'hk_trends_mode' => getRequest('hk_trends_mode', 0),
		'hk_trends_global' => getRequest('hk_trends_global', 0)
	);

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


$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.housekeeper.php', 'redirect(this.options[this.selectedIndex].value);',
	array(
		'adm.gui.php' => _('GUI'),
		'adm.housekeeper.php' => _('Housekeeping'),
		'adm.images.php' => _('Images'),
		'adm.iconmapping.php' => _('Icon mapping'),
		'adm.regexps.php' => _('Regular expressions'),
		'adm.macros.php' => _('Macros'),
		'adm.valuemapping.php' => _('Value mapping'),
		'adm.workingtime.php' => _('Working time'),
		'adm.triggerseverities.php' => _('Trigger severities'),
		'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
		'adm.other.php' => _('Other')
	)
);
$form->addItem($cmbConf);

$cnf_wdgt = new CWidget(null, 'hk');
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF HOUSEKEEPING'), $form);

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = array(
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
	);
}
else {
	$data = array(
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
	);
}

$houseKeeperForm = new CView('administration.general.housekeeper.edit', $data);
$cnf_wdgt->addItem($houseKeeperForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
