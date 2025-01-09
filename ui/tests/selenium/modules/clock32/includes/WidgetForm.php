<?php declare(strict_types = 0);
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


namespace Modules\Clock2\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldCheckBoxList,
	CWidgetFieldColor,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTimeZone
};

use Modules\Clock2\Widget;

/**
 * Clock widget form.
 */
class WidgetForm extends CWidgetForm {

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if ($this->getFieldValue('clock_type') == Widget::TYPE_DIGITAL && !$this->getFieldValue('show')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show'), _('at least one option must be selected'));
		}

		return $errors;
	}

	public function addFields(): self {
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
				? (new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
				: null
			)
			->addField(
				(new CWidgetFieldRadioButtonList('clock_type', _('Clock type'), [
					Widget::TYPE_ANALOG => _('Analog'),
					Widget::TYPE_DIGITAL => _('Digital')
				]))->setDefault(Widget::TYPE_ANALOG)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					Widget::SHOW_DATE => _('Date'),
					Widget::SHOW_TIME => _('Time'),
					Widget::SHOW_TIMEZONE => _('Time zone')
				]))
					->setDefault([Widget::SHOW_TIME])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldColor('bg_color', _('Background color')))->allowInherited()
			)
			->addField(
				new CWidgetFieldCheckBox('date_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('date_color', _('Color')))->allowInherited()
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
					Widget::HOUR_24 => _('24-hour'),
					Widget::HOUR_12 => _('12-hour')
				]))->setDefault(Widget::HOUR_24)
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
					Widget::TIMEZONE_SHORT => _('Short'),
					Widget::TIMEZONE_FULL => _('Full')
				]))->setDefault(Widget::TIMEZONE_SHORT)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
