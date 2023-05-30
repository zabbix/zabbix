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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Problems widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'],	$data['captions']['ms']['groups']['groupids'])
	: null;

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['show'])
	)
	->addField($groupids)
	->addField(array_key_exists('exclude_groupids', $data['fields'])
		? new CWidgetFieldMultiSelectGroupView($data['fields']['exclude_groupids'],
			$data['captions']['ms']['groups']['exclude_groupids']
		)
		: null
	)
	->addField(array_key_exists('hostids', $data['fields'])
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids'], $data['captions']['ms']['hosts']['hostids']))
			->setFilterPreselect(['id' => $groupids->getId(), 'submit_as' => 'groupid'])
		: null
	)
	->addField(
		new CWidgetFieldTextBoxView($data['fields']['problem'])
	)
	->addField(
		new CWidgetFieldSeveritiesView($data['fields']['severities'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['tags'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['show_tags'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['tag_name_format'])
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['tag_priority']))->setPlaceholder(_('comma-separated list'))
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['show_opdata'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_symptoms'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_suppressed'])
	)
	->addItem(getAcknowledgementStatusFieldsGroupViews($data['fields']))
	->addField(
		new CWidgetFieldSelectView($data['fields']['sort_triggers'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_timeline'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_problems_form.init('.json_encode([
		'sort_with_enabled_show_timeline' => [
			SCREEN_SORT_TRIGGERS_TIME_DESC => true,
			SCREEN_SORT_TRIGGERS_TIME_ASC => true
		]
	], JSON_THROW_ON_ERROR).');')
	->show();

function getAcknowledgementStatusFieldsGroupViews(array $fields): array {
	$acknowledgement_status_field = new CWidgetFieldRadioButtonListView($fields['acknowledgement_status']);
	$acknowledged_by_me_field = new CWidgetFieldCheckBoxView($fields['acknowledged_by_me']);

	return [
		new CLabel(_('Acknowledgement status')),
		new CFormField(new CHorList([
			$acknowledgement_status_field->getView()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$acknowledged_by_me_field->getLabel()->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$acknowledged_by_me_field->getView()
		]))
	];
}
