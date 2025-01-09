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


namespace Widgets\Honeycomb\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm,
	Fields\CWidgetFieldCheckBox,
	Fields\CWidgetFieldCheckBoxList,
	Fields\CWidgetFieldColor,
	Fields\CWidgetFieldIntegerBox,
	Fields\CWidgetFieldMultiSelectGroup,
	Fields\CWidgetFieldMultiSelectHost,
	Fields\CWidgetFieldPatternSelectItem,
	Fields\CWidgetFieldRadioButtonList,
	Fields\CWidgetFieldSelect,
	Fields\CWidgetFieldTags,
	Fields\CWidgetFieldTextArea,
	Fields\CWidgetFieldTextBox,
	Fields\CWidgetFieldThresholds
};

use CWidgetsData;

/**
 * Honeycomb widget form.
 */
class WidgetForm extends CWidgetForm {

	public const SHOW_PRIMARY_LABEL = 1;
	public const SHOW_SECONDARY_LABEL = 2;

	public const LABEL_TYPE_TEXT = 0;
	public const LABEL_TYPE_VALUE = 1;

	public const LABEL_SIZE_AUTO = 0;
	public const LABEL_SIZE_CUSTOM = 1;

	public const UNITS_POSITION_BEFORE = 0;
	public const UNITS_POSITION_AFTER = 1;

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				(new CWidgetFieldMultiSelectHost('hostids', _('Hosts')))
					->setDefault($this->isTemplateDashboard()
						? [
							CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
								CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_IDS
							)
						]
						: []
					)
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype_host', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('host_tags')
			)
			->addField(
				(new CWidgetFieldPatternSelectItem('items', _('Item patterns')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype_item', _('Item tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('item_tags')
			)
			->addField(
				new CWidgetFieldCheckBox('maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Show hosts in maintenance')
				)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					self::SHOW_PRIMARY_LABEL => _('Primary label'),
					self::SHOW_SECONDARY_LABEL => _('Secondary label')
				]))
					->setDefault([self::SHOW_PRIMARY_LABEL, self::SHOW_SECONDARY_LABEL])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_label_type', _('Type'), [
					self::LABEL_TYPE_TEXT => _('Text'),
					self::LABEL_TYPE_VALUE => _('Value')
				]))->setDefault(self::LABEL_TYPE_TEXT)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_label_decimal_places', _('Decimal places'), 0, 6))
					->setDefault(2)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				(new CWidgetFieldTextArea('primary_label', _('Text')))
					->setDefault('{HOST.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_label_size_type', null, [
					self::LABEL_SIZE_AUTO => _('Auto'),
					self::LABEL_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::LABEL_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_label_size', _('Size'), 1, 100))
					->setDefault(20)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				new CWidgetFieldCheckBox('primary_label_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('primary_label_color', _('Color')))->prefixLabel(_('Primary label'))
			)
			->addField(
				(new CWidgetFieldCheckBox('primary_label_units_show'))->setDefault(1)
			)
			->addField(
				new CWidgetFieldTextBox('primary_label_units', _('Units'))
			)
			->addField(
				(new CWidgetFieldSelect('primary_label_units_pos', _('Position'), [
					self::UNITS_POSITION_BEFORE => _('Before value'),
					self::UNITS_POSITION_AFTER => _('After value')
				]))->setDefault(self::UNITS_POSITION_AFTER)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_label_type', _('Type'), [
					self::LABEL_TYPE_TEXT => _('Text'),
					self::LABEL_TYPE_VALUE => _('Value')
				]))->setDefault(self::LABEL_TYPE_VALUE)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_label_decimal_places', _('Decimal places'), 0, 6))
					->setDefault(2)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldTextArea('secondary_label', _('Text')))
					->setDefault('{{ITEM.LASTVALUE}.fmtnum(2)}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_label_size_type', null, [
					self::LABEL_SIZE_AUTO => _('Auto'),
					self::LABEL_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::LABEL_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_label_size', _('Size'), 1, 100))
					->setDefault(30)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_label_bold', _('Bold')))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldColor('secondary_label_color', _('Color')))->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_label_units_show'))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldTextBox('secondary_label_units', _('Units')))
			)
			->addField(
				(new CWidgetFieldSelect('secondary_label_units_pos', _('Position'), [
					self::UNITS_POSITION_BEFORE => _('Before value'),
					self::UNITS_POSITION_AFTER => _('After value')
				]))->setDefault(self::UNITS_POSITION_AFTER)
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				new CWidgetFieldCheckBox('interpolation', _('Color interpolation'))
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'))
			);
	}

	public function validate(bool $strict = false): array {
		if ($strict && $this->isTemplateDashboard()) {
			$this->getField('hostids')->setValue([
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_IDS
				)
			]);
		}

		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if (!$this->getFieldValue('show')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show'), _('at least one option must be selected'));
		}

		return $errors;
	}
}
