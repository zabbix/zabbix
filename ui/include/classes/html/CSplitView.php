<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CSplitView extends CTag {

	const PANE_FIXED_START = 'start';
	const PANE_FIXED_END = 'end';

	public function __construct($items = null) {
		parent::__construct('z-split-view', true);
		parent::addItem($items);
	}

	public function addItem($value): self {
		if ($value !== null) {
			parent::addItem($value instanceof CSplitViewPane ? $value : new CSplitViewPane($value));
		}

		return $this;
	}

	public function setMinPosition(string $min_position): self {
		$this->setAttribute('min', $min_position);

		return $this;
	}

	public function setMaxPosition(string $max_position): self {
		$this->setAttribute('max', $max_position);

		return $this;
	}

	public function setPosition(string $position): self {
		$this->setAttribute('position', $position);

		return $this;
	}

	public function setFixed(string $fixed_pane): self {
		$this->setAttribute('fixed', $fixed_pane);

		return $this;
	}

	public function setFixedSize(string $fixed_size): self {
		$this->setAttribute('fixed-size', $fixed_size);

		return $this;
	}

	public function setVertical(): self {
		$this->setAttribute('vertical', true);

		return $this;
	}
}
