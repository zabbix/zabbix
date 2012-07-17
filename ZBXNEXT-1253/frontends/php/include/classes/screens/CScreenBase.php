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


class CScreenBase {

	public $isFlickerfree;
	public $mode;
	public $resourcetype;
	public $screenid;
	public $screenitem;
	public $action;
	public $hostid;
	public $period;
	public $stime;
	public $profileIdx;
	public $profileIdx2;
	public $dataId;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param boolean	$options['isFlickerfree']
	 * @param int		$options['mode']
	 * @param int		$options['resourcetype']
	 * @param int		$options['screenid']
	 * @param array		$options['screenitem']
	 * @param string	$options['action']
	 * @param int		$options['hostid']
	 * @param int		$options['period']
	 * @param int		$options['stime']
	 * @param string	$options['profileIdx']
	 * @param int		$options['profileIdx2']
	 * @param string	$options['dataId']
	 */
	public function __construct(array $options = array()) {
		$this->isFlickerfree = isset($options['isFlickerfree']) ? $options['isFlickerfree'] : true;
		$this->mode = isset($options['mode']) ? $options['mode'] : SCREEN_MODE_VIEW;
		$this->resourcetype = isset($options['resourcetype']) ? $options['resourcetype'] : null;
		$this->screenid = !empty($options['screenid']) ? $options['screenid'] : null;
		$this->action = !empty($options['action']) ? $options['action'] : null;
		$this->hostid = !empty($options['hostid']) ? $options['hostid'] : get_request('hostid', 0);
		$this->period = !empty($options['period']) ? $options['period'] : get_request('period', ZBX_MAX_PERIOD);
		$this->stime = !empty($options['stime']) ? $options['stime'] : get_request('stime', null);
		$this->profileIdx = !empty($options['profileIdx']) ? $options['profileIdx'] : '';
		$this->profileIdx2 = !empty($options['profileIdx2']) ? $options['profileIdx2'] : null;

		// calculate stime
		if ($this->stime > 19000000000000 && $this->stime < 21000000000000) {
			$this->stime = zbxDateToTime($this->stime);
		}
		if (($this->stime + $this->period) > time() || empty($this->stime)) {
			$this->stime = time() - $this->period;
		}

		// get screenitem
		if (!empty($options['screenitem'])) {
			$this->screenitem = $options['screenitem'];
		}
		elseif (!empty($options['screenitemid'])) {
			$this->screenitem = API::ScreenItem()->get(array(
				'screenitemids' => $options['screenitemid'],
				'hostids' => $this->hostid,
				'output' => API_OUTPUT_EXTEND
			));
			$this->screenitem = reset($this->screenitem);
		}

		// get screenid
		if (empty($this->screenid) && !empty($this->screenitem)) {
			$this->screenid = $this->screenitem['screenid'];
		}

		// get resourcetype
		if (is_null($this->resourcetype) && !empty($this->screenitem['resourcetype'])) {
			$this->resourcetype = $this->screenitem['resourcetype'];
		}

		// create action url
		if (empty($this->action)) {
			$this->action = 'screenedit.php?form=update&screenid='.$this->screenid.'&screenitemid='.$this->screenitem['screenitemid'];
		}
	}

	/**
	 * Save user manipulations with time control.
	 */
	public function updateProfile() {
		if (!empty($this->profileIdx)) {
			if (empty($this->profileIdx2)) {
				$this->profileIdx2 = !empty($this->screenid) ? $this->screenid : 0;
			}

			if (!empty($this->period) && $this->period >= ZBX_MIN_PERIOD) {
				CProfile::update($this->profileIdx.'.period', $this->period, PROFILE_TYPE_INT, $this->profileIdx2);
			}
			if (!empty($this->stime)) {
				CProfile::update($this->profileIdx.'.stime', $this->stime, PROFILE_TYPE_STR, $this->profileIdx2);
			}
		}
	}

	/**
	 * Create and get unique screen id for time control.
	 */
	public function getDataId() {
		if (empty($this->dataId)) {
			$this->dataId = !empty($this->screenitem) ? $this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'] : 1;
		}

		return $this->dataId;
	}

	/**
	 * Get unique screen container id.
	 */
	public function getScreenId() {
		return 'flickerfreescreen_'.$this->getDataId();
	}

	/**
	 * Get enveloped screen inside container.
	 */
	public function getOutput($item = null, $insertFlickerfreeJs = true, $flickerfreeData = array()) {
		if ($insertFlickerfreeJs) {
			$this->insertFlickerfreeJs($flickerfreeData);
		}

		if ($this->mode == SCREEN_MODE_EDIT) {
			return new CDiv(array($item, BR(), new CLink(_('Change'), $this->action)), null, $this->getScreenId());
		}
		else {
			return new CDiv($item, null, $this->getScreenId());
		}
	}

	/**
	 * Insert javascript flicker-free screen data.
	 */
	public function insertFlickerfreeJs($data = array()) {
		if ($this->isFlickerfree) {
			$jsData = array(
				'id' => $this->getDataId(),
				'screenitemid' => !empty($this->screenitem['screenitemid']) ? $this->screenitem['screenitemid'] : null,
				'screenid' => !empty($this->screenitem['screenid']) ? $this->screenitem['screenid'] : null,
				'resourcetype' => $this->resourcetype,
				'mode' => $this->mode,
				'refreshInterval' => CWebUser::$data['refresh'],
				'hostid' => $this->hostid,
				'period' => $this->period,
				'stime' => $this->stime,
				'profileIdx' => $this->profileIdx,
				'data' => !empty($data) ? $data : null
			);

			zbx_add_post_js('flickerfreeScreen.add('.zbx_jsvalue($jsData).');');
		}
	}
}
