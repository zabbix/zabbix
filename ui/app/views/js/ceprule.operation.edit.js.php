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

	init({rules, operation, overlay, window_type}) {
		this.#overlay = overlay;
		this.#tag_template = new Template(window['ceprule-operation-tag-template'].innerHTML);

		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#setValues({...operation, window_type: String(window_type)});

		this.#initActions();

		this.form = new CForm(this.form_element, rules);

		this.#setAvailableOperationOptions();
		window['ceprule-operation-execute-when'].dispatchEvent(new Event('change'));
		window['ceprule-operation-type'].dispatchEvent(new Event('change'));

		window.requestAnimationFrame(() => this.form_element.classList.remove(ZBX_STYLE_DISPLAY_NONE));
	}

	#initActions() {
		this.form_element.addEventListener('change', (e) => {
			e.target.id === 'ceprule-operation-execute-when' && this.#handleExecuteWhenChanged(e.target.value);
			e.target.id === 'ceprule-operation-type' && this.#handleOperationTypeChanged(e.target.value);
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
		const row_index = this.form_element.querySelectorAll('#ceprule-operation-tags-table tbody tr').length;

		this.form_element.querySelector('#ceprule-operation-tags-table tbody')
			.insertAdjacentElement('beforeend', this.#buildTagRow(tag, row_index));
		this.form_element.querySelector('#ceprule-operation-tags-table tbody')
			.insertAdjacentHTML('beforeend', `<tr><td class="<?= ZBX_STYLE_ERROR_CONTAINER ?>"></td></tr>`);
	}

	#buildTagRow(tag, row_index) {
		const tag_row = this.#tag_template.evaluateToElement({...tag, row_index});
		const textbox = tag_row.querySelector(`[name="tags[${row_index}][value]"]`);
		const on_operator_change = value => {
			const hidden = [<?= TAG_OPERATOR_EXISTS ?>, <?= TAG_OPERATOR_NOT_EXISTS ?>].includes(value);

			textbox.style.display = hidden ? 'none' : '';
			textbox.style.disabled = hidden;
		};

		tag_row.querySelector('z-select').addEventListener('change', e => on_operator_change(Number(e.target.value)));
		on_operator_change(Number(tag.operator));

		return tag_row;
	}

	#setAvailableOperationOptions() {
		const type = Number(this.form.findFieldByName('type').getValue());
		const execute_when = Number(this.form.findFieldByName('execute_when').getValue());
		const zselect = window['ceprule-operation-type'];

		const operation_by_execute_when = JSON.parse('<?=
			json_encode(CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN)
		?>');
		const available_options = operation_by_execute_when[execute_when];

		const events_options = [];
		const tags_options = [];
		const enable_if_allowed = (option) => {
			option.is_disabled = !operation_by_execute_when[execute_when].includes(Number(option.value));
		};

		zselect.options.forEach(option => {
			enable_if_allowed(option);
			option.extra.is_events_group ? events_options.push(option) : tags_options.push(option);
		});

		zselect.clearOptions();
		zselect.addOptionGroup({label: <?= json_encode(_('Events')) ?>, options: events_options});
		zselect.addOptionGroup({label: <?= json_encode(_('Tags')) ?>, options: tags_options});
		zselect.value = type;

		// Select first enabled option, if previous selection got disabled.
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

		this.form_element.querySelector(`[name="type"]`).value = operation.type;
		this.form_element.querySelector(`[name="execute_when"]`).value = operation.execute_when;
		this.form_element.querySelector(`[name="window_type"]`).value = operation.window_type;
		this.form_element.querySelector(`[name="sortorder"]`).value = operation.sortorder;
		this.form_element.querySelector(`[name="tag_value"]`).value = operation.tag_value;
		this.form_element.querySelector(`[name="tag"]`).value = operation.tag;
		this.form_element.querySelector(`[name="new_tag"]`).value = operation.new_tag;
		this.form_element.querySelector(`[name="suppress_until"]`).value = operation.suppress_until;
		this.form_element.querySelectorAll(`[name="severity"]`).forEach(node => {
			node.checked = node.value === operation.severity;
		});
		this.form_element.querySelectorAll(`[name="evaltype"]`).forEach(node => {
			node.checked = node.value === operation.evaltype;
		});
		this.form_element.querySelector(`[name="event_name"]`).value = operation.event_name;

		// Set enabled options.
		const zselect = window['ceprule-operation-execute-when'];
		const available_options = JSON.parse('<?=
			json_encode(CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE)
		?>')[Number(operation.window_type)];
		const options = zselect.options.map(option => ({...option,
			is_disabled: !available_options.includes(Number(option.value))
		}));

		zselect.clearOptions();
		zselect.addOptions(options);
		zselect.init();
	}

	#handleOperationTypeChanged(value) {
		value = Number(value);
		const name = window['ceprule-operation-name-argument'];
		const tag = window['ceprule-operation-tag-argument'];
		const severity = window['ceprule-operation-severity-argument'];
		const period = window['ceprule-operation-period-argument'];
		const tag_pair = window['ceprule-operation-tag-pair-argument'];
		const tag_rename = window['ceprule-operation-tag-rename-argument'];

		name.style.display = 'none';
		tag.style.display = 'none';
		tag.disabled = true;
		severity.style.display = 'none';
		period.style.display = 'none';
		tag_pair.style.display = 'none';
		tag_rename.style.display = 'none';

		if ([
			<?= CCepRuleHelper::OP_COPY_FIRST ?>,
			<?= CCepRuleHelper::OP_COPY_LAST ?>,
			<?= CCepRuleHelper::OP_DECREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_INCREASE_SEVERITY ?>,
			<?= CCepRuleHelper::OP_DISCARD ?>,
			<?= CCepRuleHelper::OP_CLOSE ?>
		].includes(value)) {
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SUPPRESS ?>
		].includes(value)) {
			period.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SET_SEVERITY ?>
		].includes(value)) {
			severity.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_SET_NAME ?>
		].includes(value)) {
			name.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>,
			<?= CCepRuleHelper::OP_DECREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_INCREASE_TAG_VALUE ?>,
			<?= CCepRuleHelper::OP_REMOVE_TAG ?>
		].includes(value)) {
			tag.style.display = '';
			tag.disabled = false;
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_RENAME_TAG ?>,
		].includes(value)) {
			tag_rename.style.display = '';
			return;
		}

		if ([
			<?= CCepRuleHelper::OP_ADD_TAG ?>,
			<?= CCepRuleHelper::OP_SET_TAG ?>,
			<?= CCepRuleHelper::OP_SET_TAG_VALUE ?>
		].includes(value)) {
			tag_pair.style.display = '';
			return;
		}
	}

	#handleExecuteWhenChanged(value) {
		this.#setAvailableOperationOptions();
	}
};

