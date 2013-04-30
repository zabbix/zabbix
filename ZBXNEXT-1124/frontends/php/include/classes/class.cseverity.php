<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CSeverity extends CTag {

	/**
	 * @param string $options['name']
	 * @param int    $options['value']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->addClass('jqueryinputset control-severity');

		$controls = array();

		foreach (getSeverityCaption() as $severity => $caption) {
			$controls[] = new CRadioButton($options['name'], $severity, null, 'severity_'.$severity, ($options['value'] == $severity));

			$label = new CLabel($caption, 'severity_'.$severity, 'severity_label_'.$severity);
			$label->attr('data-severity', $severity);
			$label->attr('data-severity-style', getSeverityStyle($severity));
			$controls[] = $label;
		}

		$this->addItem($controls);

		insert_js(
			'jQuery(function($) {
				$("#severity_0, #severity_1, #severity_2, #severity_3, #severity_4, #severity_5").change(function() {
					// remove classes from all labels
					$("div.control-severity label").each(function(i, obj) {
						obj = $(obj);
						obj.removeClass(obj.data("severityStyle"));
					});

					var label = $("#severity_label_" + $(this).val());
					label.addClass(label.data("severityStyle"));
				});

				$("#severity_label_0, #severity_label_1, #severity_label_2, #severity_label_3, #severity_label_4, #severity_label_5").mouseenter(function() {
					var obj = $(this);
					obj.addClass(obj.data("severityStyle"));
				});

				$("#severity_label_0, #severity_label_1, #severity_label_2, #severity_label_3, #severity_label_4, #severity_label_5").mouseleave(function() {
					var obj = $(this);

					if (!$("#" + obj.attr("for")).prop("checked")) {
						obj.removeClass(obj.data("severityStyle"));
					}
				});

				// click on selected severity on form load
				$("input[name=\''.$options['name'].'\']:checked").change();
			});'
		, true);
	}
}
