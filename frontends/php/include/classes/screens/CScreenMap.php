<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CScreenMap extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$severity = null;
		if (array_key_exists('severity_min', $this->screenitem)) {
			$severity = $this->screenitem['severity_min'];
		}

		$mapData = CMapHelper::get($this->screenitem['resourceid'], $severity);
		$mapData['container'] = "#map_{$this->screenitem['screenitemid']}";
		$this->insertFlickerfreeJs($mapData);

		$output = [
			(new CDiv())
				->setId('map_'.$this->screenitem['screenitemid'])
				->addStyle('width:'.$mapData['canvas']['width'].'px;')
				->addStyle('height:'.$mapData['canvas']['height'].'px;')
				->addStyle('overflow:hidden;')
		];

		if ($this->mode == SCREEN_MODE_EDIT) {
			$output += [BR(), new CLink(_('Change'), $this->action)];
		}

		$div = (new CDiv($output))
			->addClass('map-container')
			->addClass('flickerfreescreen')
			->setId($this->getScreenId())
			->setAttribute('data-timestamp', $this->timestamp)
			->addStyle('position: relative;');

		return $div;
	}
}
