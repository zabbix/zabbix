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


/**
 * Clock widget form.
 */
class CWidgetFormClock extends CWidgetForm {

	private const SIZE_PERCENT_MIN = 1;
	private const SIZE_PERCENT_MAX = 100;

	private const DEFAULT_DATE_SIZE = 20;
	private const DEFAULT_TIME_SIZE = 30;
	private const DEFAULT_TIMEZONE_SIZE = 20;

	public function __construct(array $values, ?string $templateid) {
		parent::__construct(WIDGET_CLOCK, $values, $templateid);
	}

	protected function addFields(): self {
		parent::addFields();

		$time_type = array_key_exists('time_type', $this->values) ? $this->values['time_type'] : null;

		return $this
			->addField(
				(new CWidgetFieldSelect('time_type', _('Time type'), [
					TIME_TYPE_LOCAL => _('Local time'),
					TIME_TYPE_SERVER => _('Server time'),
					TIME_TYPE_HOST => _('Host time')
				]))->setDefault(TIME_TYPE_LOCAL)
			)
			->addField($time_type == TIME_TYPE_HOST
				? (new CWidgetFieldMultiSelectItem('itemid', _('Item'), $this->templateid))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
				: null
			)
			->addField(
				(new CWidgetFieldRadioButtonList('clock_type', _('Clock type'), [
					WIDGET_CLOCK_TYPE_ANALOG => _('Analog'),
					WIDGET_CLOCK_TYPE_DIGITAL => _('Digital')
				]))->setDefault(WIDGET_CLOCK_TYPE_ANALOG)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					WIDGET_CLOCK_SHOW_DATE => _('Date'),
					WIDGET_CLOCK_SHOW_TIME => _('Time'),
					WIDGET_CLOCK_SHOW_TIMEZONE => _('Time zone')
				]))
					->setDefault([WIDGET_CLOCK_SHOW_TIME])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldCheckBox('adv_conf', _('Advanced configuration'))
			)
			->addField(
				(new CWidgetFieldColor('bg_color', _('Background color')))->allowInherited()
			)
			->addField(
				(new CWidgetFieldIntegerBox('date_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_DATE_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('date_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('date_color', _('Color')))->allowInherited()
			)
			->addField(
				(new CWidgetFieldIntegerBox('time_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_TIME_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('time_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('time_color', _('Color')))->allowInherited()
			)
			->addField(
				(new CWidgetFieldCheckBox('time_sec', _('Seconds')))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('time_format', _('Format'), [
					WIDGET_CLOCK_HOUR_TWENTY_FOUR => _('24-hour'),
					WIDGET_CLOCK_HOUR_TWELVE => _('12-hour')
				]))->setDefault(WIDGET_CLOCK_HOUR_TWENTY_FOUR)
			)
			->addField(
				(new CWidgetFieldIntegerBox('tzone_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_TIMEZONE_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('tzone_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('tzone_color', _('Color')))->allowInherited()
			)
			->addField(
				(new CWidgetFieldTimeZone('tzone_timezone', _('Time zone')))
					->setDefault($time_type == TIME_TYPE_LOCAL ? TIMEZONE_DEFAULT_LOCAL : ZBX_DEFAULT_TIMEZONE)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('tzone_format', _('Format'), [
					WIDGET_CLOCK_TIMEZONE_SHORT => _('Short'),
					WIDGET_CLOCK_TIMEZONE_FULL => _('Full')
				]))->setDefault(WIDGET_CLOCK_TIMEZONE_SHORT)
			);
	}
}
