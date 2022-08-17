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
 * Problems by severity widget form.
 */
class CWidgetFormProblemsBySv extends CWidgetForm {

	public function __construct(array $values, ?string $templateid) {
		parent::__construct(WIDGET_PROBLEMS_BY_SV, $values, $templateid);
	}

	protected function addFields(): self {
		parent::addFields();

		return $this
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
				(new CWidgetFieldRadioButtonList('show_type', _('Show'), [
					WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS => _('Host groups'),
					WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS => _('Totals')
				]))
					->setDefault(WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS)
					->setAction('var disabled = jQuery(this).filter("[value=\''.WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS.'\']")'. // TODO: AS Move to widget js
						'.is(":checked");'.
						'jQuery("#hide_empty_groups").prop("disabled", !disabled);'.
						'jQuery("#layout input").prop("disabled", disabled)'
					)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					STYLE_HORIZONTAL => _('Horizontal'),
					STYLE_VERTICAL => _('Vertical')
				]))
					->setDefault(STYLE_HORIZONTAL)
					->setFlags(
						array_key_exists('show_type', $this->values)
							&& $this->values['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS
						? CWidgetField::FLAG_DISABLED
						: 0x00
					)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_opdata', _('Show operational data'), [
					OPERATIONAL_DATA_SHOW_NONE => _('None'),
					OPERATIONAL_DATA_SHOW_SEPARATELY => _('Separately'),
					OPERATIONAL_DATA_SHOW_WITH_PROBLEM => _('With problem name')
				]))->setDefault(OPERATIONAL_DATA_SHOW_NONE)
			)
			->addField(
				new CWidgetFieldCheckBox('show_suppressed', _('Show suppressed problems'))
			)
			->addField(
				(new CWidgetFieldCheckBox('hide_empty_groups', _('Hide groups without problems')))
					->setFlags(
						array_key_exists('show_type', $this->values)
							&& $this->values['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS
						? CWidgetField::FLAG_DISABLED
						: 0x00
					)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('ext_ack', _('Problem display'), [
					EXTACK_OPTION_ALL => _('All'),
					EXTACK_OPTION_BOTH => _('Separated'),
					EXTACK_OPTION_UNACK => _('Unacknowledged only')
				]))
					->setDefault(EXTACK_OPTION_ALL)
					->setFlags(CWidgetField::FLAG_ACKNOWLEDGES)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_timeline', _('Show timeline')))->setDefault(ZBX_TIMELINE_ON)
			);
	}
}
