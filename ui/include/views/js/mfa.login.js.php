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

<script>
	const view = {

		init({qr_code_url}) {
			const qr_code_div = document.querySelector('.qr-code');
			const size = qr_code_div.clientWidth;
			const qr = new QRCode(qr_code_div, {
				text: qr_code_url,
				width: size,
				height: size,
				correctLevel : QRCode.CorrectLevel.L
			});
			const module_width = Math.ceil(size / qr._oQRCode.moduleCount);
			const qr_margin_width = module_width * 4;
			const margin_color = qr._htOption.colorLight;

			qr_code_div.style.border = `${qr_margin_width}px solid ${margin_color}`;
		}
	}
</script>
