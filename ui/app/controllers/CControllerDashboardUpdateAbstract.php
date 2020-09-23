<?php


abstract class CControllerDashboardUpdateAbstract extends CController {

	/*
	 * @var array  $widgets
	 * @var string $widget[]['widgetid']       (optional)
	 * @var array  $widget[]['pos']
	 * @var int    $widget[]['pos']['x']
	 * @var int    $widget[]['pos']['y']
	 * @var int    $widget[]['pos']['width']
	 * @var int    $widget[]['pos']['height']
	 * @var string $widget[]['type']
	 * @var string $widget[]['name']
	 * @var string $widget[]['fields']         (optional) JSON object
	 */
	protected static function validateWidgets(array $widgets, string $templateid = null): array {
		$errors = [];

		foreach ($widgets as $index => &$widget) {
			$widget_errors = [];

			if (!array_key_exists('pos', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'pos')
				);
			}
			else {
				foreach (['x', 'y', 'width', 'height'] as $field) {
					if (!is_array($widget['pos']) || !array_key_exists($field, $widget['pos'])) {
						$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.'][pos]',
							_s('the parameter "%1$s" is missing', $field)
						);
					}
				}
			}

			if (!array_key_exists('type', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'type')
				);
			}

			if (!array_key_exists('name', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'name')
				);
			}

			if (!array_key_exists('view_mode', $widget)) {
				$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'widgets['.$index.']',
					_s('the parameter "%1$s" is missing', 'view_mode')
				);
			}

			if ($widget_errors) {
				$errors = array_merge($errors, $widget_errors);

				break;
			}

			$widget_fields = array_key_exists('fields', $widget) ? $widget['fields'] : '{}';
			$widget['form'] = CWidgetConfig::getForm($widget['type'], $widget_fields, $templateid);
			unset($widget['fields']);

			if ($widget_errors = $widget['form']->validate()) {
				if ($widget['name'] === '') {
					$context = $this->hasInput('templateid')
						? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
						: CWidgetConfig::CONTEXT_DASHBOARD;

					$widget_name = CWidgetConfig::getKnownWidgetTypes($context)[$widget['type']];
				}
				else {
					$widget_name = $widget['name'];
				}

				foreach ($widget_errors as $error) {
					$errors[] = _s('Cannot save widget "%1$s".', $widget_name).' '.$error;
				}
			}
		}
		unset($widget);

		return [
			'widgets' => $widgets,
			'errors' => $errors
		];
	}
}
