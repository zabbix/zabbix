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

class CControllerWidgetSysmapView extends CController {

	private $form;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		// TODO VM: delete comment. Removed widgetid, becuase it is no longer used, after introduction of uniqueid.
		$fields = [
			'name'			=>	'string',
			'uniqueid'		=>	'required',
			'fullscreen'	=>	'in 0,1',
			'fields'		=>	'array'
//			'storage'		=>	'array' // TODO VM: implement for previous_maps
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $fields
			 * @var int    $fields['source_type']
			 * @var string $fields['filter_widget_reference']
			 * @var id     $fields['sysmapid']
			 * @var array  $storage
			 * @var string $storage['previous_maps']
			 */
			$this->form = CWidgetConfig::getForm(WIDGET_SYSMAP, $this->getInput('fields', []));
			if (!empty($errors = $this->form->validate())) {
				$ret = false;
			}

			// TODO VM: implement validation for previous_maps in storage
		}

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'][] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$fields = $this->form->getFieldsData();
		$storage = $this->getInput('storage', []);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_SYSMAP]),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'previous_maps' => array_key_exists('previous_maps', $storage) ? $storage['previous_maps'] : '',
			'fullscreen' => getRequest('fullscreen', 0),
			'uniqueid' => getRequest('uniqueid'),
			'fields' => $fields
		]));
	}
}
