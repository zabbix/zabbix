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


require_once dirname(__FILE__).'/js/administration.general.gui.php';

$guiTab = new CFormList();
$guiTab->addRow(_('Default theme'), new CComboBox('default_theme', $data['default_theme'], null, Z::getThemes()));
$guiTab->addRow(_('Dropdown first entry'), [
	new CComboBox('dropdown_first_entry', $data['dropdown_first_entry'], null, [
		ZBX_DROPDOWN_FIRST_NONE => _('None'),
		ZBX_DROPDOWN_FIRST_ALL => _('All')
	]),
	new CCheckBox('dropdown_first_remember', $data['dropdown_first_remember'] == 1, null, 1),
	_('remember selected')
]);
$guiTab->addRow(_('Search/Filter elements limit'),
	new CNumericBox('search_limit', $data['search_limit'], 6)
);
$guiTab->addRow(_('Max count of elements to show inside table cell'),
	new CNumericBox('max_in_table', $data['max_in_table'], 5)
);
$guiTab->addRow(_('Enable event acknowledges'),
	new CCheckBox('event_ack_enable', $data['event_ack_enable'] == 1, null, 1)
);
$guiTab->addRow(_('Show events not older than (in days)'),
	new CTextBox('event_expire', $data['event_expire'], 5)
);
$guiTab->addRow(_('Max count of events per trigger to show'),
	new CTextBox('event_show_max', $data['event_show_max'], 5)
);
$guiTab->addRow(_('Show warning if Zabbix server is down'),
	new CCheckBox('server_check_interval', $data['server_check_interval'] == SERVER_CHECK_INTERVAL, null,
		SERVER_CHECK_INTERVAL
	)
);

$guiView = new CTabView();
$guiView->addTab('gui', _('GUI'), $guiTab);

$guiForm = new CForm();
$guiForm->setName('guiForm');

$guiView->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$guiForm->addItem($guiView);

return $guiForm;
