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


namespace Zabbix\Widgets;

class CWidgetConfig {

	/**
	 * Array of deprecated widgets constants.
	 */
	/*public const DEPRECATED_WIDGETS = [
		WIDGET_DATA_OVER // TODO AS: moved to manifest
	];*/

	/**
	 * Classifier for non-template dashboards.
	 */
//	public const CONTEXT_DASHBOARD = 'dashboard';

	/**
	 * Classifier for template and host dashboards.
	 */
//	public const CONTEXT_TEMPLATE_DASHBOARD = 'template_dashboard';

	/**
	 * Get default names for all widget types.
	 *
	 * @static
	 *
	 * @param string $context  CWidgetConfig::CONTEXT_DASHBOARD | CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
	 *
	 * @return array
	 */
	/*public static function getKnownWidgetTypes(string $context): array {
		$types = [
			WIDGET_ACTION_LOG			=> _('Action log'), // TODO AS: moved to manifest
			WIDGET_CLOCK				=> _('Clock'), // TODO AS: moved to manifest
			WIDGET_DATA_OVER			=> _('Data overview'), // TODO AS: moved to manifest
			WIDGET_DISCOVERY			=> _('Discovery status'), // TODO AS: moved to manifest
			WIDGET_FAV_GRAPHS			=> _('Favorite graphs'), // TODO AS: moved to manifest
			WIDGET_FAV_MAPS				=> _('Favorite maps'), // TODO AS: moved to manifest
			WIDGET_GEOMAP				=> _('Geomap'), // TODO AS: moved to manifest
			WIDGET_ITEM					=> _('Item value'), // TODO AS: moved to manifest
			WIDGET_GRAPH				=> _('Graph (classic)'), // TODO AS: moved to manifest
			WIDGET_GRAPH_PROTOTYPE		=> _('Graph prototype'), // TODO AS: moved to manifest
			WIDGET_HOST_AVAIL			=> _('Host availability'), // TODO AS: moved to manifest
			WIDGET_MAP					=> _('Map'), // TODO AS: moved to manifest
			WIDGET_NAV_TREE				=> _('Map navigation tree'), // TODO AS: moved to manifest
			WIDGET_PLAIN_TEXT			=> _('Plain text'), // TODO AS: moved to manifest
			WIDGET_PROBLEM_HOSTS		=> _('Problem hosts'), // TODO AS: moved to manifest
			WIDGET_PROBLEMS				=> _('Problems'), // TODO AS: moved to manifest
			WIDGET_PROBLEMS_BY_SV		=> _('Problems by severity'), // TODO AS: moved to manifest
			WIDGET_SLA_REPORT			=> _('SLA report'), // TODO AS: moved to manifest
			WIDGET_SVG_GRAPH			=> _('Graph'), // TODO AS: moved to manifest
			WIDGET_SYSTEM_INFO			=> _('System information'), // TODO AS: moved to manifest
			WIDGET_TRIG_OVER			=> _('Trigger overview'), // TODO AS: moved to manifest
			WIDGET_URL					=> _('URL'), // TODO AS: moved to manifest
			WIDGET_WEB					=> _('Web monitoring'), // TODO AS: moved to manifest
			WIDGET_TOP_HOSTS			=> _('Top hosts') // TODO AS: moved to manifest
		];

		$types = array_filter($types,
			function(string $type) use ($context): bool {
				return self::isWidgetTypeSupportedInContext($type, $context);
			},
			ARRAY_FILTER_USE_KEY
		);

		return $types;
	}*/

