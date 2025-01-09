<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use Zabbix\Core\CWidget;

class CControllerFavoriteDelete extends CController {

	private const WIDGET_FAV_GRAPHS = 'favgraphs';
	private const WIDGET_FAV_MAPS = 'favmaps';

	protected function checkInput() {
		$fields = [
			'object' =>		'fatal|required|in itemid,sysmapid',
			'objectid' =>	'fatal|required|id'
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
		$profile = [
			'itemid' => 'web.favorite.graphids',
			'sysmapid' => 'web.favorite.sysmapids'
		];

		$affected_widget_types = [
			'graphid' => self::WIDGET_FAV_GRAPHS,
			'itemid' => self::WIDGET_FAV_GRAPHS,
			'sysmapid' => self::WIDGET_FAV_MAPS
		];

		$object = $this->getInput('object');
		$objectid = $this->getInput('objectid');

		$data = [];

		DBstart();
		$result = CFavorite::remove($profile[$object], $objectid, $object);
		$result = DBend($result);

		if ($result) {
			$data['main_block'] = '
				var addrm_fav = document.getElementById("addrm_fav");

				if (addrm_fav !== null) {
					addrm_fav.title = "'._('Add to favorites').'";
					addrm_fav.onclick = () => add2favorites("'.$object.'", "'.$objectid.'");
					addrm_fav.classList.add("'.ZBX_ICON_STAR.'");
					addrm_fav.classList.remove("'.ZBX_ICON_STAR_FILLED.'");
				}
				else {
					ZABBIX.Dashboard.getSelectedDashboardPage().getWidgets().forEach((widget) => {
						if (widget.getType() === "'.$affected_widget_types[$object].'"
								&& widget.getState() === WIDGET_STATE_ACTIVE) {
							widget._startUpdating();
						}
					});
				}
			';
		}
		else {
			$data['main_block'] = '';
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
