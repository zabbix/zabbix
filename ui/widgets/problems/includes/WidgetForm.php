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


namespace Widgets\Problems\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldSeverities,
	CWidgetFieldTags,
	CWidgetFieldTextBox
};

/**
 * Problems widget form.
 */
class WidgetForm extends CWidgetForm {

	private bool $show_tags = false;

	protected function normalizeValues(array $values): array {
		$values = self::convertDottedKeys($values);

		if (array_key_exists('show_tags', $values)) {
			$this->show_tags = $values['show_tags'] !== SHOW_TAGS_NONE;
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('show', _('Show'), [
					TRIGGERS_OPTION_RECENT_PROBLEM => _('Recent problems'),
					TRIGGERS_OPTION_IN_PROBLEM => _('Problems'),
					TRIGGERS_OPTION_ALL => _('History')
				]))->setDefault(TRIGGERS_OPTION_RECENT_PROBLEM)
			)
			->addField(
				new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldMultiSelectGroup('exclude_groupids', _('Exclude host groups'))
			)
			->addField(
				new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				new CWidgetFieldTextBox('problem', _('Problem'))
			)
			->addField(
				new CWidgetFieldSeverities('severities', _('Severity'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('tags')
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_tags', _('Show tags'), [
					SHOW_TAGS_NONE => _('None'),
					SHOW_TAGS_1 => SHOW_TAGS_1,
					SHOW_TAGS_2 => SHOW_TAGS_2,
					SHOW_TAGS_3 => SHOW_TAGS_3
				]))->setDefault(SHOW_TAGS_NONE)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('tag_name_format', _('Tag name'), [
					TAG_NAME_FULL => _('Full'),
					TAG_NAME_SHORTENED => _('Shortened'),
					TAG_NAME_NONE => _('None')
				]))
					->setDefault(TAG_NAME_FULL)
					->setFlags($this->show_tags ? 0x00 : CWidgetField::FLAG_DISABLED)
			)
			->addField(
				(new CWidgetFieldTextBox('tag_priority', _('Tag display priority')))
					->setFlags($this->show_tags ? 0x00 : CWidgetField::FLAG_DISABLED)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_opdata', _('Show operational data'), [
					OPERATIONAL_DATA_SHOW_NONE => _('None'),
					OPERATIONAL_DATA_SHOW_SEPARATELY => _('Separately'),
					OPERATIONAL_DATA_SHOW_WITH_PROBLEM => _('With problem name')
				]))->setDefault(OPERATIONAL_DATA_SHOW_NONE)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_symptoms', _('Show symptoms')))->setDefault(0)
			)
			->addField(
				new CWidgetFieldCheckBox('show_suppressed', _('Show suppressed problems'))
			)
			->addField(
				(new CWidgetFieldCheckBox('unacknowledged', _('Show unacknowledged only')))
					->setFlags(CWidgetField::FLAG_ACKNOWLEDGES)
			)
			->addField(
				(new CWidgetFieldSelect('sort_triggers', _('Sort entries by'), [
					SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_SEVERITY_ASC => _('Severity').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_NAME_DESC => _('Problem').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_NAME_ASC => _('Problem').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_HOST_NAME_DESC => _('Host').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host').' ('._('ascending').')'
				]))->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_timeline', _('Show timeline')))
					->setDefault(ZBX_TIMELINE_ON)
					->setFlags(
						!array_key_exists('sort_triggers', $this->values)
							|| !array_key_exists($this->values['sort_triggers'], [
								SCREEN_SORT_TRIGGERS_TIME_DESC => true,
								SCREEN_SORT_TRIGGERS_TIME_ASC => true
							])
						? CWidgetField::FLAG_DISABLED
						: 0x00
					)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->setDefault(ZBX_DEFAULT_WIDGET_LINES)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}
}
