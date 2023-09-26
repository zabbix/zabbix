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
 * Controller for sanitizing fields of widgets before pasting previously copied widgets or dashboard pages.
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

				$this->widgets_data[] = [
					'widget' => $widget,
					'fields' => $widget_input['fields']
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
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$widgets_fields = [];

		if (!$this->hasInput('templateid')) {
			$widgets_api = [];

			foreach ($this->widgets_data as $index => $widget_data) {
				if ($widget_data !== null) {
					$form = $widget_data['widget']->getForm($widget_data['fields'],
						$this->hasInput('templateid') ? $this->getInput('templateid') : null
					);

					$widgets_api[$index] = ['fields' => $form->fieldsToApi()];
				}
			}

			$widgets_api = CDashboardHelper::unsetInaccessibleFields([['widgets' => $widgets_api]]);
			$widgets_api = $widgets_api[0]['widgets'];

			foreach ($this->widgets_data as $index => $widget_data) {
				if ($widget_data !== null) {
					$widgets_fields[$index] = CDashboardHelper::constructWidgetFields($widgets_api[$index]['fields']);
				}
			}
		}
		else {
			foreach ($this->widgets_data as $index => $widget_data) {
				if ($widget_data !== null) {
					$widgets_fields[$index] = $widget_data['fields'];
				}
			}
		}

		$output = [
			'widgets' => []
		];

		foreach ($this->widgets_data as $index => $widget_data) {
			if ($widget_data === null) {
				$output['widgets'][$index] = null;

				continue;
			}

			$form = $widget_data['widget']->getForm($widgets_fields[$index],
				$this->hasInput('templateid') ? $this->getInput('templateid') : null
			);
			$form->validate();

			$output['widgets'][$index] = [
				'fields' => $form->getFieldsValues()
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}
}
