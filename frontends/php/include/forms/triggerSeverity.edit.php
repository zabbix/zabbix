<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
$severityTab = new CFormList('scriptsTab');

$severityForm = new CForm();
$severityForm->setName('triggerSeverity');
$severityForm->addVar('form', get_request('form', 1));
$severityForm->addVar('form_refresh', get_request('form_refresh', 0) + 1);

if(get_request('form_refresh', false)){
	$config = array(
		'severity_name_0' => get_request('severity_name_0', _('Not classified')),
		'severity_color_0' => get_request('severity_color_0', ''),
		'severity_name_1' => get_request('severity_name_1', _('Information')),
		'severity_color_1' => get_request('severity_color_1', ''),
		'severity_name_2' => get_request('severity_name_2', _('Warning')),
		'severity_color_2' => get_request('severity_color_2', ''),
		'severity_name_3' => get_request('severity_name_3', _('Average')),
		'severity_color_3' => get_request('severity_color_3', ''),
		'severity_name_4' => get_request('severity_name_4', _('High')),
		'severity_color_4' => get_request('severity_color_4', ''),
		'severity_name_5' => get_request('severity_name_5', _('Disaster')),
		'severity_color_5' => get_request('severity_color_5', ''),
	);
}
else{
	$config = select_config(false);
}

for($i=0; $i<TRIGGER_SEVERITY_COUNT; $i++){
	$severityNameTB = new CTextBox('severity_name_'.$i, $config['severity_name_'.$i]);
	$severityNameTB->setAttribute('maxlength', 32);
	$severityColorTB = new CColor('severity_color_'.$i, $config['severity_color_'.$i]);
	$severityTab->addRow(_s('Severity %s', $i), array($severityNameTB, SPACE._('Colour').SPACE, $severityColorTB));
}

$severityView = new CTabView();
$severityView->addTab('severities', _('Trigger severities'), $severityTab);
$severityForm->addItem($severityView);


// Footer
$footer = makeFormFooter(array(new CSubmit('save', _('Save'))));
$severityForm->addItem($footer);


return $severityForm;
?>
