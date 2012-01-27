<?php

class CExportReferral {

	private $references = array();


	protected function referenceList() {
		return array(
			'interface_ref' => array(
				'field' => 'interfaceid',
				'key' => 'if'
			)
		);
	}

	/**
	 * Get info about specified reference.
	 * Info recieved from referenceList method.
	 *
	 * @throws InvalidArgumentException
	 *
	 * @param string $refName
	 *
	 * @return array
	 */
	protected function getReferenceInfo($refName) {
		$references = $this->referenceList();

		if (!isset($references[$refName])) {
			throw new InvalidArgumentException(sprintf('Unknown reference name "%1$s".', $refName));
		}
		return $references[$refName];
	}

	/**
	 * Creates reference record for element.
	 *
	 * @param string $refName
	 * @param array  $element
	 *
	 * @return array
	 */
	public function createReference($refName, array $element) {
		$refInfo = $this->getReferenceInfo($refName);

		if (isset($this->references[$refName])) {
			$refNum = ++$this->references[$refName]['num'];
		}
		else {
			$this->references[$refName]['num'] = 1;
			$this->references[$refName]['refs'] = array();
			$refNum = 1;
		}


		$referenceKey = $refInfo['key'].$refNum;
		$element[$refName] = $referenceKey;
		$this->references[$refName]['refs'][$element[$refInfo['field']]] = $referenceKey;

		return $element;
	}

	/**
	 * Add reference for previosly created reference.
	 *
	 * @param string $refName
	 * @param array  $elementData
	 *
	 * @return array
	 */
	public function addReference($refName, array $elementData) {
		$refInfo = $this->getReferenceInfo($refName);

		$elementData[$refName] = $this->references[$refName]['refs'][$elementData[$refInfo['field']]];
		return $elementData;
	}

	/**
	 * @param string $refName
	 */
	public function clearReferences($refName = null) {
		if ($refName === null) {
			$this->references = array();
		}
		else {
			unset($this->references[$refName]);
		}
	}

}
