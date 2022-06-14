<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Controller for sanitizing fields of widgets before pasting previously copied widget.
 */
class CControllerDashboardWidgetsSanitize extends CController {

	private $context;
	private $widgets = [];

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'templateid' =>	'db dashboard.templateid',
			'widgets' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->context = $this->hasInput('templateid')
				? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
				: CWidgetConfig::CONTEXT_DASHBOARD;

			foreach ($this->getInput('widgets', []) as $widget) {
				$validator = new CNewValidator($widget, [
					'type' => 'required|string',
					'fields' => 'required|json'
				]);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;

					break;
				}

				$widget = $validator->getValidInput();

				if (!CWidgetConfig::isWidgetTypeSupportedInContext($widget['type'], $this->context)) {
					$ret = false;

					break;
				}

				$this->widgets[] = $widget;
			}
		}

		if (!$ret) {
			$messages = getMessages();

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'errors' => ($messages !== null) ? $messages->toString() : ''
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$widgets = [];

		foreach ($this->widgets as $index => $widget) {
			$form = CWidgetConfig::getForm($widget['type'], $widget['fields'],
				($this->context === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD) ? $this->getInput('templateid') : null
			);

			$widgets[] = ['fields' => $form->fieldsToApi()];
		}

		if ($this->context === CWidgetConfig::CONTEXT_DASHBOARD) {
			$widgets = CDashboardHelper::unsetInaccessibleFields([['widgets' => $widgets]]);
			$widgets = $widgets[0]['widgets'];
		}

		$output = [
			'widgets' => []
		];

		foreach ($widgets as $widget_index => $widget) {
			$output_fields = [];

			foreach ($widget['fields'] as $field) {

				if (array_key_exists($field['name'], $output_fields)) {
					if (!is_array($output_fields[$field['name']])) {
						$output_fields[$field['name']] = [$output_fields[$field['name']]];
					}

					$output_fields[$field['name']][] = $field['value'];
				}
				else {
					$output_fields[$field['name']] = $field['value'];
				}
			}

			$output['widgets'][$widget_index]['fields'] = $output_fields;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
