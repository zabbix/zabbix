<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	(new CDiv())
		->addClass(ZBX_STYLE_LOGO)
		->addStyle(CBrandHelper::getLogoStyle()),
	(new CUrl('zabbix.php'))
		->setArgument('action', 'dashboard.view')
		->getUrl()
	))->addClass(ZBX_STYLE_HEADER_LOGO);

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

$menu_main = (new CList())->addClass(ZBX_STYLE_TOP_NAV);

foreach ($data['menu']['main_menu'] as $menu_item) {
	$menu_main->addItem($menu_item);
}

$user_navigation = (new CList())
	->addItem(CBrandHelper::isRebranded()
		? null
		: (new CListItem(
			(new CLink(_('Support'), $data['support_url']))
				->addClass(ZBX_STYLE_TOP_NAV_SUPPORT)
				->addClass('menu-user-icon-support')
				->setAttribute('target', '_blank')
				->setTitle(_('Zabbix Technical Support'))
		))
	)
	->addItem(CBrandHelper::isRebranded()
		? null
		: (new CListItem(
			(new CLink('Share', 'https://share.zabbix.com/'))
				->addClass(ZBX_STYLE_TOP_NAV_ZBBSHARE)
				->addClass('menu-user-icon-share')
				->setAttribute('target', '_blank')
				->setTitle(_('Zabbix Share'))
		))
	)
	->addItem((new CLink(_('Help'), CBrandHelper::getHelpUrl()))
		->addClass(ZBX_STYLE_TOP_NAV_HELP)
		->addClass('menu-user-icon-help')
		->setAttribute('target', '_blank')
		->setTitle(_('Help'))
	)
	->addItem(
		$data['user']['is_guest']
			? (new CSpan())
				->addClass(ZBX_STYLE_TOP_NAV_GUEST)
				->addClass('menu-user-icon-guest')
				->setTitle(getUserFullname($data['user']))
			: (new CLink(_('User settings'), (new CUrl('zabbix.php'))
				->setArgument('action', 'userprofile.edit')
					->getUrl()
				))
				->addClass(ZBX_STYLE_TOP_NAV_PROFILE)
				->addClass('menu-user-icon-profile')
				->setTitle(getUserFullname($data['user']))
	)
	->addItem(
		(new CLink(_('Sign out'), 'javascript:;'))
		->addClass(ZBX_STYLE_TOP_NAV_SIGNOUT)
			->addClass('menu-user-icon-signout')
			->setTitle(_('Sign out'))
			->onClick('ZABBIX.logout()')
	);

(new CTag('aside', true))
	->addClass('sidebar')
	->addItem(
		(new CDiv())
			->addClass('sidebar-header')
			->addItem($logo)
			->addItem($search)
			->addItem((new CButton(null, _('Collapse sidebar')))
				->addClass('button-compact')
				->setAttribute('title', _('Collapse sidebar'))
			)
			->addItem((new CButton(null, _('Expand sidebar')))
				->addClass('button-expand')
				->setAttribute('title', _('Expand sidebar'))
			)
			->addItem((new CButton(null, _('Hide sidebar')))
				->addClass('button-sidebar-hide')
				->setAttribute('title', _('Hide sidebar'))
			)
			->addItem((new CButton(null, _('Show sidebar')))
				->addClass('button-sidebar-show')
				->setAttribute('title', _('Show sidebar'))
			)
	)
	->addItem((new CDiv())
		->addClass('sidebar-navigation')
		->addClass('scrollable')
		->addItem((new CTag('nav', true, $menu_main))
			->setId('mmenu')
			->addClass(ZBX_STYLE_TOP_NAV_CONTAINER)
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _('Main navigation'))
		)
		->addItem((new CTag('nav', true, $user_navigation))
			->addClass(ZBX_STYLE_TOP_NAV_ICONS)
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _('User menu'))
		)
	)
	->show();
