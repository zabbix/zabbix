<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 * @var array    $data
 */

$filter_view_data = array_key_exists('filter_view_data', $data) ? $data['filter_view_data'] : [];

$left_column = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
	->addItem([
		new CLabel(_('Host groups'), 'groupids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'groupids[]',
				'object_name' => 'hostGroup',
				'data' => array_key_exists('groups_multiselect', $filter_view_data)
					? $filter_view_data['groups_multiselect']
					: [],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => 'zbx_filter',
						'dstfld1' => 'groupids_',
						'real_hosts' => true,
						'enrich_parent_groups' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				->setId('groupids_#{uniqid}')
		)
	])
	->addItem([
		new CLabel(_('Hosts'), 'hostids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'hostids[]',
				'object_name' => 'hosts',
				'data' => array_key_exists('hosts_multiselect', $filter_view_data)
					? $filter_view_data['hosts_multiselect']
					: [],
				'popup' => [
					'filter_preselect_fields' => [
						'hostgroups' => 'groupids_'
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
	])
	->addItem([
		new CLabel(_('Name'), 'name'),
		new CFormField(
			(new CTextBox('name', $data['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	]);

$filter_tags_table = new CTable();
$filter_tags_table->setId('tags_#{uniqid}');

$filter_tags_table->addRow(
	(new CCol(
		(new CRadioButtonList('evaltype', (int) $data['evaltype']))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR, 'evaltype_0#{uniqid}')
			->addValue(_('Or'), TAG_EVAL_TYPE_OR, 'evaltype_2#{uniqid}')
			->setModern(true)
			->setId('evaltype_#{uniqid}')
	))->setColSpan(4)
);

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

$right_column = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
	->addItem([
		new CLabel(_('Tags')),
		new CFormField($filter_tags_table)
	])
	->addItem([
		new CLabel(_('Show tags')),
		new CFormField($tag_format_line)
	])
	->addItem([
		new CLabel(_('Tag display priority')),
		new CFormField(
			(new CTextBox('tag_priority', $data['tag_priority']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				->setAttribute('placeholder', _('comma-separated list'))
				->setEnabled((int) $data['show_tags'] !== SHOW_TAGS_NONE)
				->setId('tag_priority_#{uniqid}')
		)
	])
	->addItem([
		new CLabel(_('Show details')),
		new CFormField([
			(new CCheckBox('show_details'))
				->setChecked($data['show_details'] == 1)
				->setUncheckedValue(0)
				->removeId()
		])
	]);

$filter_template = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem([
		(new CDiv($left_column))->addClass(ZBX_STYLE_CELL),
		(new CDiv($right_column))->addClass(ZBX_STYLE_CELL)
	]);

$template = (new CForm('get'))
	->cleanItems()
	->setName('zbx_filter')
	->addItem([
		$filter_template,
		(new CSubmitButton(null))->addClass(ZBX_STYLE_DISPLAY_NONE),
		(new CVar('filter_name', '#{filter_name}'))->removeId(),
		(new CVar('filter_show_counter', '#{filter_show_counter}'))->removeId(),
		(new CVar('filter_custom_time', '#{filter_custom_time}'))->removeId(),
		(new CVar('sort', '#{sort}'))->removeId(),
		(new CVar('sortorder', '#{sortorder}'))->removeId()
	]);

if (array_key_exists('render_html', $data)) {
	/*
	 * Render HTML to prevent filter flickering after initial page load. PHP created content will be replaced by
	 * javascript with additional event handling (dynamic rows, etc.) when page will be fully loaded and javascript
	 * executed.
	 */
	$template->show();

	return;
}

(new CScriptTemplate('filter-monitoring-latest'))
	->setAttribute('data-template', 'monitoring.latest.filter')
	->addItem($template)
	->show();

(new CScriptTemplate('filter-tag-row-tmpl'))
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
		]))
			->addClass('form_row')
	)
	->show();
?>

<script type="text/javascript">
	let template = document.querySelector('[data-template="monitoring.latest.filter"]');

	function render(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();

		// host groups multiselect
		$('#groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'groupids[]',
			data: data.filter_view_data.groups_multiselect || [],
			objectOptions: {
				real_hosts: 1,
				enrich_parent_groups: 1
			},
			popup: {
				parameters: {
					multiselect: '1',
					noempty: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'groupids_' + data.uniqid,
					real_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// hosts multiselect
		$('#hostids_' + data.uniqid, container).multiSelectHelper({
			id: 'hostids_' + data.uniqid,
			object_name: 'hosts',
			name: 'hostids[]',
			data: (data.filter_view_data.hosts_multiselect || []),
			popup: {
				filter_preselect_fields: {
					hostgroups: 'groupids_' + data.uniqid
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

		// tags table
		if (data.tags.length == 0) {
			data.tags.push({'tag': '', 'value': '', 'operator': <?= TAG_OPERATOR_LIKE ?>, uniqid: data.uniqid});
		}

		$('#tags_' + data.uniqid, container)
			.dynamicRows({
				template: '#filter-tag-row-tmpl',
				rows: data.tags,
				counter: 0,
				dataCallback: (tag) => {
					tag.uniqid = data.uniqid;
					return tag;
				}
			})
			.on('afteradd.dynamicRows', function() {
				const rows = this.querySelectorAll('.form_row');

				new CTagFilterItem(rows[rows.length - 1]);
			});

		// Init existing fields once loaded.
		document.querySelectorAll('#tags_' + data.uniqid + ' .form_row').forEach(row => {
			new CTagFilterItem(row);
		});

		// Input, radio and single checkboxes.
		const fields = ['name', 'evaltype', 'show_details', 'show_tags', 'tag_name_format', 'tag_priority'];

		fields.forEach(key => {
			const elm = $('[name="' + key + '"]', container);

			if (key === 'show_details') {
				elm.prop('checked', (data[key] == 1));
			}
			else if (elm.is(':radio,:checkbox')) {
				elm.filter('[value="' + data[key] + '"]').prop('checked', true);
			}
			else {
				elm.val(data[key]);
			}
		});

		// Render subfilter fields.
		const form = container.querySelector('form');
		const subfilter_fields = ['subfilter_hostids', 'subfilter_tagnames', 'subfilter_tags', 'subfilter_data'];

		subfilter_fields.forEach(key => {
			if ((key in data) && data[key].length != 0) {
				if (Array.isArray(data[key])) {
					data[key].forEach(val => {
						const el = document.createElement('input');

						el.type = 'hidden';
						el.name = key + '[]';
						el.value = val;
						form.appendChild(el);
					});
				}
				else {
					for (const k in data[key]) {
						data[key][k].forEach(val => {
							const el = document.createElement('input');

							el.type = 'hidden';
							el.name = key + '[' + k + ']' + '[]';
							el.value = val;
							form.appendChild(el);
						});
					}
				}
			}
		});

		// Tag related filter fields must be disabled when tag showing is disabled.
		$('[name="show_tags"]', container)
			.on('change', () => {
				let disabled = ($('[name="show_tags"]:checked', container).val() == <?= SHOW_TAGS_NONE ?>);

				$('[name="tag_priority"]', container).prop('disabled', disabled);
				$('[name="tag_name_format"]', container).prop('disabled', disabled);
			})
			.trigger('change');

		// Initialize src_url.
		this.resetUnsavedState();
	}

	function expand(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();
	}

	function select(data, container) {
		$('#hostids_' + data.uniqid, container).trigger('change');
	}

	// Tab filter item events handlers.
	template.addEventListener(TABFILTERITEM_EVENT_RENDER, function (ev) {
		render.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_EXPAND, function (ev) {
		expand.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_SELECT, function (ev) {
		select.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
</script>
