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


class CFormSectionCollapsible extends CFormSection {

	private const ZBX_STYLE_COLLAPSED = 'section-collapsed';
	private const ZBX_STYLE_TOGGLE = 'section-toggle';

	private bool $is_collapsed = true;

	public function setCollapsed(bool $is_collapsed = true): self {
		$this->is_collapsed = $is_collapsed;

		return $this;
	}

	public function toString($destroy = true): string {
		$this
			->addClass($this->is_collapsed ? self::ZBX_STYLE_COLLAPSED : null)
			->setHeader(
				(new CSimpleButton(new CSpan($this->title)))
					->addClass(self::ZBX_STYLE_TOGGLE)
					->setTitle($this->is_collapsed ? _('Expand') : _('Collapse'))
			);

		return parent::toString($destroy);
	}
}
