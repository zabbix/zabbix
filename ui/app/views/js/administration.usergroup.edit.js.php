<?php
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


/**
 * @var CView $this
 */
?>
<script>
	const view = new class {

		init({templategroup_rights, hostgroup_rights, tag_filters}) {
			this.templategroup_rights = templategroup_rights;
			this.template_permission_template = new Template(document.getElementById('template-permissions-row-template').innerHTML);
			this.template_counter = 0;

			this.hostgroup_rights = hostgroup_rights;
			this.host_permission_template = new Template(document.getElementById('host-permissions-row-template').innerHTML);
			this.host_counter = 0;

			this.tag_filters = tag_filters;
			this.tag_filter_template = new Template(document.getElementById('tab-filter-row-template').innerHTML);
			this.tag_filter_counter = 0;

			const permission_types = [<?= PERM_READ_WRITE ?>, <?= PERM_READ ?>, <?= PERM_DENY ?>];

			permission_types.forEach(permission_type => {
				if (this.templategroup_rights[permission_type]) {
					this.#addTemplateRow(this.templategroup_rights[permission_type], permission_type)
				}
				if (this.hostgroup_rights[permission_type]) {
					this.#addHostRow(this.hostgroup_rights[permission_type], permission_type)
				}
			})

			const grouped_tag_filters = [];

			tag_filters.forEach(tag_filter => {
				const matching_index = grouped_tag_filters.findIndex(grouped_filter => (
					grouped_filter.some(filter => (
						filter.tag === tag_filter.tag && filter.value === tag_filter.value
					))
				));

				if (matching_index !== -1) {
					grouped_tag_filters[matching_index].push(tag_filter);
				} else {
					grouped_tag_filters.push([tag_filter]);
				}
			});

			grouped_tag_filters.forEach(group => {
				this.#addTagFilterRow(group);
			});

			document.querySelector('.add-new-template-row').addEventListener('click', () => this.#addTemplateRow());
			document.querySelector('.add-new-host-row').addEventListener('click', () => this.#addHostRow());
			document.querySelector('.add-new-tag-filter-row').addEventListener('click', () => this.#addTagFilterRow());

			document.getElementById('update').addEventListener('click', function() {
				let groups = [];

				document.querySelectorAll('.multiselect').forEach(function(multiselect) {
					let selectedItems = $(multiselect).multiSelect('getSelectedItems');
					let groupIds = selectedItems.map(function(item) {
						return item.id;
					});
					groups.push(groupIds);
				});
			});
		}

		#addTemplateRow(templategroup_rights = [], permission = <?= PERM_DENY ?>) {
			const rowid = this.template_counter++;
			const data = {
				'rowid': rowid
			};

			const new_row = this.template_permission_template.evaluate(data);

			const placeholder_row = document.querySelector('.templategroup-placeholder-row');
			placeholder_row.insertAdjacentHTML('beforebegin', new_row);

			const ms = document.getElementById('ms_new_templategroup_right_groupids_'+rowid+'_');
			$(ms).multiSelect();

			for (const id in templategroup_rights) {
				if (templategroup_rights.hasOwnProperty(id)) {
					const groups = {
						'id': id,
						'name': templategroup_rights[id]['name']
					};
					$(ms).multiSelect('addData', [groups]);
				}
			}

			const permission_radio = document
				.querySelector(`input[name="new_templategroup_right[permission][${rowid}]"][value="${permission}"]`);
			permission_radio.checked = true;

			document.dispatchEvent(new Event('tab-indicator-update'));

			document.getElementById('user-group-form').addEventListener('click', event => {
				if (event.target.classList.contains('element-table-remove')) {
					this.#removeRow(event.target);
				}
			});
		}

		#addHostRow(hostgroup_rights = [], permission = <?= PERM_DENY ?>) {
			const rowid = this.host_counter++;
			const data = {
				'rowid': rowid
			};

			const new_row = this.host_permission_template.evaluate(data);

			const placeholder_row = document.querySelector('.group-placeholder-row');
			placeholder_row.insertAdjacentHTML('beforebegin', new_row);

			const ms = document.getElementById('ms_new_group_right_groupids_'+rowid+'_');
			$(ms).multiSelect();

			for (const id in hostgroup_rights) {
				if (hostgroup_rights.hasOwnProperty(id)) {
					const groups = {
						'id': id,
						'name': hostgroup_rights[id]['name']
					};
					$(ms).multiSelect('addData', [groups]);
				}
			}

			const permission_radio = document
				.querySelector(`input[name="new_group_right[permission][${rowid}]"][value="${permission}"]`);
			if (permission_radio) {
				permission_radio.checked = true;
			}

			document.dispatchEvent(new Event('tab-indicator-update'));

			document.getElementById('user-group-form').addEventListener('click', event => {
				if (event.target.classList.contains('element-table-remove')) {
					this.#removeRow(event.target);
				}
			});
		}

		#addTagFilterRow(tag_filter_group = []) {
			const rowid = this.tag_filter_counter++;
			const data = {
				'rowid': rowid
			};

			const new_row = this.tag_filter_template.evaluate(data);

			const placeholder_row = document.querySelector('.tag-filter-placeholder-row');
			placeholder_row.insertAdjacentHTML('beforebegin', new_row);

			const ms = document.getElementById('ms_new_tag_filter_groupids_'+rowid+'_');
			$(ms).multiSelect();

			for (const id in tag_filter_group) {
				if (tag_filter_group.hasOwnProperty(id)) {
					const groups = {
						'id': tag_filter_group[id]['groupid'],
						'name': tag_filter_group[id]['name']
					};
					$(ms).multiSelect('addData', [groups]);
				}
			}

			if (tag_filter_group.length > 0) {
				const tag_id = 'new_tag_filter_tag_'+rowid;
				document.getElementById(tag_id).value = tag_filter_group[0]['tag'];

				const value_id = 'new_tag_filter_value_'+rowid;
				document.getElementById(value_id).value = tag_filter_group[0]['value'];
			}

			document.dispatchEvent(new Event('tab-indicator-update'));

			document.getElementById('user-group-form').addEventListener('click', event => {
				if (event.target.classList.contains('element-table-remove')) {
					this.#removeRow(event.target);
				}
			});
		}

		#removeRow(button) {
			button
				.closest('tr')
				.remove();

			document.dispatchEvent(new Event('tab-indicator-update'));
		}
	};

	jQuery(function($) {
		let $form = $('form[name="user_group_form"]'),
			$userdirectory = $form.find('[name="userdirectoryid"]'),
			$gui_access = $form.find('[name="gui_access"]');

		$gui_access.on('change', onFrontendAccessChange);
		onFrontendAccessChange.apply($gui_access);

		$form.submit(function() {
			$form.trimValues(['#name']);
		});

		/**
		 * Handle "Frontend access" selector change.
		 */
		function onFrontendAccessChange() {
			let gui_access = $(this).val();

			if (gui_access == <?= GROUP_GUI_ACCESS_INTERNAL ?> || gui_access == <?= GROUP_GUI_ACCESS_DISABLED ?>) {
				$userdirectory.attr('disabled', 'disabled');
			}
			else {
				$userdirectory.removeAttr('disabled');
			}
		}
	});
</script>
