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

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hk_events_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_events_trigger' => 	array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('Trigger event and alert data storage period')
	),
	'hk_events_internal' => array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('Internal event and alert data storage period')
	),
	'hk_events_discovery' =>array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('Network discovery event and alert data storage period')
	),
	'hk_events_autoreg' => 	array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('Auto-registration event and alert data storage period')
	),
	'hk_services_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_services' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('IT service data storage period')
	),
	'hk_audit_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_audit' => 			array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('Audit data storage period')
	),
	'hk_sessions_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_sessions' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), null,
		_('User session data storage period')),
	'hk_history_mode' =>	array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_history_global' =>	array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_history' => 		array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999), null,
		_('History data storage period')
	),
	'hk_trends_mode' =>		array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_trends_global' =>	array(T_ZBX_INT, O_OPT, null, IN('0,1'), null),
	'hk_trends' => 			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 99999), null,
		_('Trend data storage period')
	),
	'update' =>				array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null, null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT,	null, null, null)
);
check_fields($fields);

$data['config'] = select_config();

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();

	$configs = array(
		'hk_events_mode' => getRequest('hk_events_mode', 0),
		'hk_events_trigger' => getRequest('hk_events_trigger'),
		'hk_events_internal' => getRequest('hk_events_internal'),
		'hk_events_discovery' => getRequest('hk_events_discovery'),
		'hk_events_autoreg' => getRequest('hk_events_autoreg'),
		'hk_services_mode' => getRequest('hk_services_mode', 0),
		'hk_services' => getRequest('hk_services'),
		'hk_audit_mode' => getRequest('hk_audit_mode', 0),
		'hk_audit' => getRequest('hk_audit'),
		'hk_sessions_mode' => getRequest('hk_sessions_mode', 0),
		'hk_sessions' => getRequest('hk_sessions'),
		'hk_history_mode' => getRequest('hk_history_mode', 0),
		'hk_history_global' => getRequest('hk_history_global', 0),
		'hk_history' => getRequest('hk_history'),
		'hk_trends_mode' => getRequest('hk_trends_mode', 0),
		'hk_trends_global' => getRequest('hk_trends_global', 0),
		'hk_trends' => getRequest('hk_trends')
	);

	$result = update_config($configs);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));

	if ($result) {
		$msg = array();
		$msg[] = _s('Trigger event and alert data storage period (in days) "%1$s".', getRequest('hk_events_trigger'));
		$msg[] = _s('Internal event and alert data storage period (in days) "%1$s".', getRequest('hk_events_internal'));
		$msg[] = _s('Network discovery event and alert data storage period (in days) "%1$s".', getRequest('hk_events_discovery'));
		$msg[] = _s('Auto-registration event and alert data storage period (in days) "%1$s".', getRequest('hk_events_autoreg'));
		$msg[] = _s('IT service data storage period (in days) "%1$s".', getRequest('hk_services'));
		$msg[] = _s('Audit data storage period (in days) "%1$s".', getRequest('hk_audit'));
		$msg[] = _s('User session data storage period (in days) "%1$s".', getRequest('hk_sessions'));
		$msg[] = _s('History data storage period (in days) "%1$s".', getRequest('hk_history'));
		$msg[] = _s('Trend data storage period (in days) "%1$s".', getRequest('hk_trends'));

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, implode('; ', $msg));
	}

	DBend($result);
}


$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.housekeeper.php', 'redirect(this.options[this.selectedIndex].value);');
$cmbConf->addItems(array(
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
));
$form->addItem($cmbConf);

$cnf_wdgt = new CWidget(null, 'hk');
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF HOUSEKEEPING'), $form);

if (hasRequest('form_refresh')) {
	$data['config']['hk_events_mode'] = getRequest('hk_events_mode');
	$data['config']['hk_events_trigger'] = getRequest('hk_events_trigger', $data['config']['hk_events_trigger']);
	$data['config']['hk_events_internal'] = getRequest('hk_events_internal', $data['config']['hk_events_internal']);
	$data['config']['hk_events_discovery'] = getRequest('hk_events_discovery', $data['config']['hk_events_discovery']);
	$data['config']['hk_events_autoreg'] = getRequest('hk_events_autoreg', $data['config']['hk_events_autoreg']);
	$data['config']['hk_services_mode'] = getRequest('hk_services_mode');
	$data['config']['hk_services'] = getRequest('hk_services', $data['config']['hk_services']);
	$data['config']['hk_audit_mode'] = getRequest('hk_audit_mode');
	$data['config']['hk_audit'] = getRequest('hk_audit', $data['config']['hk_audit']);
	$data['config']['hk_sessions_mode'] = getRequest('hk_sessions_mode');
	$data['config']['hk_sessions'] = getRequest('hk_sessions', $data['config']['hk_sessions']);
	$data['config']['hk_history_mode'] = getRequest('hk_history_mode');
	$data['config']['hk_history_global'] = getRequest('hk_history_global');
	$data['config']['hk_history'] = getRequest('hk_history', $data['config']['hk_history']);
	$data['config']['hk_trends_mode'] = getRequest('hk_trends_mode');
	$data['config']['hk_trends_global'] = getRequest('hk_trends_global');
	$data['config']['hk_trends'] = getRequest('hk_trends', $data['config']['hk_trends']);
}
else {
	$data['config'] = select_config(false);
}

$houseKeeperForm = new CView('administration.general.housekeeper.edit', $data);
$cnf_wdgt->addItem($houseKeeperForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
