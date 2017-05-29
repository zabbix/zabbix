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
			'dashboardid' =>	'db dashboard.dashboardid',
			'userid' =>			'db dashboard.userid',
			'name' =>			'not_empty',
			'widgets' =>		'array',
			'save' =>			'required|in '.implode(',', [WIDGET_CONFIG_DONT_SAVE, WIDGET_CONFIG_DO_SAVE])
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $widgets
			 * @var string $widget[]['widgetid']        (optional)
			 * @var array  $widget[]['pos']             (optional)
			 * @var int    $widget[]['pos']['row']
			 * @var int    $widget[]['pos']['col']
			 * @var int    $widget[]['pos']['height']
			 * @var int    $widget[]['pos']['width']
			 * @var string $widget[]['type']
			 * @var string $widget[]['name']
			 * @var array  $widget[]['fields']
			 * @var string $widget[]['fields'][<name>]  (optional)
			 */
			foreach ($this->getInput('widgets', []) as $index => $widget) {
				// TODO VM: check widgetid - if present in $widget, must be existing widget id
				// TODO VM: (?) instead of adding optional fields, it may be more consistent to add additinal controller
				//			for checking single widget. That will make this controller more straigt forward.
				if ($this->getInput('save') == WIDGET_CONFIG_DO_SAVE) {
					if (!array_key_exists('pos', $widget)) {
						error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
							_s('the parameter "%1$s" is missing', 'pos')
						));
						$ret = false;
					}
					else {
						foreach (['row', 'col', 'height', 'width'] as $field) {
							if (!array_key_exists($field, $widget['pos'])) {
								error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.'][pos]',
									_s('the parameter "%1$s" is missing', $field)
								));
								$ret = false;
							}
						}
					}
				}

				if (!array_key_exists('type', $widget)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
						_s('the parameter "%1$s" is missing', 'type')
					));
					$ret = false;
					break;
				}

				if (!array_key_exists('name', $widget)) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
						_s('the parameter "%1$s" is missing', 'name')
					));
					$ret = false;
				}

				// AJAX is not sending empty elements,
				// absence of 'fields' element means, there are no fields for this widget
				if (!array_key_exists('fields', $widget)) {
					$widget['fields'] = [];
				}
				$widget['form'] = CWidgetConfig::getForm($widget['type'], $widget['fields']);
				unset($widget['fields']);

				if (($errors = $widget['form']->validate()) !== []) {
					// TODO VM: Add widget name to each error message.
					foreach ($errors as $key => $error) {
						// TODO VM: widgetid will not be present in case of new widget - need to find solution
						error(_s("Error in widget (id='%s'): %s.", $widget['widgetid'], $error)); // TODO VM: (?) improve error message
					}

					$ret = false;
				}

				$this->widgets[] = $widget;
			}
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				// TODO AV: "errors" => "messages"
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
		$data = [];

		if ($this->getInput('save') == WIDGET_CONFIG_DO_SAVE) {
			$dashboard = [
				'widgets' => []
			];
			if ($this->hasInput('dashboardid')) {
				$dashboard['dashboardid'] = $this->getInput('dashboardid');
			}
			if ($this->hasInput('name')) {
				$dashboard['name'] = $this->getInput('name');
			}
			if ($this->hasInput('userid')) {
				$dashboard['userid'] = $this->getInput('userid');
			}

			foreach ($this->widgets as $widget) {
				$upd_widget = [];
				if (array_key_exists('widgetid', $widget) // widgetid exist during clone action also
					&& array_key_exists('dashboardid', $dashboard)) {
					$upd_widget['widgetid'] = $widget['widgetid'];
				}

				$upd_widget += [
					'row' => $widget['pos']['row'],
					'col' => $widget['pos']['col'],
					'height' => $widget['pos']['height'],
					'width' => $widget['pos']['width'],
					'type' => $widget['type'],
					'name' => $widget['name'],
					'fields' => $this->prepareFields($widget['form']),
				];

				$dashboard['widgets'][] = $upd_widget;
			}

			if (array_key_exists('dashboardid', $dashboard)) {
				$result = (bool) API::Dashboard()->update([$dashboard]);
				$message = _('Dashboard updated');
				$error_msg =  _('Failed to update dashboard');
			}
			else {
				$result = (bool) API::Dashboard()->create([$dashboard]);
				$message = _('Dashboard created');
				$error_msg = _('Failed to create dashboard');
			}

			if ($result) {
				$data['redirect'] = (new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.view')
					->setArgument('dashboardid', $result['dashboardids'][0])
					->getUrl();
				// @TODO should be moved from here to base logic by ZBXNEXT-3892
				CSession::setValue('messageOk', $message);
			}
			else {
				// TODO AV: improve error messages
				if (!hasErrorMesssages()) {
					error($error_msg);
				}
			}
		}

		if (($messages = getMessages()) !== null) {
			// TODO AV: "errors" => "messages"
			$data['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($data)]));
	}

	/**
	 * Prepares widget fields for saving.
	 *
	 * @param CWidgetForm $form  form object with widget fields
	 *
	 * @return array  Array of widget fields ready for saving in API
	 */
	protected function prepareFields($form) {
		// TODO VM: (?) may be good idea to move it to CWidgetForm
		$fields = [];

		foreach ($form->getFields() as $field) {
			$save_type = $field->getSaveType();

			$widget_field = [
				'type' => $save_type,
				'name' => $field->getName()
			];
			$widget_field[CWidgetConfig::getApiFieldKey($save_type)] = $field->getValue();

			$fields[] = $widget_field;
		}

		return $fields;
	}
}
