<?php
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
 * @var CView $this
 * @var array $data
 */
?>

<script type="text/javascript">

	window.iconmap_edit = new class {

		/**
		 * @type {CForm}
		 */
		form;

		/**
		 * @type {HTMLElement}
		 */
		form_element;

		/**
		 * @type {string}
		 */
		#form_action;

		/**
		 * @type {string}
		 */
		#list_action;

		/**
		 * @type {Template}
		 */
		#row_template;

		/**
		 * @type {string}
		 */
		#default_imageid;

		init({rules, default_imageid}) {
			this.#default_imageid = default_imageid;
			this.#row_template = new Template(document.getElementById('icon-mapping-template').innerHTML);

			this.form_element = document.getElementById('iconmap');
			this.form = new CForm(this.form_element, rules);

			const table = document.getElementById('icon-mapping-table');
			new CSortable(table.querySelector('tbody'), {
				selector_span: ':not(.error-container-row)',
				selector_handle: '.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1
			}).on(CSortable.EVENT_SORT, e => table.querySelectorAll('[name*="sortorder"]')
				.forEach((node, index) => node.value = index)
			);

			this.form_element.addEventListener('submit', e => {
				e.preventDefault();
				this.submit();
			});

			document.getElementById('icon-mapping-table').addEventListener('click', e => this.#processAction(e));
			document.getElementById('icon-mapping-table').addEventListener('change', ({target}) => {
				if (target instanceof ZSelect && target.classList.contains('js-mapping-icon')) {
					this.#loadIcon(target.closest('tr').querySelector('img'), target.value);
				}
			});
			document.getElementById('default-mapping-icon').addEventListener('change', ({target}) => {
				this.#loadIcon(document.getElementById('default-mapping-icon-preview'), target.value);
			});

			document.getElementById('iconmap-edit').addEventListener('click', e => {
				if (e.target.classList.contains('preview')) {
					const img = document.createElement('img');
					img.src = e.target.dataset.imageFull;
					hintBox.showStaticHint(e, e.target, '', true, '', jQuery(img));
				}
			});

			const curl = new Curl(this.form_element.getAttribute('action'));
			curl.setArgument('action', this.form.findFieldByName('iconmapid') === null ? 'iconmap.create' : 'iconmap.update');
			this.#form_action = curl.getUrl();
			curl.setArgument('action', 'iconmap.list');
			this.#list_action = curl.getUrl();

			const clone_btn = document.getElementById('clone');
			clone_btn && clone_btn.addEventListener('click', () => this.#clone());

			const delete_btn = document.getElementById('delete');
			delete_btn && delete_btn.addEventListener('click', () => this.#delete(delete_btn.getAttribute('data-redirect-url')));
		}

		#clone() {
			this.#setLoadingStatus(['clone']);

			const curl = new Curl(this.form_element.getAttribute('action')),
				{name, mappings, default_iconid} = this.form.getAllValues();

			curl.setArgument('action', 'iconmap.edit');
			curl.setArgument('iconmap[name]', name);
			curl.setArgument('iconmap[default_iconid]', default_iconid);

			for (let mapping of Object.values(mappings || {})) {
				const {expression, iconid, inventory_link, sortorder} = mapping;

				curl.setArgument(`iconmap[mappings][${sortorder}][expression]`, expression);
				curl.setArgument(`iconmap[mappings][${sortorder}][iconid]`, iconid);
				curl.setArgument(`iconmap[mappings][${sortorder}][inventory_link]`, inventory_link);
				curl.setArgument(`iconmap[mappings][${sortorder}][sortorder]`, sortorder);
			}

			redirect(curl.getUrl(), 'post', 'action', undefined, true);
		}

		#processAction(e) {
			const action = e.target.getAttribute('name');

			if (action === 'add') {
				const keys = Object.keys(this.form.findFieldByName('mappings').getValue()),
					sortorder = keys.length ? Math.max(...keys) + 1 : 0,
					html = this.#row_template.evaluate({iconid: this.#default_imageid, sortorder});

				document.getElementById('iconmap-list-footer').insertAdjacentHTML('beforebegin', html);
			}
			else if (action === 'remove') {
				e.target.closest('tr').nextSibling.remove();
				e.target.closest('tr').remove();
			}
		}

		submit() {
			this.#setLoadingStatus(['add', 'update']);
			clearMessages();
			const fields = this.form.getAllValues();

			this.form.validateSubmit(fields)
				.then((result) => {
					if (!result) {
						this.#unsetLoadingStatus();

						return;
					}

					fetch(this.#form_action, {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify(fields)
					})
						.then((response) => response.json())
						.then((response) => {
							if ('error' in response) {
								this.#unsetLoadingStatus();

								throw {error: response.error};
							}

							if ('form_errors' in response) {
								this.form.setErrors(response.form_errors, true, true);
								this.form.renderErrors();
							}
							else {
								postMessageOk(response.success.title);
								location.href = this.#list_action;
							}
						})
						.catch((exception) => this.#ajaxExceptionHandler(exception));
			});
		}

		#loadIcon(img, iconid) {
			const src = 'imgstore.php?&width=<?= ZBX_ICON_PREVIEW_WIDTH ?>&height='
					+ '<?= ZBX_ICON_PREVIEW_HEIGHT ?>&iconid=' + iconid;

			img.setAttribute('src', src);
			img.setAttribute('data-image-full', 'imgstore.php?iconid=' + iconid);
		}

		#delete(url) {
			if (window.confirm('<?= _('Delete icon map?') ?>')) {
				this.#setLoadingStatus(['delete']);
				redirect(url, 'post', 'action', undefined, true);
			}
		}

		#ajaxExceptionHandler(exception) {
			if (exception instanceof TypeError) {
				throw exception;
			}

			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			addMessage(makeMessageBox('bad', messages, title));
			this.#unsetLoadingStatus();
		}

		#setLoadingStatus(loading_ids) {
			[
				document.getElementById('add'),
				document.getElementById('clone'),
				document.getElementById('delete'),
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.setAttribute('disabled', true);

					if (loading_ids.includes(button.id)) {
						button.classList.add('is-loading');
					}
				}
			});
		}

		#unsetLoadingStatus() {
			[
				document.getElementById('add'),
				document.getElementById('clone'),
				document.getElementById('delete'),
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.classList.remove('is-loading');
					button.removeAttribute('disabled');
				}
			});
		}

	}

</script>
