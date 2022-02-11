<?php declare(strict_types = 1);
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


class CWidgetConfig {

	/**
	 * Array of deprecated widgets constants.
	 */
	public const DEPRECATED_WIDGETS = [
		WIDGET_DATA_OVER
	];

	/**
	 * Classifier for non-template dashboards.
	 */
	public const CONTEXT_DASHBOARD = 'dashboard';

	/**
	 * Classifier for template and host dashboards.
	 */
	public const CONTEXT_TEMPLATE_DASHBOARD = 'template_dashboard';

	/**
	 * Get default names for all widget types.
	 *
	 * @static
	 *
	 * @param string $context  CWidgetConfig::CONTEXT_DASHBOARD | CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
	 *
	 * @return array
	 */
	public static function getKnownWidgetTypes(string $context): array {
		$types = [
			WIDGET_ACTION_LOG			=> _('Action log'),
			WIDGET_CLOCK				=> _('Clock'),
			WIDGET_DATA_OVER			=> _('Data overview'),
			WIDGET_DISCOVERY			=> _('Discovery status'),
			WIDGET_FAV_GRAPHS			=> _('Favorite graphs'),
			WIDGET_FAV_MAPS				=> _('Favorite maps'),
			WIDGET_GEOMAP				=> _('Geomap'),
			WIDGET_ITEM					=> _('Item value'),
			WIDGET_GRAPH				=> _('Graph (classic)'),
			WIDGET_GRAPH_PROTOTYPE		=> _('Graph prototype'),
			WIDGET_HOST_AVAIL			=> _('Host availability'),
			WIDGET_MAP					=> _('Map'),
			WIDGET_NAV_TREE				=> _('Map navigation tree'),
			WIDGET_PLAIN_TEXT			=> _('Plain text'),
			WIDGET_PROBLEM_HOSTS		=> _('Problem hosts'),
			WIDGET_PROBLEMS				=> _('Problems'),
			WIDGET_PROBLEMS_BY_SV		=> _('Problems by severity'),
			WIDGET_SLA_REPORT			=> _('SLA report'),
			WIDGET_SVG_GRAPH			=> _('Graph'),
			WIDGET_SYSTEM_INFO			=> _('System information'),
			WIDGET_TRIG_OVER			=> _('Trigger overview'),
			WIDGET_URL					=> _('URL'),
			WIDGET_WEB					=> _('Web monitoring'),
			WIDGET_TOP_HOSTS			=> _('Top hosts')
		];

		$types = array_filter($types,
			function(string $type) use ($context): bool {
				return self::isWidgetTypeSupportedInContext($type, $context);
			},
			ARRAY_FILTER_USE_KEY
		);

		return $types;
	}

	/**
	 * Get JavaScript classes for all widget types.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getJSClasses(): array {
		return [
			WIDGET_ACTION_LOG			=> 'CWidget',
			WIDGET_CLOCK				=> 'CWidgetClock',
			WIDGET_DATA_OVER			=> 'CWidget',
			WIDGET_DISCOVERY			=> 'CWidget',
			WIDGET_FAV_GRAPHS			=> 'CWidget',
			WIDGET_FAV_MAPS				=> 'CWidget',
			WIDGET_GEOMAP				=> 'CWidgetGeoMap',
			WIDGET_ITEM					=> 'CWidgetItem',
			WIDGET_GRAPH				=> 'CWidgetGraph',
			WIDGET_GRAPH_PROTOTYPE		=> 'CWidgetGraphPrototype',
			WIDGET_HOST_AVAIL			=> 'CWidget',
			WIDGET_MAP					=> 'CWidgetMap',
			WIDGET_NAV_TREE				=> 'CWidgetNavTree',
			WIDGET_PLAIN_TEXT			=> 'CWidget',
			WIDGET_PROBLEM_HOSTS		=> 'CWidget',
			WIDGET_PROBLEMS				=> 'CWidgetProblems',
			WIDGET_PROBLEMS_BY_SV		=> 'CWidgetProblemsBySv',
			WIDGET_SLA_REPORT			=> 'CWidget',
			WIDGET_SVG_GRAPH			=> 'CWidgetSvgGraph',
			WIDGET_SYSTEM_INFO			=> 'CWidget',
			WIDGET_TRIG_OVER			=> 'CWidgetTrigerOver',
			WIDGET_URL					=> 'CWidget',
			WIDGET_WEB					=> 'CWidget',
			WIDGET_TOP_HOSTS			=> 'CWidget'
		];
	}

	/**
	 * Get reference field name for widgets of the given type.
	 *
	 * @static
	 *
	 * @return string|null
	 */
	public static function getReferenceField(string $type): ?string {
		switch ($type) {
			case WIDGET_MAP:
			case WIDGET_NAV_TREE:
				return 'reference';

			default:
				return null;
		}
	}

