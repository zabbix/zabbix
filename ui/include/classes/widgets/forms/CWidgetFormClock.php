<?php
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


/**
 * Clock widget form.
 */
class CWidgetFormClock extends CWidgetForm {

	/**
	 * Minimum value of percentage.
	 *
	 * @var int
	 */
	private const WIDGET_CLOCK_PERCENT_MIN = 1;

	/**
	 * Maximum value of percentage.
	 *
	 * @var int
	 */
	private const WIDGET_CLOCK_PERCENT_MAX = 100;

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_CLOCK);

		// Time type field.
		$field_time_type = (new CWidgetFieldSelect('time_type', _('Time type'), [
			TIME_TYPE_LOCAL => _('Local time'),
			TIME_TYPE_SERVER => _('Server time'),
			TIME_TYPE_HOST => _('Host time')
		]))->setDefault(TIME_TYPE_LOCAL);

		if (array_key_exists('time_type', $this->data)) {
			$field_time_type->setValue($this->data['time_type']);
		}

		$this->fields[$field_time_type->getName()] = $field_time_type;

		// Item field.
		if ($field_time_type->getValue() === TIME_TYPE_HOST) {
			// Item multiselector with single value.
			$field_item = (new CWidgetFieldMsItem('itemid', _('Item'), $templateid))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
				->setMultiple(false);

			if (array_key_exists('itemid', $this->data)) {
				$field_item->setValue($this->data['itemid']);
			}

			$this->fields[$field_item->getName()] = $field_item;
		}

		// clock type
		$field_clock_type = (new CWidgetFieldRadioButtonList('clock_type', _('Clock type'), [
			WIDGET_CLOCK_TYPE_ANALOG => _('Analog'),
			WIDGET_CLOCK_TYPE_DIGITAL => _('Digital')
		]))
			->setDefault(WIDGET_CLOCK_TYPE_ANALOG)
			->setModern(true);

		if (array_key_exists('clock_type', $this->data)) {
			$field_clock_type->setValue($this->data['clock_type']);
		}

		$this->fields[$field_clock_type->getName()] = $field_clock_type;

		// field show
		$field_show =(new CWidgetFieldCheckBoxList('show', _('Show')))
			->setDefault([WIDGET_CLOCK_SHOW_TIME])
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK);

		if (array_key_exists('show', $this->data)) {
			$field_show->setValue($this->data['show']);
		}

		$this->fields[$field_show->getName()] = $field_show;

		// advanced configuration
		$field_adv_conf = (new CWidgetFieldCheckBox('adv_conf', _('Advanced configuration')))->setDefault(0);

		if (array_key_exists('adv_conf', $this->data)) {
			$field_adv_conf->setValue($this->data['adv_conf']);
		}

		$this->fields[$field_adv_conf->getName()] = $field_adv_conf;

		// background color
		$field_bg_color = (new CWidgetFieldColor('bg_color', _('Background color')))->setDefault('');

		if (array_key_exists('bg_color', $this->data)) {
			$field_bg_color->setValue($this->data['bg_color']);
		}

		$this->fields[$field_bg_color->getName()] = $field_bg_color;

		// date size
		$field_date_size = (new CWidgetFieldIntegerBox('date_size', _('Size'), self::WIDGET_CLOCK_PERCENT_MIN,
			self::WIDGET_CLOCK_PERCENT_MAX
		))->setDefault(20);

		if (array_key_exists('date_size', $this->data)) {
			$field_date_size->setValue($this->data['date_size']);
		}

		$this->fields[$field_date_size->getName()] = $field_date_size;

		// date bold
		$field_date_bold = (new CWidgetFieldCheckBox('date_bold', _('Bold')))->setDefault(0);

		if (array_key_exists('date_bold', $this->data)) {
			$field_date_bold->setValue($this->data['date_bold']);
		}

		$this->fields[$field_date_bold->getName()] = $field_date_bold;

		// date color
		$field_date_color = (new CWidgetFieldColor('date_color', _('Color')))->setDefault('');

		if (array_key_exists('date_color', $this->data)) {
			$field_date_color->setValue($this->data['date_color']);
		}

		$this->fields[$field_date_color->getName()] = $field_date_color;

		// time size
		$field_time_size = (new CWidgetFieldIntegerBox('time_size', _('Size'), self::WIDGET_CLOCK_PERCENT_MIN,
			self::WIDGET_CLOCK_PERCENT_MAX
		))->setDefault(30);

		if (array_key_exists('time_size', $this->data)) {
			$field_time_size->setValue($this->data['time_size']);
		}

		$this->fields[$field_time_size->getName()] = $field_time_size;

		// time bold
		$field_time_bold = (new CWidgetFieldCheckBox('time_bold', _('Bold')))->setDefault(0);

		if (array_key_exists('time_bold', $this->data)) {
			$field_time_bold->setValue($this->data['time_bold']);
		}

		$this->fields[$field_time_bold->getName()] = $field_time_bold;

		// time color
		$field_time_color = (new CWidgetFieldColor('time_color', _('Color')))->setDefault('');

		if (array_key_exists('time_color', $this->data)) {
			$field_time_color->setValue($this->data['time_color']);
		}

		$this->fields[$field_time_color->getName()] = $field_time_color;

		// time seconds
		$field_time_sec = (new CWidgetFieldCheckBox('time_sec', _('Seconds')))->setDefault(1);

		if (array_key_exists('time_sec', $this->data)) {
			$field_time_sec->setValue($this->data['time_sec']);
		}

		$this->fields[$field_time_sec->getName()] = $field_time_sec;

		// time format
		$field_time_format = (new CWidgetFieldRadioButtonList('time_format', _('Format'), [
			WIDGET_CLOCK_HOUR_TWENTY_FOUR => _('24-hour'),
			WIDGET_CLOCK_HOUR_TWELVE => _('12-hour')
		]))
			->setDefault(WIDGET_CLOCK_HOUR_TWENTY_FOUR)
			->setModern(true);

		if (array_key_exists('time_format', $this->data)) {
			$field_time_format->setValue($this->data['time_format']);
		}

		$this->fields[$field_time_format->getName()] = $field_time_format;

		// time zone size
		$field_tzone_size = (new CWidgetFieldIntegerBox('tzone_size', _('Size'), self::WIDGET_CLOCK_PERCENT_MIN,
			self::WIDGET_CLOCK_PERCENT_MAX
		))->setDefault(20);

		if (array_key_exists('tzone_size', $this->data)) {
			$field_tzone_size->setValue($this->data['tzone_size']);
		}

		$this->fields[$field_tzone_size->getName()] = $field_tzone_size;

		// time zone bold
		$field_tzone_bold = (new CWidgetFieldCheckBox('tzone_bold', _('Bold')))->setDefault(0);

		if (array_key_exists('tzone_bold', $this->data)) {
			$field_tzone_bold->setValue($this->data['tzone_bold']);
		}

		$this->fields[$field_tzone_bold->getName()] = $field_tzone_bold;

		// time zone color
		$field_tzone_color = (new CWidgetFieldColor('tzone_color', _('Color')))->setDefault('');

		if (array_key_exists('tzone_color', $this->data)) {
			$field_tzone_color->setValue($this->data['tzone_color']);
		}

		$this->fields[$field_tzone_color->getName()] = $field_tzone_color;

		// time zone time zone
		$field_tzone_timezone = (new CWidgetFieldTimeZone('tzone_timezone', _('Time zone')))
			->setDefault(ZBX_DEFAULT_TIMEZONE);

		if ($field_time_type->getValue() === TIME_TYPE_LOCAL) {
			$field_tzone_timezone->setDefault(TIMEZONE_DEFAULT_LOCAL);
		}

		if (array_key_exists('tzone_timezone', $this->data)) {
			$field_tzone_timezone->setValue($this->data['tzone_timezone']);
		}
		elseif ($field_time_type->getValue() === TIME_TYPE_LOCAL) {
			$field_tzone_timezone->setValue(TIMEZONE_DEFAULT_LOCAL);
		}

		$this->fields[$field_tzone_timezone->getName()] = $field_tzone_timezone;

		// time zone format
		$field_tzone_format = (new CWidgetFieldRadioButtonList('tzone_format', _('Format'), [
			WIDGET_CLOCK_TIMEZONE_SHORT => _('Short'),
			WIDGET_CLOCK_TIMEZONE_FULL => _('Full')
		]))
			->setDefault(WIDGET_CLOCK_TIMEZONE_SHORT)
			->setModern(true);

		if (array_key_exists('tzone_format', $this->data)) {
			$field_tzone_format->setValue($this->data['tzone_format']);
		}

		$this->fields[$field_tzone_format->getName()] = $field_tzone_format;
	}
}
