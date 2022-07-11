<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	public function redirect(): void {
		// Redirect as simple request.
		if ($this instanceof CControllerResponseRedirect) {
			if ($this->getFormData() === null && CMessageHelper::getTitle() === null) {
				redirect($this->getLocation());
			}

			CMessageHelper::restoreScheduleMessages();
		}

		(new CPageHeader(_('Loading...'), CWebUser::getLang()))->display();

		echo '<body>';

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

		$data = $this->getData();
		$data = json_encode($data);
		$sign = CEncryptHelper::sign($data);

		$form->addItem(new CInput('hidden', 'formdata', ''));
		$form->addItem(new CInput('hidden', 'sign', base64_encode($sign)));
		$form->addItem(new CInput('hidden', 'data', base64_encode($data)));

		return $form;
	}

	protected function getScript(): CJsScript {
		$js = '
			<script>
				document.addEventListener("DOMContentLoaded", () => document.getElementById("form-data").submit());
			</script>
		';

		return new CJsScript($js);
	}

	private function getData(): array {
		$data = [];
		$messages['messages'] = CMessageHelper::getMessages();

		if ($this instanceof CControllerResponseRedirect) {
			switch (CMessageHelper::getType()) {
				case CMessageHelper::MESSAGE_TYPE_ERROR:
					$messages[CMessageHelper::MESSAGE_TYPE_ERROR] = CMessageHelper::getTitle();
					break;
				case CMessageHelper::MESSAGE_TYPE_SUCCESS:
					$messages[CMessageHelper::MESSAGE_TYPE_SUCCESS] = CMessageHelper::getTitle();
					break;
			}

			$data = $this->getFormData();
		}

		return ['form' => $data, 'messages' => $messages];
	}
}