	/**
	 * Get JavaScript classes for all widget types.
	 *
	 * @static
	 *
	 * @return array
	 */
	/*public static function getJSClasses(): array {
		return [
			WIDGET_ACTION_LOG			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_CLOCK				=> 'CWidgetClock', // TODO AS: moved to manifest
			WIDGET_DATA_OVER			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_DISCOVERY			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_FAV_GRAPHS			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_FAV_MAPS				=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_GEOMAP				=> 'CWidgetGeoMap', // TODO AS: moved to manifest
			WIDGET_ITEM					=> 'CWidgetItem', // TODO AS: moved to manifest
			WIDGET_GRAPH				=> 'CWidgetGraph', // TODO AS: moved to manifest
			WIDGET_GRAPH_PROTOTYPE		=> 'CWidgetGraphPrototype', // TODO AS: moved to manifest
			WIDGET_HOST_AVAIL			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_MAP					=> 'CWidgetMap', // TODO AS: moved to manifest
			WIDGET_NAV_TREE				=> 'CWidgetNavTree', // TODO AS: moved to manifest
			WIDGET_PLAIN_TEXT			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_PROBLEM_HOSTS		=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_PROBLEMS				=> 'CWidgetProblems', // TODO AS: moved to manifest
			WIDGET_PROBLEMS_BY_SV		=> 'CWidgetProblemsBySv', // TODO AS: moved to manifest
			WIDGET_SLA_REPORT			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_SVG_GRAPH			=> 'CWidgetSvgGraph', // TODO AS: moved to manifest
			WIDGET_SYSTEM_INFO			=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_TRIG_OVER			=> 'CWidgetTrigerOver', // TODO AS: moved to manifest
			WIDGET_URL					=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_WEB					=> 'CWidget', // TODO AS: moved to manifest
			WIDGET_TOP_HOSTS			=> 'CWidget' // TODO AS: moved to manifest
		];
	}*/

	/**
	 * Get reference field name for widgets of the given type.
	 *
	 * @static
	 *
	 * @return string|null
	 */
	/*public static function getReferenceField(string $type): ?string {
		switch ($type) {
			case WIDGET_MAP: // TODO AS: need to check
			case WIDGET_NAV_TREE: // TODO AS: need to check
				return 'reference';

			default:
				return null;
		}
	}*/

	/**
	 * Get foreign reference field names for widgets of the given type.
	 *
	 * @static
	 *
	 * @return array
	 */
	/*public static function getForeignReferenceFields(string $type): array {
		switch ($type) {
			case WIDGET_MAP: // TODO AS: need to check
				return ['filter_widget_reference'];

			default:
				return [];
		}
	}*/

	/**
	 * Get default widget dimensions.
	 *
	 * @static
	 *
	 * @return array
	 */
	/*private static function getDefaultDimensions(): array {
		return [
			WIDGET_ACTION_LOG			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_CLOCK				=> ['width' => 4,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_DATA_OVER			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_DISCOVERY			=> ['width' => 6,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_FAV_GRAPHS			=> ['width' => 4,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_FAV_MAPS				=> ['width' => 4,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_GEOMAP				=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_ITEM					=> ['width' => 4,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_GRAPH				=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_GRAPH_PROTOTYPE		=> ['width' => 16,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_HOST_AVAIL			=> ['width' => 6,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_MAP					=> ['width' => 18,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_NAV_TREE				=> ['width' => 6,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_PLAIN_TEXT			=> ['width' => 6,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_PROBLEM_HOSTS		=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_PROBLEMS				=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_PROBLEMS_BY_SV		=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_SLA_REPORT			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_SVG_GRAPH			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_SYSTEM_INFO			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_TRIG_OVER			=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_URL					=> ['width' => 12,	'height' => 5], // TODO AS: moved to manifest
			WIDGET_WEB					=> ['width' => 6,	'height' => 3], // TODO AS: moved to manifest
			WIDGET_TOP_HOSTS			=> ['width' => 12,	'height' => 5] // TODO AS: moved to manifest
		];
	}*/

	/**
	 * Get default values for widgets.
	 *
	 * @static
	 *
	 * @param string $context  CWidgetConfig::CONTEXT_DASHBOARD | CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
	 *
	 * @return array
	 */
	/*public static function getDefaults(string $context): array {
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
	}*/

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
/*	public static function isWidgetTypeSupportedInContext(string $type, string $context): bool {
		switch ($context) {
			case self::CONTEXT_DASHBOARD:
				return true;

			case self::CONTEXT_TEMPLATE_DASHBOARD:
				switch ($type) {
					case WIDGET_CLOCK: // TODO AS: moved to manifest
					case WIDGET_GRAPH: // TODO AS: moved to manifest
					case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to manifest
					case WIDGET_ITEM: // TODO AS: moved to manifest
					case WIDGET_PLAIN_TEXT: // TODO AS: moved to manifest
					case WIDGET_URL: // TODO AS: moved to manifest
						return true;
				}
		}

		return false;
	}*/

