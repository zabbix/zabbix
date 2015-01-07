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

class CControllerFavouriteCreate extends CController {

	protected function checkInput() {
		$fields = array(
			'object' =>				'fatal|in_str:graphid,itemid,screenid,slideshowid,sysmapid|required',
			'objectid' =>			'fatal|db:items.itemid|required'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			$data['javascript'] = '';
			$this->setResponse(new CControllerResponseData($data));
		}

		return $result;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$profile = array(
					'graphid' => 'web.favorite.graphids',
					'itemid' => 'web.favorite.graphids',
					'screenid' => 'web.favorite.screenids',
					'slideshowid' => 'web.favorite.screenids',
					'sysmapid' => 'web.favorite.sysmapids'
		);

		$object = $this->getInput('object');
		$objectid = $this->getInput('objectid');

		$data['javascript'] = '';

		DBstart();

		$result = CFavorite::add($profile[$object], $objectid, $object);
		if ($result) {
			echo '$("addrm_fav").title = "'._('Remove from favourites').'";'."\n".
				'$("addrm_fav").onclick = function() { rm4favorites("'.$object.'", "'.$objectid.'"); }'."\n";
		}

		$result = DBend($result);

		if ($result) {
			echo 'switchElementClass("addrm_fav", "iconminus", "iconplus");';
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
