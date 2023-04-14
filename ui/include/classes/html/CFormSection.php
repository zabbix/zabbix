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


class CFormSection extends CTag {

	private const ZBX_STYLE_CLASS = 'form-section';
	private const ZBX_STYLE_HEADER = 'form-section-header';

	protected string $title;
	protected ?CDiv $header = null;

	public function __construct(string $title = '', string $id = null) {
		parent::__construct('div', true);

		$this->title = $title;

		$this->setId($id);
		$this->addClass(self::ZBX_STYLE_CLASS);
		$this->setHeader($title);
	}

	public function addItem($value): self {
		if ($value !== null) {
			if (is_array($value)) {
				foreach ($value as $item) {
					$this->items[] = $item;
				}
			}
			else {
				$this->items[] = $value;
			}
		}

		return $this;
	}

	public function setHeader($items): self {
		if ($items !== null) {
			$this->header = (new CDiv($items))->addClass(self::ZBX_STYLE_HEADER);
		}

		return $this;
	}

	public function toString($destroy = true): string {
		$body = $this->items;

		$this->cleanItems();

		parent::addItem([$this->header, $body]);

		return parent::toString($destroy);
	}
}
