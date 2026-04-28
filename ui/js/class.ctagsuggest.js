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


class CTagSuggest {
	static ZBX_STYLE_SUGGEST_LIST = 'multiselect-suggest';
	static ZBX_STYLE_SUGGEST_AVAILABLE = 'multiselect-available';
	static ZBX_STYLE_SUGGEST_FOUND = 'suggest-found';
	static ZBX_STYLE_SUGGEST_HIGHLIGHTED = 'suggest-hover';

	static CEP_TAGS_CORRELATED = [
		'$IS.COPIED',
		'$STATUS.CODE',
		'$RANK'
	];
	static CEP_TAGS_ALL = [
		'$IS.COPIED',
		'$IS.FIRST',
		'$IS.LAST',
		'$STATUS.CODE',
		'$RANK'
	];

	constructor(input_element, options = {}) {
		this.input = input_element;
		this.suggestions = options.suggestions || CTagSuggest.CEP_TAGS_ALL;
		this.suggestion_box = null;
		this.highlighted_index = -1;
		this.is_open = false;
		this.on_select = options.on_select || (() => {});

		this.#init();
	}

	#init() {
		this.suggestion_box = document.createElement('div');
		this.suggestion_box.className = CTagSuggest.ZBX_STYLE_SUGGEST_AVAILABLE;
		this.suggestion_box.style.display = 'none';
		this.suggestion_box.style.position = 'absolute';
		this.suggestion_box.style.zIndex = '1000';

		const ul = document.createElement('ul');
		ul.className = CTagSuggest.ZBX_STYLE_SUGGEST_LIST;
		this.suggestion_box.appendChild(ul);

		document.body.appendChild(this.suggestion_box);

		this.input.addEventListener('input', this.#onInput.bind(this));
		this.input.addEventListener('keydown', this.#onKeydown.bind(this));
		this.input.addEventListener('focus', this.#onFocus.bind(this));
		this.input.addEventListener('blur', this.#onBlur.bind(this));

		document.addEventListener('click', (e) => {
			if (!this.suggestion_box.contains(e.target) && e.target !== this.input) {
				this.#hide();
			}
		});
	}

	#onInput(e) {
		const value = e.target.value;
		if (value.startsWith('$')) {
			this.#show(value);
		}
		else {
			this.#hide();
		}
	}

	#onKeydown(e) {
		if (!this.is_open) {
			return;
		}

		const suggestions = this.suggestion_box.querySelectorAll('li');

		switch (e.key) {
			case 'ArrowDown':
				e.preventDefault();
				this.#highlight(Math.min(this.highlighted_index + 1, suggestions.length - 1));
				break;

			case 'ArrowUp':
				e.preventDefault();
				this.#highlight(Math.max(this.highlighted_index - 1, 0));
				break;

			case 'Enter':
				if (this.highlighted_index >= 0 && suggestions[this.highlighted_index]) {
					e.preventDefault();
					this.#select(suggestions[this.highlighted_index]);
				}
				break;

			case 'Tab':
			case 'Escape':
				this.#hide();
				break;
		}
	}

	#onFocus() {
		const value = this.input.value;
		if (value.startsWith('$')) {
			this.#show(value);
		}
	}

	#onBlur() {
		setTimeout(() => {
			if (!this.input.matches(':focus')) {
				this.#hide();
			}
		}, 150);
	}

	#show(needle) {
		const ul = this.suggestion_box.querySelector('ul');

		ul.innerHTML = '';

		const filtered = this.suggestions.filter(tag => tag.toUpperCase().startsWith(needle.toUpperCase()));

		if (filtered.length === 0) {
			this.#hide();
			return;
		}

		filtered.forEach((tag, index) => {
			const li = document.createElement('li');

			li.setAttribute('data-index', index);
			li.setAttribute('data-tag', tag);
			li.setAttribute('tabindex', '0');

			const highlight_start = tag.substring(0, needle.length);
			const highlight_end = tag.substring(needle.length);

			li.innerHTML = `<span class="${CTagSuggest.ZBX_STYLE_SUGGEST_FOUND}">${highlight_start}</span>${highlight_end}`;

			li.addEventListener('mouseover', () => this.#highlight(index));
			li.addEventListener('mousedown', (e) => {
				e.preventDefault();
				this.#select(li);
			});

			ul.appendChild(li);
		});

		this.#position();
		this.suggestion_box.style.display = 'block';
		this.is_open = true;
		this.highlighted_index = -1;
	}

	#hide() {
		this.suggestion_box.style.display = 'none';
		this.is_open = false;
		this.highlighted_index = -1;
	}

	#highlight(index) {
		const items = this.suggestion_box.querySelectorAll('li');

		items.forEach((item, i) => {
			item.classList.toggle(CTagSuggest.ZBX_STYLE_SUGGEST_HIGHLIGHTED, i === index);
		});

		this.highlighted_index = index;

		if (index >= 0 && items[index]) {
			items[index].scrollIntoView({block: 'nearest'});
		}
	}

	#select(li) {
		const tag = li.getAttribute('data-tag');

		this.input.value = tag;
		this.input.focus();
		this.#hide();
		this.on_select(tag, this);
		this.input.dispatchEvent(new Event('change'));
	}

	#position() {
		const input_rect = this.input.getBoundingClientRect();

		this.suggestion_box.style.width = (input_rect.width - 2) + 'px';
		this.suggestion_box.style.top = (input_rect.bottom + window.scrollY) + 'px';
		this.suggestion_box.style.left = (input_rect.left + window.scrollX) + 'px';
	}

	setSuggestions(suggestions) {
		this.suggestions = suggestions;
		this.suggestions.map((suggestion) => {
			if (!suggestion.startsWith('$')) {
				throw `Not supported entry '${suggestion}', must begin with '$'`;
			}
		});
	}

	destroy() {
		this.suggestion_box.remove();
		this.input.removeEventListener('input', this.#onInput);
		this.input.removeEventListener('keydown', this.#onKeydown);
		this.input.removeEventListener('focus', this.#onFocus);
		this.input.removeEventListener('blur', this.#onBlur);
	}
}
