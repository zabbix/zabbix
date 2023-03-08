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


namespace Zabbix\Core;

use CControllerDashboardWidgetEdit,
	CControllerDashboardWidgetView;

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\CWidgetFieldSelect;

/**
 * Base class for user widgets. If Widget.php is not provided by user widget, this class will be instantiated instead.
 */
class CWidget extends CModule {

	public const DEFAULT_FORM_CLASS		= 'WidgetForm';
	public const DEFAULT_JS_CLASS		= 'CWidget';
	public const DEFAULT_SIZE			= ['width' => 12, 'height' => 5];
	public const DEFAULT_REFRESH_RATE	= 60;

	// Dashboard widget dynamic state.
	public const SIMPLE_ITEM = 0;
	public const DYNAMIC_ITEM = 1;

	final public function getForm(array $values, ?string $templateid): CWidgetForm {
		$form_class = implode('\\', [$this->getNamespace(), 'Includes', $this->manifest['widget']['form_class']]);

		if (!class_exists($form_class)) {
			$form_class = CWidgetForm::class;
		}

		$form = new $form_class($values, $templateid);

		if ($templateid === null) {
			$refresh_rates = self::getRefreshRates();

			$form->addField(
				(new CWidgetFieldSelect('rf_rate', _('Refresh interval'),
					[
						-1 => _('Default').' ('.$refresh_rates[$this->getDefaultRefreshRate()].')'
					] + $refresh_rates
				))->setDefault(-1)
			);
		}

		return $form
			->addFields()
			->setFieldsValues();
	}

	final public function getActions(): array {
		$actions = parent::getActions() + [
			'widget.'.$this->getId().'.view' => [],
			'widget.'.$this->getId().'.edit' => []
		];

		$actions['widget.'.$this->getId().'.view'] += [
			'class' => CControllerDashboardWidgetView::class,
			'view' => 'widget.view',
			'layout' => 'layout.widget'
		];

		$actions['widget.'.$this->getId().'.edit'] += [
			'class' => CControllerDashboardWidgetEdit::class,
			'view' => 'widget.edit',
			'layout' => 'layout.json'
		];

		return $actions;
	}

	public function getDefaults(): array {
		return [
			'name' => $this->getDefaultName(),
			'size' => $this->getDefaultSize(),
			'js_class' => $this->getJSClass()
		];
	}

	public function isDeprecated(): bool {
		return false;
	}

	public function getDefaultName(): string {
		return $this->manifest['widget']['name'] !== ''
			? $this->manifest['widget']['name']
			: $this->getName();
	}

	public function getDefaultSize(): array {
		$size = $this->manifest['widget']['size'];

		if (!array_key_exists('width', $size) || !array_key_exists('height', $size)) {
			return self::DEFAULT_SIZE;
		}

		if ($size['width'] < 1) {
			$size['width'] = 1;
		}

		if ($size['width'] > DASHBOARD_MAX_COLUMNS) {
			$size['width'] = DASHBOARD_MAX_COLUMNS;
		}

		if ($size['height'] < DASHBOARD_WIDGET_MIN_ROWS) {
			$size['height'] = DASHBOARD_WIDGET_MIN_ROWS;
		}

		if ($size['height'] > DASHBOARD_WIDGET_MAX_ROWS) {
			$size['height'] = DASHBOARD_WIDGET_MAX_ROWS;
		}

		return $size;
	}

	public function getJSClass(): string {
		return $this->manifest['widget']['js_class'];
	}

	public function getDefaultRefreshRate(): int {
		return array_key_exists($this->manifest['widget']['refresh_rate'], self::getRefreshRates())
			? (int) $this->manifest['widget']['refresh_rate']
			: self::DEFAULT_REFRESH_RATE;
	}

	public function hasTemplateSupport(): bool {
		return (bool) $this->manifest['widget']['template_support'];
	}

	public function usesTimeSelector(array $fields_values): bool {
		return (bool) $this->manifest['widget']['use_time_selector'];
	}

	private static function getRefreshRates(): array {
		return [
			0 => _('No refresh'),
			10 => _n('%1$s second', '%1$s seconds', 10),
			30 => _n('%1$s second', '%1$s seconds', 30),
			60 => _n('%1$s minute', '%1$s minutes', 1),
			120 => _n('%1$s minute', '%1$s minutes', 2),
			600 => _n('%1$s minute', '%1$s minutes', 10),
			900 => _n('%1$s minute', '%1$s minutes', 15)
		];
	}
}
