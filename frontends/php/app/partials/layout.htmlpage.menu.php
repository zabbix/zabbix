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
 * @var CPartial $this
 */

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
	)
	->addItem(
		(new CList())
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
			)
			->addItem(
				$data['user']['is_guest']
					? (new CSpan())
						->addClass(ZBX_STYLE_TOP_NAV_GUEST)
						->setTitle(getUserFullname($data['user']))
					: (new CLink(null, (new CUrl('zabbix.php'))
							->setArgument('action', 'userprofile.edit')
							->getUrl()
						))
						->addClass(ZBX_STYLE_TOP_NAV_PROFILE)
						->setTitle(getUserFullname($data['user']))
			)
			->addItem(
				(new CLink(SPACE, 'javascript:;'))
					->addClass(ZBX_STYLE_TOP_NAV_SIGNOUT)
					->setTitle(_('Sign out'))
					->onClick('ZABBIX.logout()')
			)
	);

$menu = [];

/** @var CMenu $item */
foreach ($data['menu']->getItems() as $key => $item) {
	$link = (new CLink($item->getLabel()))
		->onClick('javascript: MMenu.mouseOver(\''.$item->getUniqueId().'\');')
		->onKeyup('javascript: MMenu.keyUp(\''.$item->getUniqueId().'\', event);');
	$menu[] = (new CListItem($link))
		->setId($item->getUniqueId())
		->addClass($item->isSelected() ? ZBX_STYLE_SELECTED : null);
}

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
		(new CTag('nav', true, (new CList($menu))->addClass(ZBX_STYLE_TOP_NAV)))
			->setAttribute('aria-label', _('Main navigation'))
	)
	->addItem($user_navigation)
	->addClass(ZBX_STYLE_TOP_NAV_CONTAINER)
	->setId('mmenu');

$sub_menu_div = (new CTag('nav', true))
	->setAttribute('aria-label', _('Sub navigation'))
	->onMouseover('javascript: MMenu.submenu_mouseOver();')
	->onMouseout('javascript: MMenu.mouseOut();')
	->addClass(ZBX_STYLE_TOP_SUBNAV_CONTAINER);

// 2nd level menu
foreach ($data['menu']->getItems() as $item) {
	$child_menu = (new CList())
		->setId('sub_'.$item->getUniqueId())
		->addClass(ZBX_STYLE_TOP_SUBNAV);

	foreach ($item->getItems() as $child_item) {
		// TODO: remove if statements, use CUrl instead.
		$action = $child_item->getAction();
		$url = substr($action, -4) === '.php' ? $action : 'zabbix.php?action='.$action;
		$selected = $child_item->isSelected() ? ZBX_STYLE_SELECTED : null;

		$child_menu->addItem((new CLink($child_item->getLabel(), $url))
			->addClass($selected)
			->setId($child_item->getLabel())
		);

		if ($selected) {
			insert_js('MMenu.def_label = '.json_encode($item->getUniqueId()));
		}
	}

	if (!$item->isSelected()) {
		$child_menu->setAttribute('style', 'display: none;');
	}

	$sub_menu_div->addItem($child_menu);
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
