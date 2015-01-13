<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerDashboardFavourite extends CController {

	protected function checkInput() {
		$fields = array(
			'object' =>				'in graphid,itemid,screenid,slideshowid,sysmapid|fatal|required',
			'objectids' =>			'array_db items.itemid                          |fatal|required',
			'operation' =>			'in create,delete                               |fatal|required'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$data['main_block'] = '';
			$this->setResponse(new CControllerResponseData($data));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$object = $this->getInput('object');
		$operation = $this->getInput('operation');
		$objectids = $this->getInput('objectids');

		$data = array (
			'main_block' => ''
		);

		$result = true;

		DBstart();

		switch ($object) {
			// favourite graphs
			case 'itemid':
			case 'graphid':
				zbx_value2array($objectids);
				foreach ($objectids as $id) {
					if ($operation == 'create') {
						$result &= CFavorite::add('web.favorite.graphids', $id, $object);
					}
					elseif ($operation == 'delete') {
						$result &= CFavorite::remove('web.favorite.graphids', $id, $object);
					}
				}

				$graphs = getFavouriteGraphs();
				$graphs = $graphs->toString();

				$data['main_block'] = $data['main_block'].'
					jQuery("#'.WIDGET_FAVOURITE_GRAPHS.'").html('.CJs::encodeJson($graphs).');
					jQuery(".menuPopup").remove();
					jQuery("#favouriteGraphs").data("menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteGraphs()).');';
				break;

			// favourite maps
			case 'sysmapid':
				zbx_value2array($objectids);
				foreach ($objectids as $id) {
					if ($operation == 'create') {
						$result &= CFavorite::add('web.favorite.sysmapids', $id, $object);
					}
					elseif ($operation == 'delete') {
						$result &= CFavorite::remove('web.favorite.sysmapids', $id, $object);
					}
				}

				$maps = getFavouriteMaps();
				$maps = $maps->toString();

				$data['main_block'] = $data['main_block'].'
					jQuery("#'.WIDGET_FAVOURITE_MAPS.'").html('.CJs::encodeJson($maps).');
					jQuery(".menuPopup").remove();
					jQuery("#favouriteMaps").data("menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteMaps()).');';
				break;

			// favourite screens, slideshows
			case 'screenid':
			case 'slideshowid':
				zbx_value2array($objectids);
				foreach ($objectids as $id) {
					if ($operation == 'create') {
						$result &= CFavorite::add('web.favorite.screenids', $id, $object);
					}
					elseif ($operation == 'delete') {
						$result &= CFavorite::remove('web.favorite.screenids', $id, $object);
					}
				}

				$screens = getFavouriteScreens();
				$screens = $screens->toString();

				$data['main_block'] = $data['main_block'].'
					jQuery("#'.WIDGET_FAVOURITE_SCREENS.'").html('.CJs::encodeJson($screens).');
					jQuery(".menuPopup").remove();
					jQuery("#favouriteScreens").data("menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteScreens()).');';
				break;
		}

		DBend($result);

		$this->setResponse(new CControllerResponseData($data));
	}
}
