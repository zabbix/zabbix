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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetUrlView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>			'string',
			'fields' =>			'required|array',
			'dynamic_hostid' =>	'db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $fields
			 * @var string $fields['url']              (optional)
			 * @var int    $fields['dynamic']          (optional)
			 */
			// TODO VM: validate fields
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {

		$error = null;

		// Default values
		$default = [
			'url' => '',
			'hostid' => '0',
			'isTemplatedDashboard' => false, // TODO VM: will dashboards be templated?
			'dynamic' => WIDGET_SIMPLE_ITEM
		];

		$data = $this->getInput('fields');

		if ($this->hasInput('dynamic_hostid')) {
			$data['hostid'] = $this->getInput('dynamic_hostid');
		}

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		if ($data['dynamic'] == WIDGET_DYNAMIC_ITEM && $data['hostid'] == 0) {
			$error = _('No host selected.');
		}
		else {
			$resolveHostMacros = ($data['dynamic'] == WIDGET_DYNAMIC_ITEM || $data['isTemplatedDashboard']);

			$resolved_url = CMacrosResolverHelper::resolveWidgetURL([
				'config' => $resolveHostMacros ? 'widgetURL' : 'widgetURLUser',
				'url' => $data['url'],
				'hostid' => $resolveHostMacros ? $data['hostid'] : '0'
			]);

			$data['url'] = $resolved_url ? $resolved_url : $data['url'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_URL]),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'url' => [
				'url' => $data['url'],
				'error' => $error
			]
		]));
	}
}
