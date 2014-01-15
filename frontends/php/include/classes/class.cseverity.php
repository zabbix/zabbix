<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * @param string $options['id']
	 * @param string $options['name']
	 * @param int    $options['value']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', isset($options['id']) ? $options['id'] : zbx_formatDomId($options['name']));
		$this->addClass('jqueryinputset control-severity');

		if (!isset($options['value'])) {
			$options['value'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}

		$items = array();
		$jsIds = '';
		$jsLabels = '';

		foreach (getSeverityCaption() as $severity => $caption) {
			$items[] = new CRadioButton(
				$options['name'],
				$severity,
				null,
				$options['name'].'_'.$severity,
				($options['value'] == $severity)
			);

			$css = getSeverityStyle($severity);

			$label = new CLabel($caption, $options['name'].'_'.$severity, $options['name'].'_label_'.$severity);
			$label->attr('data-severity', $severity);
			$label->attr('data-severity-style', $css);

			if ($options['value'] == $severity) {
				$label->attr('aria-pressed', 'true');
				$label->addClass($css);
			}
			else {
				$label->attr('aria-pressed', 'false');
			}

			$items[] = $label;

			$jsIds .= ', #'.$options['name'].'_'.$severity;
			$jsLabels .= ', #'.$options['name'].'_label_'.$severity;
		}

		if ($jsIds) {
			$jsIds = substr($jsIds, 2);
			$jsLabels = substr($jsLabels, 2);
		}

		$this->addItem($items);

		insert_js('
			jQuery("'.$jsLabels.'").mouseenter(function() {
				jQuery("'.$jsLabels.'").each(function() {
					var obj = jQuery(this);

					if (obj.attr("aria-pressed") == "false") {
						obj.removeClass("ui-state-hover " + obj.data("severityStyle"));
					}
				});

				var obj = jQuery(this);

				obj.addClass(obj.data("severityStyle"));
			})
			.mouseleave(function() {
				jQuery("#'.$this->getAttribute('id').' [aria-pressed=\"true\"]").trigger("mouseenter");
			});

			jQuery("'.$jsIds.'").change(function() {
				jQuery("#'.$this->getAttribute('id').' [aria-pressed=\"true\"]").trigger("mouseenter");
			});'
		, true);
	}
}
