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
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {
		init() {
			this.#initActions();
			this.#initFilter();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-massupdate')) {
					openMassupdatePopup('template.massupdate', {
						[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('template')) ?>
					}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: e.target
					});
				}
				else if (e.target.classList.contains('js-massdelete')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), false);
				}
				else if (e.target.classList.contains('js-massdelete-clear')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
			});

			document.getElementById('js-create').addEventListener('click', (e) => {
				window.popupManagerInstance.openPopup('template.edit',
					{groupids: JSON.parse(e.target.dataset.groupids)}
				);
			});

			document.getElementById('js-import').addEventListener('click', () => {
				PopUp("popup.import", {
					rules_preset: "template",
					[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('import')) ?>
				}, {
					dialogueid: "popup_import",
					dialogue_class: "modal-popup-generic"
				});
			});

			this.#setSubmitCallback();
		}

		#initFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});

			const filter_fields = ['#filter_groups_', '#filter_templates_']

			filter_fields.forEach(filter => {
				$(filter).on('change', () => this.#updateMultiselect($(filter)));
				this.#updateMultiselect($(filter));
			})
		}

		#delete(target, templateids, clear) {
			let confirmation;
			const curl = new Curl('zabbix.php');

			if (clear) {
				confirmation = templateids.length > 1
					? <?= json_encode(
						_('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
					) ?>
					: <?= json_encode(
						_('Delete and clear selected template? (Warning: all linked hosts will be cleared!)')
					) ?>;

				curl.setArgument('action', 'template.delete');
				curl.setArgument('clear', 1);
			}
			else {
				confirmation = templateids.length > 1
					? <?= json_encode(_('Delete selected templates?')) ?>
					: <?= json_encode(_('Delete selected template?')) ?>;

				curl.setArgument('action', 'template.delete');
			}

			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('template')) ?>);

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({templateids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('templates', response.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('templates');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		#updateMultiselect($ms) {
			$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('templates');
				location.href = location.href;
			});
		}
	};
</script>
