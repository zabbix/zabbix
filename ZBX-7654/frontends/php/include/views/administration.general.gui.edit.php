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

$widget = (new CWidget())
	->setTitle(_('GUI'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.gui.php')))
	);

$guiTab = (new CFormList())
	->addRow(_('Default theme'), new CComboBox('default_theme', $data['default_theme'], null, Z::getThemes()))
	->addRow(_('Dropdown first entry'), [
		new CComboBox('dropdown_first_entry', $data['dropdown_first_entry'], null, [
			ZBX_DROPDOWN_FIRST_NONE => _('None'),
			ZBX_DROPDOWN_FIRST_ALL => _('All')
		]),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CLabel([
			(new CCheckBox('dropdown_first_remember'))->setChecked($data['dropdown_first_remember'] == 1),
			_('remember selected')
		])
	])
	->addRow(_('Search/Filter elements limit'),
		(new CNumericBox('search_limit', $data['search_limit'], 6))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Max count of elements to show inside table cell'),
		(new CNumericBox('max_in_table', $data['max_in_table'], 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Enable event acknowledges'),
		(new CCheckBox('event_ack_enable'))->setChecked($data['event_ack_enable'] == 1)
	)
	->addRow(_('Show events not older than (in days)'),
		(new CTextBox('event_expire', $data['event_expire']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
	->addRow(_('Max count of events per trigger to show'),
		(new CTextBox('event_show_max', $data['event_show_max']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
	->addRow(_('Show warning if Zabbix server is down'),
		(new CCheckBox('server_check_interval', SERVER_CHECK_INTERVAL))
			->setChecked($data['server_check_interval'] == SERVER_CHECK_INTERVAL)
	);

$guiView = (new CTabView())
	->addTab('gui', _('GUI'), $guiTab)
	->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$guiForm = (new CForm())
	->addItem($guiView);

$widget->addItem($guiForm);

return $widget;
