<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


/**
 * Class makes datepicker widget field.
 */
class CWidgetFieldDatePicker extends CWidgetField {
	/**
	 * Date picker widget field.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setDefault('');
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @param string $form_name   Form name in which control is located.
	 * @param string $onselect    Callback script that is executed on date select.
	 *
	 * @return string
	 */
	public function getJavascript($form_name, $onselect = '') {
		return
			'var input = jQuery("[name=\"'.$this->getName().'\"]", jQuery("#'.$form_name.'")).get(0);'.
			'jQuery("#'.$this->getName().'_dp")'.
				'.data("clndr", create_calendar(null, input, null, null, "'.$form_name.'"))'.
				'.data("input", input)'.
				'.click(function() {'.
					'var b = jQuery(this),'.
						'o = b.offset(),'.
						'h = jQuery(b.data("clndr").clndr.clndr_calendar).outerHeight(),'. // Calendar height.
						'd = jQuery("#overlay_dialogue").offset(),'. // Dialog offset.
						't = parseInt(o.top + b.outerHeight() - h - d.top, 10),'. // Calculated calendar top position.
						'l = parseInt(o.left - d.left + b.outerWidth(), 10);'. // Calculated calendar left position.
					'b.data("clndr").clndr.clndrshow(t, l, b.data("input"));'.
					($onselect !== '' ? 'b.data("clndr").clndr.onselect = function() {'.$onselect.'};' : '').
					'return false;'.
				'})';
	}

	/**
	 * Validate "from" and "to" parameters for allowed period.
	 *
	 * @param string|null from
	 * @param string|null to
	 *
	 * @return array
	 */
	static function validateTimeSelectorPeriod($from, $to) {
		if ($from === null || $to === null) {
			return;
		}

		$errors = [];
		$ts = [];
		$range_time_parser = new CRangeTimeParser();

		foreach (['from' => $from, 'to' => $to] as $field => $value) {
			if ($range_time_parser->parse($value) !== CParser::PARSE_SUCCESS) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $field === 'from' ? _('From') : _('To'),
					_('a time unit is expected')
				);
				break;
			}
			else {
				$ts[$field] = $range_time_parser->getDateTime($field === 'from')->getTimestamp();
			}
		}

		if (!$errors) {
			$period = $ts['to'] - $ts['from'] + 1;

			if ($period < ZBX_MIN_PERIOD) {
				$errors[] = _n('Minimum time period to display is %1$s minute.',
					'Minimum time period to display is %1$s minutes.', (int) ZBX_MIN_PERIOD / SEC_PER_MIN
				);
			}
			elseif ($period > ZBX_MAX_PERIOD) {
				$errors[] = _n('Maximum time period to display is %1$s day.',
					'Maximum time period to display is %1$s days.', (int) ZBX_MAX_PERIOD / SEC_PER_DAY
				);
			}
		}

		return $errors;
	}
}
