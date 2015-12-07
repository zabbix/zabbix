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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$this->addJsFile('js/gtlc.js');
$this->addJsFile('js/flickerfreescreen.js');

$widget = (new CWidget())->setTitle(_('Maps'));

$form = (new CForm('get'))->cleanItems();

$controls = new CList();

if ($data['maps']) {
	$maps = [];
	foreach ($data['maps'] as $sysmapid => $map) {
		$maps[$sysmapid] = $map['name'];
	}

	$form->addVar('action', 'map.view')
		->addVar('fullscreen', $data['fullscreen']);

	$controls
		->addItem([_('Map'), SPACE, new CComboBox('sysmapid', $data['sysmapid'], 'submit()', $maps)])
		->addItem([_('Minimum severity'), SPACE, $data['pageFilter']->getSeveritiesMinCB()]);

	// get map parent maps
	$parent_maps = [];
	foreach (getParentMaps($data['sysmapid']) as $parent) {
		// check for permissions
		if (array_key_exists([$parent['sysmapid']], $data['maps'])) {
			$parent_maps[] = SPACE.SPACE;
			$parent_maps[] = new CLink(
				$parent['name'],
				'zabbix.php?action=map.view&sysmapid='.$parent['sysmapid'].'&fullscreen='.$data['fullscreen'].
					'&severity_min='.$data['severity_min']
			);
		}
	}
	if ($parent_maps) {
		array_unshift($parent_maps, _('Upper level maps').':');
		$controls->addItem($parent_maps);
	}

	$table = CScreenBuilder::getScreen([
		'resourcetype' => SCREEN_RESOURCE_MAP,
		'mode' => SCREEN_MODE_PREVIEW,
		'dataId' => 'mapimg',
		'screenitem' => [
			'screenitemid' => $data['sysmapid'],
			'screenid' => null,
			'resourceid' => $data['sysmapid'],
			'width' => null,
			'height' => null
		]
	])->get();

	$controls->addItem(get_icon('favourite', [
		'fav' => 'web.favorite.sysmapids',
		'elname' => 'sysmapid',
		'elid' => $data['sysmapid']
	]));
}
else {
	$table = (new CTable())->setNoDataMessage(_('No maps found.'));
}

$controls->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]));

$form->addItem($controls);
$widget->setControls($form)
	->addItem($table)
	->show();
