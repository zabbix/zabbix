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


$workingTimeTab = new CFormList('scriptsTab');
$wtTextBox = new CTextBox('work_period', $this->data['config']['work_period'], ZBX_TEXTBOX_STANDARD_SIZE);
$wtTextBox->attr('autofocus', 'autofocus');
$workingTimeTab->addRow(_('Working time'), $wtTextBox);

$workingTimeView = new CTabView();
$workingTimeView->addTab('workingTime', _('Working time'), $workingTimeTab);

$workingTimeForm = new CForm();
$workingTimeForm->setName('workingTimeForm');
$workingTimeForm->addItem($workingTimeView);
$workingTimeForm->addItem(makeFormFooter(new CSubmit('update', _('Update'))));

return $workingTimeForm;
