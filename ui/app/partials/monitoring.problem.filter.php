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
 * @var CPartial $this
 */

$left_column = (new CFormList())
	->addRow(_('Show'),
		(new CRadioButtonList('show', (int) $data['show']))
			->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM, 'show_1#{uniqid}')
			->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM, 'show_3#{uniqid}')
			->addValue(_('History'), TRIGGERS_OPTION_ALL, 'show_2#{uniqid}')
			->setId('show_#{uniqid}')
			->setModern(true)
	)
	->addRow((new CLabel(_('Host groups'), 'groupids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('groups', $data) ? $data['groups'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'groupids_',
					'with_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('groupids_#{uniqid}')
	)
	->addRow((new CLabel(_('Hosts'), 'hostids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => array_key_exists('hosts', $data) ? $data['hosts'] : [],
			'popup' => [
				'filter_preselect' => [
					'id' => 'groupids_',
					'submit_as' => 'groupid'
				],
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostids_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('hostids_#{uniqid}')
	)
	->addRow((new CLabel(_('Triggers'), 'triggerids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'triggerids[]',
			'object_name' => 'triggers',
			'data' => array_key_exists('triggers', $data) ? $data['triggers'] : [],
			'popup' => [
				'filter_preselect' => [
					'id' => 'hostids_',
					'submit_as' => 'hostid'
				],
				'parameters' => [
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'triggerids_',
					'monitored_hosts' => true,
					'with_monitored_triggers' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('triggerids_#{uniqid}')
	)
	->addRow(_('Problem'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('name_#{uniqid}')
	)
	->addRow(_('Severity'),
		(new CCheckBoxList('severities'))
			->setUniqid('#{uniqid}')
			->setOptions(CSeverityHelper::getSeverities())
			->setChecked($data['severities'])
			->setColumns(3)
			->setVertical()
			->showTitles()
	);

$filter_age = (new CNumericBox('age', $data['age'], 3, false, false, false))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	->removeId();
if ($data['age_state'] == 0) {
	$filter_age->setAttribute('disabled', 'disabled');
}

$left_column
	->addRow(_('Age less than'), [
		(new CCheckBox('age_state'))
			->setChecked($data['age_state'] == 1)
			->setUncheckedValue(0)
			->setId('age_state_#{uniqid}'),
		$filter_age,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('days')
	])
	->addRow(_('Show symptoms'), [
		(new CCheckBox('show_symptoms'))
			->setChecked($data['show_symptoms'] == 1)
			->setUncheckedValue(0)
			->setId('show_symptoms_#{uniqid}')
	])
	->addRow(_('Show suppressed problems'), [
		(new CCheckBox('show_suppressed'))
			->setChecked($data['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
			->setUncheckedValue(0)
			->setId('show_suppressed_#{uniqid}')
	])
	->addRow(
		_('Acknowledgement status'),
		(new CHorList())
			->addItem((new CRadioButtonList('acknowledgement_status', (int) $data['acknowledgement_status']))
				->addValue(_('All'), ZBX_ACK_STATUS_ALL, 'acknowledgement_status_0_#{uniqid}')
				->addValue(_('Unacknowledged'), ZBX_ACK_STATUS_UNACK, 'acknowledgement_status_1_#{uniqid}')
				->addValue(_('Acknowledged'), ZBX_ACK_STATUS_ACK, 'acknowledgement_status_2_#{uniqid}')
				->setModern(true)
			)
			->addItem((new CCheckBox('acknowledged_by_me', 1))
				->setLabelPosition(CCheckBox::LABEL_POSITION_LEFT)
				->setChecked($data['acknowledged_by_me'] == 1)
				->setUncheckedValue(0)
				->setLabel(_('By me'))
				->setId('acknowledged_by_me_#{uniqid}')
			)
	);

$filter_inventory_table = (new CTable())->setId('filter-inventory_#{uniqid}');

$inventories = array_column(getHostInventories(), 'title', 'db_field');

if(!$data['inventory']) {
	$data['inventory'] = [['field' => '', 'value' => '']];
}

$i = 0;
foreach ($data['inventory'] as $field) {
	$filter_inventory_table->addRow([
		(new CSelect('inventory['.$i.'][field]'))
			->setValue($field['field'])
			->addOptions(CSelect::createOptionsFromArray($inventories)),
		(new CTextBox('inventory['.$i.'][value]', $field['value']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('inventory['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_inventory_table->addRow(
	(new CCol(
		(new CButton('inventory_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->removeId()
	))->setColSpan(3)
);

$filter_tags_table = new CTable();
$filter_tags_table->setId('filter-tags_#{uniqid}');

$filter_tags_table->addRow(
	(new CCol(
		(new CRadioButtonList('evaltype', (int) $data['evaltype']))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR, 'evaltype_0#{uniqid}')
			->addValue(_('Or'), TAG_EVAL_TYPE_OR, 'evaltype_2#{uniqid}')
			->setModern(true)
			->setId('evaltype_#{uniqid}')
	))->setColSpan(4)
);

if(!$data['tags']) {
	$data['tags'] = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$i = 0;
foreach ($data['tags'] as $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CSelect('tags['.$i.'][operator]'))
			->addOptions(CSelect::createOptionsFromArray([
				TAG_OPERATOR_EXISTS => _('Exists'),
				TAG_OPERATOR_EQUAL => _('Equals'),
				TAG_OPERATOR_LIKE => _('Contains'),
				TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
				TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
				TAG_OPERATOR_NOT_LIKE => _('Does not contain')
			]))
			->setValue($tag['operator'])
			->setFocusableElementId('tags-'.$i.'#{uniqid}-operator-select')
			->setId('tags_'.$i.'#{uniqid}_operator'),
		(new CTextBox('tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setId('tags_'.$i.'#{uniqid}_value'),
		(new CCol(
			(new CButton('tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->removeId()
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->removeId()
	))->setColSpan(3)
);

$tag_format_line = (new CHorList())
	->addItem((new CRadioButtonList('show_tags', (int) $data['show_tags']))
		->addValue(_('None'), SHOW_TAGS_NONE, 'show_tags_0#{uniqid}')
		->addValue(SHOW_TAGS_1, SHOW_TAGS_1, 'show_tags_1#{uniqid}')
		->addValue(SHOW_TAGS_2, SHOW_TAGS_2, 'show_tags_2#{uniqid}')
		->addValue(SHOW_TAGS_3, SHOW_TAGS_3, 'show_tags_3#{uniqid}')
		->setModern(true)
		->setId('show_tags_#{uniqid}')
	)
	->addItem((new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN))
	->addItem(new CLabel(_('Tag name')))
	->addItem((new CRadioButtonList('tag_name_format', (int) $data['tag_name_format']))
		->addValue(_('Full'), TAG_NAME_FULL, 'tag_name_format_0#{uniqid}')
		->addValue(_('Shortened'), TAG_NAME_SHORTENED, 'tag_name_format_1#{uniqid}')
		->addValue(_('None'), TAG_NAME_NONE, 'tag_name_format_2#{uniqid}')
		->setModern(true)
		->setEnabled((int) $data['show_tags'] !== SHOW_TAGS_NONE)
		->setId('tag_name_format_#{uniqid}')
	);

$right_column = (new CFormList())
	->addRow(_('Host inventory'), $filter_inventory_table)
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Show tags'), $tag_format_line)
	->addRow(_('Tag display priority'),
		(new CTextBox('tag_priority', $data['tag_priority']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setAttribute('placeholder', _('comma-separated list'))
			->setEnabled((int) $data['show_tags'] !== SHOW_TAGS_NONE)
			->setId('tag_priority_#{uniqid}')
	)
	->addRow(_('Show operational data'), [
		(new CRadioButtonList('show_opdata', (int) $data['show_opdata']))
			->addValue(_('None'), OPERATIONAL_DATA_SHOW_NONE, 'show_opdata_0_#{uniqid}')
			->addValue(_('Separately'), OPERATIONAL_DATA_SHOW_SEPARATELY, 'show_opdata_1_#{uniqid}')
			->addValue(_('With problem name'), OPERATIONAL_DATA_SHOW_WITH_PROBLEM, 'show_opdata_2_#{uniqid}')
			->setModern(true)
			->setEnabled($data['compact_view'] == 0)
			->removeId()
	])

	->addRow(_('Compact view'), [
		(new CCheckBox('compact_view'))
			->setChecked($data['compact_view'] == 1)
			->setUncheckedValue(0)
			->setId('compact_view_#{uniqid}'),
		(new CDiv([
			(new CLabel(_('Show timeline'), 'show_timeline_#{uniqid}'))->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('show_timeline'))
				->setChecked($data['show_timeline'] == ZBX_TIMELINE_ON)
				->setEnabled($data['compact_view'] == 0)
				->setUncheckedValue(0)
				->setId('show_timeline_#{uniqid}')
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	])
	->addRow(_('Show details'), [
		(new CCheckBox('details'))
			->setChecked($data['details'] == 1)
			->setEnabled($data['compact_view'] == 0)
			->setUncheckedValue(0)
			->setId('details_#{uniqid}'),
		(new CDiv([
			(new CLabel(_('Highlight whole row'), 'highlight_row_#{uniqid}'))->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('highlight_row'))
				->setChecked($data['highlight_row'] == 1)
				->setEnabled($data['compact_view'] == 1)
				->setUncheckedValue(0)
				->setId('highlight_row_#{uniqid}')
		]))
			->addClass(ZBX_STYLE_FILTER_HIGHLIGHT_ROW_CB)
			->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	]);

$template = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem([
		(new CDiv($left_column))->addClass(ZBX_STYLE_CELL),
		(new CDiv($right_column))->addClass(ZBX_STYLE_CELL)
	]);
$template = (new CForm('get'))
	->setName('zbx_filter')
	->addItem([
		$template,
		(new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN),
		(new CVar('filter_name', '#{filter_name}'))->removeId(),
		(new CVar('filter_show_counter', '#{filter_show_counter}'))->removeId(),
		(new CVar('filter_custom_time', '#{filter_custom_time}'))->removeId(),
		(new CVar('sort', '#{sort}'))->removeId(),
		(new CVar('sortorder', '#{sortorder}'))->removeId()
	]);

if (array_key_exists('render_html', $data)) {
	/**
	 * Render HTML to prevent filter flickering after initial page load. PHP created content will be replaced by
	 * javascript with additional event handling (dynamic rows, etc.) when page will be fully loaded and javascript
	 * executed.
	 */
	$template->show();

	return;
}

(new CTemplateTag('filter-monitoring-problem'))
	->setAttribute('data-template', 'monitoring.problem.filter')
	->addItem($template)
	->show();

(new CTemplateTag('filter-inventory-row'))
	->addItem(
		(new CRow([
			(new CSelect('inventory[#{rowNum}][field]'))
				->addOptions(CSelect::createOptionsFromArray($inventories)),
			(new CTextBox('inventory[#{rowNum}][value]', '#{value}'))
				->removeId()
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('inventory[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass('form_row')
	)
	->show();

(new CTemplateTag('filter-tag-row-tmpl'))
	->addItem(
		(new CRow([
			(new CTextBox('tags[#{rowNum}][tag]', '#{tag}'))
				->setAttribute('placeholder', _('tag'))
				->removeId()
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CSelect('tags[#{rowNum}][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(TAG_OPERATOR_LIKE)
				->setFocusableElementId('tags-#{rowNum}#{uniqid}-operator-select')
				->setId('tags_#{rowNum}#{uniqid}_operator'),
			(new CTextBox('tags[#{rowNum}][value]', '#{value}'))
				->setAttribute('placeholder', _('value'))
				->setId('tags_#{rowNum}#{uniqid}_value')
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('tags[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->addClass('form_row')
	)
	->show();

?>
<script type="text/javascript">
	let template = document.querySelector('[data-template="monitoring.problem.filter"]');

	function render(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();

		let fields = ['show', 'name', 'tag_priority', 'show_opdata', 'show_symptoms', 'show_suppressed', 'show_tags',
				'acknowledgement_status', 'acknowledged_by_me', 'compact_view', 'show_timeline', 'details',
				'highlight_row', 'age_state', 'age', 'tag_name_format', 'evaltype'
			],
			eventHandler = {
				show: () => {
					// Dynamically disable hidden input elements to allow correct detection of unsaved changes.
					var	filter_show = ($('input[name="show"]:checked', container).val() != <?= TRIGGERS_OPTION_ALL ?>),
						filter_custom_time = $('[name="filter_custom_time"]', container).val(),
						disabled = (!filter_show || !$('[name="age_state"]:checked', container).length);

					$('[name="age"]', container).attr('disabled', disabled).closest('li').toggle(filter_show);
					$('[name="age_state"]', container).attr('disabled', !filter_show);

					if (!filter_show && filter_custom_time == 1) {
						$('[name="show"]', container)
							.prop('checked', false)
							.attr('disabled', 'disabled')
							.filter('[value="<?= TRIGGERS_OPTION_ALL ?>"]')
							.prop('checked', true)
							.removeAttr('disabled');
						this.setUrlArgument('show', <?= TRIGGERS_OPTION_ALL ?>);
					}
					else {
						$('[name="show"]', container).removeAttr('disabled');
					}

					if (this._parent) {
						this._parent.updateTimeselector(this, filter_show || (filter_custom_time == 1));
					}
				},
				age_state: (ev) => {
					$('[name="age"]', container).prop('disabled', !$('[name="age_state"]', container).is(':checked'));
				},
				compact_view: () => {
					let checked = $('[name="compact_view"]', container).is(':checked');

					$('[name="show_timeline"]', container).prop('disabled', checked);
					$('[name="details"]', container).prop('disabled', checked);
					$('[name="show_opdata"]', container).prop('disabled', checked);
					$('[name="highlight_row"]', container).prop('disabled', !checked);
				},
				show_tags: () => {
					let disabled = ($('[name="show_tags"]:checked', container).val() == <?= SHOW_TAGS_NONE ?>);

					$('[name="tag_priority"]', container).prop('disabled', disabled);
					$('[name="tag_name_format"]', container).prop('disabled', disabled);
				},
				unack_by_me: () => {
					const acknowledgement_status = container.querySelector('[name="acknowledgement_status"]:checked');
					const disabled = acknowledgement_status.value != <?= ZBX_ACK_STATUS_ACK ?>;

					if (disabled) {
						container.querySelector('[name="acknowledged_by_me"]').disabled = true;
						container.querySelector('[name="acknowledged_by_me"]').checked = false;
					}
					else {
						container.querySelector('[name="acknowledged_by_me"]').disabled = false;
					}
				}
			};

		// Input, radio and single checkboxes.
		fields.forEach((key) => {
			var elm = $('[name="' + key + '"]', container);

			if (elm.is(':radio,:checkbox')) {
				elm.filter('[value="' + data[key] + '"]').attr('checked', true);
			}
			else {
				elm.val(data[key]);
			}
		});

		// Show timeline default value is checked and it will be rendered in template therefore initialize if unchecked.
		$('[name="show_timeline"][unchecked-value="' + data['show_timeline'] + '"]', container).removeAttr('checked');

		// Severities checkboxes.
		for (const value in data.severities) {
			$('[name="severities[' + value + ']"]', container).attr('checked', true);
		}

		// Inventory table.
		$('#filter-inventory_' + data.uniqid, container).find('.form_row').remove();

		if (data.inventory.length === 0) {
			data.inventory.push({'field': '', 'value': ''});
		}

		$('#filter-inventory_' + data.uniqid, container).dynamicRows({
			template: '#filter-inventory-row',
			rows: data.inventory,
			counter: 0
		});

		// Tags table.
		$('#filter-tags_' + data.uniqid, container).find('.form_row').remove();

		if (data.tags.length === 0) {
			data.tags.push({'tag': '', 'value': '', 'operator': <?= TAG_OPERATOR_LIKE ?>, uniqid: data.uniqid});
		}

		$('#filter-tags_' + data.uniqid, container)
			.dynamicRows({
				template: '#filter-tag-row-tmpl',
				rows: data.tags,
				counter: 0,
				dataCallback: (tag) => {
					tag.uniqid = data.uniqid
					return tag;
				}
			})
			.on('afteradd.dynamicRows', function() {
				var rows = this.querySelectorAll('.form_row');
				new CTagFilterItem(rows[rows.length - 1]);
			});

		// Init existing fields once loaded.
		document.querySelectorAll('#filter-tags_' + data.uniqid + ' .form_row').forEach(row => {
			new CTagFilterItem(row);
		});

		// Host groups multiselect.
		$('#groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'groupids[]',
			data: data.filter_view_data.groups || [],
			objectOptions: {
				with_hosts: 1,
				enrich_parent_groups: 1
			},
			popup: {
				parameters: {
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'groupids_' + data.uniqid,
					multiselect: 1,
					real_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// Hosts multiselect.
		$('#hostids_' + data.uniqid, container).multiSelectHelper({
			id: 'hostids_' + data.uniqid,
			object_name: 'hosts',
			name: 'hostids[]',
			data: data.filter_view_data.hosts || [],
			popup: {
				filter_preselect: {
					id: 'groupids_' + data.uniqid,
					submit_as: 'groupid'
				},
				parameters: {
					multiselect: 1,
					srctbl: 'hosts',
					srcfld1: 'hostid',
					dstfrm: 'zbx_filter',
					dstfld1: 'hostids_' + data.uniqid,
				}
			}
		});

		// Triggers multiselect.
		$('#triggerids_' + data.uniqid, container).multiSelectHelper({
			id: 'triggerids_' + data.uniqid,
			object_name: 'triggers',
			name: 'triggerids[]',
			data: data.filter_view_data.triggers || [],
			popup: {
				filter_preselect: {
					id: 'hostids_' + data.uniqid,
					submit_as: 'hostid'
				},
				parameters: {
					srctbl: 'triggers',
					srcfld1: 'triggerid',
					dstfrm: 'zbx_filter',
					dstfld1: 'triggerids_' + data.uniqid,
					multiselect: 1,
					monitored_hosts: 1,
					with_monitored_triggers: 1
				}
			}
		});

		$('#show_' + data.uniqid, container).change(eventHandler.show).trigger('change');
		$('[name="age_state"]').change(eventHandler.age_state).trigger('change');
		$('[name="compact_view"]', container).change(eventHandler.compact_view).trigger('change');
		$('[name="show_tags"]', container).change(eventHandler.show_tags).trigger('change');
		$('[name="acknowledgement_status"]', container).change(eventHandler.unack_by_me).trigger('change');

		// Initialize src_url.
		this.resetUnsavedState();
		this.on(TABFILTERITEM_EVENT_ACTION, update.bind(this));

		if (this._parent) {
			this._parent.on(TABFILTER_EVENT_UPDATE, (ev) => {
				let form = this.getForm(),
					tabfilter = ev.detail.target;

				if (ev.detail.filter_property !== 'properties' || tabfilter._active_item !== this) {
					return;
				}

				if ($(form).find('[name="filter_custom_time"]').val() == 1) {
					$('[name="show"][value="<?= TRIGGERS_OPTION_ALL ?>"]', form).prop('checked', true);
					$('#show_' + this._data.uniqid, form).trigger('change');
					this.setUrlArgument('show', <?= TRIGGERS_OPTION_ALL ?>);
					this.updateUnsavedState();
					this.setBrowserLocationToApplyUrl();
				}
			});

			this._parent.on(TABFILTER_EVENT_NEWITEM, () => {
				let form = this.getForm();

				if ($(form).find('[name="filter_custom_time"]').val() == 1) {
					$('[name="show"][value="<?= TRIGGERS_OPTION_ALL ?>"]', form).prop('checked', true);
				}
			});
		}
	}

	function expand(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();

		// Trigger change to update timeselector ui disabled state.
		$('#show_' + data.uniqid, container).trigger('change');
	}

	function select(data, container) {
		if (this._template_rendered) {
			// Template rendered, use element events, trigger change to update timeselector ui disabled state.
			$('#show_' + data.uniqid, container).trigger('change');
		}
		else if (this._parent) {
			// Template is not rendered, use input data.
			this._parent.updateTimeselector(this, (data.show != <?= TRIGGERS_OPTION_ALL ?>));
		}
	}

	/**
	 * On filter apply or update buttons press update disabled UI fields.
	 *
	 * @param {CustomEvent} ev    CustomEvent object.
	 */
	function update(ev) {
		let action = ev.detail.action,
			container = this._content_container;

		if (action !== 'filter_apply' && action !== 'filter_update') {
			return;
		}

		$('[name="highlight_row"],[name="details"],[name="show_timeline"]', container)
			.filter(':disabled')
			.prop('checked', false);

		$('[name="show_opdata"]:disabled', container)
			.prop('checked', false)
			.filter('[value="' + <?= CControllerProblem::FILTER_FIELDS_DEFAULT['show_opdata'] ?> +'"]')
			.prop('checked', true);

		if ($('[name="age_state"]', container).not(':checked').length) {
			$('[name="age"]').val(<?= CControllerProblem::FILTER_FIELDS_DEFAULT['age'] ?>);
		}
	}

	// Tab filter item events handlers.
	template.addEventListener(TABFILTERITEM_EVENT_RENDER, function(ev) {
		render.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_EXPAND, function(ev) {
		expand.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_SELECT, function(ev) {
		select.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
</script>
