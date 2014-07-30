<?php

namespace Respect\Validation\Rules;

class Sequence extends AbstractRule {

	public function validate($input)
	{
		$last = null;

		foreach ($input as $value) {
			if (!is_null($last)) {
				if ($value - $last != 1) {
					return false;
				}
			}

			$last = $value;
		}

		return true;
	}

}
