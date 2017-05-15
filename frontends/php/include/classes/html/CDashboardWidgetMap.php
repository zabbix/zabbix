<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CDashboardWidgetMap {
	protected $widgetid;
	protected $parameters;
	protected $required_parameters;
	protected $screenitem;

	public function __construct(array $options = [], $widgetid = null) {
		$this->parameters = [
			'mode'				=> SCREEN_MODE_SLIDESHOW,
			'timestamp'			=> time(),
			'isTemplatedScreen'	=> false,
			'screenid'			=> null,
			'action'			=> null,
			'groupid'			=> null,
			'hostid'			=> 0,
			'pageFile'			=> null,
			'profileIdx'		=> '',
			'profileIdx2'		=> null,
			'updateProfile'		=> true,
			'dataId'			=> null,
			'page'				=> 1
		];

		$this->required_parameters = [
			'mode'				=> true,
			'timestamp'			=> true,
			'dataId'			=> true,
			'isTemplatedScreen'	=> true,
			'screenid'			=> true,
			'action'			=> true,
			'groupid'			=> true,
			'hostid'			=> true,
			'pageFile'			=> true,
			'profileIdx'		=> true,
			'profileIdx2'		=> true,
			'updateProfile'		=> true,
			'page'				=> false
		];

		$this->widgetid = $widgetid;

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
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$severity = null;

		if (array_key_exists('severity_min', $this->screenitem)) {
			$severity = $this->screenitem['severity_min'];
		}

		$mapData = CMapHelper::get(2, $severity);

		$mapData['container'] = "#map_{$this->screenitem['screenitemid']}";
		$this->getFlickerfreeJs($mapData);

		$output = [
			(new CDiv())
				->setId('map_'.$this->screenitem['screenitemid'])
				->addStyle('width:'.$mapData['canvas']['width'].'px;')
				->addStyle('height:'.$mapData['canvas']['height'].'px;')
				->addStyle('overflow:hidden;')
		];

		$div = (new CDiv($output))
			->addClass('map-container')
			->addClass('flickerfreescreen')
			->setId($this->getScreenId())
			->setAttribute('data-timestamp', $this->timestamp)
			->addStyle('position: relative;');

		return $div;
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
	 * Build javascript flicker-free screen data.
	 *
	 * @param array $data
	 */
	public function getFlickerfreeJs(array $data = []) {
		$jsData = [
			'id' => $this->getDataId(),
			'interval' => CWebUser::$data['refresh'],
			'resourcetype' => SCREEN_RESOURCE_MAP
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

		return 'window.flickerfreeScreen.add('.zbx_jsvalue($jsData).');';
	}

	/**
	 * Create and get unique screen id for time control.
	 *
	 * @return string
	 */
	public function getDataId() {
		return 'map_'.$this->widgetid;
	}
}