if (window.customElements.get('z-cep-tagsuggest') === undefined) {
	class ZCepTagsuggest extends HTMLInputElement {

		/** @type {Array} */
		#property_tags = ['$IS.COPIED', '$IS.FIRST', '$IS.LAST', '$IS.OPEN', '$IS.SYMPTOM'];

		/** @type {Function} */
		#handler;

		/** @type {HTMLElement} */
		#suggestion_container;

		/** @type {Number} */
		#highlighted_index = -1;

		/** @type {Number} */
		#suggestions_debounce;

		/** @type {String} */
		#last_value;

		constructor() {
			super();
			this.setAttribute('autocomplete', 'off');
			this.#handler = this.#onEvent.bind(this);
			this.#suggestion_container = this.#buildSuggestionsContainer();
		}

		#buildSuggestionsContainer() {
			const node = (new Template(`
				<div class="multiselect-available">
					<ul class="multiselect-suggest" aria-hidden="true"></ul>
				</div>
			`)).evaluateToElement();

			node.addEventListener('mouseover', (e) => {
				const tag = e.target.dataset?.tag;

				if (tag !== undefined) {
					node.querySelectorAll('li')
						.forEach(li => li.classList.toggle('suggest-hover', tag === li.dataset.tag));
				}
			});

			node.addEventListener('mousedown', (e) => {
				this.#select((e.target instanceof HTMLLIElement) ? e.target : e.target.closest('li'));
			});

			return node;
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
				else if (e.type === 'keydown' && this.#suggestion_container.isConnected) {
					const items = this.#suggestion_container.querySelectorAll('li');

					switch (e.key) {
						case 'ArrowDown':
							e.preventDefault();
							this.#highlight(Math.min(this.#highlighted_index + 1, items.length - 1));
							break;
						case 'ArrowUp':
							e.preventDefault();
							this.#highlight(Math.max(this.#highlighted_index - 1, 0));
							break;
						case 'Enter':
							if (this.#highlighted_index >= 0 && items[this.#highlighted_index]) {
								e.preventDefault();
								this.#select(items[this.#highlighted_index]);
							}
							break;
					}
				}
			}
			else if (e.type === 'scroll' || e.type === 'resize') {
				this.#hideSuggestions();
			}
		}

		#triggerSuggestions(value) {
			if (value === this.#last_value) {
				return;
			}
			else {
				this.#last_value = value;
			}

			if (!value.startsWith('$')) {
				return this.#hideSuggestions();
			}

			clearTimeout(this.#suggestions_debounce);

			const matcher = tag => tag.startsWith(value) && tag !== value;
			const suggestions = this.#property_tags.filter(matcher);
			const show = () => this.#showSuggestions(suggestions, value.length);

			suggestions.length
				? this.#suggestions_debounce = setTimeout(show, 50)
				: this.#hideSuggestions();
		}

		#showSuggestions(suggestions, match_position) {
			const ul = this.#suggestion_container.querySelector('ul');

			suggestions.sort()
				.map((suggestion, index) => (new Template(`
					<li data-tag="#{tag}"><span class="suggest-found">#{match}</span>#{unmatch}</li>
				`)).evaluateToElement({
					tag: suggestion,
					match: suggestion.substr(0, match_position),
					unmatch: suggestion.substr(match_position)
				}))
				.map((node, index) => ul.children[index] !== undefined
					? ul.children[index].replaceWith(node)
					: ul.append(node)
				);

			while (ul.children.length > suggestions.length) {
				ul.children[ul.children.length - 1].remove();
			}

			this.#positionSuggestions();
			this.#highlight(0);

			!this.#suggestion_container.isConnected && document.body.append(this.#suggestion_container);
		}

		#positionSuggestions() {
			const box = this.#suggestion_container;
			const rect = this.getBoundingClientRect();
			const gap = 2;
			const box_height = box.offsetHeight;
			const space_below = window.innerHeight - rect.bottom - gap;
			const space_above = rect.top - gap;

			if (box_height <= space_below || space_below >= space_above) {
				box.style.marginTop = '';
				box.style.top = `${rect.bottom + gap + window.scrollY}px`;
			}
			else {
				box.style.marginTop = `${-(box_height + gap)}px`;
				box.style.top = `${rect.top + window.scrollY}px`;
			}

			box.style.width = `${rect.width}px`;
			box.style.left = `${rect.left + window.scrollX}px`;
		}

		#hideSuggestions() {
			this.#last_value = null;
			clearTimeout(this.#suggestions_debounce);
			this.#suggestion_container.isConnected && this.#suggestion_container.remove();
		}

		#highlight(index) {
			this.#suggestion_container.querySelectorAll('li').forEach((item, i) => {
				item.classList.toggle('suggest-hover', i === index);
			});

			this.#highlighted_index = index;
		}

		#select(li) {
			this.value = li.dataset.tag;
			setTimeout(() => this.focus());
			this.#hideSuggestions();
		}

		connectedCallback() {
			this.addEventListener('keydown', this.#handler);
			this.addEventListener('keyup', this.#handler);
			this.addEventListener('focusin', this.#handler);
			this.addEventListener('focusout', this.#handler);

			window.addEventListener('resize', this.#handler);
			window.addEventListener('scroll', this.#handler, {capture: true, passive: true});
		}

		disconnectedCallback() {
			this.removeEventListener('keydown', this.#handler);
			this.removeEventListener('keyup', this.#handler);
			this.removeEventListener('focusin', this.#handler);
			this.removeEventListener('focusout', this.#handler);

			window.removeEventListener('resize', this.#handler);
			window.removeEventListener('scroll', this.#handler, {capture: true, passive: true});
		}
	}

	window.customElements.define('z-cep-tagsuggest', ZCepTagsuggest, {extends: 'input'});
}
