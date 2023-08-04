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
			this.template_permission_template = new Template(
				document.getElementById('templategroup-right-row-template').innerHTML
			);
			this.template_counter = 0;

			this.hostgroup_rights = hostgroup_rights;
			this.host_permission_template = new Template(
				document.getElementById('hostgroup-right-row-template').innerHTML
			);
			this.host_counter = 0;

			const permission_types = [<?= PERM_READ_WRITE ?>, <?= PERM_READ ?>, <?= PERM_DENY ?>];

			permission_types.forEach(permission_type => {
				if (this.templategroup_rights[permission_type]) {
					this.#addRightRow('templategroup', this.templategroup_rights[permission_type], permission_type);
				}
				if (this.hostgroup_rights[permission_type]) {
					this.#addRightRow('hostgroup', this.hostgroup_rights[permission_type], permission_type);
				}
			});

			document.querySelector('.js-add-templategroup-right-row').addEventListener('click', () =>
				this.#addRightRow('templategroup')
			);
			document.querySelector('.js-add-hostgroup-right-row').addEventListener('click', () =>
				this.#addRightRow('hostgroup')
			);
		}

		#addRightRow(group_type = '', groups = [], permission = <?= PERM_DENY ?>) {
			const rowid = group_type === 'templategroup' ? this.template_counter++ : this.host_counter++;
			const data = {
				'rowid': rowid
			};
			const template = group_type === 'templategroup'
				? this.template_permission_template
				: this.host_permission_template;

			const new_row = template.evaluate(data);

			const placeholder_row = document.querySelector(`.js-${group_type}-right-row-placeholder`);
			placeholder_row.insertAdjacentHTML('beforebegin', new_row);

			const ms = document.getElementById(`ms_${group_type}_right_groupids_${rowid}_`);
			$(ms).multiSelect();

			for (const id in groups) {
				if (groups.hasOwnProperty(id)) {
					const group = {
						'id': id,
						'name': groups[id]['name']
					};
					$(ms).multiSelect('addData', [group]);
				}
			}

			const permission_radio = document
				.querySelector(`input[name="${group_type}_right[permission][${rowid}]"][value="${permission}"]`);
			permission_radio.checked = true;

			document.dispatchEvent(new Event('tab-indicator-update'));

			document.getElementById('user-group-form').addEventListener('click', event => {
				if (event.target.classList.contains('js-remove-table-row')) {
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
