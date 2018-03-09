<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class CControllerWidgetFavScreensView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_FAVOURITE_SCREENS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'fullscreen' => 'in 0,1'
		]);
	}

	protected function doAction() {
		$fullscreen = (bool) $this->getInput('fullscreen', false);

		$screens = [];
		$ids = ['screenid' => [], 'slideshowid' => []];

		foreach (CFavorite::get('web.favorite.screenids') as $favourite) {
			$ids[$favourite['source']][$favourite['value']] = true;
		}

		if ($ids['screenid']) {
			$db_screens = API::Screen()->get([
				'output' => ['screenid', 'name'],
				'screenids' => array_keys($ids['screenid'])
			]);

			foreach ($db_screens as $db_screen) {
				$screens[] = [
					'screenid' => $db_screen['screenid'],
					'label' => $db_screen['name'],
					'slideshow' => false
				];
			}
		}

		if ($ids['slideshowid']) {
			foreach ($ids['slideshowid'] as $slideshowid) {
				if (slideshow_accessible($slideshowid, PERM_READ)) {
					$db_slideshow = get_slideshow_by_slideshowid($slideshowid, PERM_READ);

					if ($db_slideshow) {
						$screens[] = [
							'slideshowid' => $db_slideshow['slideshowid'],
							'label' => $db_slideshow['name'],
							'slideshow' => true
						];
					}
				}
			}
		}

		CArrayHelper::sort($screens, ['label']);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_FAVOURITE_SCREENS]),
			'screens' => $screens,
			'fullscreen' => $fullscreen,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
