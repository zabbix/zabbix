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


abstract class CControllerResponse {

	protected $location = '';

	protected $messages = [];


	public function getLocation(): string {
		return $this->location;
	}

	public function getMessages(): array {
		return $this->messages;
	}

	public function addMessage(string $msg): void {
		$this->messages[] = $msg;
	}

	public function redirect(): void {
		// Redirect as simple request.
		if ($this instanceof CControllerResponseRedirect) {
			if ($this->getFormData() === null && $this->getMessageOk() === null && $this->getMessageError() === null) {
				redirect($this->getLocation());
			}
		}

		(new CPageHeader(_('Loading...')))->display();

		echo '<body lang="'.CWebUser::getLang().'">';

		echo $this
			->getForm()
			->toString();

		echo $this
			->getScript()
			->toString();

		echo '</body></html>';
		session_write_close();
		exit();
	}

	protected function getForm(): CForm {
		$form = (new CForm())
				->cleanItems()
				->setAction($this->getLocation())
				->setEnctype('multipart/form-data')
				->setId('form-data');

		foreach ($this->getMessages() as $value) {
			$form->addItem(new CInput('hidden', 'system-messages[]', $value));
		}

		if ($this instanceof CControllerResponseRedirect) {
			if ($this->getMessageOk() !== null) {
				$form->addItem(new CInput('hidden', 'system-message-ok', $this->getMessageOk()));
			}

			if ($this->getMessageError() !== null) {
				$form->addItem(new CInput('hidden', 'system-message-error', $this->getMessageError()));
			}

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
		}

		return $form;
	}

	protected function getScript() {
		$js = '
			<script>
				document.addEventListener("DOMContentLoaded", () => document.getElementById("form-data").submit());
			</script>
		';

		return new CJsScript($js);
	}
}
