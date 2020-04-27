<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * Controller to sanitize widget fields before copied widget is created.
 *
 * This involves unset of unaccessible values specified in copied widget fields.
 */
class CControllerDashboardWidgetSanitize extends CControllerDashboardAbstract {

	protected function checkInput() {
		$fields = [
			'type' => 'string|required',
			'fields' => 'json'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sanitizable_types = [ZBX_WIDGET_FIELD_TYPE_GROUP, ZBX_WIDGET_FIELD_TYPE_HOST, ZBX_WIDGET_FIELD_TYPE_ITEM,
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, ZBX_WIDGET_FIELD_TYPE_GRAPH, ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
			ZBX_WIDGET_FIELD_TYPE_MAP
		];

		$form = CWidgetConfig::getForm($this->getInput('type'), $this->getInput('fields', '{}'));
		$resource_fields = [];
		$output = [
			'fields' => []
		];

		foreach ($form->getFields() as $field) {
			$value = $field->getValue();
			$is_value_set = ($value !== null && $value != $field->getDefault());

			if (!$is_value_set) {
				continue;
			}
			elseif (in_array($field->getSaveType(), $sanitizable_types)) {
				// Resource fields are prepared for sanitization.
				if (!is_array($value)) {
					$value = [$value];
				}

				foreach ($value as $val) {
					$resource_fields[] = [
						'type' => $field->getSaveType(),
						'name' => $field->getName(),
						'value' => $val
					];
				}
			}
			else {
				// Non resource fields are returned unchanged.
				$output['fields'][$field->getName()] = $value;
			}
		}

		if ($resource_fields) {
			$resource_fields = $this->unsetInaccessibleFields([['fields' => $resource_fields]]);

			foreach ($resource_fields[0]['fields'] as $field) {
				$output['fields'][$field['name']][] = $field['value'];
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
