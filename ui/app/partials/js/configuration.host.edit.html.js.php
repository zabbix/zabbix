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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

<script type="text/javascript">
	'use strict';

	var host_edit = {

		init() {
			this.initTemplatesTab();
			this.initMacrosTab();
			this.initInventoryTab();
			this.initEncriptionTab();
		},

		initTemplatesTab() {
			document.getElementById('linked-template').addEventListener('click', event => {
				if (event.target.classList.contains('js-tmpl-unlink')) {
					if (event.target.dataset.templateid === undefined) {
						return;
					}

					event.target.closest('tr').remove();
				}
				else if (event.target.classList.contains('js-tmpl-unlink-and-clear')) {
					if (event.target.dataset.templateid === undefined) {
						return;
					}

					event.target.closest('tr').remove();
				}
			});
		},

		initMacrosTab() {
			// todo
			console.log(`At galaxy far far away, there is an ugly code put into common.template.edit.js.php... this code must be addpted for life on host planet.`);
		},

		initInventoryTab() {
			document.querySelectorAll('[name=inventory_mode]').forEach(item => {
				item.addEventListener('change', function () {
					let inventory_fields = document.querySelectorAll('[name^="host_inventory"]'),
						item_links = document.querySelectorAll('.populating_item');

					switch (this.value) {
						case '<?= HOST_INVENTORY_DISABLED ?>':
							inventory_fields.forEach(i => i.disabled = true);
							item_links.forEach(i => i.style.display = 'none');
							break;

						case '<?= HOST_INVENTORY_MANUAL ?>':
							inventory_fields.forEach(i => i.disabled = false);
							item_links.forEach(i => i.style.display = 'none');
							break;

						case '<?= HOST_INVENTORY_AUTOMATIC ?>':
							inventory_fields.forEach(i => i.disabled = i.classList.contains('linked_to_item'));
							item_links.forEach(i => i.style.display = '');
							break;
					}
				})
			});
		},

		initEncriptionTab() {
			document.querySelectorAll('[name=tls_connect], [name^=tls_in_]').forEach(field => {
				field.addEventListener('change', () => this.updateEncriptionFields());
			});

			if (document.querySelector('#change_psk')) {
				document.querySelector('#change_psk').addEventListener('click', () => {
					document.querySelector('#change_psk').closest('div').remove();
					document.querySelector('[for="change_psk"]').remove();
					this.updateEncriptionFields();
				});
			}

			this.updateEncriptionFields();
		},

		updateEncriptionFields() {
			let selected_connection = document.querySelector('[name="tls_connect"]:checked').value,
				use_psk = (document.querySelector('[name="tls_in_psk"]').checked
					|| selected_connection == <?= HOST_ENCRYPTION_PSK ?>),
				use_cert = (document.querySelector('[name="tls_in_cert"]').checked
					|| selected_connection == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

			// If PSK is selected or checked.
			if (document.querySelector('#change_psk')) {
				document.querySelector('#change_psk').closest('div').style.display = use_psk ? '' : 'none';
				document.querySelector('[for="change_psk"]').style.display = use_psk ? '' : 'none';

				// As long as button is there, other PSK fields must be hidden.
				use_psk = false;
			}
			document.querySelector('#tls_psk_identity').closest('div').style.display = use_psk ? '' : 'none';
			document.querySelector('[for="tls_psk_identity"]').style.display = use_psk ? '' : 'none';
			document.querySelector('#tls_psk').closest('div').style.display = use_psk ? '' : 'none';
			document.querySelector('[for="tls_psk"]').style.display = use_psk ? '' : 'none';

			// If certificate is selected or checked.
			document.querySelector('#tls_issuer').closest('div').style.display = use_cert ? '' : 'none';
			document.querySelector('[for="tls_issuer"]').style.display = use_cert ? '' : 'none';
			document.querySelector('#tls_subject').closest('div').style.display = use_cert ? '' : 'none';
			document.querySelector('[for="tls_subject"]').style.display = use_cert ? '' : 'none';
		}
	};
</script>