	/**
	 * Get default refresh rate for widget type.
	 *
	 * @static
	 *
	 * @param string $type  Widget type - 'WIDGET_*' constant.
	 *
	 * @return int  default refresh rate, 0 for no refresh
	 */
	/*public static function getDefaultRfRate(string $type): int {
		switch ($type) {
			case WIDGET_ACTION_LOG: // TODO AS: moved to manifest
			case WIDGET_DATA_OVER: // TODO AS: moved to manifest
			case WIDGET_TOP_HOSTS: // TODO AS: moved to manifest
			case WIDGET_DISCOVERY: // TODO AS: moved to manifest
			case WIDGET_GEOMAP: // TODO AS: moved to manifest
			case WIDGET_GRAPH: // TODO AS: moved to manifest
			case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to manifest
			case WIDGET_PLAIN_TEXT: // TODO AS: moved to manifest
			case WIDGET_ITEM: // TODO AS: moved to manifest
			case WIDGET_PROBLEM_HOSTS: // TODO AS: moved to manifest
			case WIDGET_PROBLEMS: // TODO AS: moved to manifest
			case WIDGET_PROBLEMS_BY_SV: // TODO AS: moved to manifest
			case WIDGET_SVG_GRAPH: // TODO AS: moved to manifest
			case WIDGET_TRIG_OVER: // TODO AS: moved to manifest
			case WIDGET_WEB: // TODO AS: moved to manifest
				return SEC_PER_MIN; 60

			case WIDGET_CLOCK: // TODO AS: moved to manifest
			case WIDGET_FAV_GRAPHS: // TODO AS: moved to manifest
			case WIDGET_FAV_MAPS: // TODO AS: moved to manifest
			case WIDGET_HOST_AVAIL: // TODO AS: moved to manifest
			case WIDGET_MAP: // TODO AS: moved to manifest
			case WIDGET_NAV_TREE: // TODO AS: moved to manifest
			case WIDGET_SYSTEM_INFO: // TODO AS: moved to manifest
				return 15 * SEC_PER_MIN;

			case WIDGET_SLA_REPORT: // TODO AS: moved to manifest
			case WIDGET_URL: // TODO AS: moved to manifest
				return 0;
		}
	}*/

	/**
	 * Get all possible widget refresh intervals.
	 *
	 * @return array
	 */
//	public static function getRfRates() {
//		return [
//			0 => _('No refresh'),
//			SEC_PER_MIN / 6 => _n('%1$s second', '%1$s seconds', 10),
//			SEC_PER_MIN / 2 => _n('%1$s second', '%1$s seconds', 30),
//			SEC_PER_MIN => _n('%1$s minute', '%1$s minutes', 1),
//			SEC_PER_MIN * 2 => _n('%1$s minute', '%1$s minutes', 2),
//			SEC_PER_MIN * 10 => _n('%1$s minute', '%1$s minutes', 10),
//			SEC_PER_MIN * 15 => _n('%1$s minute', '%1$s minutes', 15)
//		];
//	}

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
	/*public static function usesTimeSelector(string $type, array $fields): bool {
		switch ($type) {
			case WIDGET_GRAPH: // TODO AS: moved to manifest
			case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to manifest
				return true;

			case WIDGET_SVG_GRAPH: // TODO AS: moved to Widget.php
				return !CWidgetFormSvgGraph::hasOverrideTime($fields); // TODO AS: Move to Widget module for SVG Graph

			default:
				return false;
		}
	}*/

	/**
	 * Check if widget type belongs to iterators.
	 *
	 * @static
	 *
	 * @param string $type  Widget type - 'WIDGET_*' constant.
	 *
	 * @return bool
	 */
	// TODO AS: Move to GraphPrototype/CWidget
//	public static function isIterator(string $type): bool {
//		switch ($type) {
//			case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to manifest
//				return true;

//			default:
//				return false;
//		}
//	}

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
	/*private static function hasPadding(string $type, array $fields, int $view_mode): bool {
		if ($view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER) {     // TODO AS: if has .dashboard-grid-widget-hidden-header
			switch ($type) {
//				case WIDGET_CLOCK:
//					return $fields['clock_type'] === WIDGET_CLOCK_TYPE_ANALOG; // TODO AS: if has .clock-analog {padding: 0}

				case WIDGET_GRAPH: // TODO AS: moved to Widget.php
				case WIDGET_MAP: // TODO AS: moved to Widget.php
				case WIDGET_SVG_GRAPH: // TODO AS: moved to Widget.php
					return true;

				default:
					return false;
			}
		}
		else {
			switch ($type) {
//				case WIDGET_CLOCK:
//					return $fields['clock_type'] === WIDGET_CLOCK_TYPE_ANALOG;

				case WIDGET_HOST_AVAIL: // TODO AS: moved to Widget.php
					return (count($fields['interface_type']) != 1);

				case WIDGET_PROBLEMS_BY_SV: // TODO AS: moved to Widget.php
					return $fields['show_type'] != WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS;

				case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to Widget.php
				case WIDGET_ITEM: // TODO AS: moved to Widget.php
				case WIDGET_URL: // TODO AS: moved to Widget.php
					return false;

				default:
					return true;
			}
		}
	}*/

