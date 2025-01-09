<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use Zabbix\Core\{
	CModule,
	CWidget
};

/**
 * Controller for strict validation of widget configuration form.
 */
class CControllerDashboardWidgetCheck extends CController {

	private ?CWidget $widget = null;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>		'required|string',
			'fields' =>		'array',
			'templateid' =>	'db dashboard.templateid',
			'name' =>		'required|string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$widget = APP::ModuleManager()->getModule($this->getInput('type'));

			if ($widget !== null && $widget->getType() === CModule::TYPE_WIDGET) {
				$this->widget = $widget;
			}
			else {
				error(_('Inaccessible widget type.'));

				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$form = $this->widget->getForm($this->getInput('fields', []),
			$this->hasInput('templateid') ? $this->getInput('templateid') : null
		);

		$output = [];

		if ($errors = $form->validate(true)) {
			foreach ($errors as $error) {
				error($error);
			}

			$output['error']['messages'] = array_column(get_and_clear_messages(), 'message');
		}
		else {
			$output['fields'] = $form->getFieldsValues();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
