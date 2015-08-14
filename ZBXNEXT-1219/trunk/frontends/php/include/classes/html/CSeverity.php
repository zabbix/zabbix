<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CSeverity extends CList {

	const CONTROL_CLASS_NAME = 'trigger-severity-radio';

	/**
	 * @param string $options['name']
	 * @param int    $options['value']
	 */
	public function __construct(array $options = []) {
		parent::__construct();

		$id = zbx_formatDomId($options['name']);

		$this->addClass(ZBX_STYLE_LIST_HOR_CHECK_RADIO);
		$this->addClass(self::CONTROL_CLASS_NAME);
		$this->setId($id);

		if (!array_key_exists('value', $options)) {
			$options['value'] = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}

		$config = select_config();

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$radio = (new CInput('radio', $options['name'], $severity))
				->setId(zbx_formatDomId($options['name'].'_'.$severity));
			if ($severity === $options['value']) {
				$radio->setAttribute('checked', 'checked');
			}

			parent::addItem(
				(new CListItem(
					new CLabel([$radio, getSeverityName($severity, $config)], $options['name'].'_'.$severity)
				))
					->setAttribute('data-severity-style', getSeverityStyle($severity))
					->setAttribute('area-pressed', $severity === $options['value'] ? 'true' : 'false')
			);
		}

		static $js_initialized = false;

		if (!$js_initialized) {
			insert_js(
				'jQuery(".'.self::CONTROL_CLASS_NAME.' li").mouseenter(function() {'."\n".
				'	jQuery(".'.self::CONTROL_CLASS_NAME.' li").each(function() {'."\n".
				'		var obj = jQuery(this);'."\n".
				''."\n".
				'		if (obj.attr("area-pressed") == "false") {'."\n".
				'			obj.removeClass(obj.data("severity-style"));'."\n".
				'		}'."\n".
				'	});'."\n".
				''."\n".
				'	var obj = jQuery(this);'."\n".
				''."\n".
				'	obj.addClass(obj.data("severity-style"));'."\n".
				'})'."\n".
				'.mouseleave(function() {'."\n".
				'	jQuery(".'.self::CONTROL_CLASS_NAME.' [area-pressed=\"true\"]").trigger("mouseenter");'."\n".
				'});'."\n".
				''."\n".
				'jQuery(".'.self::CONTROL_CLASS_NAME.' input[type=\"radio\"]").change(function() {'."\n".
				'	jQuery(".'.self::CONTROL_CLASS_NAME.' input[type=\"radio\"]").not(":checked").closest("li").attr("area-pressed", "false");'."\n".
				'	jQuery(".'.self::CONTROL_CLASS_NAME.' input[type=\"radio\"]:checked").closest("li").attr("area-pressed", "true");'."\n".
				'	jQuery(".'.self::CONTROL_CLASS_NAME.' li[area-pressed=\"true\"]").trigger("mouseenter");'."\n".
				'});'."\n".
				''."\n".
				'jQuery(".'.self::CONTROL_CLASS_NAME.' input[type=\"radio\"]:checked").trigger("change");'
			, true);

			$js_initialized = true;
		}
	}
}
