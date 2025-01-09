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


class CSection extends CTag {

	private const ZBX_STYLE_HEAD = 'section-head';
	private const ZBX_STYLE_BODY = 'section-body';
	private const ZBX_STYLE_FOOT = 'section-foot';

	protected ?CDiv $header = null;
	protected ?CDiv $footer = null;

	public function __construct($items = null) {
		parent::__construct('section', true, $items);
	}

	public function addItem($value): self {
		if ($value !== null) {
			$this->items[] = $value;
		}

		return $this;
	}

	public function setHeader($header_items): self {
		if ($header_items !== null) {
			$this->header = (new CDiv($header_items))->addClass(self::ZBX_STYLE_HEAD);
		}

		return $this;
	}

	public function setFooter($footer_items): self {
		if ($footer_items !== null) {
			$this->footer = (new CDiv($footer_items))->addClass(self::ZBX_STYLE_FOOT);
		}

		return $this;
	}

	public function toString($destroy = true): string {
		$body = (new CDiv($this->items))->addClass(self::ZBX_STYLE_BODY);

		$this->cleanItems();

		parent::addItem([$this->header, $body, $this->footer]);

		return parent::toString($destroy);
	}
}
