<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Screens'))
	->addItem((new CList())
	->addClass(ZBX_STYLE_OBJECT_GROUP)
	->addItem([
		(new CSpan())->addItem(new CLink(_('All screens'), 'screenconf.php')),
		'/',
		(new CSpan())
			->addClass(ZBX_STYLE_SELECTED)
			->addItem(
				new CLink($data['screen']['name'], 'screens.php?elementid='.$data['screen']['screenid'].
					'&fullscreen='.$data['fullscreen']
				)
			)
	]))
	->addItem((new CFilter('web.screens.filter.state'))->addNavigator());

$controls = (new CList())
	->addItem(
		new CComboBox('config', 'screens.php', 'redirect(this.options[this.selectedIndex].value);', [
			'screens.php' => _('Screens'),
			'slides.php' => _('Slide shows')
		])
	)
	->addItem($data['screen']['editable']
		? (new CButton('edit', _('Edit screen')))
			->onClick('redirect("screenedit.php?screenid='.$data['screen']['screenid'].'")')
		: null
	);

// Append screens combobox to page header.
$form = (new CForm())
	->setName('headerForm')
	->addVar('fullscreen', $data['fullscreen']);

if (check_dynamic_items($data['screen']['screenid'], 0)) {
	$pageFilter = new CPageFilter([
		'groups' => [
			'monitored_hosts' => true,
			'with_items' => true
		],
		'hosts' => [
			'monitored_hosts' => true,
			'with_items' => true,
			'DDFirstLabel' => _('not selected')
		],
		'hostid' => getRequest('hostid'),
		'groupid' => getRequest('groupid')
	]);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	$controls
		->addItem([ _('Group').SPACE, $pageFilter->getGroupsCB()])
		->addItem([ _('Host').SPACE, $pageFilter->getHostsCB()]);
}

// page header
$controls
	->addItem(get_icon('favourite',
		[
			'fav' => 'web.favorite.screenids',
			'elname' => 'screenid',
			'elid' => $data['screen']['screenid']
		]
	))
	->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]));

$form->addItem($controls);

$widget->setControls($form);

// Append screens to widget.
$screenBuilder = new CScreenBuilder([
	'screenid' => $data['screen']['screenid'],
	'mode' => SCREEN_MODE_PREVIEW,
	'profileIdx' => 'web.screens',
	'profileIdx2' => $data['screen']['screenid'],
	'groupid' => getRequest('groupid'),
	'hostid' => getRequest('hostid'),
	'period' => $data['period'],
	'stime' => $data['stime']
]);
$widget->addItem(
	(new CDiv($screenBuilder->show()))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
);

CScreenBuilder::insertScreenStandardJs([
	'timeline' => $screenBuilder->timeline,
	'profileIdx' => $screenBuilder->profileIdx
]);

return $widget;
