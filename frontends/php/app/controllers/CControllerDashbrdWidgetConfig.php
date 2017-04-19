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


class CControllerDashbrdWidgetConfig extends CController {

	private $widget_config;

	public function __construct() {
		parent::__construct();

		$this->widget_config = new CWidgetConfig(); // TODO VM: maybe better to convert to static functions
	}

	protected function checkInput() {
		$fields = [
			'widgetid'		=>	'', // TODO VM: in db.widget
			'fields'		=>	'array',
		];

		$ret = $this->validateInput($fields);
		if ($ret) {
			/*
			 * @var string	fields['type']
			 * @var int		fields['time_type']			(optional)
			 * @var string	fields['itemid']			(optional)
			 * @var string	fields['caption']			(optional)
			 * @var string	fields['url']				(optional)
			 */
			if ($this->hasInput('fields')) {
				$widget_fields = $this->getInput('fields');

				// Each field array should contain widget type
				if (!array_key_exists('type', $widget_fields)) {
					$ret = false;
				}
				// We will work only with known widget types
				elseif (!in_array($widget_fields['type'], $this->widget_config->getKnownWidgetTypes($this->getUserType()))) {
					$ret = false;
				}
				// TODO VM: validation
			}
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['dialogue' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$widget = [];
		$dialogue = [];

		// default fields data
		$fields = [
			'type' => WIDGET_CLOCK,
		];

		// get data for current widget - in case we are switching between types, and no fields for widget are given
		if ($this->hasInput('widgetid')) {
			$dialogue['widgetid'] = $this->getInput('widgetid');
			$widget = $this->widget_config->getConfig($dialogue['widgetid']);
		}

		// Get fields from dialogue form
		$dialogue_fields = $this->hasInput('fields') ? $this->getInput('fields') : [];

		// Take default values, replce with saved ones, replace with selected in dialogue
		$dialogue['fields'] = array_merge($fields, $widget, $dialogue_fields);

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => $dialogue,
			'known_widget_types_w_names' => $this->widget_config->getKnownWidgetTypesWNames($this->getUserType())
		]));
	}
}
