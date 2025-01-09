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


namespace Widgets\ActionLog\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBoxList,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectUser,
	CWidgetFieldMultiSelectAction,
	CWidgetFieldMultiSelectMediaType,
	CWidgetFieldSelect,
	CWidgetFieldTextBox,
	CWidgetFieldTimePeriod
};

use CWidgetsData;

/**
 * Action log widget form.
 */
class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				new CWidgetFieldMultiSelectUser('userids', _('Recipients'))
			)
			->addField(
				new CWidgetFieldMultiSelectAction('actionids', _('Actions'))
			)
			->addField(
				new CWidgetFieldMultiSelectMediaType('mediatypeids', _('Media types'))
			)
			->addField(
				new CWidgetFieldCheckBoxList('statuses', _('Status'), [
					ALERT_STATUS_NOT_SENT => _('In progress'),
					ALERT_STATUS_SENT => _('Sent/Executed'),
					ALERT_STATUS_FAILED => _('Failed')
				])
			)
			->addField(
				new CWidgetFieldTextBox('message', _('Search string'))
			)
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldSelect('sort_triggers', _('Sort entries by'), [
					SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_MEDIA_TYPE_DESC => _('Media type').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_MEDIA_TYPE_ASC => _('Media type').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_STATUS_DESC => _('Status').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_STATUS_ASC => _('Status').' ('._('ascending').')',
					SCREEN_SORT_TRIGGERS_RECIPIENT_DESC => _('Recipient').' ('._('descending').')',
					SCREEN_SORT_TRIGGERS_RECIPIENT_ASC => _('Recipient').' ('._('ascending').')'
				]))->setDefault(SCREEN_SORT_TRIGGERS_TIME_DESC)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', _('Show lines'), ZBX_MIN_WIDGET_LINES, ZBX_MAX_WIDGET_LINES))
					->setDefault(ZBX_DEFAULT_WIDGET_LINES)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}
}
