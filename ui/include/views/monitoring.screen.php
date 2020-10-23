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

$this->includeJsFile('monitoring.screen.js.php');

$web_layout_mode = CViewHelper::loadLayoutMode();

$widget = (new CWidget())->setWebLayoutMode($web_layout_mode);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$widget
		->setTitle(_('Screens'))
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
				(new CSpan())->addItem(new CLink(_('All screens'), 'screenconf.php')),
				'/',
				(new CSpan())
					->addClass(ZBX_STYLE_SELECTED)
					->addItem(
						new CLink($data['screen']['name'], (new CUrl('screens.php'))
							->setArgument('elementid', $data['screen']['screenid'])
					))
		]));
}

$controls = new CList();

if ($data['has_dynamic_widgets']) {
	$controls
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
						'dstfrm' => 'headerForm',
						'monitored_hosts' => 1,
						'with_items' => 1
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		);

	zbx_add_post_js(
		'jQuery("#dynamic_hostid").on("change", function() {'.
			'var hosts = jQuery(this).multiSelect("getData"),'.
				'url = new Curl("screens.php", false);'.

			// Make URL.
			'url.setArgument("elementid", '.$data['screen']['screenid'].');'.
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

$controls
	->addItem($data['screen']['editable']
		? (new CButton('edit', _('Edit screen')))
			->onClick('redirect("screenedit.php?screenid='.$data['screen']['screenid'].'", "get", "", false, false)')
			->setEnabled($data['allowed_edit'])
		: null
	)
	->addItem(get_icon('favourite', [
			'fav' => 'web.favorite.screenids',
			'elname' => 'screenid',
			'elid' => $data['screen']['screenid']
		]
	))
	->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]));

$widget->setControls((new CTag('nav', true, (new CList())
	->addItem((new CForm('get'))
		->setName('headerForm')
		->addItem($controls)
	)))
		->setAttribute('aria-label', _('Content controls'))
);

// Append screens to widget.
$screenBuilder = new CScreenBuilder([
	'screenid' => $data['screen']['screenid'],
	'mode' => SCREEN_MODE_PREVIEW,
	'hostid' => array_key_exists('hostid', $data) ? $data['hostid'] : null,
	'profileIdx' => $data['profileIdx'],
	'profileIdx2' => $data['profileIdx2'],
	'from' => $data['from'],
	'to' => $data['to']
]);

$widget->addItem(
	(new CFilter(new CUrl()))
		->setProfile($data['profileIdx'], $data['profileIdx2'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($screenBuilder->timeline['from'], $screenBuilder->timeline['to'],
			$web_layout_mode != ZBX_LAYOUT_KIOSKMODE)
);

$widget->addItem((new CDiv($screenBuilder->show()))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER));

CScreenBuilder::insertScreenStandardJs($screenBuilder->timeline);

$widget->show();
