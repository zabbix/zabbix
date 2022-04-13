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


class CControllerDashboardWidgetCheck extends CController {

	private $context;

	protected function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput() {
		$fields = [
			'templateid' =>	'db dashboard.templateid',
			'type' =>		'required|string',
			'name' =>		'required|string',
			'fields' =>		'json'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->context = $this->hasInput('templateid')
				? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
				: CWidgetConfig::CONTEXT_DASHBOARD;

			if (!CWidgetConfig::isWidgetTypeSupportedInContext($this->getInput('type'), $this->context)) {
				error(_('Widget type is not supported in this context.'));

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

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$form = CWidgetConfig::getForm($this->getInput('type'), $this->getInput('fields', '{}'),
			($this->context === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD) ? $this->getInput('templateid') : null
		);

		$output = [];

		if ($errors = $form->validate(true)) {
			foreach ($errors as $error) {
				error($error);
			}

			$output['error']['messages'] = array_column(get_and_clear_messages(), 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
