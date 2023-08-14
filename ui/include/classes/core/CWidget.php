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

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectGraph,
	CWidgetFieldMultiSelectGraphPrototype,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectItemPrototype,
	CWidgetFieldMultiSelectMap,
	CWidgetFieldMultiSelectService,
	CWidgetFieldMultiSelectSla,
	CWidgetFieldSelect
};

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

		/** @var CWidgetField $field */
		foreach ($form->getFields() as $field) {
			if (array_key_exists($field->getName(), $in_params)) {
				$field->setInType($in_params[$field->getName()]['type']);

				if (in_array($in_params[$field->getName()]['type'], ['_host', '_hosts'])) {
					$field->acceptDashboard();
				}

				$field->acceptWidget();

				if (array_key_exists('prevent_default', $in_params[$field->getName()])) {
					$field->preventDefault();
				}
			}
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

	public function getIn(): array {
		return $this->manifest['widget']['in'];
	}

	public function getOut(): array {
		return $this->manifest['widget']['out'];
	}

	public function getDefaultRefreshRate(): int {
		return array_key_exists($this->manifest['widget']['refresh_rate'], self::getRefreshRates())
			? (int) $this->manifest['widget']['refresh_rate']
			: self::DEFAULT_REFRESH_RATE;
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

	private function makeField($name, $param): ?CWidgetField {
		switch ($param['type']) {
			case '_hostgroupid':
				return (new CWidgetFieldMultiSelectGroup($name, _('Host group')))->setMultiple(false);

			case '_hostgroupids':
				return new CWidgetFieldMultiSelectGroup($name, _('Host groups'));

			case '_hostid':
				return (new CWidgetFieldMultiSelectHost($name, _('Host')))->setMultiple(false);

			case '_hostids':
				return new CWidgetFieldMultiSelectHost($name, _('Hosts'));

			case '_itemid':
				return (new CWidgetFieldMultiSelectItem($name, _('Item')))->setMultiple(false);

			case '_itemprototypeid':
				return (new CWidgetFieldMultiSelectItemPrototype($name, _('Item prototype')))->setMultiple(false);

			case '_graphid':
				return (new CWidgetFieldMultiSelectGraph($name, _('Graph')))->setMultiple(false);

			case '_graphprototypeid':
				return (new CWidgetFieldMultiSelectGraphPrototype($name, _('Graph prototype')))->setMultiple(false);

			case '_mapid':
				return (new CWidgetFieldMultiSelectMap($name, _('Map')))->setMultiple(false);

			case '_serviceid':
				return (new CWidgetFieldMultiSelectService($name, _('Service')))->setMultiple(false);

			case '_slaid':
				return (new CWidgetFieldMultiSelectSla($name, _('SLA')))->setMultiple(false);

			default:
				return null;
		}
	}
}
