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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerDashboardFavourite extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'object' =>		'fatal|required|in graphid,itemid,screenid,slideshowid,sysmapid',
			'objectids' =>	'fatal|required|array_id',
			'operation' =>	'fatal|required|in create,delete'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => '']));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$object = $this->getInput('object');
		$operation = $this->getInput('operation');
		$objectids = $this->getInput('objectids');

		$data = [];
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

				$data['main_block'] =
					'jQuery("#'.WIDGET_FAVOURITE_GRAPHS.'").html('.CJs::encodeJson($graphs).');'.
					'jQuery(".action-menu").remove();'.
					'jQuery("#favouriteGraphs").data('.
						'"menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteGraphs()).
					');';
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

				$data['main_block'] =
					'jQuery("#'.WIDGET_FAVOURITE_MAPS.'").html('.CJs::encodeJson($maps).');'.
					'jQuery(".action-menu").remove();'.
					'jQuery("#favouriteMaps").data('.
						'"menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteMaps()).
					');';
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

				$data['main_block'] =
					'jQuery("#'.WIDGET_FAVOURITE_SCREENS.'").html('.CJs::encodeJson($screens).');'.
					'jQuery(".action-menu").remove();'.
					'jQuery("#favouriteScreens").data('.
						'"menu-popup", '.CJs::encodeJson(CMenuPopupHelper::getFavouriteScreens()).
					');';
				break;

			default:
				$data['main_block'] = '';
		}

		DBend($result);

		$this->setResponse(new CControllerResponseData($data));
	}
}
