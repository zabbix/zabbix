<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


$filter_tags_table = (new CTable())
	->setId('tags_#{uniqid}')
	->addRow(
		(new CCol(
			(new CRadioButtonList('evaltype', (int) $data['evaltype']))
				->setId('evaltype_#{uniqid}')
				->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), TAG_EVAL_TYPE_OR)
				->setModern(true)
		))->setColSpan(4)
);
$tags = array_values($data['tags']);

if (!$tags) {
	$tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

foreach ($tags as $i => $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->removeId(),
		(new CRadioButtonList('tags['.$i.'][operator]', (int) $tag['operator']))
			->addValue(_('Contains'), TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
			->setId('tags_'.$i.'_#{uniqid}')
			->setModern(true),
		(new CTextBox('tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->removeId(),
		(new CCol(
			(new CButton('tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$left_column = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow((new CLabel(_('Host groups'), 'groupids__ms')),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('groups_multiselect', $data) ? $data['groups_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'groupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)->setId('groupids_#{uniqid}')
	)
	->addRow(_('IP'),
		(new CTextBox('ip', $data['ip']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow(_('DNS'),
		(new CTextBox('dns', $data['dns']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow(_('Port'),
		(new CTextBox('port', $data['port']))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
			->removeId()
	)
	->addRow(_('Severity'),
		(new CSeverityCheckBoxList('severities'))
			->setChecked($data['severities'])
			->setUniqid('#{uniqid}')
			->setId('severities_#{uniqid}')
	);

$right_column = (new CFormList())
	->addRow(_('Status'),
		(new CRadioButtonList('status', (int) $data['status']))
			->addValue(_('Any'), -1, 'status_1#{uniqid}')
			->addValue(_('Enabled'), HOST_STATUS_MONITORED, 'status_2#{uniqid}')
			->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED, 'status_3#{uniqid}')
			->setId('status_#{uniqid}')
			->setModern(true)
	)
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Show hosts in maintenance'), [
		(new CCheckBox('maintenance_status'))
			->setChecked($data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
			->setId('maintenance_status_#{uniqid}')
			->setUncheckedValue(HOST_MAINTENANCE_STATUS_OFF),
		(new CDiv([
			(new CLabel(_('Show suppressed problems'), 'show_suppressed_#{uniqid}'))
				->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('show_suppressed'))
				->setId('show_suppressed_#{uniqid}')
				->setChecked($data['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
				->setEnabled($data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON),
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	]);

$template = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem([
		(new CDiv($left_column))->addClass(ZBX_STYLE_CELL),
		(new CDiv($right_column))->addClass(ZBX_STYLE_CELL)
	]);
$template = (new CForm('get'))
	->cleanItems()
	->addItem($template)
	->addVar('filter_name', '#{filter_name}')
	->addVar('filter_show_counter', '#{filter_show_counter}')
	->addVar('filter_custom_time', '#{filter_custom_time}');

if (array_key_exists('render_html', $data)) {
	/**
	 * Render HTML to prevent filter flickering after initial page load. PHP created content will be replaced by
	 * javascript with additional event handling (dynamic rows, etc.) when page will be fully loaded and javascript
	 * executed.
	 */
	$template->show();

	return;
}

(new CScriptTemplate('filter-monitoring-hosts'))
	->setAttribute('data-template', 'monitoring.host.filter')
	->addItem($template)
	->show();

?>
<script type="text/javascript">
$(function($) {
	let template = document.querySelector('[data-template="monitoring.host.filter"]');

	function render(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();

		// Host groups multiselect.
		$('#groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'groupids[]',
			data: data.groups_multiselect||[],
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

		// Show hosts in maintenance events.
		$('[name="maintenance_status"]', container).click(function () {
			$('[name="show_suppressed"]', container).prop('disabled', !this.checked);
		});

		// Tags table
		var tag_row = new Template($('#filter-tag-row-tmpl').html()),
			i = 0;
		$('#tags_' + data.uniqid + ' tr.form_row', container).remove();

		if (!data.tags.length) {
			data.tags.push({tag: '', value: '', operator: <?= TAG_OPERATOR_LIKE ?>});
		}

		data.tags.forEach(tag => {
			var $row = $(tag_row.evaluate({rowNum: i++}));

			$row.find('[name$="[tag]"]').val(tag.tag);
			$row.find('[name$="[value]"]').val(tag.value);
			$row.find('[name$="[operator]"][value="'+tag.operator+'"]').attr('checked', 'checked');

			$row.insertBefore($('#tags_' + data.uniqid + ' tr', container).last());
		});
		$('#tags_' + data.uniqid, container).dynamicRows({template: '#filter-tag-row-tmpl'});

		// Input, radio and single checkboxes.
		['name', 'ip', 'dns', 'port', 'status', 'evaltype', 'maintenance_status', 'show_suppressed'].forEach((key) => {
			var elm = $('[name="' + key + '"]', container);

			if (elm.is(':radio,:checkbox')) {
				elm.filter('[value="' + data[key] + '"]').attr('checked', true);
			}
			else {
				elm.val(data[key]);
			}
		});

		// Severities checkboxes.
		Object.keys(data.severities).forEach((value) => {
			$('[name="severities[' + value + ']"]', container).attr('checked', true);
		});
	}

	function expand(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();
	}

	// Tab filter item events handlers.
	template.addEventListener(TABFILTERITEM_EVENT_RENDER, function (ev) {
		render.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_EXPAND, function (ev) {
		expand.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
});
</script>
