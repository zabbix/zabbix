<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * @var CView $this
 */

$widget = (new CWidget())
	->setTitle(_('GUI'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$gui_tab = (new CFormList())
	->addRow(_('Default theme'),
		(new CComboBox('default_theme', $data['default_theme'], null, APP::getThemes()))
			->setAttribute('autofocus', 'autofocus')
	)
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
			->setUncheckedValue('0')
			->setChecked($data['server_check_interval'] == SERVER_CHECK_INTERVAL)
	)
	->addRow((new CLabel(_('Working time'), 'work_period'))->setAsteriskMark(),
	(new CTextBox('work_period', $data['work_period']))
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		->setAriaRequired()
	)
	->addRow(_('Show technical errors'),
		(new CCheckBox('show_technical_errors'))
			->setUncheckedValue('0')
			->setChecked($data['show_technical_errors'] == 1)
	)
	->addRow(
		(new CLabel(_('Max history display period'), 'history_period'))->setAsteriskMark(),
		(new CTextBox('history_period', $data['history_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Time filter default period'), 'period_default'))->setAsteriskMark(),
		(new CTextBox('period_default', $data['period_default']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Max period'), 'max_period'))->setAsteriskMark(),
		(new CTextBox('max_period', $data['max_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	);

$gui_view = (new CTabView())
	->addTab('gui', _('GUI'), $gui_tab)
	->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$form = (new CForm())
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'gui.update')
		->getUrl()
	)
	->addItem($gui_view);

$widget
	->addItem($form)
	->show();
