<?php

namespace Respect\Validation\Exceptions;

class SequenceException extends ValidationException {
	public static $defaultTemplates = array(
		self::MODE_DEFAULT => array(
			self::STANDARD => '{{name}} must be a valid sequence',
		),
		self::MODE_NEGATIVE => array(
			self::STANDARD => '{{name}} must not be a valid sequence',
		)
	);
}
