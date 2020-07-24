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


$fields = $data['filter'];
$filter_tags_table = (new CTable())
	->setId('filter_tags_#{uniqid}')
	->addRow(
		(new CCol(
			(new CRadioButtonList('filter_evaltype', (int) $fields['evaltype']))
				->setId('filter_evaltype_#{uniqid}')
				->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), TAG_EVAL_TYPE_OR)
				->setModern(true)
		))->setColSpan(4)
);
$tags = array_values($fields['tags']);

if (!$tags) {
	$tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

foreach ($tags as $i => $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('filter_tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->removeId(),
		(new CRadioButtonList('filter_tags['.$i.'][operator]', (int) $tag['operator']))
			->addValue(_('Contains'), TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
			->setId('filter_tags_'.$i.'_#{uniqid}')
			->setModern(true),
		(new CTextBox('filter_tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->removeId(),
		(new CCol(
			(new CButton('filter_tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('filter_tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$left_column = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('filter_name', $fields['name']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('groups_multiselect', $data) ? $data['groups_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)->setId('filter_groupids_#{uniqid}')
	)
	->addRow(_('IP'),
		(new CTextBox('filter_ip', $fields['ip']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow(_('DNS'),
		(new CTextBox('filter_dns', $fields['dns']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->removeId()
	)
	->addRow(_('Port'),
		(new CTextBox('filter_port', $fields['port']))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
			->removeId()
	)
	->addRow(_('Severity'),
		(new CSeverityCheckBoxList('filter_severities'))
			->setChecked($fields['severities'])
			->setUniqid('#{uniqid}')
			->setId('filter_severities_#{uniqid}')
	);

$right_column = (new CFormList())
	->addRow(
		_('Status'),
		(new CHorList())
			->addItem((new CRadioButtonList('filter_status', (int) $fields['status']))
				->addValue(_('Any'), -1, 'filter_status_1#{uniqid}')
				->addValue(_('Enabled'), HOST_STATUS_MONITORED, 'filter_status_2#{uniqid}')
				->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED, 'filter_status_3#{uniqid}')
				->setId('filter_status_#{uniqid}')
				->setModern(true)
			)
	)
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Show hosts in maintenance'), [
		(new CCheckBox('filter_maintenance_status'))
			->setChecked($fields['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
			->setId('filter_maintenance_status_#{uniqid}')
			->setUncheckedValue(HOST_MAINTENANCE_STATUS_OFF),
		(new CDiv([
			(new CLabel(_('Show suppressed problems'), 'filter_show_suppressed_#{uniqid}'))
				->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('filter_show_suppressed'))
				->setId('filter_show_suppressed_#{uniqid}')
				->setChecked($fields['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
				->setEnabled($fields['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON),
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
	->addItem($template);

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
		let is_sortable = $(this._target).closest('.ui-sortable').length > 0;

		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="save_as"],[name="filter_set"]').hide()
			.filter(is_sortable ? '[name="filter_set"]' : '[name="save_as"]').show();

		// Host groups multiselect.
		$('#filter_groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'filter_groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'filter_groupids[]',
			data: data.groups_multiselect||[],
			popup: {
				parameters: {
					multiselect: '1',
					noempty: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'filter_groupids_' + data.uniqid,
					real_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// Tags table
		var tag_row = new Template($('#filter-tag-row-tmpl').html()),
			i = 0;

		data.filter.tags.forEach(tag => {
			var $row = $(tag_row.evaluate({rowNum: i++}));

			$row.find('[name$="[tag]"]').val(tag.tag);
			$row.find('[name$="[value]"]').val(tag.value);
			$row.find('[name$="[operator]"][value="'+tag.operator+'"]').attr('checked', 'checked');

			$('#filter_tags_' + data.uniqid, container).append($row);
		});
		$('#filter_tags_' + data.uniqid, container).dynamicRows({template: '#filter-tag-row-tmpl'});

		// Show hosts in maintenance events.
		$('[name="filter_maintenance_status"]', container).click(function () {
			$('[name="filter_show_suppressed"]', container).prop('disabled', !this.checked);
		});
	}

	function expand(data, container) {
		let is_sortable = $(this._target).closest('.ui-sortable').length > 0;

		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="save_as"],[name="filter_set"]').hide()
			.filter(is_sortable ? '[name="filter_set"]' : '[name="save_as"]').show();
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
