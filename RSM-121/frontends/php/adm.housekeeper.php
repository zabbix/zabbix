<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

$page['title'] = _('Configuration of housekeeper');
$page['file'] = 'adm.housekeeper.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'alert_history' => array(T_ZBX_INT, O_NO, null, BETWEEN(0, 65535), 'isset({save})',
		_('Do not keep actions older than (in days)')),
	'event_history' => array(T_ZBX_INT, O_NO, null,	BETWEEN(0, 65535), 'isset({save})',
		_('Do not keep events older than (in days)')),
	'save' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT,	null,			null,	null)
);
check_fields($fields);

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	DBstart();

	$configs = array(
		'event_history' => get_request('event_history'),
		'alert_history' => get_request('alert_history')
	);
	$result = update_config($configs);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));

	if ($result) {
		$msg = array();
		$msg[] = _s('Do not keep events older than (in days) "%1$s".', get_request('event_history'));
		$msg[] = _s('Do not keep actions older than (in days) "%1$s".', get_request('alert_history'));

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, implode('; ', $msg));
	}

	DBend($result);
}


$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.housekeeper.php', 'redirect(this.options[this.selectedIndex].value);');
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
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF HOUSEKEEPER'), $form);


$data = array();
$data['form_refresh'] = get_request('form_refresh', 0);

if ($data['form_refresh']) {
	$data['config']['alert_history'] = get_request('alert_history');
	$data['config']['event_history'] = get_request('event_history');
}
else {
	$data['config'] = select_config(false);
}

$houseKeeperForm = new CView('administration.general.housekeeper.edit', $data);
$cnf_wdgt->addItem($houseKeeperForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
