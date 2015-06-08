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
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];

		// get time control
		$timeControlData = [
			'id' => $this->getDataId(),
			'containerid' => $containerid,
			'objDims' => $graphDims,
			'loadImage' => 1,
			'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		];

		// host feature
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			$newitemid = get_same_item_for_host($resourceid, $this->hostid);
			$resourceid = !empty($newitemid) ? $newitemid : '';
		}

		if ($this->mode == SCREEN_MODE_PREVIEW && !empty($resourceid)) {
			$this->action = 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$resourceid.'&period='.$this->timeline['period'].
					'&stime='.$this->timeline['stimeNow'].$this->getProfileUrlParams();
		}

		if ($resourceid && $this->mode != SCREEN_MODE_EDIT) {
			if ($this->mode == SCREEN_MODE_PREVIEW) {
				$timeControlData['loadSBox'] = 1;
			}
		}

		$timeControlData['src'] = $resourceid
			? 'chart.php?itemids[]='.$resourceid.'&'.$this->screenitem['url'].'&width='.$this->screenitem['width'].'&height='.$this->screenitem['height']
			: 'chart3.php?';

		$timeControlData['src'] .= ($this->mode == SCREEN_MODE_EDIT)
			? '&period=3600&stime='.date(TIMESTAMP_FORMAT, time())
			: '&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'];

		$timeControlData['src'] .= $this->getProfileUrlParams();

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

			if ($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_SLIDESHOW) {
				$item = new CDiv();
			}
			elseif ($this->mode == SCREEN_MODE_PREVIEW) {
				$item = new CLink(null, 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$resourceid.'&period='.$this->timeline['period'].
						'&stime='.$this->timeline['stimeNow']);
			}
			$item->setAttribute('id', $containerid);

			return $this->getOutput($item);
		}
	}
}
