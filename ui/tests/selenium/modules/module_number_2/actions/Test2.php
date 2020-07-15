<?php declare(strict_types = 1);

namespace Modules\Example_B\Actions;

use CController as CAction;

class Test2 extends CAction {

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
		$response->setTitle('2nd Module');

		$this->setResponse($response);
	}
}
