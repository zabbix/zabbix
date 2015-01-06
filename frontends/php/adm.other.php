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


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Other configuration parameters');
$page['file'] = 'adm.other.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'refresh_unsupported' =>	array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535), 'isset({update})',
		_('Refresh unsupported items (in sec)')
	),
	'discovery_groupid' =>		array(T_ZBX_INT, O_OPT, null, DB_ID, 'isset({update})',
		_('Group for discovered hosts')
	),
	'alert_usrgrpid' =>			array(T_ZBX_INT, O_OPT, null, DB_ID, 'isset({update})',
		_('User group for database down message')
	),
	'snmptrap_logging' =>		array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Log unmatched SNMP traps')),
	// actions
	'update' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();
	$result = update_config(array(
		'refresh_unsupported' => getRequest('refresh_unsupported'),
		'alert_usrgrpid' => getRequest('alert_usrgrpid'),
		'discovery_groupid' => getRequest('discovery_groupid'),
		'snmptrap_logging' => getRequest('snmptrap_logging', 0)
	));
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.other.php', 'redirect(this.options[this.selectedIndex].value);',
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

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('OTHER CONFIGURATION PARAMETERS'), $form);

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = array(
		'refresh_unsupported' => getRequest('refresh_unsupported', $config['refresh_unsupported']),
		'discovery_groupid' => getRequest('discovery_groupid', $config['discovery_groupid']),
		'alert_usrgrpid' => getRequest('alert_usrgrpid', $config['alert_usrgrpid']),
		'snmptrap_logging' => getRequest('snmptrap_logging', 0)
	);
}
else {
	$data = array(
		'refresh_unsupported' => $config['refresh_unsupported'],
		'discovery_groupid' => $config['discovery_groupid'],
		'alert_usrgrpid' => $config['alert_usrgrpid'],
		'snmptrap_logging' => $config['snmptrap_logging']
	);
}

$data['discovery_groups'] = API::HostGroup()->get(array(
	'output' => array('groupid', 'name'),
	'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
	'editable' => true
));
order_result($data['discovery_groups'], 'name');

$data['alert_usrgrps'] = DBfetchArray(DBselect('SELECT u.usrgrpid,u.name FROM usrgrp u'));
order_result($data['alert_usrgrps'], 'name');

$otherForm = new CView('administration.general.other.edit', $data);
$cnf_wdgt->addItem($otherForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
