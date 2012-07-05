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

	public $is_flickerfree;
	public $mode;
	public $screenid;
	public $screenitem;
	public $action;
	public $id;
	public $hostid;
	public $period;
	public $stime;
	public $profile_idx;

	public function __construct(array $options = array()) {
		$this->is_flickerfree = isset($options['is_flickerfree']) ? $options['is_flickerfree'] : true;
		$this->mode = isset($options['mode']) ? $options['mode'] : SCREEN_MODE_VIEW;
		$this->screenid = !empty($options['screenid']) ? $options['screenid'] : null;
		$this->action = !empty($options['action']) ? $options['action'] : '';
		$this->hostid = !empty($options['hostid']) ? $options['hostid'] : get_request('hostid', 0);
		$this->period = !empty($options['period']) ? $options['period'] : get_request('period', ZBX_MAX_PERIOD);
		$this->stime = !empty($options['stime']) ? $options['stime'] : get_request('stime', null);
		$this->profile_idx = !empty($options['profile_idx']) ? $options['profile_idx'] : '';

		// get screenitem
		if (!empty($options['screenitem'])) {
			$this->screenitem = $options['screenitem'];
		}
		elseif (!empty($options['screenitemid'])) {
			$this->screenitem = API::ScreenItem()->get(array(
				'screenitemids' => $options['screenitemid'],
				'hostids' => $this->hostid,
				'output' => API_OUTPUT_EXTEND,
				'fillReals' => true
			));
			$this->screenitem = reset($this->screenitem);
		}

		if (empty($this->screenid) && !empty($this->screenitem)) {
			$this->screenid = $this->screenitem['screenid'];
		}
	}

	public function updateProfile() {
		if (!empty($this->profile_idx)) {
			if (!empty($this->period) && $this->period >= ZBX_MIN_PERIOD) {
				CProfile::update($this->profile_idx.'.period', $this->period, PROFILE_TYPE_INT, $this->screenid);
			}
			if (!empty($this->stime)) {
				CProfile::update($this->profile_idx.'.stime', $this->stime, PROFILE_TYPE_STR, $this->screenid);
			}
		}
	}

	public function getId() {
		if (empty($this->id) && !empty($this->screenitem)) {
			$this->id = 'flickerfreescreen_'.$this->screenitem['screenitemid'];
		}

		return $this->id;
	}

	public function getOutput($item = null, $insertFlickerfreeJs = true) {
		if ($insertFlickerfreeJs) {
			$this->insertFlickerfreeJs();
		}

		if ($this->mode == SCREEN_MODE_EDIT) {
			return new CDiv(array($item, BR(), new CLink(_('Change'), $this->action)), null, $this->getId());
		}
		else {
			return new CDiv($item, null, $this->getId());
		}
	}

	public function insertFlickerfreeJs() {
		if ($this->is_flickerfree) {
			$data = array(
				'screenitemid' => $this->screenitem['screenitemid'],
				'screenid' => $this->screenitem['screenid'],
				'resourcetype' => $this->screenitem['resourcetype'],
				'mode' => $this->mode,
				'refreshInterval' => CWebUser::$data['refresh'],
				'hostid' => $this->hostid,
				'period' => $this->period,
				'stime' => $this->stime,
				'profile_idx' => $this->profile_idx
			);
			zbx_add_post_js('flickerfreeScreen.add('.zbx_jsvalue($data).');');
		}
	}
}
