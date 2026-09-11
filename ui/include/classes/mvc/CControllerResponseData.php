<?php
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


class CControllerResponseData extends CControllerResponse {

	private array $data;

	private string $title = '';

	private string $file_name = '';
	private string $file_mime_type = '';

	private bool $view_enabled = true;

	public function __construct(array $data) {
		$this->data = $data;
	}

	public function getData(): array {
		return $this->data;
	}

	public function setTitle(string $title): static {
		$this->title = $title;

		return $this;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setFileName(string $file_name): static {
		$this->file_name = $file_name;

		return $this;
	}

	public function getFileName(): string {
		return $this->file_name;
	}

	public function setFileMimeType(string $mime_type): static {
		$this->file_mime_type = $mime_type;

		return $this;
	}

	public function getFileMimeType(): string {
		return $this->file_mime_type;
	}

	public function disableView(): static {
		$this->view_enabled = false;

		return $this;
	}

	public function isViewEnabled(): bool {
		return $this->view_enabled;
	}
}
