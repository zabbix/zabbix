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


/**
 * Top items widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\TopItems\Includes\CWidgetFieldColumnsListView;

$form = new CWidgetFormView($data);

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$form
	->addField($groupids)
	->addField(array_key_exists('hostids', $data['fields'])
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
			->setFilterPreselect([
				'id' => $groupids->getId(),
				'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
				'submit_as' => 'groupid'
			])
		: null
	)
	->addField(array_key_exists('host_tags_evaltype', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['host_tags_evaltype'])
		: null
	)
	->addField(array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['layout'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addFieldsGroup(
				(new CWidgetFieldsGroupView(_('Host ordering')))
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['host_ordering_order_by'])
					)
					->addField(
						(new CWidgetFieldPatternSelectItemView($data['fields']['host_ordering_item']))
							->removeLabel()
							->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
					)
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['host_ordering_order'])
					)
					->addField(
						new CWidgetFieldIntegerBoxView($data['fields']['host_ordering_limit'])
					)
					->addRowClass('fields-group-host-ordering')
			)
			->addFieldsGroup(
				(new CWidgetFieldsGroupView(_('Item ordering')))
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['item_ordering_order_by'])
					)
					->addField(
						(new CWidgetFieldPatternSelectHostView($data['fields']['item_ordering_host']))
							->removeLabel()
							->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
					)
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['item_ordering_order'])
					)
					->addField(
						(new CWidgetFieldIntegerBoxView($data['fields']['item_ordering_limit']))
							->setFieldHint(makeHelpIcon(_('Limit applies to each "Item pattern" separately')))
					)
					->addRowClass('fields-group-item-ordering')
			)
			->addField(
				new CWidgetFieldRadioButtonListView($data['fields']['show_column_header'])
			)
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_topitems_form.init('.json_encode([
		'templateid' => $data['templateid']
	], JSON_THROW_ON_ERROR).');')
	->show();
