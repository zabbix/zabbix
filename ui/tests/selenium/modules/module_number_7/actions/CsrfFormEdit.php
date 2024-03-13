<?php

namespace Modules\CSRF\Actions;

use CController;
use CControllerResponseData;

class CsrfFormEdit extends CController {

	public function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		return $this->validateInput([
			'text' => 'string'
		]);
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [
			'action' => 'csrftoken.form.update',
			'text' => ''
		];
		$this->getInputs($data, ['text']);

		$response = new CControllerResponseData($data);
		$response->setTitle('If not set throws 500 error');

		$this->setResponse($response);
	}
}
