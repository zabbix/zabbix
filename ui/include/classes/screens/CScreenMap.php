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


class CScreenMap extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$map_options = [];

		if (array_key_exists('severity_min', $this->screenitem)) {
			$map_options['severity_min'] = $this->screenitem['severity_min'];
		}

		$map_data = CMapHelper::get($this->screenitem['resourceid'], $map_options);
		$map_data['container'] = '#map_'.$this->screenitem['screenitemid'];
		$this->insertFlickerfreeJs($map_data);

		$output = [
			(new CDiv())->setId('map_'.$this->screenitem['screenitemid'])
		];

		if ($this->mode == SCREEN_MODE_EDIT) {
			$output += [BR(), new CLink(_x('Change', 'verb'), $this->action)];
		}

		$div = (new CDiv($output))
			->addClass('flickerfreescreen')
			->setId($this->getScreenId())
			->setAttribute('data-timestamp', $this->timestamp);

		// Add map to additional wrapper to enable horizontal scrolling.
		$div = (new CDiv($div))->addClass('sysmap-scroll-container');

		return $div;
	}
}
