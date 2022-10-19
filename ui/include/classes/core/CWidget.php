<?php declare(strict_types = 0);
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


namespace Zabbix\Core;

use CControllerDashboardWidgetEdit;

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\CWidgetFieldSelect;

/**
 * Base class for user modules. If Module.php is not provided by user module, this class will be instantiated instead.
 */
class CWidget extends CModule {

	public const DEFAULT_FORM_CLASS		= 'WidgetForm';
	public const DEFAULT_JS_CLASS		= 'CWidget';
	public const DEFAULT_SIZE			= ['width' => 12, 'height' => 5];
	public const DEFAULT_REFRESH_RATE	= 60;

	// Dashboard widget types.
	public const ACTION_LOG			= 'actionlog';
	public const CLOCK				= 'clock';
	public const DISCOVERY			= 'discovery';
	public const FAV_GRAPHS			= 'favgraphs';
	public const FAV_MAPS			= 'favmaps';
	public const GEOMAP				= 'geomap';
	public const GRAPH				= 'graph';
	public const GRAPH_PROTOTYPE	= 'graphprototype';
	public const HOST_AVAIL			= 'hostavail';
	public const MAP				= 'map';
	public const NAV_TREE			= 'navtree';
	public const PLAIN_TEXT			= 'plaintext';
	public const PROBLEM_HOSTS		= 'problemhosts';
	public const PROBLEMS			= 'problems';
	public const PROBLEMS_BY_SV		= 'problemsbysv';
	public const SLA_REPORT			= 'slareport';
	public const SVG_GRAPH			= 'svggraph';
	public const SYSTEM_INFO		= 'systeminfo';
	public const TOP_HOSTS			= 'tophosts';
	public const TRIG_OVER			= 'trigover';
	public const URL				= 'url';
	public const WEB				= 'web';
	public const ITEM				= 'item';

	// Deprecated widget types.
	public const DATA_OVER	= 'dataover';

	// Dashboard widget dynamic state.
	public const SIMPLE_ITEM = 0;
	public const DYNAMIC_ITEM = 1;

	final public function getForm(array $values, ?string $templateid): CWidgetForm {
		$form_class = array_key_exists('form_class', $this->manifest['widget'])
			? $this->manifest['widget']['form_class']
			: self::DEFAULT_FORM_CLASS;

		$form_class = is_file($this->getDir().'/includes/'.$form_class.'.php')
			? implode('\\', [$this->getNamespace(), 'Includes', $form_class])
			: CWidgetForm::class;

		$form = new $form_class($values, $templateid);

		if ($templateid === null) {
			$refresh_rates = [
				0 => _('No refresh'),
				SEC_PER_MIN / 6 => _n('%1$s second', '%1$s seconds', 10),
				SEC_PER_MIN / 2 => _n('%1$s second', '%1$s seconds', 30),
				SEC_PER_MIN => _n('%1$s minute', '%1$s minutes', 1),
				SEC_PER_MIN * 2 => _n('%1$s minute', '%1$s minutes', 2),
				SEC_PER_MIN * 10 => _n('%1$s minute', '%1$s minutes', 10),
				SEC_PER_MIN * 15 => _n('%1$s minute', '%1$s minutes', 15)
			];

			$default_refresh_rate_label = array_key_exists($this->getDefaultRefreshRate(), $refresh_rates)
				? $refresh_rates[$this->getDefaultRefreshRate()]
				: $this->getDefaultRefreshRate();

			$form->addField(
				new CWidgetFieldSelect('rf_rate', _('Refresh interval'), [
						CWidgetFieldSelect::DEFAULT_VALUE => _('Default').' ('.$default_refresh_rate_label.')'
					] + $refresh_rates
				)
			);
		}

		return $form
			->addFields()
			->setFieldsValues();
	}

	final public function getActions(): array {
		return array_merge_recursive([
			'widget.'.$this->getId().'.view' => [
				'class' => 'WidgetView',
				'view' => 'widget.view',
				'layout' => 'layout.widget'
			],
			'widget.'.$this->getId().'.edit' => [
				'class' => CControllerDashboardWidgetEdit::class,
				'view' => 'widget.edit',
				'layout' => 'layout.json'
			]
		], parent::getActions());
	}

	public function getConfiguration(array $fields_values, int $view_mode): array {
		return [
			'padding' => $this->hasPadding($fields_values, $view_mode)
		];
	}

	public function getDefaults(): array {
		return [
			'name' => $this->getDefaultName(),
			'size' => $this->getDefaultSize(),
			'js_class' => $this->getJSClass()
		];
	}

	public function isIterator(): bool {
		return false;
	}

	public function isDeprecated(): bool {
		return false;
	}

	public function getDefaultName(): string {
		return $this->manifest['widget']['name'] !== ''
			? _($this->manifest['widget']['name'])
			: _($this->manifest['name']);
	}

	public function getDefaultSize(): array {
		return $this->manifest['widget']['size'];
	}

	public function getJSClass(): string {
		return $this->manifest['widget']['js_class'];
	}

	public function getDefaultRefreshRate(): int {
		return (int) $this->manifest['widget']['refresh_rate'];
	}

	public function hasTemplateSupport(): bool {
		return (bool) $this->manifest['widget']['template_support'];
	}

	public function usesTimeSelector(array $fields_values): bool {
		return (bool) $this->manifest['widget']['use_time_selector'];
	}

	protected function hasPadding(array $fields_values, int $view_mode): bool {
		return $view_mode != ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;
	}
}
