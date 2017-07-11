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


class CControllerWidgetUrlView extends CController {

	private $form;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>			'string',
			'fields' =>			'array',
			'dynamic_hostid' =>	'db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $fields
			 * @var string $fields['url']      (optional)
			 * @var int    $fields['dynamic']  (optional)
			 */
			$this->form = CWidgetConfig::getForm(WIDGET_URL, $this->getInput('fields', []));

			if ($errors = $this->form->validate()) {
				$ret = false;
			}
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
		$fields = $this->form->getFieldsData();
		$error = null;
		$dynamic_hostid = $this->getInput('dynamic_hostid', '0');
		$isTemplatedDashboard = false; // TODO VM: will dashboards be templated?

		if ($fields['dynamic'] == WIDGET_DYNAMIC_ITEM && $dynamic_hostid == 0) {
			$error = _('No host selected.');
		}
		else {
			$resolveHostMacros = ($fields['dynamic'] == WIDGET_DYNAMIC_ITEM || $isTemplatedDashboard);

			$resolved_url = CMacrosResolverHelper::resolveWidgetURL([
				'config' => $resolveHostMacros ? 'widgetURL' : 'widgetURLUser',
				'url' => $fields['url'],
				'hostid' => $resolveHostMacros ? $dynamic_hostid : '0'
			]);

			$fields['url'] = $resolved_url ? $resolved_url : $fields['url'];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_URL]),
			'url' => [
				'url' => $fields['url'],
				'error' => $error
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
