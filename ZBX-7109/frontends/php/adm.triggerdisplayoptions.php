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

$page['title'] = _('Configuration of trigger displaying options');
$page['file'] = 'adm.triggerdisplayoptions.php';

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	// VAR					        TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	'problem_unack_color' =>	array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
	'problem_ack_color' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
	'ok_unack_color' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
	'ok_ack_color' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
	'problem_unack_style' =>	array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
	'problem_ack_style' =>		array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
	'ok_unack_style' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
	'ok_ack_style' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
	'ok_period' =>				array(T_ZBX_INT, O_OPT,	null,	null,		'isset({save})'),
	'blink_period' =>			array(T_ZBX_INT, O_OPT,	null,	null,		'isset({save})'),

	'save'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT,	null,	null,	null)
);
check_fields($fields);


if (isset($_REQUEST['save'])) {
	$configs = array(
		'ok_period' => get_request('ok_period'),
		'blink_period' => get_request('blink_period'),
		'problem_unack_color' => get_request('problem_unack_color'),
		'problem_ack_color' => get_request('problem_ack_color'),
		'ok_unack_color' => get_request('ok_unack_color'),
		'ok_ack_color' => get_request('ok_ack_color'),
		'problem_unack_style' => get_request('problem_unack_style', 0),
		'problem_ack_style' => get_request('problem_ack_style', 0),
		'ok_unack_style' => get_request('ok_unack_style', 0),
		'ok_ack_style' => get_request('ok_ack_style', 0)
	);

	$result = update_config($configs);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}


$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.triggerdisplayoptions.php', 'redirect(this.options[this.selectedIndex].value);');
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
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF ZABBIX'), $form);

$data = array();
$data['form_refresh'] = get_request('form_refresh', 0);

// form has been submitted
if ($data['form_refresh']) {
	$data['ok_period'] = get_request('ok_period');
	$data['blink_period'] = get_request('blink_period');
	$data['problem_unack_color'] = get_request('problem_unack_color');
	$data['problem_ack_color'] = get_request('problem_ack_color');
	$data['ok_unack_color'] = get_request('ok_unack_color');
	$data['ok_ack_color'] = get_request('ok_ack_color');
	$data['problem_unack_style'] = get_request('problem_unack_style');
	$data['problem_ack_style'] = get_request('problem_ack_style');
	$data['ok_unack_style'] = get_request('ok_unack_style');
	$data['ok_ack_style'] = get_request('ok_ack_style');
}
else {
	$config = select_config(false);
	$data['ok_period'] = $config['ok_period'];
	$data['blink_period'] = $config['blink_period'];
	$data['problem_unack_color'] = $config['problem_unack_color'];
	$data['problem_ack_color'] = $config['problem_ack_color'];
	$data['ok_unack_color'] = $config['ok_unack_color'];
	$data['ok_ack_color'] = $config['ok_ack_color'];
	$data['problem_unack_style'] = $config['problem_unack_style'];
	$data['problem_ack_style'] = $config['problem_ack_style'];
	$data['ok_unack_style'] = $config['ok_unack_style'];
	$data['ok_ack_style'] = $config['ok_ack_style'];
}

$triggerDisplayingForm = new CView('administration.general.triggerDisplayOptions.edit', $data);
$cnf_wdgt->addItem($triggerDisplayingForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
