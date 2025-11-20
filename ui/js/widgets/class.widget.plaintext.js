/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

		const iframes = this._content_body.querySelectorAll('.js-iframe');

		for (const iframe of iframes) {
			iframe.addEventListener('load', () => {
				const content_document = iframe.contentDocument.documentElement.querySelector('body');
				const iframe_styles = getComputedStyle(iframe);

				content_document.style.margin = '0px';
				content_document.style.font = iframe_styles.font;

				const resizeIframe = () => {
					const height = Math.ceil(content_document.scrollHeight);
					const width = Math.ceil(content_document.scrollWidth);

					iframe.style.height = `${height}px`;
					iframe.style.width = `${width}px`;
				};

				resizeIframe();

				iframe.resize_observer = new ResizeObserver(resizeIframe);
				iframe.resize_observer.observe(content_document);
			});
		}
	}
}
