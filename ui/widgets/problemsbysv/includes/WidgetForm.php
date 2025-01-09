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


namespace Widgets\ProblemsBySv\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSeverities,
	CWidgetFieldTags,
	CWidgetFieldTextBox
};

use Widgets\ProblemsBySv\Widget;

/**
 * Problems by severity widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		$is_show_type_totals = array_key_exists('show_type', $this->values)
			&& $this->values['show_type'] == Widget::SHOW_TOTALS;

		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('exclude_groupids', _('Exclude host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				new CWidgetFieldTextBox('problem', _('Problem'))
			)
			->addField(
				new CWidgetFieldSeverities('severities', _('Severity'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype', _('Problem tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('tags')
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('show_type', _('Show'), [
					Widget::SHOW_GROUPS => _('Host groups'),
					Widget::SHOW_TOTALS => _('Totals')
				]))->setDefault(Widget::SHOW_GROUPS)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					STYLE_HORIZONTAL => _('Horizontal'),
					STYLE_VERTICAL => _('Vertical')
				]))
					->setDefault(STYLE_HORIZONTAL)
					->setFlags($this->isTemplateDashboard() || $is_show_type_totals
						? 0x00
						: CWidgetField::FLAG_DISABLED
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
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldCheckBox('hide_empty_groups', _('Hide groups without problems')))
					->setFlags($is_show_type_totals ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('ext_ack', _('Problem display'), [
					EXTACK_OPTION_ALL => _('All'),
					EXTACK_OPTION_BOTH => _('Separated'),
					EXTACK_OPTION_UNACK => _('Unacknowledged only')
				]))->setDefault(EXTACK_OPTION_ALL)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_timeline', _('Show timeline')))->setDefault(ZBX_TIMELINE_ON)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}
}
