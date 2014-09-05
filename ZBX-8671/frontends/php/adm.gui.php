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

$page['title'] = _('Configuration of GUI');
$page['file'] = 'adm.gui.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'default_theme'           => array(T_ZBX_STR, O_OPT, null,        IN('"'.implode('","', $themes).'"'),
		'isset({update})'),
	'event_ack_enable'        => array(T_ZBX_INT, O_OPT, null,        IN('1'),            null),
	'event_expire'            => array(T_ZBX_INT, O_OPT, null,        BETWEEN(1, 99999),  'isset({update})', _('Show events not older than (in days)')),
	'event_show_max'          => array(T_ZBX_INT, O_OPT, null,        BETWEEN(1, 99999),  'isset({update})', _('Max count of events per trigger to show')),
	'dropdown_first_entry'    => array(T_ZBX_INT, O_OPT, null,        IN('0,1,2'),        'isset({update})'),
	'dropdown_first_remember' => array(T_ZBX_INT, O_OPT, null,        IN('1'),            null),
	'max_in_table'            => array(T_ZBX_INT, O_OPT, null,        BETWEEN(1, 99999),  'isset({update})', _('Max count of elements to show inside table cell')),
	'search_limit'            => array(T_ZBX_INT, O_OPT, null,        BETWEEN(1, 999999), 'isset({update})', _('Search/Filter elements limit')),
	'server_check_interval'   => array(T_ZBX_INT, O_OPT, null,        null,               null, _('Zabbix server activity check interval')),
	'update'                  => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,               null),
	'form_refresh'            => array(T_ZBX_INT, O_OPT, null,        null,               null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();

	$configs = array(
		'default_theme' => getRequest('default_theme'),
		'event_ack_enable' => (is_null(getRequest('event_ack_enable')) ? 0 : 1),
		'event_expire' => getRequest('event_expire'),
		'event_show_max' => getRequest('event_show_max'),
		'dropdown_first_entry' => getRequest('dropdown_first_entry'),
		'dropdown_first_remember' => (is_null(getRequest('dropdown_first_remember')) ? 0 : 1),
		'max_in_table' => getRequest('max_in_table'),
		'search_limit' => getRequest('search_limit'),
		'server_check_interval' => getRequest('server_check_interval', 0)
	);

	$result = update_config($configs);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));

	if ($result) {
		$msg = array();
		$msg[] = _s('Default theme "%1$s".', getRequest('default_theme'));
		$msg[] = _s('Event acknowledges "%1$s".', getRequest('event_ack_enable'));
		$msg[] = _s('Show events not older than (in days) "%1$s".', getRequest('event_expire'));
		$msg[] = _s('Show events max "%1$s".', getRequest('event_show_max'));
		$msg[] = _s('Dropdown first entry "%1$s".', getRequest('dropdown_first_entry'));
		$msg[] = _s('Dropdown remember selected "%1$s".', getRequest('dropdown_first_remember'));
		$msg[] = _s('Max count of elements to show inside table cell "%1$s".', getRequest('max_in_table'));
		$msg[] = _s('Zabbix server is running check interval "%1$s".', getRequest('server_check_interval'));

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, implode('; ', $msg));
	}

	DBend($result);
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.gui.php', 'redirect(this.options[this.selectedIndex].value);');
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

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF GUI'), $form);

$data = array();
$data['form_refresh'] = getRequest('form_refresh', 0);

if ($data['form_refresh']) {
	$data['config']['default_theme'] = getRequest('default_theme');
	$data['config']['event_ack_enable'] = getRequest('event_ack_enable');
	$data['config']['dropdown_first_entry'] = getRequest('dropdown_first_entry');
	$data['config']['dropdown_first_remember'] = getRequest('dropdown_first_remember');
	$data['config']['search_limit'] = getRequest('search_limit');
	$data['config']['max_in_table'] = getRequest('max_in_table');
	$data['config']['event_expire'] = getRequest('event_expire');
	$data['config']['event_show_max'] = getRequest('event_show_max');
	$data['config']['server_check_enabled'] = getRequest('server_check_enabled');
	$data['config']['server_check_interval'] = getRequest('server_check_interval', 0);
}
else {
	$data['config'] = select_config(false);
}

$guiForm = new CView('administration.general.gui.edit', $data);
$cnf_wdgt->addItem($guiForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
