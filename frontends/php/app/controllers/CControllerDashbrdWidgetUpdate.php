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

class CControllerDashbrdWidgetUpdate extends CController {

	private $widgets;

	public function __construct() {
		parent::__construct();

		$this->widgets = [];
	}

	protected function checkInput() {
		$fields = [
			'dashboard_id' =>	'required|db dashboard.dashboardid',
			'widgets' =>		'required|array',
			'save' =>			'required|in '.implode(',', [WIDGET_CONFIG_DONT_SAVE, WIDGET_CONFIG_DO_SAVE])
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $widgets
			 * @var string $widget[]['widgetid']
			 * @var array  $widget[]['pos']            (optional)
			 * @var int    $widget[]['pos']['row']
			 * @var int    $widget[]['pos']['col']
			 * @var int    $widget[]['pos']['height']
			 * @var int    $widget[]['pos']['width']
			 * @var array  $widget[]['fields']
			 */
			foreach ($this->getInput('widgets') as $widget) {
				if (array_key_exists('fields', $widget)) {
					$widget_fields = $widget['fields'];

					if (!array_key_exists('type', $widget_fields)) {
						error(_('No widget type')); // TODO VM: (?) improve message
						$ret = false;
						break; // no need to check fields, if widget type is unknown
					}

					$widget['form'] = CWidgetConfig::getForm($widget_fields);
					unset($widget['fields']);

					$errors = $widget['form']->validate();
					if (!empty($errors)) {
						// Add widget name to each error message.
						foreach ($errors as $key => $value) {
							$errors[$key] = _s("Error in widget (id='%s'): %s", $widget['widgetid'], $value); // TODO VM: (?) improve error message
						}

						error($errors);
						$ret = false;
					}
				}
				$this->widgets[] = $widget;
			}
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$return = [];
		$save = (int)$this->getInput('save');
		if ($save === WIDGET_CONFIG_DO_SAVE) {
			$dashboard = [];
			$dashboard['dashboardid'] = $this->getInput('dashboard_id');
			$dashboard['widgets'] = [];

			foreach ($this->widgets as $widget) {
				$widget_to_save = [];
				$widget_to_save['widgetid'] = $widget['widgetid'];

				if (array_key_exists('pos', $widget)) {
					$widget_to_save['row'] = $widget['pos']['row'];
					$widget_to_save['col'] = $widget['pos']['col'];
					$widget_to_save['height'] = $widget['pos']['height'];
					$widget_to_save['width'] = $widget['pos']['width'];
				}

				if (array_key_exists('form', $widget)) {
					$prepared = $this->prepareFields($widget['form']);
					if (array_key_exists('type', $prepared)) {
						$widget_to_save['type'] = $prepared['type'];
					}
					if (array_key_exists('name', $prepared)) {
						$widget_to_save['name'] = $prepared['name'];
					}
					if (array_key_exists('fields', $prepared)) {
						$widget_to_save['fields'] = $prepared['fields'];
					}
				}

				$dashboard['widgets'][] = $widget_to_save;
			}

			$result = (bool) API::Dashboard()->update([$dashboard]);

			if ($result) {
				$return['messages'] = makeMessageBox(true, [], _('Dashboard updated'))->toString();
			}
			else {
				if (!hasErrorMesssages()) {
					error(_('Failed to update dashboard')); // In case of unknown error
				}
				$return['errors'] = getMessages()->toString();
			}
		}
		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($return)]));
	}

	/**
	 * Prepares widget fields for saving
	 * @param CWidgetForm $form form object with widget fields
	 *
	 * @return array With keys 'type', 'name', 'fields'
	 */
	protected function prepareFields($form) {
		$ret = [];
		$fields = $form->getFields();
		foreach ($fields as $field){
			$name = $field->getName();
			if ($name === 'type') {
				$ret['type'] = $field->getValue();
			} elseif ($name === 'name') {
				$ret['name'] = $field->getValue();
			} else {
				$field_to_save = [];
				$field_to_save['type'] = $field->getSaveType();
				$field_to_save['name'] = $field->getName();

				$field_key = CWidgetConfig::getApiFieldKey($field_to_save['type']);
				$field_to_save[$field_key] = $field->getValue();

				if (!array_key_exists('fields', $ret)) {
					$ret['fields'] = [];
				}
				$ret['fields'][] = $field_to_save;
			}
		}
		return $ret;
	}
}
