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


class CWidgetHostCard extends CWidget {

	setContents(response) {
		super.setContents(response);

		this.#adjustSections();
	}

	onResize() {
		this.#adjustSections();
	}

	#adjustSections() {
		this.#adjustSectionHostGroups();
		this.#adjustSectionTemplates();
		this.#adjustSectionInventory();
		this.#adjustSectionTags();
	}

	#adjustSectionHostGroups() {
		const section = this._contents.querySelector('.section-host-groups');

		if (section === null) {
			return;
		}

		const button_more = section.querySelector(`.${ZBX_STYLE_LINK_ALT}`);

		if (button_more === null) {
			return;
		}

		const container = section.querySelector('.host-groups');
		const elements = container.querySelectorAll('.host-group');

		this.#adjustButtonMore(container, elements, button_more);
	}

	#adjustSectionTemplates() {
		const section = this._contents.querySelector('.section-templates');

		if (section === null) {
			return;
		}

		const button_more = section.querySelector(`.${ZBX_STYLE_LINK_ALT}`);

		if (button_more === null) {
			return;
		}

		const container = section.querySelector('.templates');
		const elements = container.querySelectorAll('.template');

		this.#adjustButtonMore(container, elements, button_more);
	}

	#adjustSectionInventory() {
		const section = this._contents.querySelector('.section-inventory');
		const cell_height = 54;

		if (section === null) {
			return;
		}

		section.style.setProperty('--span', Math.ceil(section.offsetHeight / cell_height));
	}

	#adjustSectionTags() {
		const section = this._contents.querySelector('.section-tags');

		if (section === null) {
			return;
		}

		const button_more = section.querySelector(`.${ZBX_ICON_MORE}`);

		if (button_more === null) {
			return;
		}

		const container = section.querySelector('.tags');
		const elements = container.querySelectorAll('.tag');

		this.#adjustButtonMore(container, elements, button_more);
	}

	#adjustButtonMore(container, elements, button_more) {
		button_more.style.display = 'none';
		container.classList.remove('has-ellipsis');

		for (const element of elements) {
			element.style.display = '';
		}

		if (container.offsetHeight === container.scrollHeight) {
			return;
		}

		container.classList.add('has-ellipsis');

		if (container.offsetHeight === container.scrollHeight) {
			return;
		}

		button_more.style.display = '';

		for (const element of [...elements].reverse()) {
			if (button_more.getBoundingClientRect().bottom > container.getBoundingClientRect().bottom) {
				element.style.display = 'none';
			}
		}
	}
}
