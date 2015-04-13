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

$page['title'] = _('Configuration of working time');
$page['file'] = 'adm.workingtime.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'work_period' =>	array(T_ZBX_TP, O_OPT, null, null, 'isset({update})', _('Working time')),
	// actions
	'update' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();
	$result = update_config(array('work_period' => getRequest('work_period')));
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.workingtime.php', 'redirect(this.options[this.selectedIndex].value);',
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
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF WORKING TIME'), $form);

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = array('work_period' => getRequest('work_period', $config['work_period']));
}
else {
	$data = array('work_period' => $config['work_period']);
}

$workingTimeForm = new CView('administration.general.workingtime.edit', $data);
$cnf_wdgt->addItem($workingTimeForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
