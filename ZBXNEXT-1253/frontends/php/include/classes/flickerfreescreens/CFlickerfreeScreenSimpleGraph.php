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
	private $stime;

	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->hostid = get_request('hostid', 0);
		$this->stime = get_request('stime', null);
	}

	public function get() {
		$domGraphid = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$containerid = 'graph_cont_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$graphDims = getGraphDims();
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];

		// get timecontroll
		$timecontrollData = array(
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
			if (!empty($newitemid)) {
				$this->screenitem['resourceid'] = $newitemid;
			}
			else {
				$this->screenitem['resourceid'] = '';
			}
		}

		if ($this->mode == SCREEN_MODE_PREVIEW && !empty($this->screenitem['resourceid'])) {
			$this->action = 'history.php?action=showgraph&itemid='.$this->screenitem['resourceid'].url_params(array('period', 'stime'));
		}

		$timeline = array();
		$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

		if (!zbx_empty($this->screenitem['resourceid']) && $this->mode != SCREEN_MODE_EDIT) {
			$timeline['period'] = $this->effectiveperiod;

			if (isset($this->stime)) {
				$timeline['usertime'] = date('YmdHis', zbxDateToTime($this->stime) + $timeline['period']);
			}
			if ($this->mode == SCREEN_MODE_PREVIEW) {
				$timecontrollData['loadSBox'] = 1;
			}
		}

		$timecontrollData['src'] = zbx_empty($this->screenitem['resourceid'])
			? 'chart3.php?'
			: 'chart.php?itemid='.$this->screenitem['resourceid'].'&'.$this->screenitem['url'].'width='.$this->screenitem['width']
				.'&height='.$this->screenitem['height'];

		// process output
		if ($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_VIEW) {
			$item = new CDiv();
		}
		else {
			$item = new CLink(null, $this->action);
		}
		$item->setAttribute('id', $containerid);

		$output = array($item);
		if ($this->mode == SCREEN_MODE_EDIT) {
			$output[] = BR();
			$output[] = new CLink(_('Change'), $this->action);
		}

		if ($this->mode == SCREEN_MODE_VIEW) {
			insert_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timecontrollData).');');
		}
		else {
			zbx_add_post_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($timecontrollData).');');
		}

		return $output;
	}
}
