<?php
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


require_once __DIR__.'/../../include/CBehavior.php';

/**
 * Behavior for widgets.
 */
class CWidgetBehavior extends CBehavior {

	/**
	 * Create new or edit widget and fill fields.
	 *
	 * @param CDashboardElement    $dashboard    dashboard element
	 * @param string               $type         widget type
	 * @param array  			   $data         given fields and values
	 * @param string 			   $name         widget name
	 *
	 * @return CFormElement
	 */
	public function openWidgetAndFill($dashboard, $type, $data, $name = null) {
		if ($name === null) {
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($type)]);
		}
		else {
			$form = $dashboard->getWidget($name)->edit()->asForm();
		}

		$form->fill($data);

		return $form;
	}

	/**
	 * Return widget type.
	 *
	 * @param CWidgetElement	$widget		widget for which type is obtained
	 *
	 * @return string
	 */
	public function getWidgetType($widget) {
		$class_attribute = $widget->query('class:dashboard-grid-widget-contents')->one()->getAttribute('class');

		return str_replace('dashboard-widget-', '', explode(' ', $class_attribute)[1]);
	}
}