	/**
	 * Get foreign reference field names for widgets of the given type.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getForeignReferenceFields(string $type): array {
		switch ($type) {
			case WIDGET_MAP:
				return ['filter_widget_reference'];

			default:
				return [];
		}
	}

	/**
	 * Get default widget dimensions.
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getDefaultDimensions(): array {
		return [
			WIDGET_ACTION_LOG			=> ['width' => 12,	'height' => 5],
			WIDGET_CLOCK				=> ['width' => 4,	'height' => 3],
			WIDGET_DATA_OVER			=> ['width' => 12,	'height' => 5],
			WIDGET_DISCOVERY			=> ['width' => 6,	'height' => 3],
			WIDGET_FAV_GRAPHS			=> ['width' => 4,	'height' => 3],
			WIDGET_FAV_MAPS				=> ['width' => 4,	'height' => 3],
			WIDGET_GEOMAP				=> ['width' => 12,	'height' => 5],
			WIDGET_ITEM					=> ['width' => 4,	'height' => 3],
			WIDGET_GRAPH				=> ['width' => 12,	'height' => 5],
			WIDGET_GRAPH_PROTOTYPE		=> ['width' => 16,	'height' => 5],
			WIDGET_HOST_AVAIL			=> ['width' => 6,	'height' => 3],
			WIDGET_MAP					=> ['width' => 18,	'height' => 5],
			WIDGET_NAV_TREE				=> ['width' => 6,	'height' => 5],
			WIDGET_PLAIN_TEXT			=> ['width' => 6,	'height' => 3],
			WIDGET_PROBLEM_HOSTS		=> ['width' => 12,	'height' => 5],
			WIDGET_PROBLEMS				=> ['width' => 12,	'height' => 5],
			WIDGET_PROBLEMS_BY_SV		=> ['width' => 12,	'height' => 5],
			WIDGET_SLA_REPORT			=> ['width' => 12,	'height' => 5],
			WIDGET_SVG_GRAPH			=> ['width' => 12,	'height' => 5],
			WIDGET_SYSTEM_INFO			=> ['width' => 12,	'height' => 5],
			WIDGET_TRIG_OVER			=> ['width' => 12,	'height' => 5],
			WIDGET_URL					=> ['width' => 12,	'height' => 5],
			WIDGET_WEB					=> ['width' => 6,	'height' => 3],
			WIDGET_TOP_HOSTS			=> ['width' => 12,	'height' => 5]
		];
	}

	/**
	 * Get default values for widgets.
	 *
	 * @static
	 *
	 * @param string $context  CWidgetConfig::CONTEXT_DASHBOARD | CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
	 *
	 * @return array
	 */
	public static function getDefaults(string $context): array {
		$ret = [];

		$dimensions = self::getDefaultDimensions();
		$js_clases = self::getJSClasses();

		foreach (self::getKnownWidgetTypes($context) as $type => $name) {
			$ret[$type] = [
				'name' => $name,
				'size' => $dimensions[$type],
				'js_class' => $js_clases[$type],
				'iterator' => self::isIterator($type),
				'reference_field' => self::getReferenceField($type),
				'foreign_reference_fields' => self::getForeignReferenceFields($type)
			];
		}

		return $ret;
	}

