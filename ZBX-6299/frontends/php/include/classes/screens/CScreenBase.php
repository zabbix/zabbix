<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CScreenBase {

	/**
	 * @see CScreenBuilder::isFlickerfree
	 */
	public $isFlickerfree;

	/**
	 * Page file.
	 *
	 * @var string
	 */
	public $pageFile;

	/**
	 * @see CScreenBuilder::mode
	 */
	public $mode;

	/**
	 * @see CScreenBuilder::timestamp
	 */
	public $timestamp;

	/**
	 * Resource (screen) type
	 *
	 * @var int
	 */
	public $resourcetype;

	/**
	 * Screen id
	 *
	 * @var int
	 */
	public $screenid;

	/**
	 * Screen item data
	 *
	 * @var array
	 */
	public $screenitem;

	/**
	 * Action
	 *
	 * @var string
	 */
	public $action;

	/**
	 * Group id
	 *
	 * @var int
	 */
	public $groupid;

	/**
	 * Host id
	 *
	 * @var int
	 */
	public $hostid;

	/**
	 * Time control timeline
	 *
	 * @var array
	 */
	public $timeline;

	/**
	 * @see CScreenBuilder::profileIdx
	 */
	public $profileIdx;

	/**
	 * @see CScreenBuilder::profileIdx2
	 */
	public $profileIdx2;

	/**
	 * @see CScreenBuilder::updateProfile
	 */
	public $updateProfile;

	/**
	 * Time control dom element id
	 *
	 * @var string
	 */
	public $dataId;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param boolean	$options['isFlickerfree']
	 * @param string	$options['pageFile']
	 * @param int		$options['mode']
	 * @param int		$options['timestamp']
	 * @param int		$options['resourcetype']
	 * @param int		$options['screenid']
	 * @param array		$options['screenitem']
	 * @param string	$options['action']
	 * @param int		$options['groupid']
	 * @param int		$options['hostid']
	 * @param int		$options['period']
	 * @param int		$options['stime']
	 * @param string	$options['profileIdx']
	 * @param int		$options['profileIdx2']
	 * @param boolean	$options['updateProfile']
	 * @param array		$options['timeline']
	 * @param string	$options['dataId']
	 */
	public function __construct(array $options = array()) {
		$this->isFlickerfree = isset($options['isFlickerfree']) ? $options['isFlickerfree'] : true;
		$this->mode = isset($options['mode']) ? $options['mode'] : SCREEN_MODE_SLIDESHOW;
		$this->timestamp = !empty($options['timestamp']) ? $options['timestamp'] : time();
		$this->resourcetype = isset($options['resourcetype']) ? $options['resourcetype'] : null;
		$this->screenid = !empty($options['screenid']) ? $options['screenid'] : null;
		$this->action = !empty($options['action']) ? $options['action'] : null;
		$this->groupid = !empty($options['groupid']) ? $options['groupid'] : null;
		$this->hostid = !empty($options['hostid']) ? $options['hostid'] : null;
		$this->dataId = !empty($options['dataId']) ? $options['dataId'] : null;

		// get page file
		if (!empty($options['pageFile'])) {
			$this->pageFile = $options['pageFile'];
		}
		else {
			global $page;
			$this->pageFile = $page['file'];
		}

		// calculate timeline
		$this->profileIdx = !empty($options['profileIdx']) ? $options['profileIdx'] : '';
		$this->profileIdx2 = !empty($options['profileIdx2']) ? $options['profileIdx2'] : null;
		$this->updateProfile = isset($options['updateProfile']) ? $options['updateProfile'] : true;
		$this->timeline = !empty($options['timeline']) ? $options['timeline'] : null;
		if (empty($this->timeline)) {
			$this->timeline = $this->calculateTime(array(
				'profileIdx' => $this->profileIdx,
				'profileIdx2' => $this->profileIdx2,
				'updateProfile' => $this->updateProfile,
				'period' => !empty($options['period']) ? $options['period'] : null,
				'stime' => !empty($options['stime']) ? $options['stime'] : null
			));
		}

		// get screenitem
		if (!empty($options['screenitem'])) {
			$this->screenitem = $options['screenitem'];
		}
		elseif (!empty($options['screenitemid'])) {
			if (!empty($this->hostid)) {
				$this->screenitem = API::TemplateScreenItem()->get(array(
					'screenitemids' => $options['screenitemid'],
					'hostids' => $this->hostid,
					'output' => API_OUTPUT_EXTEND
				));
			}
			else {
				$this->screenitem = API::ScreenItem()->get(array(
					'screenitemids' => $options['screenitemid'],
					'output' => API_OUTPUT_EXTEND
				));
			}

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
	 * Create and get unique screen id for time control.
	 *
	 * @return string
	 */
	public function getDataId() {
		if (empty($this->dataId)) {
			$this->dataId = !empty($this->screenitem) ? $this->screenitem['screenitemid'].'_'.$this->screenitem['screenid'] : 1;
		}

		return $this->dataId;
	}

	/**
	 * Get unique screen container id.
	 *
	 * @return string
	 */
	public function getScreenId() {
		return 'flickerfreescreen_'.$this->getDataId();
	}

	/**
	 * Get profile url params.
	 *
	 * @return string
	 */
	public function getProfileUrlParams() {
		return '&updateProfile='.(int) $this->updateProfile.'&profileIdx='.$this->profileIdx.'&profileIdx2='.$this->profileIdx2;
	}

	/**
	 * Get enveloped screen inside container.
	 *
	 * @param object	$item
	 * @param boolean	$insertFlickerfreeJs
	 * @param array		$flickerfreeData
	 *
	 * @return CDiv
	 */
	public function getOutput($item = null, $insertFlickerfreeJs = true, $flickerfreeData = array()) {
		if ($insertFlickerfreeJs) {
			$this->insertFlickerfreeJs($flickerfreeData);
		}

		if ($this->mode == SCREEN_MODE_EDIT) {
			$div = new CDiv(array($item, BR(), new CLink(_('Change'), $this->action)), 'flickerfreescreen', $this->getScreenId());
		}
		else {
			$div = new CDiv($item, 'flickerfreescreen', $this->getScreenId());
		}

		$div->setAttribute('data-timestamp', $this->timestamp);
		$div->addStyle('position: relative;');

		return $div;
	}

	/**
	 * Insert javascript flicker-free screen data.
	 *
	 * @param array $data
	 */
	public function insertFlickerfreeJs($data = array()) {
		$jsData = array(
			'id' => $this->getDataId(),
			'isFlickerfree' => $this->isFlickerfree,
			'pageFile' => $this->pageFile,
			'resourcetype' => $this->resourcetype,
			'mode' => $this->mode,
			'timestamp' => $this->timestamp,
			'interval' => CWebUser::$data['refresh'],
			'screenitemid' => !empty($this->screenitem['screenitemid']) ? $this->screenitem['screenitemid'] : null,
			'screenid' => !empty($this->screenitem['screenid']) ? $this->screenitem['screenid'] : $this->screenid,
			'groupid' => $this->groupid,
			'hostid' => $this->hostid,
			'timeline' => $this->timeline,
			'profileIdx' => $this->profileIdx,
			'profileIdx2' => $this->profileIdx2,
			'updateProfile' => $this->updateProfile,
			'data' => !empty($data) ? $data : null
		);

		zbx_add_post_js('window.flickerfreeScreen.add('.zbx_jsvalue($jsData).');');
	}

	/**
	 * Insert javascript flicker-free screen data.
	 *
	 * @static
	 *
	 * @param array		$options
	 * @param string	$options['profileIdx']
	 * @param int		$options['profileIdx2']
	 * @param boolean	$options['updateProfile']
	 * @param int		$options['period']
	 * @param string	$options['stime']
	 *
	 * @return array
	 */
	public static function calculateTime(array $options = array()) {
		if (!array_key_exists('updateProfile', $options)) {
			$options['updateProfile'] = true;
		}
		if (empty($options['profileIdx2'])) {
			$options['profileIdx2'] = 0;
		}

		// show only latest data without update is set only period
		if (!empty($options['period']) && empty($options['stime'])) {
			$options['updateProfile'] = false;
			$options['profileIdx'] = '';
		}

		// period
		if (empty($options['period'])) {
			$options['period'] = !empty($options['profileIdx'])
				? CProfile::get($options['profileIdx'].'.period', ZBX_PERIOD_DEFAULT, $options['profileIdx2'])
				: ZBX_PERIOD_DEFAULT;
		}
		else {
			if ($options['period'] < ZBX_MIN_PERIOD) {
				show_message(_n('Warning. Minimum time period to display is %1$s hour.',
						'Warning. Minimum time period to display is %1$s hours.', (int) ZBX_MIN_PERIOD / SEC_PER_HOUR));
				$options['period'] = ZBX_MIN_PERIOD;
			}
			elseif ($options['period'] > ZBX_MAX_PERIOD) {
				show_message(_n('Warning. Maximum time period to display is %1$s day.',
						'Warning. Maximum time period to display is %1$s days.', (int) ZBX_MAX_PERIOD / SEC_PER_DAY));
				$options['period'] = ZBX_MAX_PERIOD;
			}
		}
		if ($options['updateProfile'] && !empty($options['profileIdx'])) {
			CProfile::update($options['profileIdx'].'.period', $options['period'], PROFILE_TYPE_INT, $options['profileIdx2']);
		}

		// stime
		$time = time();
		$usertime = null;
		$stimeNow = null;
		$isNow = 0;

		if (!empty($options['stime'])) {
			$stimeUnix = zbxDateToTime($options['stime']);

			if ($stimeUnix > $time || zbxAddSecondsToUnixtime($options['period'], $stimeUnix) > $time) {
				$stimeNow = $options['stime'];
				$options['stime'] = date(TIMESTAMP_FORMAT, $time - $options['period']);
				$usertime = date(TIMESTAMP_FORMAT, $time);
				$isNow = 1;
			}
			else {
				$usertime = date(TIMESTAMP_FORMAT, zbxAddSecondsToUnixtime($options['period'], $stimeUnix));
				$isNow = 0;
			}

			if ($options['updateProfile'] && !empty($options['profileIdx'])) {
				CProfile::update($options['profileIdx'].'.stime', $options['stime'], PROFILE_TYPE_STR, $options['profileIdx2']);
				CProfile::update($options['profileIdx'].'.isnow', $isNow, PROFILE_TYPE_INT, $options['profileIdx2']);
			}
		}
		else {
			if (!empty($options['profileIdx'])) {
				$isNow = CProfile::get($options['profileIdx'].'.isnow', null, $options['profileIdx2']);
				if ($isNow) {
					$options['stime'] = date(TIMESTAMP_FORMAT, $time - $options['period']);
					$usertime = date(TIMESTAMP_FORMAT, $time);
					$stimeNow = date(TIMESTAMP_FORMAT, zbxAddSecondsToUnixtime(SEC_PER_YEAR, $options['stime']));

					if ($options['updateProfile']) {
						CProfile::update($options['profileIdx'].'.stime', $options['stime'], PROFILE_TYPE_STR, $options['profileIdx2']);
					}
				}
				else {
					$options['stime'] = CProfile::get($options['profileIdx'].'.stime', null, $options['profileIdx2']);
					$usertime = date(TIMESTAMP_FORMAT, zbxAddSecondsToUnixtime($options['period'], $options['stime']));
				}
			}

			if (empty($options['stime'])) {
				$options['stime'] = date(TIMESTAMP_FORMAT, $time - $options['period']);
				$usertime = date(TIMESTAMP_FORMAT, $time);
				$stimeNow = date(TIMESTAMP_FORMAT, zbxAddSecondsToUnixtime(SEC_PER_YEAR, $options['stime']));
				$isNow = 1;

				if ($options['updateProfile'] && !empty($options['profileIdx'])) {
					CProfile::update($options['profileIdx'].'.stime', $options['stime'], PROFILE_TYPE_STR, $options['profileIdx2']);
					CProfile::update($options['profileIdx'].'.isnow', $isNow, PROFILE_TYPE_INT, $options['profileIdx2']);
				}
			}
		}

		return array(
			'period' => $options['period'],
			'stime' => $options['stime'],
			'stimeNow' => !empty($stimeNow) ? $stimeNow : $options['stime'],
			'starttime' => date(TIMESTAMP_FORMAT, $time - ZBX_MAX_PERIOD),
			'usertime' => $usertime,
			'isNow' => $isNow
		);
	}

	/**
	 * Easy way to view time data.
	 *
	 * @static
	 *
	 * @param array		$options
	 * @param int		$options['period']
	 * @param string	$options['stime']
	 * @param string	$options['stimeNow']
	 * @param string	$options['starttime']
	 * @param string	$options['usertime']
	 * @param int		$options['isNow']
	 */
	public static function debugTime(array $time = array()) {
		return 'period='.zbx_date2age(0, $time['period']).', ('.$time['period'].')<br/>'.
				'starttime='.date('F j, Y, g:i a', zbxDateToTime($time['starttime'])).', ('.$time['starttime'].')<br/>'.
				'stime='.date('F j, Y, g:i a', zbxDateToTime($time['stime'])).', ('.$time['stime'].')<br/>'.
				'stimeNow='.date('F j, Y, g:i a', zbxDateToTime($time['stimeNow'])).', ('.$time['stimeNow'].')<br/>'.
				'usertime='.date('F j, Y, g:i a', zbxDateToTime($time['usertime'])).', ('.$time['usertime'].')<br/>'.
				'isnow='.$time['isNow'].'<br/>';
	}
}
