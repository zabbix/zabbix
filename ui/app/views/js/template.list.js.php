<?php declare(strict_types = 0);
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
							<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?> :
								<?= json_encode(CCsrfTokenHelper::get('template')) ?>
						}, {
							dialogue_class: 'modal-popup-static',
							trigger_element: e.target
						}
					);
				}
				else if (e.target.classList.contains('js-massdelete')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), false);
				}
				else if (e.target.classList.contains('js-massdelete-clear')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-edit')) {
					this.#edit({templateid: e.target.dataset.templateid})
				}
			});

			document.getElementById('js-create').addEventListener('click', (e) => {
				this.#edit({groupids: JSON.parse(e.target.dataset.groupids)})
			});

			document.getElementById('js-import').addEventListener('click', () => {
				PopUp("popup.import", {
					rules_preset: "template",
					<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>: "<?=CCsrfTokenHelper::get('import') ?>"
				}, {
					dialogueid: "popup_import",
					dialogue_class: "modal-popup-generic"
				});
			});
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

		#edit(parameters) {
			const overlay = PopUp('template.edit', parameters, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows('templates');
				postMessageOk(e.detail.title);

				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				location.href = location.href;
			});
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

			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('template')) ?>
			);

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
	};
</script>
