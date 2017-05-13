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

		$this->widget_config = new CWidgetConfig();
	}

	protected function checkInput() {
		$fields = [
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

				// Field array should contain widget type
				if (!array_key_exists('type', $widget_fields)) {
					error(_('No widget type')); // TODO VM: (?) improve message
					$ret = false;
				}
				// We will work only with known widget types
				// TODO VM: (?) what should happen if I will open dashboard with widget, here I don't have right for this widget type.
				elseif (!in_array($widget_fields['type'], $this->widget_config->getKnownWidgetTypes($this->getUserType()))) {
					error(_('Unknown widget type')); // TODO VM: (?) improve message
					$ret = false;
				}
			}
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['body' => CJs::encodeJson('')]));
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

		// TODO VM: (?) get current widget fields data from JS
		//			(1) by getting current values from widget config, we can set default values to same fields in different widget type
		//			(2) it may add unreasonable complaxity
//		// get data for current widget - in case we are switching between types, and no fields for widget are given
//		if ($this->hasInput('widgetid')) {
//			$dialogue['widgetid'] = $this->getInput('widgetid');
//			$widget = $this->widget_config->getConfig($dialogue['widgetid']);
//		}

		// Get fields from dialogue form
		$dialogue_fields = $this->hasInput('fields') ? $this->getInput('fields') : [];

		// Take default values, replce with saved ones, replace with selected in dialogue
		$fields_data = array_merge($fields, $widget, $dialogue_fields);
		$dialogue['form'] = $this->widget_config->getForm($fields_data, $this->getUserType());
		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => $dialogue,
		]));
	}
}
