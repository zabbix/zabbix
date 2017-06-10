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


class CControllerWidgetDataOverView extends CController
{
	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>	'string',
			'fields' =>	'required|array'
		];

		$ret = $this->validateInput($fields);
		/*
		 * @var array  $fields
		 * @var array  $fields['groupids']     (optional)
		 * @var string $fields['application']  (optional)
		 * @var int    $fields['style']        (optional) in (STYLE_LEFT,STYLE_TOP)
		 */

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction()
	{
		$fields = $this->getInput('fields');
		$application = array_key_exists('application', $fields) ? $fields['application'] : '';

		$data = [
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_DATA_OVERVIEW]),
			'groupids' => array_key_exists('groupids', $fields) ? (array) $fields['groupids'] : null,
			'applicationids' => null,
			'style' => array_key_exists('style', $fields) ? $fields['style'] : STYLE_LEFT,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// application filter
		if ($application !== '') {
			$data['applicationids'] = array_keys(API::Application()->get([
				'output' => [],
				'groupids' => $data['groupids'],
				'search' => ['name' => $application],
				'preservekeys' => true
			]));
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
