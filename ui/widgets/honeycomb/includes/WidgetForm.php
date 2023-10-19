<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

namespace Widgets\Honeycomb\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm,
	Fields\CWidgetFieldCheckBox,
	Fields\CWidgetFieldCheckBoxList,
	Fields\CWidgetFieldColor,
	Fields\CWidgetFieldIntegerBox,
	Fields\CWidgetFieldItemPatternSelect,
	Fields\CWidgetFieldMultiSelectGroup,
	Fields\CWidgetFieldMultiSelectHost,
	Fields\CWidgetFieldRadioButtonList,
	Fields\CWidgetFieldTags,
	Fields\CWidgetFieldTextArea,
	Fields\CWidgetFieldThresholds
};

use CWidgetsData;

/**
 * Honeycomb widget form.
 */
class WidgetForm extends CWidgetForm {

	public const SHOW_PRIMARY = 1;
	public const SHOW_SECONDARY = 2;

	private const SIZE_PERCENT_MIN = 1;
	private const SIZE_PERCENT_MAX = 100;

	private const DEFAULT_PRIMARY_SIZE = 5;
	private const DEFAULT_SECONDARY_SIZE = 10;

	public const SIZE_CUSTOM = 1;
	private const SIZE_AUTO = 0;

	public const BOLD_ON = 1;

	public const INTERPOLATION_ON = 1;

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
						: CWidgetFieldMultiSelectHost::DEFAULT_VALUE
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
				(new CWidgetFieldItemPatternSelect('items', _('Item pattern')))
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
				(new CWidgetFieldCheckBox(
					'maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Show hosts in maintenance')
				))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					WidgetForm::SHOW_PRIMARY => _('Primary label'),
					WidgetForm::SHOW_SECONDARY => _('Secondary label')
				]))
					->setDefault([WidgetForm::SHOW_PRIMARY])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldTextArea('primary_label', _('Primary label')))
					->setDefault('{HOST.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_label_size_type', null, [
					self::SIZE_AUTO => _('Auto'),
					self::SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_label_size', _('Size'), self::SIZE_PERCENT_MIN,
					self::SIZE_PERCENT_MAX)
				)->setDefault(self::DEFAULT_PRIMARY_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('primary_label_bold', _('Bold'))
			)
			->addField(
				new CWidgetFieldColor('primary_label_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldTextArea('secondary_label', _('Secondary label')))
					->setDefault('{ITEM.LASTVALUE}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_label_size_type', null, [
					self::SIZE_AUTO => _('Auto'),
					self::SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_label_size', _('Size'), self::SIZE_PERCENT_MIN,
					self::SIZE_PERCENT_MAX)
				)->setDefault(self::DEFAULT_SECONDARY_SIZE)
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_label_bold', _('Bold')))->setDefault(self::BOLD_ON)
			)
			->addField(
				new CWidgetFieldColor('secondary_label_color', _('Color'))
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('interpolation', _('Color interpolation')))
					->setDefault(self::INTERPOLATION_ON)
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

		return parent::validate($strict);
	}
}
