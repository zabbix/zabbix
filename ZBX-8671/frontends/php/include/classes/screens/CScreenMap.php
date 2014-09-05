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


class CScreenMap extends CScreenBase {

	/**
	 * Params for monitoring maps js.
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$image = new CImg('map.php?noedit=1&sysmapid='.$this->screenitem['resourceid'].'&width='.$this->screenitem['width']
			.'&height='.$this->screenitem['height'].'&curtime='.time());
		$image->setAttribute('id', 'map_'.$this->screenitem['screenitemid']);

		if ($this->mode == SCREEN_MODE_PREVIEW) {
			$sysmap = API::Map()->get(array(
				'sysmapids' => $this->screenitem['resourceid'],
				'output' => API_OUTPUT_EXTEND,
				'selectSelements' => API_OUTPUT_EXTEND,
				'selectLinks' => API_OUTPUT_EXTEND,
				'expandUrls' => true,
				'nopermissions' => true,
				'preservekeys' => true
			));
			$sysmap = reset($sysmap);

			$image->setSrc($image->getAttribute('src').'&severity_min='.$sysmap['severity_min']);

			$actionMap = getActionMapBySysmap($sysmap, array('severity_min' => $sysmap['severity_min']));
			$image->setMap($actionMap->getName());

			$output = array($actionMap, $image);
		}
		elseif ($this->mode == SCREEN_MODE_EDIT) {
			$output = array($image, BR(), new CLink(_('Change'), $this->action));
		}
		else {
			$output = array($image);
		}

		$this->insertFlickerfreeJs();

		$div = new CDiv($output, 'map-container flickerfreescreen', $this->getScreenId());
		$div->setAttribute('data-timestamp', $this->timestamp);
		$div->addStyle('position: relative;');

		return $div;
	}
}
