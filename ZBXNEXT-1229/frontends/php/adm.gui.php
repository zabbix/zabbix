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

$page['title'] = _('Configuration of GUI');
$page['file'] = 'adm.gui.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());

$fields = array(
	'default_theme'				=> array(T_ZBX_STR, O_OPT, null, IN('"'.implode('","', $themes).'"'), 'isset({update})',
		_('Default theme')
	),
	'dropdown_first_entry'		=> array(T_ZBX_INT, O_OPT, null, IN('0,1,2'), 'isset({update})',
		_('Dropdown first entry')
	),
	'dropdown_first_remember'	=> array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('remember selected')),
	'search_limit'				=> array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 999999), 'isset({update})',
		_('Search/Filter elements limit')
	),
	'max_in_table'				=> array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), 'isset({update})',
		_('Max count of elements to show inside table cell')
	),
	'event_ack_enable'			=> array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Enable event acknowledges')),
	'event_expire'				=> array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), 'isset({update})',
		_('Show events not older than (in days)')
	),
	'event_show_max'			=> array(T_ZBX_INT, O_OPT, null, BETWEEN(1, 99999), 'isset({update})',
		_('Max count of events per trigger to show')
	),
	'server_check_interval'		=> array(T_ZBX_INT, O_OPT, null, IN(SERVER_CHECK_INTERVAL), null,
		_('Show warning if Zabbix server is down')
	),
	// actions
	'update'					=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh'				=> array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();
	$result = update_config(array(
		'default_theme' => getRequest('default_theme'),
		'dropdown_first_entry' => getRequest('dropdown_first_entry'),
		'dropdown_first_remember' => getRequest('dropdown_first_remember', 0),
		'search_limit' => getRequest('search_limit'),
		'max_in_table' => getRequest('max_in_table'),
		'event_ack_enable' => getRequest('event_ack_enable', 0),
		'event_expire' => getRequest('event_expire'),
		'event_show_max' => getRequest('event_show_max'),
		'server_check_interval' => getRequest('server_check_interval', 0)
	));
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.gui.php', 'redirect(this.options[this.selectedIndex].value);',
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
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF GUI'), $form);

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = array(
		'default_theme' => getRequest('default_theme', $config['default_theme']),
		'dropdown_first_entry' => getRequest('dropdown_first_entry', $config['dropdown_first_entry']),
		'dropdown_first_remember' => getRequest('dropdown_first_remember', 0),
		'search_limit' => getRequest('search_limit', $config['search_limit']),
		'max_in_table' => getRequest('max_in_table', $config['max_in_table']),
		'event_ack_enable' => getRequest('event_ack_enable', 0),
		'event_expire' => getRequest('event_expire', $config['event_expire']),
		'event_show_max' => getRequest('event_show_max', $config['event_show_max']),
		'server_check_interval' => getRequest('server_check_interval', 0)
	);
}
else {
	$data = array(
		'default_theme' => $config['default_theme'],
		'dropdown_first_entry' => $config['dropdown_first_entry'],
		'dropdown_first_remember' => $config['dropdown_first_remember'],
		'search_limit' => $config['search_limit'],
		'max_in_table' => $config['max_in_table'],
		'event_ack_enable' => $config['event_ack_enable'],
		'event_expire' => $config['event_expire'],
		'event_show_max' => $config['event_show_max'],
		'server_check_interval' => $config['server_check_interval']
	);
}

$guiForm = new CView('administration.general.gui.edit', $data);
$cnf_wdgt->addItem($guiForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
