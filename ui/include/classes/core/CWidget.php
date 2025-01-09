<?php declare(strict_types = 0);
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


namespace Zabbix\Core;

use CControllerDashboardWidgetEdit,
	CControllerDashboardWidgetView,
	CWidgetsData;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldReference,
	CWidgetFieldSelect
};

/**
 * Base class for user widgets. If Widget.php is not provided by user widget, this class will be instantiated instead.
 */
class CWidget extends CModule {

	public const DEFAULT_FORM_CLASS		= 'WidgetForm';
	public const DEFAULT_JS_CLASS		= 'CWidget';
	public const DEFAULT_SIZE			= ['width' => 36, 'height' => 5];
	public const DEFAULT_REFRESH_RATE	= 60;

	final public function getForm(array $values, ?string $templateid): CWidgetForm {
		$form_class = implode('\\', [$this->getNamespace(), 'Includes', $this->manifest['widget']['form_class']]);

		if (!class_exists($form_class)) {
			$form_class = CWidgetForm::class;
		}

		$form = new $form_class($values, $templateid);

		$refresh_rates = self::getRefreshRates();

		$form->addField(
			(new CWidgetFieldSelect('rf_rate', _('Refresh interval'),
				[
					-1 => _('Default').' ('.$refresh_rates[$this->getDefaultRefreshRate()].')'
				] + $refresh_rates
			))->setDefault(-1)
		);

		$in_params = $this->getIn();

		if ($form_class === CWidgetForm::class) {
			foreach ($in_params as $name => $param) {
				$form->addField($this->makeField($name, $param));
			}
		}
		else {
			$form->addFields();
		}

		$data_types = CWidgetsData::getDataTypes();

		/** @var CWidgetField $field */
		foreach ($form->getFields() as $field) {
			if (array_key_exists($field->getName(), $in_params)) {
				$data_type = $in_params[$field->getName()]['type'];

				$field->setInType($data_type);

				if (array_key_exists($data_type, $data_types) && $data_types[$data_type]['accepts_dashboard']) {
					$field->acceptDashboard();
				}

				$field->acceptWidget();

				if (array_key_exists('prevent_default', $in_params[$field->getName()])) {
					$field->preventDefault();
				}
			}
		}

		if ($this->getOut()) {
			$form->addField(
				new CWidgetFieldReference()
			);
		}

		$form->setFieldsValues();

		return $form;
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
			'js_class' => $this->getJSClass(),
			'in' => $this->getIn(),
			'out' => $this->getOut()
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

		if ($size['height'] < 1) {
			$size['height'] = 1;
		}

		if ($size['height'] > DASHBOARD_MAX_ROWS) {
			$size['height'] = DASHBOARD_MAX_ROWS;
		}

		return $size;
	}

	public function getJSClass(): string {
		return $this->manifest['widget']['js_class'];
	}

	public function getIn(): array {
		return $this->manifest['widget']['in'];
	}

	public function getOut(): array {
		return $this->manifest['widget']['out'];
	}

	/**
	 * Get initial configuration for new widget editor. This will override form defaults for specified fields.
	 *
	 * @return array
	 */
	public function getInitialFieldsValues(): array {
		return [];
	}

	public function getDefaultRefreshRate(): int {
		return array_key_exists($this->manifest['widget']['refresh_rate'], self::getRefreshRates())
			? (int) $this->manifest['widget']['refresh_rate']
			: self::DEFAULT_REFRESH_RATE;
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

	private function makeField($name, $param): ?CWidgetField {
		$data_types = CWidgetsData::getDataTypes();

		if (!array_key_exists($param['type'], $data_types)) {
			return null;
		}

		[
			'field_class' => $field_class,
			'label' => $label,
			'is_multiple' => $is_multiple
		] = $data_types[$param['type']];

		/** @var CWidgetField $field */
		$field = new $field_class($name, $label);

		if ($is_multiple !== null) {
			$field->setMultiple($is_multiple);
		}

		if (array_key_exists('required', $param) && $param['required']) {
			$field->setFlags(CWidgetField::FLAG_LABEL_ASTERISK | CWidgetField::FLAG_NOT_EMPTY);
		}

		return $field;
	}
}
