<?php

class GlobalRegExp {
	const ERROR_REGEXP_EMPTY = 1;
	const ERROR_REGEXP_NOT_EXISTS = 2;

	/**
	 * @var bool
	 */
	protected $isGlobalRegexp;

	/**
	 * Checks if expression is valid
	 *
	 * @static
	 * @throws Exception
	 * @param $regExp
	 * @return bool
	 */
	public static function isValid($regExp) {
		if (zbx_empty($regExp)) {
			throw new Exception('Empty expression', self::ERROR_REGEXP_EMPTY);
		}

		if ($regExp[0] == '@') {
			$regExp = substr($regExp, 1);

			$sql = 'SELECT regexpid'.
				' FROM regexps r'.
				' WHERE r.name='.zbx_dbstr($regExp);
			if (!DBfetch(DBselect($sql))) {
				throw new Exception('Global expression does not exists', self::ERROR_REGEXP_NOT_EXISTS);
			}
		}

		return true;
	}

	/**
	 * Initialize expression, gets data from db for global expressions
	 *
	 * @throws Exception
	 * @param $regExp
	 */
	public function __construct($regExp) {
		if ($regExp[0] == '@') {
			$this->isGlobalRegexp = true;
			$this->expression = array();

			$regExp = substr($regExp, 1);
			$sql = 'SELECT regexpid, expression, expression_type, exp_delimiter, case_sensitive'.
				' FROM expressions e, regexps r'.
				' WHERE e.regexpid = r.regexpid'.
				' AND r.name='.zbx_dbstr($regExp);
			$dbRegExps = DBselect($sql);
			while ($expression = DBfetch($dbRegExps)) {
				$this->expression[] = $expression;
			}
			if (empty($this->expression)) {
				throw new Exception('Does not exists', self::ERROR_REGEXP_NOT_EXISTS);
			}
		}
		else {
			$this->isGlobalRegexp = false;
			$this->expression = $regExp;
		}
	}

	/**
	 * @param $string
	 * @return bool
	 */
	public function match($string) {
		if ($this->isGlobalRegexp) {
			$result = (bool) preg_match($this->expression, $string);
		}
		else {
			$result = true;

			foreach ($this->expression as $expression){

				if ($expression['expression_type'] == EXPRESSION_TYPE_TRUE || $expression['expression_type'] == EXPRESSION_TYPE_FALSE) {
					$pattern = '/'.$expression['expression'].'/';
					if ($expression['case_sensitive']) {
						$pattern .= 'i';
					}

					$expectedResult = ($expression['expression_type'] == EXPRESSION_TYPE_TRUE);

					$result = (preg_match($pattern, $string) == $expectedResult);
				}
				else {
					if ($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED) {
						$paterns = explode($expression['exp_delimiter'], $expression['expression']);
					}
					else {
						$paterns = array($expression['expression']);
					}

					$expectedResult = ($expression['expression_type'] != EXPRESSION_TYPE_NOT_INCLUDED);


					if ($expression['case_sensitive']) {
						foreach ($paterns as  $patern) {
							$result &= ((zbx_strstr($string, $patern) !== false) == $expectedResult);
						}
					}
					else {
						foreach ($paterns as  $patern) {
							$result &= ((zbx_stristr($string, $patern) !== false) == $expectedResult);
						}
					}
				}

				if (!$result) {
					break;
				}
			}

		}

		return $result;
	}

}
