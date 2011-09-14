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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

$houseKeeperTab = new CFormList('scriptsTab');
$houseKeeperTab->addRow(_('Do not keep actions older than (in days)'), new CNumericBox('alert_history', $this->data['config']['alert_history'], 5));
$houseKeeperTab->addRow(_('Do not keep events older than (in days)'), new CNumericBox('event_history', $this->data['config']['event_history'], 5));

$houseKeeperView = new CTabView();
$houseKeeperView->addTab('houseKeeper', _('Housekeeper'), $houseKeeperTab);

$houseKeeperForm = new CForm();
$houseKeeperForm->setName('houseKeeperForm');
//$houseKeeperForm->setHelp('web.config.housekeeper.php');
$houseKeeperForm->addVar('form', $this->data['form']);
$houseKeeperForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$houseKeeperForm->addVar('config', get_request('config', 0));
$houseKeeperForm->addItem($houseKeeperView);
$houseKeeperForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save')))));

return $houseKeeperForm;
?>
