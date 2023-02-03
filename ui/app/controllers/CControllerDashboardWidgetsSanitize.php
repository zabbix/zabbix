<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


use Zabbix\Core\CModule;

/**
 * Controller for sanitizing fields of widgets before pasting previously copied widget.
 */
class CControllerDashboardWidgetsSanitize extends CController {

	private array $widgets_data = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'templateid' =>	'db dashboard.templateid',
			'widgets' =>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			foreach ($this->getInput('widgets', []) as $widget) {
				$validator = new CNewValidator($widget, [
					'type' =>	'required|string',
					'fields' =>	'required|array'
				]);

				foreach ($validator->getAllErrors() as $error) {
					error($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;

					break;
				}

				$widget_input = $validator->getValidInput();

				$widget = APP::ModuleManager()->getModule($widget_input['type']);

				if ($widget === null || $widget->getType() !== CModule::TYPE_WIDGET) {
					$this->widgets_data[] = null;

					continue;
				}

				if ($this->hasInput('templateid') && !$widget->hasTemplateSupport()) {
					error(_('Widget type is not supported in this context.'));

					$ret = false;

					break;
				}

				$this->widgets_data[] = [
					'type' => $widget_input['type'],
					'form' => $widget->getForm($widget_input['fields'],
						$this->hasInput('templateid') ? $this->getInput('templateid') : null
					)
				];
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$widgets = [];

		foreach ($this->widgets_data as $index => $widget_data) {
			if ($widget_data !== null) {
				$widgets[$index] = ['fields' => $widget_data['form']->fieldsToApi()];
			}
		}

		if (!$this->hasInput('templateid')) {
			$widgets = CDashboardHelper::unsetInaccessibleFields([['widgets' => $widgets]]);
			$widgets = $widgets[0]['widgets'];
		}

		$output = [
			'widgets' => []
		];

		foreach ($this->widgets_data as $index => $widget_data) {
			if ($widget_data === null) {
				$output['widgets'][$index] = null;

				continue;
			}

			$output_fields = [];

			foreach ($widgets[$index]['fields'] as $field) {
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

			$output['widgets'][$index]['fields'] = $output_fields;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
