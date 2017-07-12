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
require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetSystemView extends CController {

	private $form;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>	'string',
			'fields' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array        $fields
			 * @var array|string $fields['groupids']          (optional)
			 * @var array|string $fields['exclude_groupids']  (optional)
			 * @var string       $fields['problem']           (optional)
			 * @var array        $fields['severities']        (optional)
			 * @var int          $fields['maintenance']       (optional)
			 * @var int          $fields['ext_ack']           (optional)
			 */
			$this->form = CWidgetConfig::getForm(WIDGET_SYSTEM_STATUS, $this->getInput('fields', []));

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

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_SYSTEM_STATUS]),
			'filter' => [
				'groupids' => getSubGroups($fields['groupids']),
				'exclude_groupids' => getSubGroups($fields['exclude_groupids']),
				'problem' => $fields['problem'],
				'severities' => $fields['severities'],
				'maintenance' => $fields['maintenance'],
				'ext_ack' => $fields['ext_ack']
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