	/**
	 * Check if widget type is supported in a given context.
	 *
	 * @static
	 *
	 * @param string $type     Widget type - 'WIDGET_*' constant.
	 * @param string $context  CWidgetConfig::CONTEXT_DASHBOARD | CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
	 *
	 * @return bool
	 */
	public static function isWidgetTypeSupportedInContext(string $type, string $context): bool {
		switch ($context) {
			case self::CONTEXT_DASHBOARD:
				return true;

			case self::CONTEXT_TEMPLATE_DASHBOARD:
				switch ($type) {
					case WIDGET_CLOCK:
					case WIDGET_GRAPH:
					case WIDGET_GRAPH_PROTOTYPE:
					case WIDGET_ITEM:
					case WIDGET_PLAIN_TEXT:
					case WIDGET_URL:
						return true;

					default:
						return false;
				}
		}
	}

	/**
	 * Get default refresh rate for widget type.
	 *
	 * @static
	 *
	 * @param string $type  Widget type - 'WIDGET_*' constant.
	 *
	 * @return int  default refresh rate, 0 for no refresh
	 */
	public static function getDefaultRfRate(string $type): int {
		switch ($type) {
			case WIDGET_ACTION_LOG:
			case WIDGET_DATA_OVER:
			case WIDGET_TOP_HOSTS:
			case WIDGET_DISCOVERY:
			case WIDGET_GEOMAP:
			case WIDGET_GRAPH:
			case WIDGET_GRAPH_PROTOTYPE:
			case WIDGET_PLAIN_TEXT:
			case WIDGET_ITEM:
			case WIDGET_PROBLEM_HOSTS:
			case WIDGET_PROBLEMS:
			case WIDGET_PROBLEMS_BY_SV:
			case WIDGET_SVG_GRAPH:
			case WIDGET_TRIG_OVER:
			case WIDGET_WEB:
				return SEC_PER_MIN;

			case WIDGET_CLOCK:
			case WIDGET_FAV_GRAPHS:
			case WIDGET_FAV_MAPS:
			case WIDGET_HOST_AVAIL:
			case WIDGET_MAP:
			case WIDGET_NAV_TREE:
			case WIDGET_SYSTEM_INFO:
				return 15 * SEC_PER_MIN;

			case WIDGET_SLA_REPORT:
			case WIDGET_URL:
				return 0;
		}
	}

	/**
	 * Get all possible widget refresh intervals.
	 *
	 * @return array
	 */
	public static function getRfRates() {
		return [
			0 => _('No refresh'),
			SEC_PER_MIN / 6 => _n('%1$s second', '%1$s seconds', 10),
			SEC_PER_MIN / 2 => _n('%1$s second', '%1$s seconds', 30),
			SEC_PER_MIN => _n('%1$s minute', '%1$s minutes', 1),
			SEC_PER_MIN * 2 => _n('%1$s minute', '%1$s minutes', 2),
			SEC_PER_MIN * 10 => _n('%1$s minute', '%1$s minutes', 10),
			SEC_PER_MIN * 15 => _n('%1$s minute', '%1$s minutes', 15)
		];
	}

	/**
	 * Check if time selector is necessary for widget having specified type and fields.
	 *
	 * @static
	 *
	 * @param string $type    Widget type - 'WIDGET_*' constant.
	 * @param array  $fields
	 *
	 * @return bool
	 */
	public static function usesTimeSelector(string $type, array $fields): bool {
		switch ($type) {
			case WIDGET_GRAPH:
			case WIDGET_GRAPH_PROTOTYPE:
				return true;

			case WIDGET_SVG_GRAPH:
				return !CWidgetFormSvgGraph::hasOverrideTime($fields);

			default:
				return false;
		}
	}

	/**
	 * Check if widget type belongs to iterators.
	 *
	 * @static
	 *
	 * @param string $type  Widget type - 'WIDGET_*' constant.
	 *
	 * @return bool
	 */
	public static function isIterator(string $type): bool {
		switch ($type) {
			case WIDGET_GRAPH_PROTOTYPE:
				return true;

			default:
				return false;
		}
	}

