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

$web_layout_mode = CViewHelper::loadLayoutMode();

$widget = (new CWidget())->setWebLayoutMode($web_layout_mode);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$widget
		->setTitle(_('Slide shows'))
		->setTitleSubmenu([
			'main_section' => [
				'items' => [
					'screens.php' => _('Screens'),
					'slides.php' => _('Slide shows')
				]
			]
		])
		->addItem((new CList())
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _x('Hierarchy', 'screen reader'))
			->addClass(ZBX_STYLE_OBJECT_GROUP)
			->addClass(ZBX_STYLE_FILTER_BREADCRUMB)
			->addItem([
				(new CSpan())->addItem(new CLink(_('All slide shows'), 'slideconf.php')),
				'/',
				(new CSpan())
					->addClass(ZBX_STYLE_SELECTED)
					->addItem(
						new CLink($data['screen']['name'], (new CUrl('slides.php'))
							->setArgument('elementid', $data['screen']['slideshowid'])
						)
					)
			])
		);
}

$favourite_icon = get_icon('favourite', [
	'fav' => 'web.favorite.screenids',
	'elname' => 'slideshowid',
	'elid' => $this->data['elementId']
]);

$refresh_icon = get_icon('screenconf');

$refresh_icon->setMenuPopup(CMenuPopupHelper::getRefresh(WIDGET_SLIDESHOW, 'x'.$this->data['refreshMultiplier'],
	true, ['elementid' => $this->data['elementId']]
));

$controls = null;

if ($data['has_dynamic_widgets']) {
	$controls = (new CList())
		->addItem(new CLabel(_('Host'), 'hostid'))
		->addItem(
			(new CMultiSelect([
				'name' => 'dynamic_hostid',
				'object_name' => 'hosts',
				'data' => $data['host'],
				'multiple' => false,
				'popup' => [
					'parameters' => [
						'srctbl' => 'hosts',
						'srcfld1' => 'hostid',
						'dstfld1' => 'dynamic_hostid',
						'monitored_hosts' => 1
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		);

	zbx_add_post_js(
		'jQuery("#dynamic_hostid").on("change", function() {'.
			'var hosts = jQuery(this).multiSelect("getData"),'.
				'url = new Curl("slides.php", false);'.

			// Make URL.
			'url.setArgument("elementid", '.$data['screen']['slideshowid'].');'.
			'if (hosts.length) {'.
				'url.setArgument("hostid", hosts[0].id);'.
			'}'.
			'else {'.
				'url.setArgument("reset", "reset");'.
			'}'.

			// Push URL change.
			'return redirect(url.getUrl(), "get", "", false, false);'.
		'});'
	);
}

$widget->setControls((new CList([
	(new CForm('get'))
		->setAttribute('aria-label', _('Main filter'))
		->setName('slideHeaderForm')
		->addItem($controls),
	(new CTag('nav', true, (new CList())
		->addItem($data['screen']['editable']
			? (new CButton('edit', _('Edit slide show')))
				->onClick('redirect("slideconf.php?form=update&slideshowid='.$data['screen']['slideshowid'].'")')
				->setEnabled($data['allowed_edit'])
			: null
		)
		->addItem($favourite_icon)
		->addItem($refresh_icon)
		->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
	))
		->setAttribute('aria-label', _('Content controls'))
])));

$widget
	->addItem(
		(new CFilter(new CUrl()))
			->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['timeline']['from'], $data['timeline']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE)
	)
	->addItem(
		(new CDiv((new CDiv())->addStyle('position: relative;margin-top: 20px;')->addClass('is-loading')))
			->setId(WIDGET_SLIDESHOW)
	);

require_once dirname(__FILE__).'/js/monitoring.slides.js.php';

$widget->show();
