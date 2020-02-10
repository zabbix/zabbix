<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CInputSecret extends CTag {

	/**
	 * Default CSS class name for HTML root element.
	 */
	public const ZBX_STYLE_CLASS = 'input-secret';

	public function __construct(string $name, string $caption, string $placeholder = '') {
		parent::__construct('div', true);

		$this
			->setId(uniqid('input-secret-'))
			->addClass(self::ZBX_STYLE_CLASS)
			->addItem(
				(new CPassBox($name, $caption))
					->setAttribute('placeholder', $placeholder)
					->setEnabled(false)
			)
			->addItem((new CButton(null, _('Set new value')))->addClass('btn-change'));

		zbx_add_post_js($this->getPostJS());
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").inputSecret();';
	}
}
