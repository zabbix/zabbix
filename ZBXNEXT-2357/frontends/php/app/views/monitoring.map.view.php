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


$mapWidget = new CWidget('hat_maps');

if ($data['maps']) {
	$mapTable = new CTable(null, 'map map-container');
	$mapTable->setAttribute('style', 'margin-top: 4px;');

	$mapComboBox = new CComboBox('sysmapid', $data['sysmapid'], 'submit()');
	foreach ($data['maps'] as $sysmapid => $map) {
		$mapComboBox->addItem($sysmapid, $map['name']);
	}

	$headerMapForm = new CForm('get');
	$headerMapForm->addVar('action', 'map.view');
	$headerMapForm->addVar('fullscreen', $data['fullscreen']);
	$headerMapForm->addItem(array(_('Maps'), SPACE, $mapComboBox));

	$headerSeverityMinForm = new CForm('get');
	$headerSeverityMinForm->addVar('action', 'map.view');
	$headerSeverityMinForm->addVar('fullscreen', $data['fullscreen']);
	$headerSeverityMinForm->addItem(array(SPACE, _('Minimum severity'), SPACE, $data['pageFilter']->getSeveritiesMinCB()));

	$mapWidget->addHeader($data['map']['name'], array($headerMapForm, $headerSeverityMinForm));

	// get map parent maps
	$parentMaps = array();
	foreach (getParentMaps($data['sysmapid']) as $parent) {
		// check for permissions
		if (isset($data['maps'][$parent['sysmapid']])) {
			$parentMaps[] = SPACE.SPACE;
			$parentMaps[] = new CLink($parent['name'], 'zabbix.php?action=map.view&sysmapid='.$parent['sysmapid'].'&fullscreen='.$data['fullscreen'].'&severity_min='.$data['severity_min']);
		}
	}
	if (!empty($parentMaps)) {
		array_unshift($parentMaps, _('Upper level maps').':');
		$mapWidget->addHeader($parentMaps);
	}

	$actionMap = getActionMapBySysmap($data['map'], array('severity_min' => $data['severity_min']));

	$mapTable->addRow($actionMap);

	$imgMap = new CImg('map.php?sysmapid='.$data['sysmapid'].'&severity_min='.$data['severity_min']);
	$imgMap->setMap($actionMap->getName());
	$mapTable->addRow($imgMap);

	$icons = array(
		get_icon('favourite', array(
			'fav' => 'web.favorite.sysmapids',
			'elname' => 'sysmapid',
			'elid' => $data['sysmapid']
		)),
		'&nbsp'
	);
}
else {
	$mapTable = new CTable(_('No maps found.'), 'map map-container');
	$mapTable->setAttribute('style', 'margin-top: 4px;');

	$icons = array();
}

$icons[] = get_icon('fullscreen', array('fullscreen' => $data['fullscreen']));

$mapWidget->addItem($mapTable);
$mapWidget->addPageHeader(_('NETWORK MAPS'), $icons);

$mapWidget->show();
