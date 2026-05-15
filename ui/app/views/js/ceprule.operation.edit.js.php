<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

window.ceprule_operation_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	/** @type {Overlay} */
	#overlay;

	/** @type {Template} */
	#tag_template;

	/** @type {String} */
	#window_type;

	init({rules, operation, overlay, window_type}) {
		this.#window_type = String(window_type);
		this.#overlay = overlay;
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');

		this.#tag_template = new Template(window['ceprule-operation-tag-template'].innerHTML);
		this.#setValues(operation);

		this.#initActions();
		this.form = new CForm(this.form_element, rules);
		this.#setAvailableExecuteWhenOptions();
		this.#setAvailableOperationOptions();
		window['ceprule-operation-execute-when'].dispatchEvent(new Event('change'));
		window['ceprule-operation-eviction-cause'].dispatchEvent(new Event('change'));
		window['ceprule-operation-action'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => this.form_element.style.display = '');
	}

	#initActions() {
		this.form_element.addEventListener('change', (e) => {
			e.target.name === 'eviction_cause' && this.#setAvailableOperationOptions();
			e.target.id === 'ceprule-operation-execute-when' && this.#handleExecuteWhenChanged(e.target.value);
			e.target.id === 'ceprule-operation-action' && this.#handleActionChanged(e.target.value);
		}, {capture: true});

		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-tag-add')) {
				this.#addTagRow({tag: '', operator: <?= TAG_OPERATOR_EQUAL ?>, value: ''});
			}
			else if (e.target.classList.contains('js-tag-remove')) {
				e.target.closest('tr').remove();
			}
		});
	}

	#addTagRow(tag) {
		tag.row_index = this.form_element.querySelectorAll('#ceprule-operation-tags-table tbody tr').length;

		this.form_element.querySelector('#ceprule-operation-tags-table tbody')
			.insertAdjacentElement('beforeend', this.#tag_template.evaluateToElement(tag));
		this.form_element.querySelector('#ceprule-operation-tags-table tbody')
			.insertAdjacentHTML('beforeend', `<tr><td class="<?= ZBX_STYLE_ERROR_CONTAINER ?>"></td></tr>`);
	}

	#setAvailableExecuteWhenOptions() {
		const zselect = window['ceprule-operation-execute-when'];
		const options = zselect.options;

		zselect.clearOptions();
		zselect.addOptions(options.map(option => {
			const value = Number(option.value);

			option.disabled = false;

			if (value == <?= ZBX_CEP_OP_WHEN_EVENT_OCCURRED ?>) {
				option.disabled = false;
			}
			else if (value == <?= ZBX_CEP_OP_WHEN_EVENT_EVICTED ?>) {
				option.disabled = this.#window_type === '<?= ZBX_CEP_WINDOW_NONE ?>';
			}
			else if (value == <?= ZBX_CEP_OP_WHEN_WINDOW_CLOSED ?>) {
				option.disabled = this.#window_type !== '<?= ZBX_CEP_WINDOW_CAUSE_SYMPTOM ?>';
			}
			else if (value == <?= ZBX_CEP_OP_WHEN_TAGS_CORRELATED ?>) {
				option.disabled = this.#window_type !== '<?= ZBX_CEP_WINDOW_TAG_MATCH ?>';
			}
			else if (value == <?= ZBX_CEP_OP_WHEN_PATTERN_MATCHED ?>) {
				option.disabled = this.#window_type !== '<?= ZBX_CEP_WINDOW_PATTERN_MATCH ?>';
			}

			return option;
		}));

		if (!zselect.value.length) {
			zselect.value = zselect.options.find(option => !option.disabled).value;
		}
	}

	#setAvailableOperationOptions() {
		const type = Number(this.form.findFieldByName('type').getValue());
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const eviction_cause = Number(this.form.findFieldByName('eviction_cause').getValue());
		const zselect = window['ceprule-operation-action'];

		const events_options = [];
		const tags_options = [];
		const enable_if_allowed = (option) => {
			const value = Number(option.value);

			option.is_disabled = false;
			if (this.#window_type === '<?= ZBX_CEP_WINDOW_PATTERN_MATCH ?>') {
				option.is_disabled = !(value == <?= ZBX_CEP_OP_COPY_LAST ?> || value == <?= ZBX_CEP_OP_COPY_FIRST ?>);
			}

			if (value == <?= ZBX_CEP_OP_DISCARD ?>) {
				option.is_disabled = (execute_when == <?= ZBX_CEP_OP_WHEN_TAGS_CORRELATED ?>)
					|| (execute_when == <?= ZBX_CEP_OP_WHEN_EVENT_EVICTED ?>
						&& eviction_cause == <?= ZBX_CEP_EVICTION_CAUSE_DURATION ?>);
			}
		};

		zselect.options.forEach(option => {
			enable_if_allowed(option);
			option.extra.is_events_group ? events_options.push(option) : tags_options.push(option);
		});

		zselect.clearOptions();
		zselect.addOptionGroup({label: <?= json_encode(_('Events')) ?>, options: events_options});
		zselect.addOptionGroup({label: <?= json_encode(_('Tags')) ?>, options: tags_options});

		// Select first enabled optioin, if previous selection got disabled.
		zselect.value = type;
		if (!zselect.value.length) {
			zselect.value = [...events_options, ...tags_options]
				.find(option => !option.is_disabled).value;
		}
	}

	#setValues(operation) {
		if (operation.tags === undefined) {
			operation.tags = {0: {tag: '', operator: <?= TAG_OPERATOR_EQUAL ?>, value: ''}};
		}

		for (const tag of Object.values(operation.tags)) {
			this.#addTagRow(tag);
		}

		for (const field_name in operation) {
			const field_value = operation[field_name];

			[...this.form_element.querySelectorAll(`[name="${field_name}"]`)]
				.map(node => {
					if (node.type === 'radio') {
						node.checked = node.value === field_value;
					}
					else {
						node.value = field_value
					}
				});
		}
	}

	#handleActionChanged(value) {
		value = Number(value);
		const name = window['ceprule-operation-name-argument'];
		const tag = window['ceprule-operation-tag-argument'];
		const severity = window['ceprule-operation-severity-argument'];
		const tag_pair = window['ceprule-operation-tag-pair-argument'];
		const tag_rename = window['ceprule-operation-tag-rename-argument'];

		name.style.display = 'none';
		tag.style.display = 'none';
		severity.style.display = 'none';
		tag_pair.style.display = 'none';
		tag_rename.style.display = 'none';

		if ([
			<?= ZBX_CEP_OP_COPY_FIRST ?>,
			<?= ZBX_CEP_OP_COPY_LAST ?>,
			<?= ZBX_CEP_OP_SUPPRESS ?>,
			<?= ZBX_CEP_OP_DECREASE_SEVERITY ?>,
			<?= ZBX_CEP_OP_INCREASE_SEVERITY ?>,
			<?= ZBX_CEP_OP_DISCARD ?>,
			<?= ZBX_CEP_OP_CLOSE ?>
		].includes(value)) {
			return;
		}

		if ([
			<?= ZBX_CEP_OP_SET_SEVERITY ?>
		].includes(value)) {
			severity.style.display = '';
			return;
		}

		if ([
			<?= ZBX_CEP_OP_SET_NAME ?>
		].includes(value)) {
			name.style.display = '';
			return;
		}

		if ([
			<?= ZBX_CEP_OP_REMOVE_TAG ?>,
			<?= ZBX_CEP_OP_DECREASE_TAG_VALUE ?>,
			<?= ZBX_CEP_OP_INCREASE_TAG_VALUE ?>,
			<?= ZBX_CEP_OP_REMOVE_TAG ?>
		].includes(value)) {
			tag.style.display = '';
			return;
		}

		if ([
			<?= ZBX_CEP_OP_RENAME_TAG ?>,
		].includes(value)) {
			tag_rename.style.display = '';
			return;
		}

		if ([
			<?= ZBX_CEP_OP_ADD_TAG ?>,
			<?= ZBX_CEP_OP_SET_TAG ?>,
			<?= ZBX_CEP_OP_SET_TAG_VALUE ?>
		].includes(value)) {
			tag_pair.style.display = '';
			return;
		}
	}

	#handleExecuteWhenChanged(value) {
		// Toggle secondary options.
		window['ceprule-operation-eviction-cause']
			.style.display = value === '<?= ZBX_CEP_OP_WHEN_EVENT_EVICTED ?>' ? '' : 'none';
		window['ceprule-operation-event-type']
			.style.display = this.#window_type === '<?= ZBX_CEP_WINDOW_CAUSE_SYMPTOM ?>'
				&& value === '<?= ZBX_CEP_OP_WHEN_EVENT_OCCURRED ?>' ? '' : 'none';

		// Update a dependent selector view.
		this.#setAvailableOperationOptions();

		[...this.form_element.querySelectorAll('[is="z-cep-tagsuggest"]')]
			.map(node => {
				if (value == <?= ZBX_CEP_OP_WHEN_TAGS_CORRELATED ?>) {
					node.setAttribute('disable-position-tags', '');
				}
				else {
					node.removeAttribute('disable-position-tags');
				}
			});
	}
};

