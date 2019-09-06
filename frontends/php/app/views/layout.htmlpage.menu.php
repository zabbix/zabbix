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


$user_navigation = (new CList())
	->addClass(ZBX_STYLE_TOP_NAV_ICONS)
	->addItem(
		(new CForm('get', 'zabbix.php'))
			->cleanItems()
			->addItem([
				(new CVar('action', 'search'))->removeId(),
				(new CTextBox('search', getRequest('search', ''), false, 255))
					->setAttribute('autocomplete', 'off')
					->addClass(ZBX_STYLE_SEARCH)
					->setAttribute('aria-label', _('type here to search')),
				(new CSubmitButton('&nbsp;'))
					->addClass(ZBX_STYLE_BTN_SEARCH)
					->setTitle(_('Search'))
			])
			->setAttribute('role', 'search')
	);

$user_menu = (new CList())
	->setAttribute('role', 'navigation')
	->setAttribute('aria-label', _('User menu'))
	->addItem(CBrandHelper::isRebranded()
		? null
		: (new CListItem(
			(new CLink(_('Support'), $data['support_url']))
				->addClass(ZBX_STYLE_TOP_NAV_SUPPORT)
				->setAttribute('target', '_blank')
				->setTitle(_('Zabbix Technical Support'))
		))->addStyle('padding-left:0')
	)
	->addItem(CBrandHelper::isRebranded()
		? null
		: (new CListItem(
			(new CLink('Share', 'https://share.zabbix.com/'))
				->addClass(ZBX_STYLE_TOP_NAV_ZBBSHARE)
				->setAttribute('target', '_blank')
				->setTitle(_('Zabbix Share'))
		))
	)
	->addItem((new CLink(SPACE, CBrandHelper::getHelpUrl()))
		->addClass(ZBX_STYLE_TOP_NAV_HELP)
		->setAttribute('target', '_blank')
		->setTitle(_('Help'))
	);

if (!$data['user']['is_guest']) {
	$user_menu->addItem(
		(new CLink(SPACE, 'zabbix.php?action=userprofile.edit'))
			->addClass(ZBX_STYLE_TOP_NAV_PROFILE)
			->setTitle(getUserFullname($data['user']))
	);
}

$user_menu->addItem(
	(new CLink(SPACE, 'javascript:;'))
		->addClass(ZBX_STYLE_TOP_NAV_SIGNOUT)
		->setTitle(_('Sign out'))
		->onClick('ZABBIX.logout()')
);

$user_navigation->addItem($user_menu);

// 1st level menu
$top_menu = (new CDiv())
	->addItem(
		(new CLink(
			(new CDiv())
				->addClass(ZBX_STYLE_LOGO)
				->addStyle(CBrandHelper::getLogoStyle()),
			'zabbix.php?action=dashboard.view'
		))
			->addClass(ZBX_STYLE_HEADER_LOGO)
	)
	->addItem(
		(new CTag('nav', true, (new CList($data['menu']['main_menu']))->addClass(ZBX_STYLE_TOP_NAV)))
			->setAttribute('aria-label', _('Main navigation'))
	)
	->addItem($user_navigation)
	->addClass(ZBX_STYLE_TOP_NAV_CONTAINER)
	->setId('mmenu');

$sub_menu_div = (new CTag('nav', true))
	->setAttribute('aria-label', _('Sub navigation'))
	->addClass(ZBX_STYLE_TOP_SUBNAV_CONTAINER)
	->onMouseover('javascript: MMenu.submenu_mouseOver();')
	->onMouseout('javascript: MMenu.mouseOut();');

// 2nd level menu
foreach ($data['menu']['sub_menus'] as $label => $sub_menu) {
	$sub_menu_row = (new CList())
		->addClass(ZBX_STYLE_TOP_SUBNAV)
		->setId('sub_'.$label);

	foreach ($sub_menu as $id => $sub_page) {
		$url = new CUrl($sub_page['menu_url']);
		if ($sub_page['menu_action'] !== null) {
			$url->setArgument('action', $sub_page['menu_action']);
		}

		$url
			->setArgument('ddreset', 1)
			->removeArgument('sid');

		$sub_menu_item = (new CLink($sub_page['menu_text'], $url->getUrl()))->setAttribute('tabindex', 0);
		if ($sub_page['selected']) {
			$sub_menu_item->addClass(ZBX_STYLE_SELECTED);
		}

		$sub_menu_row->addItem($sub_menu_item);
	}

	if ($data['menu']['selected'] === $label) {
		$sub_menu_row->setAttribute('style', 'display: block;');
		insert_js('MMenu.def_label = '.zbx_jsvalue($label));
	}
	else {
		$sub_menu_row->setAttribute('style', 'display: none;');
	}
	$sub_menu_div->addItem($sub_menu_row);
}

if ($data['server_name'] !== '') {
	$sub_menu_div->addItem(
		(new CDiv($data['server_name']))->addClass(ZBX_STYLE_SERVER_NAME)
	);
}

(new CTag('header', true))
	->addItem(
		(new CDiv())
			->addItem($top_menu)
			->addItem($sub_menu_div)
	)
	->show();
