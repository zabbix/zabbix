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

	$page_menu = (new CTag('header', true))->setAttribute('role', 'banner');
	$page_menu_div = (new CDiv())->addClass('nav')->setAttribute('role', 'navigation');

	$top_menu_items = (new CList($data['menu']['main_menu']))->addClass('top-nav');

	// 1st level menu
	$top_menu = (new CDiv($top_menu_items))
		->addClass('top-nav-container')
		->setId('mmenu');

	$icons = (new CList())->addClass('top-nav-icons');

	$form = new CForm('get', 'search.php');
	$search = new CTextBox('search', '', 20, false, 255);
	$search->setAttribute('autocomplete', 'off');
	$search->addClass('search');

	$button = (new CSubmitButton(SPACE))->addClass('btn-search');
	$form->addItem([$search, $button]);
	$icons->addItem($form);

	$zshare = (new CLink('Share', 'https://share.zabbix.com/'))
		->addClass('top-nav-zbbshare')
		->removeSID()
		->setAttribute('target', '_blank')
		->setAttribute('title', _('Zabbix Share'));
	$icons->addItem($zshare);

	$help = (new CLink(SPACE, 'http://www.zabbix.com/documentation/'))
		->addClass('top-nav-help')
		->removeSID()
		->setAttribute('target', '_blank')
		->setAttribute('title', _('Help'));
	$icons->addItem($help);

	$profile = (new CLink(SPACE, 'profile.php'))
		->addClass('top-nav-profile')
		->setAttribute('title', _('Profile'));
	$icons->addItem($profile);

	$signout = (new CLink(SPACE, 'index.php?reconnect=1'))
		->addClass('top-nav-signout')
		->setAttribute('title', _('Sign out'));
	$icons->addItem($signout);
	$top_menu->addItem($icons);

	$page_menu_div->addItem($top_menu);

	// 2nd level menu
	$sub_menu_table = (new CTable())
		->addClass('sub_menu')
		->addClass('maxwidth')
		->addClass('ui-widget-header');
	$menu_divs = [];
	$menu_selected = false;
	foreach ($data['menu']['sub_menus'] as $label => $sub_menu) {
		$sub_menu_row = (new CList())->addClass('top-subnav');
		foreach ($sub_menu as $id => $sub_page) {
			if (empty($sub_page['menu_text'])) {
				$sub_page['menu_text'] = SPACE;
			}

			$url = new CUrl($sub_page['menu_url']);
			if ($sub_page['menu_action'] !== null) {
				$url->setArgument('action', $sub_page['menu_action']);
			}
			else {
				$url->setArgument('ddreset', 1);
			}
			$url->removeArgument('sid');

			$sub_menu_item = (new CLink($sub_page['menu_text'], $url->getUrl()))->removeSID();
			if ($sub_page['selected']) {
				$sub_menu_item->addClass('selected');
			}

			$sub_menu_row->addItem($sub_menu_item);
		}

		$sub_menu_div = (new CDiv($sub_menu_row))
			->addClass('top-subnav-container')
			->setId('sub_'.$label)
			->onMouseover('javascript: MMenu.submenu_mouseOver();')
			->onMouseout('javascript: MMenu.mouseOut();');

		if ($data['menu']['selected'] == $label) {
			$menu_selected = true;
			$sub_menu_div->setAttribute('style', 'display: block;');
			insert_js('MMenu.def_label = '.zbx_jsvalue($label));
		}
		else {
			$sub_menu_div->setAttribute('style', 'display: none;');
		}
		$menu_divs[] = $sub_menu_div;
	}

	$sub_menu_div = (new CDiv(SPACE))
		->setId('sub_empty')
		->setAttribute('style', 'display: '.($menu_selected ? 'none;' : 'block;'));

	$menu_divs[] = $sub_menu_div;

	$page_menu_div->addItem($menu_divs);
	$page_menu_div->addItem($sub_menu_table);
	$page_menu->addItem($page_menu_div);
	$page_menu->show();
