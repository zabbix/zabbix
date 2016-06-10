<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * Is templated screen
	 *
	 * @var bool
	 */
	public $isTemplatedScreen;

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
	 * Screen parameters with default values.
	 *
	 * @var array
	 */
	public $parameters;

	/**
	 * Screen parameters config.
	 *
	 * @var array
	 */
	public $required_parameters;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param boolean	$options['isFlickerfree']
	 * @param string	$options['pageFile']
	 * @param int		$options['mode']
	 * @param int		$options['timestamp']
	 * @param int		$options['resourcetype']
	 * @param bool		$options['isTemplatedScreen']
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
	public function __construct(array $options = []) {
		$this->parameters = [
			'isFlickerfree'		=> true,
			'mode'				=> SCREEN_MODE_SLIDESHOW,
			'timestamp'			=> time(),
			'resourcetype'		=> null,
			'isTemplatedScreen'	=> false,
			'screenid'			=> null,
			'action'			=> null,
			'groupid'			=> null,
			'hostid'			=> 0,
			'pageFile'			=> null,
			'profileIdx'		=> '',
			'profileIdx2'		=> null,
			'updateProfile'		=> true,
			'timeline'			=> null,
			'dataId'			=> null
		];

		$this->resourcetype = array_key_exists('resourcetype', $options) ? $options['resourcetype'] : null;

		$this->required_parameters = [
			'isFlickerfree'		=> true,
			'mode'				=> true,
			'timestamp'			=> true,
			'resourcetype'		=> true,
			'dataId'			=> true
		];

		switch ($this->resourcetype) {
			case SCREEN_RESOURCE_HTTPTEST_DETAILS:
				$this->required_parameters += [
					'isTemplatedScreen'	=> false,
					'screenid'			=> false,
					'action'			=> false,
					'groupid'			=> false,
					'hostid'			=> false,
					'pageFile'			=> false,
					'profileIdx'		=> false,
					'profileIdx2'		=> true,
					'updateProfile'		=> false,
					'timeline'			=> false
				];
				break;

			case SCREEN_RESOURCE_DISCOVERY:
				$this->required_parameters += [
					'isTemplatedScreen'	=> false,
					'screenid'			=> false,
					'action'			=> false,
					'groupid'			=> false,
					'hostid'			=> false,
					'pageFile'			=> false,
					'profileIdx'		=> false,
					'profileIdx2'		=> false,
					'updateProfile'		=> false,
					'timeline'			=> false
				];
				break;

			default:
				$this->required_parameters += [
					'isTemplatedScreen'	=> true,
					'screenid'			=> true,
					'action'			=> true,
					'groupid'			=> true,
					'hostid'			=> true,
					'pageFile'			=> true,
					'profileIdx'		=> true,
					'profileIdx2'		=> true,
					'updateProfile'		=> true,
					'timeline'			=> true
				];
		}

		// Get screenitem if its required or resource type is null.
		$this->screenitem = [];
		if (array_key_exists('screenitem', $options) && is_array($options['screenitem'])) {
			$this->screenitem = $options['screenitem'];
		}
		elseif (array_key_exists('screenitemid', $options) && $options['screenitemid'] > 0) {
			$screenitem_output = ['screenitemid', 'screenid', 'resourcetype', 'resourceid', 'width', 'height',
				'elements', 'halign', 'valign', 'style', 'url', 'dynamic', 'sort_triggers', 'application',
				'max_columns'
			];

			if ($this->hostid != 0) {
				$this->screenitem = API::TemplateScreenItem()->get([
					'output' => $screenitem_output,
					'screenitemids' => $options['screenitemid'],
					'hostids' => $this->hostid
				]);
			}
			else {
				$this->screenitem = API::ScreenItem()->get([
					'output' => $screenitem_output,
					'screenitemids' => $options['screenitemid']
				]);
			}

			if ($this->screenitem) {
				$this->screenitem = reset($this->screenitem);
			}
		}

		// Get resourcetype.
		if ($this->resourcetype === null && array_key_exists('resourcetype',$this->screenitem)) {
			$this->resourcetype = $this->screenitem['resourcetype'];
		}

		foreach ($this->parameters as $pname => $default_value) {
			if ($this->required_parameters[$pname]) {
				$this->$pname = array_key_exists($pname, $options) ? $options[$pname] : $default_value;
			}
		}

		// Get page file.
		if ($this->required_parameters['pageFile'] && $this->pageFile === null) {
			global $page;
			$this->pageFile = $page['file'];
		}

		// Calculate timeline.
		if ($this->required_parameters['timeline'] && $this->timeline === null) {
			$this->timeline = $this->calculateTime([
				'profileIdx' => $this->profileIdx,
				'profileIdx2' => $this->profileIdx2,
				'updateProfile' => $this->updateProfile,
				'period' => array_key_exists('period', $options) ? $options['period'] : null,
				'stime' => array_key_exists('stime', $options) ? $options['stime'] : null
			]);
		}

		// Get screenid.
		if ($this->required_parameters['screenid'] && $this->screenid === null && $this->screenitem) {
			$this->screenid = $this->screenitem['screenid'];
		}

		// Create action URL.
		if ($this->required_parameters['action'] && $this->action === null && $this->screenitem) {
			$this->action = 'screenedit.php?form=update&screenid='.$this->screenid.'&screenitemid='.
				$this->screenitem['screenitemid'];
		}
	}

	/**
	 * Create and get unique screen id for time control.
	 *
	 * @return string
	 */
	public function getDataId() {
		if ($this->dataId === null) {
			$this->dataId = $this->screenitem
				? $this->screenitem['screenitemid'].'_'.$this->screenitem['screenid']
				: 1;
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
		return '&updateProfile='.(int) $this->updateProfile.'&profileIdx='.$this->profileIdx.'&profileIdx2='.
			$this->profileIdx2;
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
	public function getOutput($item = null, $insertFlickerfreeJs = true, $flickerfreeData = []) {
		if ($insertFlickerfreeJs) {
			$this->insertFlickerfreeJs($flickerfreeData);
		}

		$div = (new CDiv($item))
			->addClass('flickerfreescreen')
			->setAttribute('data-timestamp', $this->timestamp)
			->setId($this->getScreenId());

		if ($this->mode == SCREEN_MODE_EDIT) {
			$div->addItem(
				(new CDiv([
					new CLink(_('Change'), $this->action)
				]))->addClass(ZBX_STYLE_CENTER)
			);
		}

		return $div;
	}

	/**
	 * Insert javascript flicker-free screen data.
	 *
	 * @param array $data
	 */
	public function insertFlickerfreeJs(array $data = []) {
		$jsData = [
			'id' => $this->getDataId(),
			'interval' => CWebUser::$data['refresh']
		];

		$parameters = $this->parameters;

		// unset redundant parameters
		unset($parameters['isTemplatedScreen'], $parameters['action'], $parameters['dataId']);

		foreach ($parameters as $pname => $default_value) {
			if ($this->required_parameters[$pname]) {
				$jsData[$pname] = $this->$pname;
			}
		}

		if ($this->screenitem) {
			$jsData['screenitemid'] = array_key_exists('screenitemid', $this->screenitem)
				? $this->screenitem['screenitemid']
				: null;
		}

		if ($this->required_parameters['screenid']) {
			$jsData['screenid'] = array_key_exists('screenid', $this->screenitem)
				? $this->screenitem['screenid']
				: $this->screenid;
		}

		if ($data) {
			$jsData['data'] = $data;
		}

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
	public static function calculateTime(array $options = []) {
		if (!array_key_exists('updateProfile', $options)) {
			$options['updateProfile'] = true;
		}
		if (empty($options['profileIdx2'])) {
			$options['profileIdx2'] = 0;
		}

		// Show only latest data without update is set only period.
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
				show_error_message(_n('Minimum time period to display is %1$s minute.',
					'Minimum time period to display is %1$s minutes.',
					(int) ZBX_MIN_PERIOD / SEC_PER_MIN
				));
				$options['period'] = ZBX_MIN_PERIOD;
			}
			elseif ($options['period'] > ZBX_MAX_PERIOD) {
				show_error_message(_n('Maximum time period to display is %1$s day.',
					'Maximum time period to display is %1$s days.',
					(int) ZBX_MAX_PERIOD / SEC_PER_DAY
				));
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
				$stimeNow = zbxAddSecondsToUnixtime(SEC_PER_YEAR, $options['stime']);
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

		return [
			'period' => $options['period'],
			'stime' => date(TIMESTAMP_FORMAT, zbxDateToTime($options['stime'])),
			'stimeNow' => ($stimeNow === null)
				? date(TIMESTAMP_FORMAT, zbxAddSecondsToUnixtime(SEC_PER_YEAR, $options['stime']))
				: $stimeNow,
			'starttime' => date(TIMESTAMP_FORMAT, $time - ZBX_MAX_PERIOD),
			'usertime' => $usertime,
			'isNow' => $isNow
		];
	}
}
