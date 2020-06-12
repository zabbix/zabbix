<?php
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


class CControllerFormDataResponseRedirect extends CControllerResponse {

	private $location;
	private $messageOk = null;
	private $messageError = null;
	private $formData = null;

	public function __construct($location) {
		if ($location instanceof CUrl) {
			$location = $location->getUrl();
		}

		$this->location = $location;
	}

	public function getLocation(): string {
		return $this->location;
	}

	public function getFormData(): ?array {
		return $this->formData;
	}

	public function setFormData(array $formData): void {
		$this->formData = $formData;
	}

	public function setMessageOk(string $messageOk): void {
		$this->messageOk = $messageOk;
	}

	public function getMessageOk(): ?string {
		return $this->messageOk;
	}

	public function setMessageError(string $messageError): void {
		$this->messageError = $messageError;
	}

	public function getMessageError(): ?string {
		return $this->messageError;
	}

	public function redirect(): void {
		if ($this->getMessageOk() !== null) {
			CSessionHelper::set('messageOk', $this->getMessageOk());
		}
		if ($this->getMessageError() !== null) {
			CSessionHelper::set('messageError', $this->getMessageError());
		}
		global $ZBX_MESSAGES;
		if (isset($ZBX_MESSAGES)) {
			CSessionHelper::set('messages', $ZBX_MESSAGES);
		}

		// Redirect as simple request.
		if ($this->getFormData() === null) {
			redirect($this->getLocation());
		}

		(new CPageHeader(_('Loading...')))->display();
		echo '<body>';

		echo ($this->getForm())->toString();

		echo ($this->getScript())->toString();

		echo '</body></html>';
		session_write_close();
		exit();
	}

	private function getForm(): CForm {
		$form = (new CForm())
				->cleanItems()
				->setAction($this->getLocation())
				->setId('form-data');

		foreach ($this->formData as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $k => $val) {
					$form->addItem(new CInput('hidden', $key.'['.$k.']', $val));
				}
			}
			else {
				$form->addItem(new CInput('hidden', $key, $value));
			}
		}

		return $form;
	}

	private function getScript() {
		$js = '
			<script>
				document.addEventListener("DOMContentLoaded", function() {
					document.getElementById(\'form-data\').submit();
				});
			</script>
		';

		return new CJsScript($js);
	}
}