	/**
	 * Check if widget has padding or not.
	 *
	 * @static
	 *
	 * @param string $type       Widget type - 'WIDGET_*' constant.
	 * @param array  $fields     Widget form fields
	 * @param int    $view_mode  Widget view mode. ZBX_WIDGET_VIEW_MODE_NORMAL by default
	 *
	 * @return bool
	 */
	private static function hasPadding(string $type, array $fields, int $view_mode): bool {
		if ($view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER) {
			switch ($type) {
				case WIDGET_CLOCK:
				case WIDGET_GRAPH:
				case WIDGET_MAP:
				case WIDGET_SVG_GRAPH:
					return true;

				default:
					return false;
			}
		}
		else {
			switch ($type) {
				case WIDGET_HOST_AVAIL:
					return (count($fields['interface_type']) != 1);

				case WIDGET_PROBLEMS_BY_SV:
					return $fields['show_type'] != WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS;

				case WIDGET_GRAPH_PROTOTYPE:
				case WIDGET_ITEM:
				case WIDGET_URL:
					return false;

				default:
					return true;
			}
		}
	}

	/**
	 * Get widget configuration based on widget type, fields and current view mode.
	 *
	 * @param string $type       Widget type - 'WIDGET_*' constant.
	 * @param array  $fields     Widget form fields
	 * @param int    $view_mode  Widget view mode
	 *
	 * @return array
	 */
	public static function getConfiguration(string $type, array $fields, int $view_mode): array {
		return [
			'padding' => self::hasPadding($type, $fields, $view_mode)
		];
	}

	/**
	 * Get Form object for widget with provided data.
	 *
	 * @static
	 *
	 * @param string $type             Widget type - 'WIDGET_*' constant.
	 * @param string $data             JSON string with widget fields.
	 * @param string|null $templateid  Template ID for template dashboards or null for non-template dashboards.
	 *
	 * @return CWidgetForm
	 */
	public static function getForm(string $type, string $data, ?string $templateid): CWidgetForm {
		switch ($type) {
			case WIDGET_ACTION_LOG:
				return new CWidgetFormActionLog($data, $templateid);

			case WIDGET_CLOCK:
				return new CWidgetFormClock($data, $templateid);

			case WIDGET_DATA_OVER:
				return new CWidgetFormDataOver($data, $templateid);

			case WIDGET_GEOMAP:
				return new CWidgetFormGeoMap($data, $templateid);

			case WIDGET_GRAPH:
				return new CWidgetFormGraph($data, $templateid);

			case WIDGET_GRAPH_PROTOTYPE:
				return new CWidgetFormGraphPrototype($data, $templateid);

			case WIDGET_HOST_AVAIL:
				return new CWidgetFormHostAvail($data, $templateid);

			case WIDGET_MAP:
				return new CWidgetFormMap($data, $templateid);

			case WIDGET_NAV_TREE:
				return new CWidgetFormNavTree($data, $templateid);

			case WIDGET_PLAIN_TEXT:
				return new CWidgetFormPlainText($data, $templateid);

			case WIDGET_PROBLEM_HOSTS:
				return new CWidgetFormProblemHosts($data, $templateid);

			case WIDGET_PROBLEMS:
				return new CWidgetFormProblems($data, $templateid);

			case WIDGET_PROBLEMS_BY_SV:
				return new CWidgetFormProblemsBySv($data, $templateid);

			case WIDGET_SLA_REPORT:
				return new CWidgetFormSlaReport($data, $templateid);

			case WIDGET_SVG_GRAPH:
				return new CWidgetFormSvgGraph($data, $templateid);

			case WIDGET_SYSTEM_INFO:
				return new CWidgetFormSystemInfo($data, $templateid);

			case WIDGET_TRIG_OVER:
				return new CWidgetFormTrigOver($data, $templateid);

			case WIDGET_URL:
				return new CWidgetFormUrl($data, $templateid);

			case WIDGET_WEB:
				return new CWidgetFormWeb($data, $templateid);

			case WIDGET_ITEM:
				return new CWidgetFormItem($data, $templateid);

			case WIDGET_TOP_HOSTS:
				return new CWidgetFormTopHosts($data, $templateid);

			default:
				return new CWidgetForm($data, $templateid, $type);
		}
	}
}
