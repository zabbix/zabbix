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

	protected function checkInput() {
		$fields = [
			'widgetid' =>	'db widget.widgetid',
			'type' =>		'in '.implode(',', array_keys(CWidgetConfig::getKnownWidgetTypes())),
			'name' =>		'string',
			'fields' =>		'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var string fields[<name>]  (optional)
			 */
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
		$type = $this->getInput('type', WIDGET_CLOCK);
		$form = CWidgetConfig::getForm($type, $this->getInput('fields', []));

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => [
				'type' => $this->getInput('type', WIDGET_CLOCK),
				'name' => $this->getInput('name', ''),
				'form' => $form,
			],
			'captions' => $this->getCaptions($form)
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources
	 *
	 * @param CWidgetForm $form
	 *
	 * @return array
	 */
	private function getCaptions($form) {
		$captions = [
			'groups' => [],
			'items' => []
		];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldGroup) {
				foreach ($field->getValue(true) as $groupid) {
					$captions['groups'][$groupid] = true;
				}
			}
			elseif ($field instanceof CWidgetFieldItem) {
				$captions['items'][$field->getValue(true)] = '';
			}
		}
		unset($captions['items'][0]);

		foreach ($captions as $resource => $list) {
			if (!$list) {
				continue;
			}

			switch ($resource) {
				case 'groups':
					$groups = API::HostGroup()->get([
						'output' => ['groupid', 'name'],
						'groupids' => array_keys($list)
					]);

					$captions['groups'] = [];

					foreach ($groups as $group) {
						$captions['groups'][] = [
							'id' => $group['groupid'],
							'name' => $group['name']
						];
					}
					break;

				case 'items'::
					$items = API::Item()->get([
						'output' => ['itemid', 'hostid', 'key_', 'name'],
						'selectHosts' => ['name'],
						'itemids' => array_keys($list),
						'webitems' => true
					]);

					if ($items) {
						$items = CMacrosResolverHelper::resolveItemNames($items);

						foreach ($items as $item) {
							$captions['items'][$item['itemid']] =
								$item['hosts'][0]['name'].NAME_DELIMITER.$item['name_expanded'];
						}
					}
					break;
			}
		}

		return $captions;
	}
}
