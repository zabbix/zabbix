<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
include('include/views/js/administration.general.triggerSeverity.js.php');

$severityTab = new CFormList('scriptsTab');

$headerDiv = new CDiv(_('Custom severity'), 'inlineblock');
$headerDiv->addStyle('width: 16.3em; margin-left: 3px; zoom:1; *display: inline;');
$severityTab->addRow(SPACE, array($headerDiv, _('Colour')));

$severityNameTB0 = new CTextBox('severity_name_0', $this->data['config']['severity_name_0']);
$severityNameTB0->addStyle('width: 15em;');
$severityNameTB0->setAttribute('maxlength', 32);
$severityColorTB0 = new CColor('severity_color_0', $this->data['config']['severity_color_0']);
$severityTab->addRow(_('Not classified'), array($severityNameTB0, SPACE, $severityColorTB0));

$severityNameTB1 = new CTextBox('severity_name_1', $this->data['config']['severity_name_1']);
$severityNameTB1->addStyle('width: 15em;');
$severityNameTB1->setAttribute('maxlength', 32);
$severityColorTB1 = new CColor('severity_color_1', $this->data['config']['severity_color_1']);
$severityTab->addRow(_('Information'), array($severityNameTB1, SPACE, $severityColorTB1));

$severityNameTB2 = new CTextBox('severity_name_2', $this->data['config']['severity_name_2']);
$severityNameTB2->addStyle('width: 15em;');
$severityNameTB2->setAttribute('maxlength', 32);
$severityColorTB2 = new CColor('severity_color_2', $this->data['config']['severity_color_2']);
$severityTab->addRow(_('Warning'), array($severityNameTB2, SPACE, $severityColorTB2));

$severityNameTB3 = new CTextBox('severity_name_3', $this->data['config']['severity_name_3']);
$severityNameTB3->addStyle('width: 15em;');
$severityNameTB3->setAttribute('maxlength', 32);
$severityColorTB3 = new CColor('severity_color_3', $this->data['config']['severity_color_3']);
$severityTab->addRow(_('Average'), array($severityNameTB3, SPACE, $severityColorTB3));

$severityNameTB4 = new CTextBox('severity_name_4', $this->data['config']['severity_name_4']);
$severityNameTB4->addStyle('width: 15em;');
$severityNameTB4->setAttribute('maxlength', 32);
$severityColorTB4 = new CColor('severity_color_4', $this->data['config']['severity_color_4']);
$severityTab->addRow(_('High'), array($severityNameTB4, SPACE, $severityColorTB4));

$severityNameTB5 = new CTextBox('severity_name_5', $this->data['config']['severity_name_5']);
$severityNameTB5->addStyle('width: 15em;');
$severityNameTB5->setAttribute('maxlength', 32);
$severityColorTB5 = new CColor('severity_color_5', $this->data['config']['severity_color_5']);
$severityTab->addRow(_('Disaster'), array($severityNameTB5, SPACE, $severityColorTB5));

$severityTab->addRow(SPACE);
$severityTab->addInfo(_('Custom severity names affect all locales and require manual translation!'));

$severityView = new CTabView();
$severityView->addTab('severities', _('Trigger severities'), $severityTab);

$severityForm = new CForm();
$severityForm->setName('triggerSeverity');
$severityForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$severityForm->addItem($severityView);
$severityForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), new CButton('resetDefaults', _('Reset defaults'))));

return $severityForm;
?>
