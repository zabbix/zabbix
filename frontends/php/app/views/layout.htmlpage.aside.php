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


$logo = (new CLink(
	CBrandHelper::getLogo(),
	(new CUrl('zabbix.php'))
		->setArgument('action', 'dashboard.view')
		->getUrl()
	))->addClass(ZBX_STYLE_LOGO);

$search = (new CForm('get', 'zabbix.php'))
	->cleanItems()
	->addClass(ZBX_STYLE_FORM_SEARCH)
	->setAttribute('role', 'search')
	->addItem([
		(new CVar('action', 'search'))->removeId(),
		(new CTextBox('search', getRequest('search', ''), false, 255))
			->setAttribute('autocomplete', 'off')
			->addClass(ZBX_STYLE_SEARCH)
			->setAttribute('aria-label', _('type here to search')),
		(new CDiv())->addClass('search-icon')
	]);

(new CTag('aside', true))
	->addClass('sidebar')
	->addClass(CView::getSidebarMode() == ZBX_SIDEBAR_VIEW_MODE_COMPACT ? 'is-compact' : null)
	->addClass(CView::getSidebarMode() == ZBX_SIDEBAR_VIEW_MODE_HIDDEN ? 'is-hidden' : null)
	->addItem(
		(new CDiv([
			$logo,
			(new CButton(null, _('Collapse sidebar')))
				->addClass('button-sidebar-compact js-sidebar-mode')
				->setAttribute('title', _('Collapse sidebar')),
			(new CButton(null, _('Expand sidebar')))
				->addClass('button-sidebar-expand js-sidebar-mode')
				->setAttribute('title', _('Expand sidebar')),
			(new CButton(null, _('Hide sidebar')))
				->addClass('button-sidebar-hide js-sidebar-mode')
				->setAttribute('title', _('Hide sidebar')),
			(new CButton(null, _('Show sidebar')))
				->addClass('button-sidebar-show js-sidebar-mode')
				->setAttribute('title', _('Show sidebar')),
			$search
		]))->addClass('sidebar-header')
	)
	->addItem(
		(new CDiv([
			(new CTag('nav', true, APP::Component()->get('menu.main')->addClass('menu-main')))
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('Main navigation')),
			(new CTag('nav', true, APP::Component()->get('menu.user')->addClass('menu-user')))
				->addClass('navigation-user')
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('User menu'))
		]))
			->addClass('sidebar-navigation')
			->addClass('scrollable')
	)
	->show();
