<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFlickerfreeScreenSimpleGraph extends CFlickerfreeScreenItem {

	private $hostid;
	private $period;
	private $stime;

	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->hostid = !empty($options['hostid']) ? $options['hostid'] : get_request('hostid', 0);
		$this->period = !empty($options['period']) ? $options['period'] : get_request('period', ZBX_MAX_PERIOD);
		$this->stime = !empty($options['stime']) ? $options['stime'] : get_request('stime', null);
	}

	public function get() {
		$domGraphid = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$containerid = 'graph_container_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'];
		$graphDims = getGraphDims();
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];

		// get time control
		$timeControlData = array(
			'id' => $this->screenitem['resourceid'],
			'domid' => $domGraphid,
			'containerid' => $containerid,
			'objDims' => $graphDims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'loadScroll' => 0,
			'dynamic' => 0,
			'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		);

		// host feature
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			$newitemid = get_same_item_for_host($this->screenitem['resourceid'], $this->hostid);
			$this->screenitem['resourceid'] = !empty($newitemid) ? $newitemid : '';
		}

		if ($this->mode == SCREEN_MODE_PREVIEW && !empty($this->screenitem['resourceid'])) {
			$this->action = 'history.php?action=showgraph&itemid='.$this->screenitem['resourceid'].url_params(array('period', 'stime'));
		}

		$timeline = array(
			'period' => $this->period,
			'starttime' => date('YmdHis', time() - ZBX_MAX_PERIOD)
		);

		if (!zbx_empty($this->screenitem['resourceid']) && $this->mode != SCREEN_MODE_EDIT) {
			if (!empty($this->stime)) {
				$timeline['usertime'] = date('YmdHis', zbxDateToTime($this->stime) + $timeline['period']);
			}
			if ($this->mode == SCREEN_MODE_PREVIEW) {
				$timeControlData['loadSBox'] = 1;
			}
		}

		$timeControlData['src'] = zbx_empty($this->screenitem['resourceid'])
			? 'chart3.php?'
			: 'chart.php?itemid='.$this->screenitem['resourceid'].'&'.$this->screenitem['url'].'width='.$this->screenitem['width']
				.'&height='.$this->screenitem['height'];

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			return 'timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).')';
		}
		else {
			zbx_add_post_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timeControlData).');');

			if ($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_VIEW) {
				$item = new CDiv();
			}
			else {
				$item = new CLink(null, $this->action);
			}
			$item->setAttribute('id', $containerid);

			return $this->getOutput($item);
		}
	}
}
