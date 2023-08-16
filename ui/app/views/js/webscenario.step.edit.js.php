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
?>


window.webscenario_step_edit_popup = new class {

	/** @type {Overlay} */
	#overlay;

	/** @type {HTMLDivElement} */
	#dialogue;

	/** @type {HTMLFormElement} */
	#form;

	/** @type {HTMLTableElement} */
	#query_fields;

	/** @type {HTMLTableElement} */
	#post_fields;

	init({query_fields, post_fields, variables, headers}) {
		this.#overlay = overlays_stack.getById('webscenario-step-edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = this.#overlay.$dialogue.$body[0].querySelector('form');

		this.#query_fields = document.getElementById('step-query-fields');
		this.#post_fields = document.getElementById('step-post-fields');

		this.#initQueryFields(query_fields);
		this.#initPostFields(post_fields);
		this.#initVariables(variables);
		this.#initHeaders(headers);

		document.getElementById('post_type').addEventListener('change', (e) => this.#togglePostType(e));
		document.getElementById('retrieve_mode').addEventListener('change', () => this.#updateForm());
		this.#form.querySelector('.js-parse-url').addEventListener('click', () => this.#parseUrl());

		this.#updateForm();
	}

	submit() {
		const fields = getFormFields(this.#form);

		for (const field of ['name', 'url', 'posts', 'timeout', 'required', 'status_codes']) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		for (const field of ['query_fields', 'post_fields', 'variables', 'headers']) {
			if (field in fields) {
				for (const pair of Object.values(fields[field])) {
					pair.name = pair.name.trim();
					pair.value = pair.value.trim();
				}
			}
		}

		this.#overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'webscenario.step.check');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.#overlay.dialogueid);

			this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
		});
	}

	#initQueryFields(query_fields) {
		const $query_fields = jQuery(this.#query_fields);

		$query_fields.dynamicRows({
			template: '#step-query-field-row-tmpl',
			rows: query_fields
		});

		this.#initTextareaFlexible($query_fields);
		this.#initSortable($query_fields);
	}

	#initPostFields(post_fields) {
		const $post_fields = jQuery(this.#post_fields);

		$post_fields.dynamicRows({
			template: '#step-post-field-row-tmpl',
			rows: post_fields
		});

		this.#initTextareaFlexible($post_fields);
		this.#initSortable($post_fields);
	}

	#initVariables(variables) {
		const $variables = jQuery('#step-variables');

		$variables.dynamicRows({
			template: '#step-variable-row-tmpl',
			rows: variables
		});

		this.#initTextareaFlexible($variables);
	}

	#initHeaders(headers) {
		const $headers = jQuery('#step-headers');

		$headers.dynamicRows({
			template: '#step-header-row-tmpl',
			rows: headers
		});

		this.#initTextareaFlexible($headers);
		this.#initSortable($headers);
	}

	#initTextareaFlexible($table) {
		$table
			.on('afteradd.dynamicRows', () => {
				jQuery('.form_row:last .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $table).textareaFlexible();
			})
			.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
	}

	#initSortable($table) {
		$table
			.sortable({
				disabled: $table[0].querySelectorAll('.<?= ZBX_STYLE_SORTABLE ?>').length < 2,
				items: 'tbody .<?= ZBX_STYLE_SORTABLE ?>',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: (e, ui) => {
					for (const td of ui.find('>td')) {
						const $td = jQuery(td);
						$td.css('width', $td.width());
					}

					// When dragging element on Safari, it jumps out of the table.
					if (SF) {
						// Move back draggable element to proper position.
						ui.css('left', '5px');
					}

					return ui;
				},
				start: (e, ui) => {
					jQuery(ui.placeholder).height(jQuery(ui.helper).height());
				},
				stop: (e, ui) => {
					for (const td of ui.item.find('>td')) {
						jQuery(td).removeAttr('style');
					}

					ui.item.removeAttr('style');
				}
			})
			.on('afteradd.dynamicRows afterremove.dynamicRows', () => {
				const is_disabled = $table[0].querySelectorAll('.<?= ZBX_STYLE_SORTABLE ?>').length < 2;

				for (const drag_icon of $table[0].querySelectorAll('div.<?= ZBX_STYLE_DRAG_ICON ?>')) {
					drag_icon.classList.toggle('<?= ZBX_STYLE_DISABLED ?>', is_disabled);
				}

				$table.sortable({disabled: is_disabled});
			})
			.trigger('afteradd.dynamicRows');
	}

	#togglePostType(e) {
		try {
			this.#updatePosts(e.target.value != <?= ZBX_POSTTYPE_RAW ?>);
			this.#updateForm();
		}
		catch (error) {
			this.#form.querySelector('[name="post_type"]:not(:checked)').checked = true;
			this.#showErrorDialog(<?= json_encode(_('Cannot convert POST data:')) ?> + '<br><br>' + error, e.target);
		}
	}

	#updatePosts(is_raw) {
		const posts = document.getElementById('posts');
		let pairs = [];

		if (is_raw) {
			pairs = this.#parsePostRawToPairs(posts.value.trim());

			for (const row of this.#post_fields.querySelectorAll('tbody .<?= ZBX_STYLE_SORTABLE ?>')) {
				row.remove();
			}

			const $table = jQuery(this.#post_fields);
			const last_row = this.#post_fields.querySelector('tbody tr:last-of-type');
			const row_template = new Template(document.getElementById('step-post-field-row-tmpl').innerHTML);
			const template = document.createElement('template');

			$table.data('dynamicRows').counter = 0;

			for (const pair of pairs) {
				const data = {
					name: pair.name,
					value: pair.value,
					rowNum: $table.data('dynamicRows').counter++
				};
				template.innerHTML = row_template.evaluate(data);

				last_row.before(template.content.firstChild);
			}

			$table.trigger('afteradd.dynamicRows');
		}
		else {
			for (const row of this.#post_fields.querySelectorAll('tbody .<?= ZBX_STYLE_SORTABLE ?>')) {
				const name = row.querySelector('[name$="[name]"]').value;
				const value = row.querySelector('[name$="[value]"]').value;

				if (name !== '' || value !== '') {
					pairs.push({name, value});
				}
			}

			posts.value = this.#parsePostPairsToRaw(pairs);
		}
	}

	#parsePostPairsToRaw(pairs) {
		const fields = [];

		for (const pair of pairs) {
			if (pair.name === '') {
				throw <?= json_encode(_('Values without names are not allowed in form fields.')) ?>;
			}

			const parts = [];

			parts.push(encodeURIComponent(pair.name.replace(/'/g,'%27').replace(/"/g,'%22')));

			if (pair.value !== '') {
				parts.push(encodeURIComponent(pair.value.replace(/'/g,'%27').replace(/"/g,'%22')));
			}

			fields.push(parts.join('='));
		}

		return fields.join('&');
	}

	#parsePostRawToPairs(value) {
		if (value === '') {
			return [{name: '', value: ''}];
		}

		const pairs = [];

		for (const pair of value.split('&')) {
			const fields = pair.split('=');

			if (fields[0] === '') {
				throw <?= json_encode(_('Values without names are not allowed in form fields.')) ?>;
			}

			if (fields[0].length > 255) {
				throw <?= json_encode(_('Name of the form field should not exceed 255 characters.')) ?>;
			}

			if (fields.length == 1) {
				fields.push('');
			}

			const malformed = fields.length > 2;
			const non_printable_chars = fields[0].match(/%[01]/) || fields[1].match(/%[01]/);

			if (malformed || non_printable_chars) {
				throw <?= json_encode(_('Data is not properly encoded.')) ?>;
			}

			pairs.push({
				name: decodeURIComponent(fields[0].replace(/\+/g, ' ')),
				value: decodeURIComponent(fields[1].replace(/\+/g, ' '))
			})
		}

		return pairs;
	}

	#showErrorDialog(message, trigger_element) {
		overlayDialogue({
			title: <?= json_encode(_('Error')) ?>,
			class: 'modal-popup position-middle',
			content: jQuery('<span>').html(message),
			buttons: [{
				title: <?= json_encode(_('Ok')) ?>,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, jQuery(trigger_element));
	}

	#updateForm() {
		const post_type = this.#form.querySelector('[name="post_type"]:checked').value;
		for (const field of this.#form.querySelectorAll('.js-field-post-fields')) {
			field.style.display = post_type == <?= ZBX_POSTTYPE_FORM ?> ? '' : 'none';
		}

		for (const field of this.#form.querySelectorAll('.js-field-posts')) {
			field.style.display = post_type == <?= ZBX_POSTTYPE_RAW ?> ? '' : 'none';
		}

		const retrieve_mode = this.#form.querySelector('[name="retrieve_mode"]:checked').value;
		const posts_elements = this.#form.querySelectorAll(
			'[name="post_type"], #step-post-fields textarea, #step-post-fields button, #posts'
		);

		if (retrieve_mode == <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>) {
			for (const element of posts_elements) {
				element.setAttribute('disabled', 'disabled');
			}

			jQuery(this.#post_fields).sortable('disable');

			for (const element of this.#post_fields.querySelectorAll('.<?= ZBX_STYLE_DRAG_ICON ?>')) {
				element.classList.add('<?= ZBX_STYLE_DISABLED ?>');
			}
		}
		else {
			for (const element of posts_elements) {
				element.removeAttribute('disabled');
			}

			if (this.#post_fields.querySelectorAll('.<?= ZBX_STYLE_SORTABLE ?>').length > 1) {
				jQuery(this.#post_fields).sortable('enable');

				for (const element of this.#post_fields.querySelectorAll('.<?= ZBX_STYLE_DRAG_ICON ?>')) {
					element.classList.remove('<?= ZBX_STYLE_DISABLED ?>');
				}
			}

			jQuery('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', jQuery(this.#post_fields)).textareaFlexible();
		}
	}

	#parseUrl() {
		const url = document.getElementById('url');
		const parsed_url = parseUrlString(url.value);

		if (parsed_url === false) {
			const message = <?= json_encode(_('Failed to parse URL.')) ?> + '<br><br>'
				+ <?= json_encode(_('URL is not properly encoded.')) ?>;

			return this.#showErrorDialog(message, url);
		}

		url.value = parsed_url.url;

		if (!parsed_url.pairs.length) {
			return;
		}

		const $table = jQuery(this.#query_fields);

		for (const row of this.#query_fields.querySelectorAll('tbody .<?= ZBX_STYLE_SORTABLE ?>')) {
			const name = row.querySelector('[name$="[name]"]').value;
			const value = row.querySelector('[name$="[value]"]').value;

			if (name === '' && value === '') {
				row.remove();
			}
		}

		const last_row = this.#query_fields.querySelector('tbody tr:last-of-type');
		const row_template = new Template(document.getElementById('step-query-field-row-tmpl').innerHTML);
		const template = document.createElement('template');

		for (const pair of parsed_url.pairs) {
			const data = {
				name: pair.name,
				value: pair.value,
				rowNum: $table.data('dynamicRows').counter++
			};
			template.innerHTML = row_template.evaluate(data);

			last_row.before(template.content.firstChild);
		}

		$table.trigger('afteradd.dynamicRows');
	}

	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
};
