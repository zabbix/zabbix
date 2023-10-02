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


class CFormFieldsetCollapsible extends CFormFieldset {

	public const ZBX_STYLE_TOGGLE = 'toggle';

	protected bool $is_expanded = false;

	public function __construct(string $caption, $body = null) {
		parent::__construct($caption, $body);

		$this->addClass(ZBX_STYLE_COLLAPSIBLE);
	}

	public function setExpanded(bool $expanded = true): self {
		$this->is_expanded = $expanded;

		return $this;
	}

	protected function makeLegend(): string {
		return (new CTag('legend', true,
			(new CSimpleButton(new CSpan($this->caption)))
				->addClass(self::ZBX_STYLE_TOGGLE)
				->addClass($this->is_expanded ? ZBX_ICON_CHEVRON_UP : ZBX_ICON_CHEVRON_DOWN)
				->setTitle($this->is_expanded ? _('Collapse') : _('Expand'))
		))->toString();
	}

	public function toString($destroy = true): string {
		if (!$this->is_expanded) {
			$this->addClass(ZBX_STYLE_COLLAPSED);
		}

		return parent::toString($destroy);
	}
}
