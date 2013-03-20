<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'refresh_unsupported' => array(T_ZBX_INT, O_NO,	null, BETWEEN(0, 65535), 'isset({save})',
		_('Refresh unsupported items (in sec)')),
	'alert_usrgrpid' =>			array(T_ZBX_INT, O_NO,	null,		DB_ID,				'isset({save})'),
	'discovery_groupid' =>		array(T_ZBX_INT, O_NO,	null,		DB_ID,				'isset({save})'),
	'snmptrap_logging' =>		array(T_ZBX_INT, O_OPT,	null,		IN('1'),			null),
	'save' =>					array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT, null,				null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT,	null,		null,				null)
);
check_fields($fields);

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	DBstart();

	$orig_config = select_config(false);

	$configs = array(
		'refresh_unsupported' => get_request('refresh_unsupported'),
		'alert_usrgrpid' => get_request('alert_usrgrpid'),
		'discovery_groupid' => get_request('discovery_groupid'),
		'snmptrap_logging' => get_request('snmptrap_logging') ? 1 : 0
	);
	$result = update_config($configs);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
	if ($result) {
		$msg = array();
		$msg[] = _s('Refresh unsupported items (in sec) "%1$s".', get_request('refresh_unsupported'));
		if (!is_null($val = get_request('discovery_groupid'))) {
			$val = API::HostGroup()->get(array(
				'groupids' => $val,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND
			));

			if (!empty($val)) {
				$val = array_pop($val);
				$msg[] = _('Group for discovered hosts.').' ['.$val['name'].']';

				if (bccomp($val['groupid'], $orig_config['discovery_groupid']) != 0) {
					setHostGroupInternal($orig_config['discovery_groupid'], ZBX_NOT_INTERNAL_GROUP);
					setHostGroupInternal($val['groupid'], ZBX_INTERNAL_GROUP);
				}
			}
		}
		if (!is_null($val = get_request('alert_usrgrpid'))) {
			if (0 == $val) {
				$val = _('None');
			}
			else {
				$val = DBfetch(DBselect('SELECT u.name FROM usrgrp u WHERE u.usrgrpid='.$val));
				$val = $val['name'];
			}

			$msg[] = _('User group for database down message.').' ['.$val.']';
		}

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, implode('; ', $msg));
	}

	DBend($result);
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.other.php', 'redirect(this.options[this.selectedIndex].value);');
$cmbConf->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeper'),
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

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('OTHER CONFIGURATION PARAMETERS'), $form);

$data = array();
$data['form_refresh'] = get_request('form_refresh', 0);

if ($data['form_refresh']) {
	$data['config']['discovery_groupid'] = get_request('discovery_groupid');
	$data['config']['alert_usrgrpid'] = get_request('alert_usrgrpid');
	$data['config']['refresh_unsupported'] = get_request('refresh_unsupported');
	$data['config']['snmptrap_logging'] = get_request('snmptrap_logging');
}
else {
	$data['config'] = select_config(false);
}

$data['discovery_groups'] = API::HostGroup()->get(array(
	'sortfield' => 'name',
	'editable' => true,
	'output' => API_OUTPUT_EXTEND
));
$data['alert_usrgrps'] = DBfetchArray(DBselect('SELECT u.usrgrpid,u.name FROM usrgrp u WHERE '.DBin_node('u.usrgrpid').' ORDER BY u.name'));

$otherForm = new CView('administration.general.other.edit', $data);
$cnf_wdgt->addItem($otherForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
