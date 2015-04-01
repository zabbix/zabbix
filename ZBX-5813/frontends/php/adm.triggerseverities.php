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

$page['title'] = _('Configuration of trigger severities');
$page['file'] = 'adm.triggerseverities.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'severity_name_0' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('Not classified')),
	'severity_color_0' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Not classified')),
	'severity_name_1' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('Information')),
	'severity_color_1' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Information')),
	'severity_name_2' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('Warning')),
	'severity_color_2' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Warning')),
	'severity_name_3' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('Average')),
	'severity_color_3' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Average')),
	'severity_name_4' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('High')),
	'severity_color_4' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('High')),
	'severity_name_5' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({update})', _('Disaster')),
	'severity_color_5' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Disaster')),
	// actions
	'update' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();
	$result = update_config(array(
		'severity_name_0' => getRequest('severity_name_0'),
		'severity_color_0' => getRequest('severity_color_0'),
		'severity_name_1' => getRequest('severity_name_1'),
		'severity_color_1' => getRequest('severity_color_1'),
		'severity_name_2' => getRequest('severity_name_2'),
		'severity_color_2' => getRequest('severity_color_2'),
		'severity_name_3' => getRequest('severity_name_3'),
		'severity_color_3' => getRequest('severity_color_3'),
		'severity_name_4' => getRequest('severity_name_4'),
		'severity_color_4' => getRequest('severity_color_4'),
		'severity_name_5' => getRequest('severity_name_5'),
		'severity_color_5' => getRequest('severity_color_5')
	));
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.triggerseverities.php',
	'redirect(this.options[this.selectedIndex].value);',
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
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF TRIGGER SEVERITIES'), $form);

$config = select_config();

if (hasRequest('form_refresh')) {
	$data = array(
		'severity_name_0' => getRequest('severity_name_0', $config['severity_name_0']),
		'severity_color_0' => getRequest('severity_color_0', $config['severity_color_0']),
		'severity_name_1' => getRequest('severity_name_1', $config['severity_name_1']),
		'severity_color_1' => getRequest('severity_color_1', $config['severity_color_1']),
		'severity_name_2' => getRequest('severity_name_2', $config['severity_name_2']),
		'severity_color_2' => getRequest('severity_color_2', $config['severity_color_2']),
		'severity_name_3' => getRequest('severity_name_3', $config['severity_name_3']),
		'severity_color_3' => getRequest('severity_color_3', $config['severity_color_3']),
		'severity_name_4' => getRequest('severity_name_4', $config['severity_name_4']),
		'severity_color_4' => getRequest('severity_color_4', $config['severity_color_4']),
		'severity_name_5' => getRequest('severity_name_5', $config['severity_name_5']),
		'severity_color_5' => getRequest('severity_color_5', $config['severity_color_5'])
	);
}
else {
	$data = array(
		'severity_name_0' => $config['severity_name_0'],
		'severity_color_0' => $config['severity_color_0'],
		'severity_name_1' => $config['severity_name_1'],
		'severity_color_1' => $config['severity_color_1'],
		'severity_name_2' => $config['severity_name_2'],
		'severity_color_2' => $config['severity_color_2'],
		'severity_name_3' => $config['severity_name_3'],
		'severity_color_3' => $config['severity_color_3'],
		'severity_name_4' => $config['severity_name_4'],
		'severity_color_4' => $config['severity_color_4'],
		'severity_name_5' => $config['severity_name_5'],
		'severity_color_5' => $config['severity_color_5']
	);
}

$triggerSeverityForm = new CView('administration.general.triggerSeverity.edit', $data);
$cnf_wdgt->addItem($triggerSeverityForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
