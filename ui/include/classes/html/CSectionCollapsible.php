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


class CSectionCollapsible extends CSection {

	private const ZBX_STYLE_TOGGLE = 'section-toggle';

	private bool $is_expanded = true;
	private string $profile_key = '';

	public function setExpanded(bool $is_expanded): self {
		$this->is_expanded = $is_expanded;

		return $this;
	}

	public function setProfileIdx(string $profile_key): self {
		$this->profile_key = $profile_key;

		return $this;
	}

	public function toString($destroy = true): string {
		$this->addClass($this->is_expanded ? null : ZBX_STYLE_COLLAPSED);

		$toggle = $this->is_expanded
			? new CButtonIcon(ZBX_ICON_CHEVRON_UP, _('Collapse'))
			: new CButtonIcon(ZBX_ICON_CHEVRON_DOWN, _('Expand'));

		$toggle
			->addClass(self::ZBX_STYLE_TOGGLE)
			->onClick('toggleSection("'.$this->getId().'", "'.$this->profile_key.'");');

		if ($this->header === null) {
			$this->setHeader($toggle);
		}
		else {
			$this->header->addItem($toggle);
		}

		return parent::toString($destroy);
	}
}
