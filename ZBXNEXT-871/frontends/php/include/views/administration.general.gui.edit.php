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

$config = $this->data['form_refresh'] ? $data['config'] : select_config(false);

$comboTheme = new CComboBox('default_theme', $config['default_theme']);
$comboTheme->addItem('css_ob.css', S_ORIGINAL_BLUE);
$comboTheme->addItem('css_bb.css', S_BLACK_AND_BLUE);
$comboTheme->addItem('css_od.css', S_DARK_ORANGE);

$comboDdFirstEntry = new CComboBox('dropdown_first_entry', $config['dropdown_first_entry']);
$comboDdFirstEntry->addItem(ZBX_DROPDOWN_FIRST_NONE, S_NONE);
$comboDdFirstEntry->addItem(ZBX_DROPDOWN_FIRST_ALL, S_ALL_S);
$checkDdFirstRemember = new CCheckBox('dropdown_first_remember', $config['dropdown_first_remember'], null, 1);

$guiTab = new CFormList('scriptsTab');
$guiTab->addRow(S_DEFAULT_THEME, array($comboTheme));
$guiTab->addRow(S_DROPDOWN_FIRST_ENTRY, array($comboDdFirstEntry, $checkDdFirstRemember, S_DROPDOWN_REMEMBER_SELECTED));
$guiTab->addRow(S_SEARCH_LIMIT, new CNumericBox('search_limit', $config['search_limit'], 6));
$guiTab->addRow(S_MAX_IN_TABLE, new CNumericBox('max_in_table', $config['max_in_table'], 5));
$guiTab->addRow(S_EVENT_ACKNOWLEDGES, new CCheckBox('event_ack_enable', $config['event_ack_enable'], null, 1));
$guiTab->addRow(S_SHOW_EVENTS_NOT_OLDER.SPACE.'('.S_DAYS.')', new CTextBox('event_expire',$config['event_expire'], 5));
$guiTab->addRow(S_MAX_COUNT_OF_EVENTS, new CTextBox('event_show_max',$config['event_show_max'], 5));

$guiView = new CTabView();
$guiView->addTab('severities', _('GUI'), $guiTab);

$guiForm = new CForm();
$guiForm->setName('guiForm');
$guiForm->addVar('form', $data['form']);
$guiForm->addVar('form_refresh', $data['form_refresh'] + 1);
$guiForm->addVar('config', get_request('config', 8));
$guiForm->addItem($guiView);
$guiForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save')))));

return $guiForm;
?>
