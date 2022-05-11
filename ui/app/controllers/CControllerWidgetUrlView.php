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


class CControllerWidgetUrlView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_URL);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$error = null;

		$is_template_dashboard = ($this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD);

		// Editing template dashboard?
		if ($is_template_dashboard && !$this->hasInput('dynamic_hostid')) {
			$error = _('No data.');
		}
		else {
			$is_dynamic_item = ($is_template_dashboard || $fields['dynamic'] == WIDGET_DYNAMIC_ITEM);

			$dynamic_hostid = $this->getInput('dynamic_hostid', '0');

			if ($is_dynamic_item && $dynamic_hostid == 0) {
				$error = _('No host selected.');
			}
			else {
				$resolved_url = CMacrosResolverHelper::resolveWidgetURL([
					'config' => $is_dynamic_item ? 'widgetURL' : 'widgetURLUser',
					'url' => $fields['url'],
					'hostid' => $is_dynamic_item ? $dynamic_hostid : '0'
				]);

				$fields['url'] = $resolved_url ? $resolved_url : $fields['url'];
			}

			if (!$error && !CHtmlUrlValidator::validate($fields['url'], ['allow_user_macro' => false])) {
				$error = _s('Provided URL "%1$s" is invalid.', $fields['url']);
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'url' => [
				'url' => $fields['url'],
				'error' => $error
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'config' => [
				'iframe_sandboxing_enabled' => CSettingsHelper::get(CSettingsHelper::IFRAME_SANDBOXING_ENABLED),
				'iframe_sandboxing_exceptions' => CSettingsHelper::get(CSettingsHelper::IFRAME_SANDBOXING_EXCEPTIONS)
			]
		]));
	}
}
