/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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


class CWidgetPlainText extends CWidget {

	_setContents(content) {
		super._setContents(content);

		for (const iframe of this._content_body.querySelectorAll('.js-iframe')) {
			iframe.addEventListener('load', () => {
				iframe.width = '100%';

				const iframe_content_body = iframe.contentDocument.body;
				const computed_styles = getComputedStyle(iframe);

				iframe_content_body.style.margin = '0';
				iframe_content_body.style.font = computed_styles.font;
				iframe_content_body.style.color = computed_styles.color;

				const resizeIframe = () => {
					const content_scroll_width = iframe_content_body.scrollWidth;

					if (content_scroll_width > iframe_content_body.clientWidth) {
						iframe.style.minWidth = `${content_scroll_width}px`;
					}

					iframe_content_body.style.width = 'max-content';
					iframe.style.maxWidth = `${iframe_content_body.scrollWidth + 1}px`;

					iframe_content_body.style.width = '';

					iframe.style.height = `${iframe_content_body.scrollHeight}px`;
				};

				const resize_observer = new ResizeObserver(resizeIframe);
				resize_observer.observe(iframe_content_body);

				resizeIframe();
			});
		}
	}
}
