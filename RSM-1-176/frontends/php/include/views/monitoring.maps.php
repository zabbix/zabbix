<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/js/general.script.confirm.js.php';
require_once dirname(__FILE__).'/js/monitoring.maps.js.php';

$mapWidget = new CWidget('hat_maps');
$mapTable = new CTable(_('No maps defined.'), 'map map-container');
$mapTable->setAttribute('style', 'margin-top: 4px;');

$icon = $fsIcon = null;

if (!empty($this->data['maps'])) {
	$mapComboBox = new CComboBox('sysmapid', get_request('sysmapid', 0), 'submit()');
	foreach ($this->data['maps'] as $sysmapId => $map) {
		$mapComboBox->addItem($sysmapId, get_node_name_by_elid($sysmapId, null, ': ').$map['name']);
	}

	$headerForm = new CForm('get');
	$headerForm->addVar('fullscreen', $this->data['fullscreen']);
	$headerForm->addItem($mapComboBox);

	$mapWidget->addHeader($this->data['map']['name'], $headerForm);

	// get map parent maps
	$parentMaps = array();
	foreach (getParentMaps($this->data['sysmapid']) as $parent) {
		// check for permissions
		if (isset($this->data['maps'][$parent['sysmapid']])) {
			$parentMaps[] = SPACE.SPACE;
			$parentMaps[] = new Clink($parent['name'], 'maps.php?sysmapid='.$parent['sysmapid'].'&fullscreen='.$this->data['fullscreen']);
		}
	}
	if (!empty($parentMaps)) {
		array_unshift($parentMaps, _('Upper level maps').':');
		$mapWidget->addHeader($parentMaps);
	}

	$actionMap = getActionMapBySysmap($this->data['map']);

	$mapTable->addRow($actionMap);

	$imgMap = new CImg('map.php?sysmapid='.$this->data['sysmapid']);
	$imgMap->setMap($actionMap->getName());
	$mapTable->addRow($imgMap);

	$icon = get_icon('favourite', array(
		'fav' => 'web.favorite.sysmapids',
		'elname' => 'sysmapid',
		'elid' => $this->data['sysmapid']
	));
	$fsIcon = get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']));
}

$mapWidget->addItem($mapTable);
$mapWidget->addPageHeader(_('NETWORK MAPS'), array($icon, SPACE, $fsIcon));

return $mapWidget;
