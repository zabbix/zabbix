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
		$this->attr('id', zbx_formatDomId($options['name']));
		$this->addClass('jqueryinputset control-severity');

		if (!isset($options['value'])) {
			$options['value'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}

		$controls = array();

		$jsIds = '';
		$jsLabels = '';

		foreach (getSeverityCaption() as $severity => $caption) {
			$controls[] = new CRadioButton($options['name'], $severity, null, $options['name'].'_'.$severity, ($options['value'] == $severity));

			$label = new CLabel($caption, $options['name'].'_'.$severity, $options['name'].'_label_'.$severity);
			$label->attr('data-severity', $severity);
			$label->attr('data-severity-style', getSeverityStyle($severity));
			$controls[] = $label;

			$jsIds .= ', #'.$options['name'].'_'.$severity;
			$jsLabels .= ', #'.$options['name'].'_label_'.$severity;
		}

		if ($jsIds) {
			$jsIds = substr($jsIds, 2);
			$jsLabels = substr($jsLabels, 2);
		}

		$this->addItem($controls);

		insert_js('
			jQuery("'.$jsIds.'").change(function() {
				jQuery("#'.$this->getAttribute('id').' label").each(function(i, obj) {
					obj = jQuery(obj);
					obj.removeClass(obj.data("severityStyle"));
				});

				var label = jQuery("#'.$options['name'].'_label_" + jQuery(this).val());
				label.addClass(label.data("severityStyle"));
			});

			jQuery("'.$jsLabels.'").mouseenter(function() {
				var obj = jQuery(this);

				obj.addClass(obj.data("severityStyle"));
			})
			.mouseleave(function() {
				var obj = jQuery(this);

				if (!jQuery("#" + obj.attr("for")).prop("checked")) {
					obj.removeClass(obj.data("severityStyle"));
				}
			});

			// click on selected severity on form load
			jQuery("input[name=\''.$options['name'].'\']:checked").change();'
		, true);
	}
}