	/**
	 * Get widget configuration based on widget type, fields and current view mode.
	 *
	 * @param string $type       Widget type - 'WIDGET_*' constant.
	 * @param array  $fields     Widget form fields
	 * @param int    $view_mode  Widget view mode
	 *
	 * @return array
	 */
	/*public static function getConfiguration(string $type, array $fields, int $view_mode): array {
		return [
			'padding' => self::hasPadding($type, $fields, $view_mode)
		];
	}*/

	/**
	 * Get Form object for widget with provided data.
	 *
	 * @param string      $type        Widget type - 'WIDGET_*' constant.
	 * @param array       $values      JSON string with widget fields.
	 * @param string|null $templateid  Template ID for template dashboards or null for non-template dashboards.
	 *
	 * @return CWidgetForm
	 */
	/*public static function getForm(string $type, array $values, ?string $templateid): CWidgetForm {
		switch ($type) {
			case WIDGET_ACTION_LOG: // TODO AS: moved to manifest
				return new \CWidgetFormActionLog($values, $templateid);

			case WIDGET_CLOCK:
				return new \CWidgetFormClock($values, $templateid); // TODO AS: moved to manifest

			case WIDGET_DATA_OVER: // TODO AS: moved to manifest
				return new \CWidgetFormDataOver($values, $templateid);

			case WIDGET_GEOMAP: // TODO AS: moved to manifest
				return new \CWidgetFormGeoMap($values, $templateid);

			case WIDGET_GRAPH: // TODO AS: moved to manifest
				return new \CWidgetFormGraph($values, $templateid);

			case WIDGET_GRAPH_PROTOTYPE: // TODO AS: moved to manifest
				return new \CWidgetFormGraphPrototype($values, $templateid);

			case WIDGET_HOST_AVAIL: // TODO AS: moved to manifest
				return new \CWidgetFormHostAvail($values, $templateid);

			case WIDGET_ITEM: // TODO AS: moved to manifest
				return new \CWidgetFormItem($values, $templateid);

			case WIDGET_MAP: // TODO AS: moved to manifest
				return new \CWidgetFormMap($values, $templateid);

			case WIDGET_NAV_TREE: // TODO AS: moved to manifest
				return new \CWidgetFormNavTree($values, $templateid);

			case WIDGET_PLAIN_TEXT: // TODO AS: moved to manifest
				return new \CWidgetFormPlainText($values, $templateid);

			case WIDGET_PROBLEM_HOSTS: // TODO AS: moved to manifest
				return new \CWidgetFormProblemHosts($values, $templateid);

			case WIDGET_PROBLEMS: // TODO AS: moved to manifest
				return new \CWidgetFormProblems($values, $templateid);

			case WIDGET_PROBLEMS_BY_SV: // TODO AS: moved to manifest
				return new \CWidgetFormProblemsBySv($values, $templateid);

			case WIDGET_SLA_REPORT: // TODO AS: moved to manifest
				return new \CWidgetFormSlaReport($values, $templateid);

			case WIDGET_SVG_GRAPH: // TODO AS: moved to manifest
				return new \CWidgetFormSvgGraph($values, $templateid);

			case WIDGET_SYSTEM_INFO: // TODO AS: moved to manifest
				return new \CWidgetFormSystemInfo($values, $templateid);

			case WIDGET_TOP_HOSTS: // TODO AS: moved to manifest
				return new \CWidgetFormTopHosts($values, $templateid);

			case WIDGET_TRIG_OVER: // TODO AS: moved to manifest
				return new \CWidgetFormTrigOver($values, $templateid);

			case WIDGET_URL: // TODO AS: moved to manifest
				return new \CWidgetFormUrl($values, $templateid);

			case WIDGET_WEB: // TODO AS: moved to manifest
				return new \CWidgetFormWeb($values, $templateid);

			default:
				return new CWidgetForm($type, $values, $templateid);
		}
	}*/
}
