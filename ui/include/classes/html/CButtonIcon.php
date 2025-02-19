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


class CButtonIcon extends CSimpleButton {

	public function __construct(string $icon_class, ?string $title = null) {
		parent::__construct();

		$this
			->addClass(ZBX_STYLE_BTN_ICON)
			->addClass($icon_class);

		if ($title !== null) {
			$this->setTitle($title);
		}
	}

	public function setSmall(): self {
		$this->addClass(ZBX_STYLE_BTN_SMALL);

		return $this;
	}
}
