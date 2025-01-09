<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CPartial $this
 * @var array $data
 */

$header = (new CDiv())
	->addClass('sidebar-header')
	->addItem(
		(new CLink(
			[
				makeLogo(LOGO_TYPE_SIDEBAR),
				makeLogo(LOGO_TYPE_SIDEBAR_COMPACT)
			],
			CMenuHelper::getFirstUrl()
		))->addClass(ZBX_STYLE_LOGO)
	)
	->addItem(
		(new CDiv([
			(new CButtonIcon(ZBX_ICON_CHEVRON_DOUBLE_LEFT, _('Collapse sidebar')))
				->addClass('js-sidebar-mode')
				->addClass('button-compact'),
			(new CButtonIcon(ZBX_ICON_CHEVRON_DOUBLE_RIGHT, _('Expand sidebar')))
				->addClass('js-sidebar-mode')
				->addClass('button-expand'),
			(new CButtonIcon(ZBX_ICON_COLLAPSE, _('Hide sidebar')))
				->addClass('js-sidebar-mode')
				->addClass('button-hide'),
			(new CButtonIcon(ZBX_ICON_EXPAND, _('Show sidebar')))
				->addClass('js-sidebar-mode')
				->addClass('button-show')
		]))->addClass('sidebar-header-buttons')
	);

$server_name = ($data['server_name'] !== '')
	? (new CDiv($data['server_name']))->addClass(ZBX_STYLE_SERVER_NAME)
	: null;

$search_icon = (new CButtonIcon(ZBX_ICON_SEARCH, _('Search')))
	->addClass('js-search')
	->setAttribute('type', 'submit');

if (getRequest('search', '') === '') {
	$search_icon->setAttribute('disabled', '');
}

$search = (new CForm('get', 'zabbix.php'))
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