// TODO: move to file maybe..
if (window.customElements.get('z-cep-tagsuggest') === undefined) {
	class ZCepTagsuggest extends HTMLInputElement {

		#position_tags = ['$IS.FIRST', '$IS.LAST'];
		#property_tags = ['$IS.COPIED', '$RANK', '$STATUS.CODE'];
		#handler;
		#suggestion_template;
		#suggestion_container;
		#suggestions = [];

		constructor() {
			super();
			this.setAttribute('autocomplete', 'off');
			this.#handler = this.#onEvent.bind(this);
			this.#suggestion_template = new Template('<li><span class="suggest-found">#{match}</span>#{unmatch}</li>');
			this.#suggestion_container = (new Template(`
				<div class="multiselect-available">
					<ul class="multiselect-suggest" aria-hidden="true"></ul>
				</div>
			`)).evaluateToElement();

			this.#suggestion_container.addEventListener('click', console.log);
			this.#suggestion_container.addEventListener('mouseenter', console.log);
			this.#suggestion_container.addEventListener('mouseleave', console.log);
		}

		#onEvent(e) {
			if (e instanceof FocusEvent) {
				if (e.type === 'focusout') {
					this.#hideSuggestions();
				}
				else if (e.type === 'focusin') {
					this.#triggerSuggestions(e.target.value);
				}
			}
			else if (e instanceof KeyboardEvent) {
				if (e.type === 'keyup') {
					this.#triggerSuggestions(e.target.value);
				}
				else if (e.type === 'keydown') {
					if (e.key === 'ArrowUp') {
					}
					else if (e.key === 'ArrowDown') {
						// TODO: add "suggest-hover" class to focused li.
						// TODO: confirm suggestion on enter and click
					}
				}
			}
		}

		#triggerSuggestions(value) {
			this.#suggestions = [
				...this.hasAttribute('disable-position-tags') ? [] : this.#position_tags,
				...this.#property_tags
			];

			this.#suggestions = !value.startsWith('$')
				? []
				: this.#suggestions.filter(tag => tag.startsWith(value));

			this.#suggestions.length
				? this.#showSuggestions(this.#suggestions, value.length)
				: this.#hideSuggestions();
		}

		#showSuggestions(suggestions, match_position) {
			const suggestions_html = suggestions.sort()
				.map(suggestion => this.#suggestion_template.evaluate({
					match: suggestion.substr(0, match_position),
					unmatch: suggestion.substr(match_position)
				})).join('');

			window.requestAnimationFrame(() => {
				this.#positionSuggestions();
				this.#suggestion_container.querySelector('ul').innerHTML = suggestions_html;
				!this.#suggestion_container.isConnected && document.body.append(this.#suggestion_container);
			});
		}

		#positionSuggestions() {
			const {x, y, width} = this.getBoundingClientRect();

			this.#suggestion_container.style.width = `${width - 20}px`;
			this.#suggestion_container.style.top = `${y + 24}px`;
			this.#suggestion_container.style.left = `${x}px`;
		}

		#hideSuggestions() {
			this.#suggestion_container.isConnected && this.#suggestion_container.remove();
		}

		connectedCallback() {
			this.addEventListener('keydown', this.#handler);
			this.addEventListener('keyup', this.#handler);
			this.addEventListener('focusin', this.#handler);
			this.addEventListener('focusout', this.#handler);
		}

		disconnectedCallback() {
			this.removeEventListener('keydown', this.#handler);
			this.removeEventListener('keyup', this.#handler);
			this.removeEventListener('focusin', this.#handler);
			this.removeEventListener('focusout', this.#handler);
		}
	}

	window.customElements.define('z-cep-tagsuggest', ZCepTagsuggest, {extends: 'input'});
}
