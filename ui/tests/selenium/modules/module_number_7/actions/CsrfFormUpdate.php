<?php

namespace Modules\CSRF\Actions;

use CUrl;
use CMessageHelper;
use CController;
use CControllerResponseData;
use CControllerResponseRedirect;

class CsrfFormUpdate extends CController {

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
			'action' => $this->getAction()
		];
		$data = $this->getInputAll();

		$response = new CControllerResponseData([]);
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'csrftoken.form')
		);
		$response->setFormData($data);
		CMessageHelper::setSuccessTitle('CSRF token validation succeeded.');
		$this->setResponse($response);
	}
}
