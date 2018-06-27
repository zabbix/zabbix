<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.gui.php'))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$guiTab = (new CFormList())
	->addRow(_('Default theme'),
		(new CComboBox('default_theme', $data['default_theme'], null, Z::getThemes()))
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Dropdown first entry'), [
		new CComboBox('dropdown_first_entry', $data['dropdown_first_entry'], null, [
			ZBX_DROPDOWN_FIRST_NONE => _('None'),
			ZBX_DROPDOWN_FIRST_ALL => _('All')
		]),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('dropdown_first_remember'))
			->setLabel(_('remember selected'))
			->setChecked($data['dropdown_first_remember'] == 1)
	])
	->addRow((new CLabel(_('Limit for search and filter results'), 'search_limit'))->setAsteriskMark(),
		(new CNumericBox('search_limit', $data['search_limit'], 6))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Max count of elements to show inside table cell'), 'max_in_table'))->setAsteriskMark(),
		(new CNumericBox('max_in_table', $data['max_in_table'], 5))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Show warning if Zabbix server is down'),
		(new CCheckBox('server_check_interval', SERVER_CHECK_INTERVAL))
			->setChecked($data['server_check_interval'] == SERVER_CHECK_INTERVAL)
	);

$guiView = (new CTabView())
	->addTab('gui', _('GUI'), $guiTab)
	->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$guiForm = (new CForm())
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem($guiView);

$widget->addItem($guiForm);

return $widget;
