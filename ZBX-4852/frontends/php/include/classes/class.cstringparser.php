<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CStringParser {

	private $expression = false;
	private $currentSymbol = false;
	private $currentSymbolNum = false;
	private $totalSymbols = false;
	private $expressionStructureSymbols = array();
	private $ess = false; // shortcut for previous
	private $currentLevel = 0;
	private $levelType = null;
	private $rulesIndex = array();
	private $parsedTree = array();
	private $levelData = array();
	private $flags = array();
	private $errors = array();
	private $indexes = array();
	private $indexLevels = array();
	public $debugOutput = '';

	public function __construct($rules) {
		$this->expressionStructureSymbols = $rules;
		$this->ess =& $this->expressionStructureSymbols;
		$this->prepareRules();
	}

	private function prepareRules() {
		foreach ($this->ess as $key => $ruleset) {
			$this->ess[$key]['ruleName'] = $key;
			$indexKeys = isset($ruleset['parent']) ? $ruleset['parent'] : 'independent';

			if (!is_array($indexKeys)) {
				$indexKeys = array($indexKeys);
			}

			foreach ($indexKeys as $indexKey) {
				if (!isset($this->rulesIndex[$indexKey])) {
					$this->rulesIndex[$indexKey] = array();
				}
				$this->rulesIndex[$indexKey][] = $key;
			}
		}
	}

	public function parse($string) {
		unset($this->parsedTree);
		unset($this->indexes);
		unset($this->levelData);
		unset($this->errors);
		unset($this->indexLevels);
		$this->parsedTree = null;
		$this->levelData = array();
		$this->indexes = array();
		$this->errors = array();
		$this->indexLevels = array();
		$this->expression = $string;
		$this->totalSymbols = mb_strlen($this->expression); // should be changed to zbx_strlen
		$this->levelData[$this->currentLevel] = array();
		$this->levelData[$this->currentLevel]['levelType'] = 'independent';

		if (isset($this->ess[$this->levelData[$this->currentLevel]['levelType']]['levelIndex'])) {
			$this->addToIndex();
		}
		else {
			$this->indexLevels[$this->currentLevel] = array();
		}
		$this->indexes =& $this->indexLevels[$this->currentLevel];

		for ($this->currentSymbolNum = 0; $this->currentSymbolNum < $this->totalSymbols; $this->currentSymbolNum++) {
			$this->currentSymbol = mb_substr($this->expression, $this->currentSymbolNum, 1); // should be changed to zbx_substr
			$this->checkSymbol();
		}

		if (empty($this->levelData[0]['levelType'])) {
			$this->levelData[0]['levelType'] = null;
		}
		$this->levelData[0]['openSymbolNum'] = 0;
		$this->levelData[0]['closeSymbolNum'] = mb_strlen($this->expression) - 1;
		$this->levelData[0]['value'] = $this->expression;

		$this->validate($this->levelData, $this->levelData[0], 0);
		$this->parsedTree =& $this->levelData[0];

		return count($this->errors) > 0 ? false : true;
	}

	private function addToIndex() {
		if (!isset($this->levelData[$this->currentLevel]['indexes'])) {
			$this->levelData[$this->currentLevel]['indexes'] = array();
		}
		$this->indexLevels[$this->currentLevel] =& $this->levelData[$this->currentLevel]['indexes'];
	}

	private function removeFromIndex () {
		unset($this->indexLevels[$this->currentLevel]);
	}

	private function checkSymbol() {
		if ($this->currentLevel > 0 && isset($this->levelData[$this->currentLevel]['levelType']) && isset($this->ess[$this->levelData[$this->currentLevel]['levelType']])) {
			$this->currentRuleSet =& $this->ess[$this->levelData[$this->currentLevel]['levelType']];
			$this->monitorRulesSet =& $this->rulesIndex[$this->levelData[$this->currentLevel]['levelType']];
		}
		else {
			unset($this->currentRuleSet);
			$this->currentRuleSet = null;
			$this->monitorRulesSet =& $this->rulesIndex['independent'];
		}
		$this->checkOpenSymbol();
		$this->checkCloseSymbol();
	}

	private function checkOpenSymbol() {
		if (isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] == $this->currentSymbolNum - 1) {
			return false;
		}

		$ruleSetName = $this->selectOpenRule();
		if ($ruleSetName === false) {
			return false;
		}

		$this->currentLevel++;
		$newLevel = array();
		$newLevel['levelType'] = $ruleSetName;
		$newLevel['openSymbol'] = $this->currentSymbol;
		$newLevel['openSymbolNum'] = $this->currentSymbolNum;
		$this->levelData[$this->currentLevel] =& $newLevel;

		if (isset($this->ess[$newLevel['levelType']]['levelIndex']) && $this->ess[$newLevel['levelType']]['levelIndex'] === true) {
			$this->addToIndex();
		}
		$this->checkSymbol();
	}

	private function selectOpenRule() {
		if (is_array($this->monitorRulesSet)) {
			$openRules = array();
			foreach ($this->monitorRulesSet as $key => &$ruleSetName) {
				if (!isset($this->ess[$ruleSetName]['openSymbol'])) {
					continue;
				}
				if (!is_array($this->ess[$ruleSetName]['openSymbol'])) {
					$this->ess[$ruleSetName]['openSymbol'] = array($this->ess[$ruleSetName]['openSymbol'] => 'default');
				}

				if (isset($this->levelData[$this->currentLevel]['parts']) && is_array($this->levelData[$this->currentLevel]['parts']) && count($this->levelData[$this->currentLevel]['parts']) > 0) {
					$currentLastPart = end($this->levelData[$this->currentLevel]['parts']);
					if ($currentLastPart === null) {
						$currentLastPart = false;
					}
				}
				else {
					$currentLastPart = false;
				}

				foreach ($this->ess[$ruleSetName]['openSymbol'] as $openSymbol => $symbolType) {
					if ($this->currentSymbol == $openSymbol
							&& ($this->levelData[$this->currentLevel]['levelType'] != $this->ess[$ruleSetName]['ruleName']
								|| ($this->levelData[$this->currentLevel]['levelType'] == $this->ess[$ruleSetName]['ruleName']
									&& ($this->levelData[$this->currentLevel]['openSymbol'] != $this->currentSymbol
										|| $this->levelData[$this->currentLevel]['openSymbolNum'] != $this->currentSymbolNum)))
									&& ($symbolType != 'individual' || $currentLastPart === false || $currentLastPart['closeSymbol'] != $openSymbol)
									&& (!isset($this->ess[$ruleSetName]['allowedSymbolsBefore']) || ($currentLastPart === false
										&& preg_match(
											"/^".$this->ess[$ruleSetName]['allowedSymbolsBefore']."$/",
											mb_substr($this->expression, $this->levelData[$this->currentLevel]['openSymbolNum'] + 1,
											$this->currentSymbolNum-$this->levelData[$this->currentLevel]['openSymbolNum'] - 1)))
										|| ($currentLastPart !== false && preg_match(
											"/^".$this->ess[$ruleSetName]['allowedSymbolsBefore']."$/",
											mb_substr($this->expression, $currentLastPart['closeSymbolNum'] + 1,
											$this->currentSymbolNum-$currentLastPart['closeSymbolNum'] - 1))))) {
						if ($symbolType == 'valueDependent' && (isset($this->ess[$ruleSetName]['allowedValues']) || isset($this->ess[$ruleSetName]['allowedSymbols']))) {
							if (!isset($this->ess[$ruleSetName]['closeSymbol'])) {
								continue;
							}
							if (!is_array($this->ess[$ruleSetName]['closeSymbol'])) {
								$this->ess[$ruleSetName]['closeSymbol'] = array($this->ess[$ruleSetName]['closeSymbol'] => 'default');
							}

							$ends = array();
							foreach ($this->ess[$ruleSetName]['closeSymbol'] as $closeSymbol => $type) {
								//TODO: should be corrected -> way of looking closing symbol depending on close symbol type
								$ends[$closeSymbol] = mb_strpos($this->expression, $closeSymbol, $this->currentSymbolNum);
								if (!is_int($ends[$closeSymbol])) {
									unset($ends[$closeSymbol]);
								}
							}
							asort($ends, SORT_NUMERIC);

							$endSymbols = array_keys($ends);
							$endSymbol = array_shift($endSymbols);
							$endPoint = array_shift($ends);

							$openprepend = (!isset($this->ess[$ruleSetName]['inclusive']) || $this->ess[$ruleSetName]['inclusive'] !== true) ? mb_strlen($this->currentSymbol) : 0;
							$openpostend = (!isset($this->ess[$ruleSetName]['inclusive']) || $this->ess[$ruleSetName]['inclusive'] !== true) ? mb_strlen($endSymbol) * -1 : 0;

							$compareValue = mb_substr($this->expression, $this->currentSymbolNum+$openprepend, $endPoint + $openpostend - ($this->currentSymbolNum + $openprepend) + 1);

							if (isset($this->ess[$ruleSetName]['allowedValues'])) {
								$allowedValuesValid = true;
								if (!is_array($this->ess[$ruleSetName]['allowedValues'])) {
									$this->ess[$ruleSetName]['allowedValues'] = array($this->ess[$ruleSetName]['allowedValues']);
								}
								$allowedValuesValid = false;
								if (in_array($compareValue, $this->ess[$ruleSetName]['allowedValues'])) {
									$allowedValuesValid = true;
								}
							}

							if (isset($this->ess[$ruleSetName]['allowedSymbols'])) {
								$allowedSymbolsValid = false;
								if (!is_array($this->ess[$ruleSetName]['allowedSymbols'])) {
									$this->ess[$ruleSetName]['allowedSymbols'] = array($this->ess[$ruleSetName]['allowedSymbols']);
								}

								foreach ($this->ess[$ruleSetName]['allowedSymbols'] as $regexp) {
									if (preg_match("/^".$regexp."$/", $compareValue)) {
										$allowedSymbolsValid = true;
										break;
									}
									else {
										$allowedSymbolsValid = false;
									}
								}
							}

							if ((isset($this->ess[$ruleSetName]['allowedValues']) && isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedValuesValid && $allowedSymbolsValid)
								|| (isset($this->ess[$ruleSetName]['allowedValues']) && !isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedValuesValid)
								|| (!isset($this->ess[$ruleSetName]['allowedValues']) && isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedSymbolsValid)) {
								return $ruleSetName;
							}
						}
						elseif ($symbolType != 'valueDependent') {
							$openRules[$key] = &$ruleSetName;
						}
					}
				}
			}

			if (count($openRules) > 0) {
				ksort($openRules, SORT_NUMERIC);
				return array_shift($openRules);
			}
			else {
				return false;
			}
		}
		return false;
	}

	private function checkCloseSymbol() {
		if (isset($this->levelData[$this->currentLevel]['parts']) && is_array($this->levelData[$this->currentLevel]['parts']) && count($this->levelData[$this->currentLevel]['parts']) > 0) {
			$currentLastPart = end($this->levelData[$this->currentLevel]['parts']);
			if ($currentLastPart === null) {
				$currentLastPart = false;
			}
		}
		else {
			$currentLastPart = false;
		}

		if (!is_array($this->currentRuleSet)
				|| !isset($this->currentRuleSet['closeSymbol'])
				|| $this->levelData[$this->currentLevel]['openSymbolNum'] == $this->currentSymbolNum
				|| ($currentLastPart !== false && $this->levelData[$this->currentLevel]['levelType'] == $currentLastPart['levelType']
					&& $this->currentSymbolNum == $currentLastPart['closeSymbolNum'])
					|| (isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] == $this->currentSymbolNum - 1)) {
			return false;
		}

		if (!is_array($this->currentRuleSet['closeSymbol'])) {
			$this->currentRuleSet['closeSymbol'] = array($this->currentRuleSet['closeSymbol'] => 'default');
		}

		if (isset($this->levelData[$this->currentLevel]['nextData'])) {
			$nextSymbols = $this->levelData[$this->currentLevel]['nextData'];
			foreach ($nextSymbols as $closeSymbol => &$nextSymbolData) {
				$nextRules =& $nextSymbolData['nextRules'];
				foreach ($nextRules as &$ruleData) {
					if (!is_array($ruleData['openSymbol'])) {
						$ruleData['openSymbol'] = array($ruleData['openSymbol'] => 'default');
					}

					$openSymbol = null;
					foreach ($ruleData['openSymbol'] as $oSymbol => $symbolType) {
						if ($oSymbol != $closeSymbol) {
							continue;
						}
						$openSymbol = $oSymbol;
						break;
					}

					if (!is_array($ruleData['closeSymbol'])) {
						$ruleData['closeSymbol'] = array($ruleData['closeSymbol'] => 'default');
					}

					$cCloseSymbol = null;
					foreach ($ruleData['closeSymbol'] as $cSymbol => $symbolType) {
						if ($cSymbol != $this->currentSymbol) {
							continue;
						}
						$cCloseSymbol = $cSymbol;
						break;
					}

					if ($openSymbol === null || $cCloseSymbol === null) {
						continue;
					}

					$tmpLevel = $this->currentLevel;
					$tmpRuleSet =& $this->currentRuleSet;
					$this->currentLevel++;
					$this->flags['nextData'] = true;

					unset($this->currentRuleSet);
					$this->currentRuleSet =& $ruleData;

					$newLevel = array();
					$newLevel['levelType'] = $ruleData['ruleName'];
					$newLevel['openSymbol'] = $closeSymbol;
					$newLevel['openSymbolNum'] = $nextSymbolData['occurred'];
					$this->levelData[$this->currentLevel] =& $newLevel;
					$this->checkCloseSymbol();
					unset($this->flags['nextData']);

					if ($tmpLevel == $this->currentLevel) {
						$nextLevelData = array_pop($this->levelData[$this->currentLevel]['parts']);
						if (count($this->levelData[$this->currentLevel]['parts']) == 0) {
							unset($this->levelData[$this->currentLevel]['parts']);
						}
						$this->levelData[$this->currentLevel]['closeSymbol'] = $closeSymbol;
						$this->levelData[$this->currentLevel]['closeSymbolNum'] = $nextSymbolData['occurred'];
						$this->saveToParent();

						$newLevel = array();
						$newLevel['levelType'] = $ruleData['ruleName'];
						$newLevel['openSymbol'] = $closeSymbol;
						$newLevel['openSymbolNum'] = $nextSymbolData['occurred'];
						$this->levelData[$this->currentLevel] =& $newLevel;
						$this->currentSymbolNum = $nextSymbolData['occurred'];
						$this->currentSymbol = $closeSymbol;
						$this->checkSymbol();
						return null;
					}
					elseif ($tmpLevel >= $this->currentLevel) {
						unset($this->currentRuleSet);
						$this->currentRuleSet =& $tmpRuleSet;
						unset($this->levelData[$this->currentLevel]);
						$this->currentLevel--;
					}
				}
			}
		}

		foreach ($this->currentRuleSet['closeSymbol'] as $closeSymbol => $symbolType) {
			if ($this->currentSymbol != $closeSymbol) {
				continue;
			}
			if ($symbolType == 'nextEnd') {
				if (!isset($this->levelData[$this->currentLevel]['nextData'])) {
					$this->levelData[$this->currentLevel]['nextData'] = array();
				}
				$this->prepareNextEndSymbol();
				$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['occurred'] = $this->currentSymbolNum;
			}
			if ($symbolType == 'default') {
				$this->closeRule();
				if (!isset($this->flags['nextData'])) {
					$this->checkSymbol();
				}
				return null;
			}
		}

		if (isset($this->currentRuleSet['escapeSymbol']) && !isset($this->levelData[$this->currentLevel]['escaped']) && $this->currentRuleSet['escapeSymbol'] == $this->currentSymbol) {
			$this->levelData[$this->currentLevel]['escaped'] = $this->currentSymbolNum;
		}
		elseif (isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] != $this->currentSymbolNum) {
			unset($this->levelData[$this->currentLevel]['escaped']);
		}
	}

	private function prepareNextEndSymbol() {
		if (!isset($this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol])) {
			$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol] = array();
			$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['nextRules'] = array();

			$nextRules =& $this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['nextRules'];
			$levelRules =& $this->rulesIndex[$this->levelData[$this->currentLevel-1]['levelType']];

			foreach ($levelRules as $ruleName) {
				if ($ruleName == $this->levelData[$this->currentLevel]['levelType']) {
					continue;
				}
				if (!is_array($this->ess[$ruleName]['openSymbol'])) {
					$this->ess[$ruleName]['openSymbol'] = array($this->ess[$ruleName]['openSymbol'] => 'default');
				}

				foreach ($this->ess[$ruleName]['openSymbol'] as $openSymbol => $symbolType) {
					if ($this->currentSymbol == $openSymbol) {
						$nextRules[] =& $this->ess[$ruleName];
					}
				}
			}
		}
	}

	private function closeRule() {
		$this->levelData[$this->currentLevel]['closeSymbol'] = $this->currentSymbol;
		$this->levelData[$this->currentLevel]['closeSymbolNum'] = $this->currentSymbolNum;
		$this->saveToParent();
		$this->currentLevel--;
	}

	private function saveToParent() {
		if (isset($this->levelData[$this->currentLevel]['nextData'])) {
			unset($this->levelData[$this->currentLevel]['nextData']);
		}
		if (!isset($this->levelData[$this->currentLevel - 1]['parts'])) {
			$this->levelData[$this->currentLevel-1]['parts'] = array();
		}

		$currentItem =& $this->levelData[$this->currentLevel];
		if (isset($currentItem['parts'])) {
			ksort($currentItem['parts'], SORT_NUMERIC);
		}
		$key = $currentItem['openSymbolNum'].'_'.$currentItem['closeSymbolNum'];
		$this->levelData[$this->currentLevel - 1]['parts'][$key] =& $this->levelData[$this->currentLevel];
		unset($this->levelData[$this->currentLevel]);

		if (isset($this->ess[$currentItem['levelType']]['levelIndex']) && $this->ess[$currentItem['levelType']]['levelIndex'] === true) {
			$this->removeFromIndex();
		}
		if (isset($this->ess[$currentItem['levelType']]['indexItem']) && $this->ess[$currentItem['levelType']]['indexItem'] === true) {
			$this->indexItem($currentItem);
		}
	}

	private function indexItem(&$itemToIndex) {
		$levelType = $itemToIndex['levelType'];
		if (is_array($this->indexLevels) && isset($itemToIndex) && is_array($itemToIndex)) {
			foreach ($this->indexLevels as &$level) {
				if (!isset($level[$levelType])) {
					$level[$levelType] = array();
				}
				if (!isset($level[$levelType][$itemToIndex['openSymbolNum'].'_'.$itemToIndex['closeSymbolNum']])) {
					$level[$levelType][$itemToIndex['openSymbolNum'].'_'.$itemToIndex['closeSymbolNum']] =& $itemToIndex;
				}
			}
		}
	}

	private function validate(&$parent, &$levelData, $index) {
		$skipAfterEmpty = false;
		$values = $this->levelValue($levelData);

		if (isset($parent['parts']) && is_array($parent['parts'])) {
			$nextKey = key($parent['parts']);
			$nextItem =& $parent['parts'][$nextKey];
			prev($parent['parts']);
			prev($parent['parts']);
			$prevKey = key($parent['parts']);
			$prevItem = $prevKey !== null ? $parent['parts'][$prevKey]: false;
			reset($parent['parts']);
			$startKey = key($parent['parts']);
			$startItem =& $parent['parts'][$startKey];
			end($parent['parts']);
			$endKey = key($parent['parts']);
			$endItem = $parent['parts'][$endKey];
		}
		else {
			$startKey = $endKey = $nextKey = $prevKey = false;
			$startItem = $endItem = $nextItem = $prevItem = false;
		}

		if (isset($this->ess[$levelData['levelType']]['allowedSymbolsBefore'])) {
			if (isset($parent['openSymbolNum']) && $startKey == $index && $parent['openSymbolNum'] < $levelData['openSymbolNum'])  {
				$startCut = $parent['openSymbolNum'] + 1;
				$endCut = $levelData['openSymbolNum'];
			}
			elseif ($startKey != $index && $prevItem && isset($prevItem['closeSymbolNum']) && $prevItem['closeSymbolNum'] < $levelData['openSymbolNum']) {
				$startCut = $prevItem['closeSymbolNum'] + 1;
				$endCut = $levelData['openSymbolNum'];
			}
			else {
				$startCut = $levelData['openSymbolNum'];
				$endCut = $levelData['openSymbolNum'];
			}

			$beforesymbols = mb_substr($this->expression, $startCut, $endCut-$startCut);

			if (!preg_match("/^".$this->ess[$levelData['levelType']]['allowedSymbolsBefore']."$/", $beforesymbols)) {
				$this->errors[] = array(
					'errorCode' => 4,
					'errorMsg' => 'Not allowed symbols detected before '.$levelData['levelType'].'. Check expression starting from symbol #'.($startCut + 1).' up to symbol #'.$endCut.'. Debug / symbols before: <'.$beforesymbols.'>/ RegExp: '.$this->ess[$levelData['levelType']]['allowedSymbolsBefore'],
					'errStart' => $startCut,
					'errEnd' => $endCut,
					'errValues' => array($beforesymbols)
				);
			}
		}

		if (isset($this->ess[$levelData['levelType']]['allowedSymbolsAfter'])) {
			if (isset($parent['closeSymbolNum']) && $endKey == $index && $parent['closeSymbolNum'] > $levelData['closeSymbolNum']) {
				$startCut = $levelData['closeSymbolNum'] + 1;
				$endCut = $parent['closeSymbolNum'];
			}
			elseif ($endKey !== $index && $nextItem && isset($nextItem['openSymbolNum']) && $nextItem['openSymbolNum'] > $levelData['closeSymbolNum']) {
				$startCut = $levelData['closeSymbolNum'] + 1;
				$endCut = $nextItem['openSymbolNum'];
			}
			else {
				$startCut = $levelData['closeSymbolNum'];
				$endCut = $levelData['closeSymbolNum'];
			}
			$aftersymbols = mb_substr($this->expression, $startCut, $endCut-$startCut);

			if (!preg_match("/^".$this->ess[$levelData['levelType']]['allowedSymbolsAfter']."$/", $aftersymbols)) {
				$this->errors[] = array(
					'errorCode' => 5,
					'errorMsg' => 'Not allowed symbols detected after '.$levelData['levelType'].'. Check expression starting from symbol #'.($startCut+1).' up to symbol #'.$endCut.'. Debug / symbols after: <'.$aftersymbols.'>/ RegExp: '.$this->ess[$levelData['levelType']]['allowedSymbolsAfter'],
					'errStart' => $startCut,
					'errEnd' => $endCut,
					'errValues' => array($aftersymbols)
				);
			}
		}

		if (isset($this->ess[$levelData['levelType']]['isEmpty']) && $this->ess[$levelData['levelType']]['isEmpty'] === true) {
			if (is_array($values) && (count($values) > 1 || (count($values) > 0 && mb_strlen($values[0]['value']) > 0))) {
				foreach ($values as &$val) {
					$this->errors[] = array(
						'errorCode' => 3,
						'errorMsg' => $levelData['levelType'].' has unnecessary symbols. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Debug: <'.$val['value'].'>',
						'errStart' => $val['from'],
						'errEnd' => $val['until'],
						'errValues' => array($val['value'])
					);
				}
			}
			else {
				$skipAfterEmpty = true;
			}
		}

		if (!$skipAfterEmpty) {
			foreach ($values as $val) {
				$notclean = $val['value'];
				if (isset($this->ess[$levelData['levelType']]['ignorSymbols'])) {
					$val['value'] = preg_replace("/".$this->ess[$levelData['levelType']]['ignorSymbols']."/", '', $val['value']);
				}

				$errRegExps = array();
				$errValues = array();

				if (isset($this->ess[$levelData['levelType']]['allowedSymbols'])) {
					$allowedSymbolsValid = false;
					if (!is_array($this->ess[$levelData['levelType']]['allowedSymbols'])) {
						$this->ess[$levelData['levelType']]['allowedSymbols'] = array($this->ess[$levelData['levelType']]['allowedSymbols']);
					}
					foreach ($this->ess[$levelData['levelType']]['allowedSymbols'] as $regexp) {
						if (preg_match("/^".$regexp."$/", $val['value'])) {
							$allowedSymbolsValid = true;
							break;
						}
						else {
							$allowedSymbolsValid = false;
						}
					}
				}
				$notAllowedSymbolsValid = true;
				if (isset($this->ess[$levelData['levelType']]['notAllowedSymbols'])) {
					if (!is_array($this->ess[$levelData['levelType']]['notAllowedSymbols'])) {
						$this->ess[$levelData['levelType']]['notAllowedSymbols'] = array($this->ess[$levelData['levelType']]['notAllowedSymbols']);
					}
					foreach ($this->ess[$levelData['levelType']]['notAllowedSymbols'] as $regexp) {
						if (preg_match("/".$regexp."/", $val['value'], $matches)) {
							$errRegExps[] = $regexp;
							$errValues[] = $matches[0];
							$notAllowedSymbolsValid = false;
						}
					}
				}

				if ((isset($this->ess[$levelData['levelType']]['allowedSymbols']) && !$allowedSymbolsValid)
						|| (isset($this->ess[$levelData['levelType']]['notAllowedSymbols']) && !$notAllowedSymbolsValid)) {
					$this->errors[] = array(
						'errorCode' => 2,
						'errorMsg' => 'Not allowed symbols or sequence of symbols detected in '.$levelData['levelType'].'. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Debug: '.$notclean.' / '.$val['value'].'/ Error RegExp: '.implode(', ', $errRegExps),
						'errStart' => $val['from'],
						'errEnd' => $val['until'],
						'errValues' => $errValues
					);
				}
				$errValues = array();
				$allowedValuesValid = true;

				if (isset($this->ess[$levelData['levelType']]['allowedValues'])) {
					if (!is_array($this->ess[$levelData['levelType']]['allowedValues'])) {
						$this->ess[$levelData['levelType']]['allowedValues'] = array($this->ess[$levelData['levelType']]['allowedValues']);
					}
					$allowedValuesValid = false;
					if (in_array($val['value'], $this->ess[$levelData['levelType']]['allowedValues'])) {
						$allowedValuesValid = true;
					}
					if (!$allowedValuesValid) {
						$errValues[] = $val['value'];
					}
				}

				$notAllowedValuesValid = true;
				if (isset($this->ess[$levelData['levelType']]['notAllowedValues'])) {
					if (!is_array($this->ess[$levelData['levelType']]['notAllowedValues'])) {
						$this->ess[$levelData['levelType']]['notAllowedValues'] = array($this->ess[$levelData['levelType']]['notAllowedValues']);
					}
					foreach ($this->ess[$levelData['levelType']]['notAllowedValues'] as $notAllowedValue) {
						if ($val['value'] == $notAllowedValue) {
							$errValues[] = $notAllowedValue;
							$notAllowedValuesValid = false;
							break;
						}
					}
				}

				if ((isset($this->ess[$levelData['levelType']]['allowedValues']) && !$allowedValuesValid)
						|| (isset($this->ess[$levelData['levelType']]['notAllowedValues']) && !$notAllowedValuesValid)) {
					$this->errors[] = array(
						'errorCode' => 6,
						'errorMsg' => 'Not allowed value detected in '.$levelData['levelType'].'. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Not allowed values: '.implode(', ', $errValues).'. Debug: '.$notclean.' / '.$val['value'],
						'errStart' => $val['from'],
						'errEnd' => $val['until'],
						'errValues' => $errValues
					);
				}
			}
		}
		if (isset($this->ess[$levelData['levelType']]['customValidate'])) {
			if (!is_array($this->ess[$levelData['levelType']]['customValidate'])) {
				$this->ess[$levelData['levelType']]['customValidate'] = array($this->ess[$levelData['levelType']]['customValidate']);
			}

			foreach ($this->ess[$levelData['levelType']]['customValidate'] as &$customFunction) {
				if (!is_callable($customFunction)) {
					continue;
				}

				$ret = call_user_func_array($customFunction, array(&$parent, &$levelData, $index, &$this->expression, &$this->ess[$levelData['levelType']]));
				if (isset($ret['valid']) && $ret['valid'] === false && isset($ret['errArray']) && is_array($ret['errArray'])
						&& isset($ret['errArray']['errorCode']) && isset($ret['errArray']['errStart']) && isset($ret['errArray']['errEnd'])) {
					$this->errors[] = $ret['errArray'];
				}
			}
		}

		if (isset($levelData['parts']) && is_array($levelData['parts'])) {
			foreach ($levelData['parts'] as $key => &$level) {
				$this->validate($levelData, $level, $key);
			}
		}
	}

	private function levelValue(&$levelData) {
		$value = '';
		$values = array();
		$openprepend = (!isset($this->ess[$levelData['levelType']]['inclusive']) || $this->ess[$levelData['levelType']]['inclusive'] !== true) && isset($levelData['openSymbol']) ? mb_strlen($levelData['openSymbol']) : 0;
		$openpostend = (!isset($this->ess[$levelData['levelType']]['inclusive']) || $this->ess[$levelData['levelType']]['inclusive'] !== true) && isset($levelData['closeSymbol'])? mb_strlen($levelData['closeSymbol']) * -1 : 0;
		if (isset($levelData['parts']) && is_array($levelData['parts'])) {
			$prev = null;
			end($levelData['parts']);
			$endKey = key($levelData['parts']);
			foreach ($levelData['parts'] as $key => &$level) {
				if (!isset($prev) && ($level['openSymbolNum'] > $levelData['openSymbolNum'] + $openprepend)) {
					$val = array();
					$val['value'] = mb_substr($this->expression, $levelData['openSymbolNum'] + $openprepend, $level['openSymbolNum'] - ($levelData['openSymbolNum'] + $openprepend)); // should be changed to zbx_substr
					$val['from'] = $levelData['openSymbolNum'] + $openprepend;
					$val['until'] = $level['openSymbolNum'];
					$value .= $val['value'];
					$values[] = $val;
				}

				if (isset($prev) && $level['openSymbolNum'] > $prev['closeSymbolNum'] + mb_strlen($prev['closeSymbol'])) {
					$val = array();
					$val['value'] = mb_substr($this->expression, $prev['closeSymbolNum'] + mb_strlen($prev['closeSymbol']), $level['openSymbolNum'] - ($prev['closeSymbolNum'] + mb_strlen($prev['closeSymbol']))); // should be changed to zbx_substr
					$val['from'] = $prev['closeSymbolNum'] + mb_strlen($prev['closeSymbol']);
					$val['until'] = $level['openSymbolNum'];
					$value .= $val['value'];
					$values[] = $val;
				}
				if ($endKey == $key && ($level['closeSymbolNum'] + mb_strlen($level['closeSymbol']) < $levelData['closeSymbolNum'] + $openpostend + 1)) {
					$val = array();
					$val['value'] = mb_substr($this->expression, $level['closeSymbolNum'] + mb_strlen($level['closeSymbol']), $levelData['closeSymbolNum'] + $openpostend - ($level['closeSymbolNum'] + mb_strlen($level['closeSymbol'])) + 1); // should be changed to zbx_substr
					$val['from'] = $level['closeSymbolNum'] + mb_strlen($level['closeSymbol']);
					$val['until'] = $levelData['closeSymbolNum'] + $openpostend;
					$value .= $val['value'];
					$values[] = $val;
				}
				$prev =& $level;
			}
		}
		else {
			$val = array();
			$val['value'] = mb_substr($this->expression, $levelData['openSymbolNum'] + $openprepend, $levelData['closeSymbolNum'] + $openpostend - ($levelData['openSymbolNum'] + $openprepend) + 1); // should be changed to zbx_substr
			$val['from'] = $levelData['openSymbolNum'] + $openprepend;
			$val['until'] = $levelData['closeSymbolNum'] + $openpostend;
			$value .= $val['value'];
			$values[] = $val;
		}
		return $values;
	}

	public function getElements($index) {
		return !isset($this->indexes[$index]) ? array() : $this->indexes[$index];
	}

	public function getTree() {
		return $this->parsedTree;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function saveDebug($debugStr) {
		global $debugfile;

		file_put_contents($debugfile, $debugStr, FILE_APPEND);
	}
}
