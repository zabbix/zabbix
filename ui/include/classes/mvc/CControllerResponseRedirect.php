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


class CControllerResponseRedirect extends CControllerResponse {

	protected string $url;

	protected array $form_data = [];

	public function __construct(CUrl $redirect_to) {
		$this->url = $redirect_to->getUrl();

		if (!(new CFrontendActionValidator())->validate($this->url)) {
			throw new CAccessDeniedException();
		}
	}

	public function setFormData(array $form_data): static {
		$this->form_data = $form_data;

		return $this;
	}

	public function getFormData(): array {
		return $this->form_data;
	}

	public function redirect(): void {
		CMessageHelper::restoreScheduleMessages();

		$form_data = $this->getFormData();
		$messages = $this->getMessages();

		if (!$form_data && !$messages) {
			redirect($this->url);
		}

		$data = ['form' => $form_data, 'messages' => $messages];

		(new CHtmlPageHeader(_('Loading...'), CWebUser::getLang()))->show();

		echo '<body>'.$this->autoSubmit($data).'</body></html>';

		session_write_close();

		exit;
	}

	protected function autoSubmit(array $data): string {
		$form = (new CForm('post', $this->url, 'multipart/form-data'))->setName('auto-submit');

		$data_str = json_encode($data);
		$sign = CEncryptHelper::sign($data_str);

		$form->addItem(new CInput('hidden', 'formdata', ''));
		$form->addItem(new CInput('hidden', 'sign', base64_encode($sign)));
		$form->addItem(new CInput('hidden', 'data', base64_encode($data_str)));

		$script =
			'<script>'.
				'document.addEventListener("DOMContentLoaded", () => document.forms["auto-submit"].submit());'.
			'</script>';

		return $form->toString().$script;
	}

	protected function getMessages(): array {
		$result = [];

		if (($title = CMessageHelper::getTitle()) !== null) {
			switch (CMessageHelper::getType()) {
				case CMessageHelper::MESSAGE_TYPE_ERROR:
					$result[CMessageHelper::MESSAGE_TYPE_ERROR] = $title;
					break;
				case CMessageHelper::MESSAGE_TYPE_SUCCESS:
					$result[CMessageHelper::MESSAGE_TYPE_SUCCESS] = $title;
					break;
			}
		}

		if ($messages = CMessageHelper::getMessages()) {
			$result['messages'] = $messages;
		}

		return $result;
	}
}
