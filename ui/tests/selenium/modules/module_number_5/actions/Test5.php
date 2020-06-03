<?php declare(strict_types = 1);

namespace Modules\Example_E\Actions;

use CController as CAction;

class Test5 extends CAction {

	public function init() {
		$this->disableSIDvalidation();
	}

	/**
	 * @inheritDoc
	 */
	protected function checkPermissions() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function checkInput() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function doAction() {
		$response = new \CControllerResponseData([]);
		$response->setTitle('пятый модуль');

		$this->setResponse($response);
	}
}
