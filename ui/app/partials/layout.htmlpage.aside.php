<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var CPartial $this
 */

$header = (new CDiv())
	->addClass('sidebar-header')
	->addItem(
		(new CLink(
			[
				makeLogo(LOGO_TYPE_SIDEBAR)->addClass('sidebar-logo'),
				makeLogo(LOGO_TYPE_SIDEBAR_COMPACT)->addClass('sidebar-logo-compact')
			],
			CMenuHelper::getFirstUrl()
		))->addClass(ZBX_STYLE_LOGO)
	)
	->addItem(
		(new CDiv([
			(new CButton(null, _('Collapse sidebar')))
				->addClass('button-compact js-sidebar-mode')
				->setAttribute('title', _('Collapse sidebar')),
			(new CButton(null, _('Expand sidebar')))
				->addClass('button-expand js-sidebar-mode')
				->setAttribute('title', _('Expand sidebar')),
			(new CButton(null, _('Hide sidebar')))
				->addClass('button-hide js-sidebar-mode')
				->setAttribute('title', _('Hide sidebar')),
			(new CButton(null, _('Show sidebar')))
				->addClass('button-show js-sidebar-mode')
				->setAttribute('title', _('Show sidebar'))
		]))->addClass('sidebar-header-buttons')
	);

$server_name = ($data['server_name'] !== '')
	? (new CDiv($data['server_name']))->addClass(ZBX_STYLE_SERVER_NAME)
	: null;

$search_icon = (new CSubmitButton(null))
	->addClass('search-icon')
	->setTitle(_('Search'));

if (getRequest('search', '') === '') {
	$search_icon->setAttribute('disabled', '');
}

$search = (new CForm('get', 'zabbix.php'))
	->cleanItems()
	->addClass(ZBX_STYLE_FORM_SEARCH)
	->setAttribute('role', 'search')
	->addItem([
		(new CVar('action', 'search'))->removeId(),
		(new CTextBox('search', getRequest('search', ''), false, 255))
			->addClass(ZBX_STYLE_SEARCH)
			->setAttribute('aria-label', _('type here to search'))
			->disableAutocomplete(),
		$search_icon
	]);

(new CTag('aside', true))
	->addClass('sidebar')
	->addClass(CViewHelper::loadSidebarMode() == ZBX_SIDEBAR_VIEW_MODE_COMPACT ? 'is-compact' : null)
	->addClass(CViewHelper::loadSidebarMode() == ZBX_SIDEBAR_VIEW_MODE_HIDDEN ? 'is-hidden' : null)

	->addItem([$header, $server_name, $search])
	->addItem(
		(new CDiv([
			(new CTag('nav', true, APP::Component()->get('menu.main')->addClass('menu-main')))
				->addClass('nav-main')
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('Main navigation')),
			(new CTag('nav', true, APP::Component()->get('menu.user')->addClass('menu-user')))
				->addClass('nav-user')
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('User menu'))
		]))
			->addClass('sidebar-nav')
			->addClass('scrollable')
			// Do not allow focusing scrollable container.
			->setAttribute('tabindex', '-1')
	)
	->show();
