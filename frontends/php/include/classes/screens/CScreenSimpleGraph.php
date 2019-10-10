<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CScreenSimpleGraph extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$resourceid = !empty($this->screenitem['real_resourceid']) ? $this->screenitem['real_resourceid'] : $this->screenitem['resourceid'];
		$containerid = 'graph_container_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$graphDims = getGraphDims();
		$graphDims['graphHeight'] = (int) $this->screenitem['height'];
		$graphDims['width'] = (int) $this->screenitem['width'];

		// get time control
		$timeControlData = [
			'id' => $this->getDataId(),
			'containerid' => $containerid,
			'objDims' => $graphDims,
			'loadImage' => 1
		];

		// host feature
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			$newitemid = get_same_item_for_host($resourceid, $this->hostid);
			$resourceid = !empty($newitemid) ? $newitemid : '';
		}

		if ($this->mode == SCREEN_MODE_PREVIEW && !empty($resourceid)) {
			$this->action = (new CUrl('history.php'))
				->setArgument('action', HISTORY_GRAPH)
				->setArgument('itemids', [$resourceid])
				->setArgument('from', $this->timeline['from'])
				->setArgument('to', $this->timeline['to'])
				->setArgument('profileIdx', $this->profileIdx)
				->setArgument('profileIdx2', $this->profileIdx2)
				->getUrl();
		}

		if ($resourceid && $this->mode != SCREEN_MODE_EDIT) {
			if ($this->mode == SCREEN_MODE_PREVIEW) {
				$timeControlData['loadSBox'] = 1;
			}
		}

		if ($resourceid) {
			$src = (new CUrl('chart.php'))
				->setArgument('itemids', [$resourceid])
				->setArgument('width', $this->screenitem['width'])
				->setArgument('height', $this->screenitem['height']);
		}
		else {
			$src = new CUrl('chart3.php');
		}

		if ($this->mode == SCREEN_MODE_EDIT) {
			$src
				->setArgument('from', ZBX_PERIOD_DEFAULT_FROM)
				->setArgument('to', ZBX_PERIOD_DEFAULT_TO);
		}
		else {
			$src
				->setArgument('from', $this->timeline['from'])
				->setArgument('to', $this->timeline['to']);
		}

		$src
			->setArgument('profileIdx', $this->profileIdx)
			->setArgument('profileIdx2', $this->profileIdx2);

		$timeControlData['src'] = $src->getUrl();

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			return 'timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.CJs::encodeJson($timeControlData).')';
		}
		else {
			if ($this->mode == SCREEN_MODE_SLIDESHOW) {
				insert_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.CJs::encodeJson($timeControlData).');');
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.CJs::encodeJson($this->timeline).', '.CJs::encodeJson($timeControlData).');');
			}

			if ($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_SLIDESHOW || !$resourceid) {
				$item = new CDiv();
			}
			elseif ($this->mode == SCREEN_MODE_PREVIEW) {
				$item = new CLink(null, (new CUrl('history.php'))
					->setArgument('action', HISTORY_GRAPH)
					->setArgument('itemids', [$resourceid])
					->setArgument('from', $this->timeline['from'])
					->setArgument('to', $this->timeline['to'])
					->getUrl()
				);
			}
			$item->setId($containerid);

			return $this->getOutput($item);
		}
	}
}
