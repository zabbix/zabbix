<?php

class CStringParser {
	private $expression = false;
	private $currentSymbol = false;
	private $currentSymbolNum = false;
	private $totalSymbols = false;
	private $expressionStructureSymbols = Array();
	private $ess = false; // shortcut for previous
	private $currentLevel = 0;
	private $levelType = NULL;
	private $rulesIndex = Array();
	private $parsedTree = Array();
	private $levelData = Array();
	private $flags = Array();
	private $errors = Array();
	private $indexes = Array();
	private $indexLevels = Array();
	public $debugOutput = '';

	public function __construct($rules) {
		$this->expressionStructureSymbols = $rules;
		$this->ess =& $this->expressionStructureSymbols;
		$this->prepareRules();
	}

	private function prepareRules() {
		foreach($this->ess as $key => $ruleset) {
			$this->ess[$key]['ruleName'] = $key;
			$indexKeys = isset($ruleset['parent']) ? $ruleset['parent'] : 'independent';

			if(!is_array($indexKeys)) $indexKeys = Array($indexKeys);

			foreach($indexKeys as $indexKey) {
				if(!isset($this->rulesIndex[$indexKey])) $this->rulesIndex[$indexKey] = Array();
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
		$this->parsedTree = NULL;
		$this->levelData = Array();
		$this->indexes = Array();
		$this->errors = Array();
		$this->indexLevels = Array();
		$this->expression = $string;
		$this->totalSymbols = mb_strlen($this->expression); // should be changed to zbx_strlen
		$this->levelData[$this->currentLevel] = Array();
		$this->levelData[$this->currentLevel]['levelType'] = 'independent';
		if(isset($this->ess[$this->levelData[$this->currentLevel]['levelType']]['levelIndex']))
		        $this->addToIndex();
                else
                        $this->indexLevels[$this->currentLevel] = Array();

                $this->indexes =& $this->indexLevels[$this->currentLevel];

		for($this->currentSymbolNum = 0; $this->currentSymbolNum < $this->totalSymbols; $this->currentSymbolNum++) {
			$this->currentSymbol = mb_substr($this->expression, $this->currentSymbolNum, 1); // should be changed to zbx_substr
			$this->checkSymbol();
		}

		$this->validateFatal();

		$this->levelData[0]['openSymbolNum'] = 0;
		$this->levelData[0]['closeSymbolNum'] = mb_strlen($this->expression)-1;
		$this->levelData[0]['value'] = $this->expression;
		$this->validate($this->levelData, $this->levelData[0], 0);

		//if(count($this->errors) > 0) $this->saveDebug(print_r($this->errors, true));

		$this->parsedTree =& $this->levelData[0];
		//SDII($this->parsedTree);

		//$this->saveDebug(print_r($this->parsedTree, true));

		return count($this->errors) > 0 ? false : true;
	}

	private function addToIndex() {
		if(!isset($this->levelData[$this->currentLevel]['indexes']))
			$this->levelData[$this->currentLevel]['indexes'] = Array();

		$this->indexLevels[$this->currentLevel] =& $this->levelData[$this->currentLevel]['indexes'];
	}

	private function removeFromIndex () {
		unset($this->indexLevels[$this->currentLevel]);
	}

	private function checkSymbol() {
		//$this->saveDebug("checking symbol {$this->levelData[$this->currentLevel]['levelType']}\n");
		if($this->currentLevel > 0 && isset($this->levelData[$this->currentLevel]['levelType']) && isset($this->ess[$this->levelData[$this->currentLevel]['levelType']])) {
			$this->currentRuleSet =& $this->ess[$this->levelData[$this->currentLevel]['levelType']];
			$this->monitorRulesSet =& $this->rulesIndex[$this->levelData[$this->currentLevel]['levelType']];
		}else{
			unset($this->currentRuleSet);
			$this->currentRuleSet = NULL;
			$this->monitorRulesSet =& $this->rulesIndex['independent'];
		}
		$this->checkOpenSymbol();
		$this->checkCloseSymbol();
	}

	private function checkOpenSymbol() {
		//$this->saveDebug("checking children Open symbol of {$this->levelData[$this->currentLevel]['levelType']}\n");
		if(isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] == $this->currentSymbolNum-1)
			return false;

		$ruleSetName = $this->selectOpenRule();

		if($ruleSetName === false) return false;

		//$this->saveDebug("current selected Open symbol {$ruleSetName}\n");
		$this->currentLevel++;
		$newLevel = Array();
		$newLevel['levelType'] = $ruleSetName;
		$newLevel['openSymbol'] = $this->currentSymbol;
		$newLevel['openSymbolNum'] = $this->currentSymbolNum;
		$this->levelData[$this->currentLevel] =& $newLevel;
		if(isset($this->ess[$newLevel['levelType']]['levelIndex']) && $this->ess[$newLevel['levelType']]['levelIndex'] === true) {
			$this->addToIndex();
		}
		$this->checkSymbol();
	}

	private function selectOpenRule() {
		if(is_array($this->monitorRulesSet)) {
			$openRules = Array();
			foreach($this->monitorRulesSet as $key => &$ruleSetName) {
				//$this->saveDebug("checking open symbol {$ruleSetName}\n");
				if(!isset($this->ess[$ruleSetName]['openSymbol']))
					continue;
				if(!is_array($this->ess[$ruleSetName]['openSymbol'])) {
					//$this->saveDebug("Before Converting Open symbols of {$ruleSetName}: ".var_export($this->ess[$ruleSetName]['openSymbol'], true)."\n");
					$this->ess[$ruleSetName]['openSymbol'] = Array($this->ess[$ruleSetName]['openSymbol'] => 'default');
					//$this->saveDebug("After Converting Open symbols of {$ruleSetName}: ".var_export($this->ess[$ruleSetName]['openSymbol'], true)."\n");
				}

				//$this->saveDebug("{$ruleSetName} open symbols:\n".var_export($this->ess[$ruleSetName]['openSymbol'], true)."\n");

				if(isset($this->levelData[$this->currentLevel]['parts']) && is_array($this->levelData[$this->currentLevel]['parts']) && count($this->levelData[$this->currentLevel]['parts']) > 0) {
				        $currentLastPart = end($this->levelData[$this->currentLevel]['parts']);
				        if($currentLastPart === NULL) $currentLastPart = false;
				}else
				        $currentLastPart = false;

				foreach($this->ess[$ruleSetName]['openSymbol'] as $openSymbol => $symbolType) {
					//$this->saveDebug("comparing open symbol {$this->currentSymbol} == {$openSymbol} ?\n");

					if($this->currentSymbol == $openSymbol &&
					(
						$this->levelData[$this->currentLevel]['levelType'] != $this->ess[$ruleSetName]['ruleName'] ||
						(
							$this->levelData[$this->currentLevel]['levelType'] == $this->ess[$ruleSetName]['ruleName'] &&
                                                        (
								$this->levelData[$this->currentLevel]['openSymbol'] != $this->currentSymbol ||
								$this->levelData[$this->currentLevel]['openSymbolNum'] != $this->currentSymbolNum
                                                        )
                                                )
                                        ) && (
						$symbolType != 'individual' ||
						$currentLastPart === false ||
						$currentLastPart['closeSymbol'] != $openSymbol
                                        ) && (
						!isset($this->ess[$ruleSetName]['allowedSymbolsBefore']) ||
						(
						        $currentLastPart === false &&
                                                        preg_match("/^".$this->ess[$ruleSetName]['allowedSymbolsBefore']."$/",
								mb_substr($this->expression, $this->levelData[$this->currentLevel]['openSymbolNum']+1,
                                                                        $this->currentSymbolNum-$this->levelData[$this->currentLevel]['openSymbolNum']-1))
                                                ) || (
                                                        $currentLastPart !== false &&
                                                        preg_match("/^".$this->ess[$ruleSetName]['allowedSymbolsBefore']."$/",
							        mb_substr($this->expression, $currentLastPart['closeSymbolNum']+1,
							                $this->currentSymbolNum-$currentLastPart['closeSymbolNum']-1)) //change to zbx_substr
                                                )
                                        )) {
						//$this->saveDebug("found Open symbol {$ruleSetName}\n");
						if($symbolType == 'valueDependent' && (isset($this->ess[$ruleSetName]['allowedValues']) || isset($this->ess[$ruleSetName]['allowedSymbols']))) {
							//$this->saveDebug("found {$symbolType} Open symbol {$ruleSetName}\n");

							if(!isset($this->ess[$ruleSetName]['closeSymbol'])) {
								continue;
							}

							if(!is_array($this->ess[$ruleSetName]['closeSymbol'])) {
								$this->ess[$ruleSetName]['closeSymbol'] = Array($this->ess[$ruleSetName]['closeSymbol'] => 'default');
							}

							$ends = Array();
							foreach($this->ess[$ruleSetName]['closeSymbol'] as $closeSymbol => $type) {
								//TODO: should be corrected -> way of looking closing symbol depending on close symbol type
								$ends[$closeSymbol] = mb_strpos($this->expression, $closeSymbol, $this->currentSymbolNum);
								if(!is_int($ends[$closeSymbol])) unset($ends[$closeSymbol]);
							}
							asort($ends, SORT_NUMERIC);

							$endSymbols = array_keys($ends);
							$endSymbol = array_shift($endSymbols);
							$endPoint = array_shift($ends);

							$openprepend = (!isset($this->ess[$ruleSetName]['inclusive']) || $this->ess[$ruleSetName]['inclusive'] !== true) ? mb_strlen($this->currentSymbol) : 0;
							$openpostend = (!isset($this->ess[$ruleSetName]['inclusive']) || $this->ess[$ruleSetName]['inclusive'] !== true) ? mb_strlen($endSymbol) * -1 : 0;

							$compareValue = mb_substr($this->expression, $this->currentSymbolNum+$openprepend, $endPoint+$openpostend-($this->currentSymbolNum+$openprepend)+1);

							if(isset($this->ess[$ruleSetName]['allowedValues'])) {
								$allowedValuesValid = true;
								if(!is_array($this->ess[$ruleSetName]['allowedValues'])) {
									$this->ess[$ruleSetName]['allowedValues'] = Array($this->ess[$ruleSetName]['allowedValues']);
								}
								$allowedValuesValid = false;
								if(in_array($compareValue, $this->ess[$ruleSetName]['allowedValues'])) {
									$allowedValuesValid = true;
								}
							}

							if(isset($this->ess[$ruleSetName]['allowedSymbols'])) {
								$allowedSymbolsValid = false;
								if(!is_array($this->ess[$ruleSetName]['allowedSymbols'])) {
									$this->ess[$ruleSetName]['allowedSymbols'] = Array($this->ess[$ruleSetName]['allowedSymbols']);
								}

								foreach($this->ess[$ruleSetName]['allowedSymbols'] as $regexp) {
									if(preg_match("/^".$regexp."$/", $compareValue)) {
										$allowedSymbolsValid = true;
										break;
									}
									else{
										$allowedSymbolsValid = false;
									}
								}
							}

							if(	(isset($this->ess[$ruleSetName]['allowedValues']) && isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedValuesValid && $allowedSymbolsValid) ||
								(isset($this->ess[$ruleSetName]['allowedValues']) && !isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedValuesValid) ||
								(!isset($this->ess[$ruleSetName]['allowedValues']) && isset($this->ess[$ruleSetName]['allowedSymbols']) && $allowedSymbolsValid)) {
								//$this->saveDebug("Selected {$symbolType} Open symbol {$ruleSetName}\n");
								return $ruleSetName;
							}
						}else if($symbolType != 'valueDependent') {
							//$this->saveDebug("found Open symbol {$ruleSetName} -> {$symbolType}\n");
							$openRules[$key] = &$ruleSetName;
						}
					}
				}
			}

			//$this->saveDebug("current Open symbol:".var_export($this->currentSymbol, true)."\n");
			//$this->saveDebug("found total Open symbols:\n".var_export($openRules, true)."\n\n");

			if(count($openRules) > 0) {
				ksort($openRules, SORT_NUMERIC);
				return array_shift($openRules);
			}else{
				return false;
			}
		}

		return false;
	}

	private function checkCloseSymbol() {
		//$this->saveDebug("checking Close symbol {$this->levelData[$this->currentLevel]['levelType']} -> ".(isset($this->levelData[$this->currentLevel]['escaped']) ? var_export($this->levelData[$this->currentLevel]['escaped'], true) : '')."\n");

		if(isset($this->levelData[$this->currentLevel]['parts']) && is_array($this->levelData[$this->currentLevel]['parts']) && count($this->levelData[$this->currentLevel]['parts']) > 0) {
		        $currentLastPart = end($this->levelData[$this->currentLevel]['parts']);
		        if($currentLastPart === NULL) $currentLastPart = false;
		}else
		        $currentLastPart = false;

		if(	!is_array($this->currentRuleSet) ||
			!isset($this->currentRuleSet['closeSymbol']) ||
			$this->levelData[$this->currentLevel]['openSymbolNum'] == $this->currentSymbolNum ||
			($currentLastPart !== false && $this->levelData[$this->currentLevel]['levelType'] == $currentLastPart['levelType'] && $this->currentSymbolNum == $currentLastPart['closeSymbolNum']) ||
			(isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] == $this->currentSymbolNum-1)
			)
			return false;

		if(!is_array($this->currentRuleSet['closeSymbol'])) {
			//$this->saveDebug("Before Transforming {$this->levelData[$this->currentLevel]['levelType']} close symbols before:\n".var_export($this->currentRuleSet['closeSymbol'], true)."\n");
			$this->currentRuleSet['closeSymbol'] = Array($this->currentRuleSet['closeSymbol'] => 'default');
			//$this->saveDebug("After Transforming {$this->levelData[$this->currentLevel]['levelType']} close symbols after:\n".var_export($this->currentRuleSet['closeSymbol'], true)."\n");
		}else {
			//$this->saveDebug("Not transforming {$this->levelData[$this->currentLevel]['levelType']} close symbols:\n".var_export($this->currentRuleSet['closeSymbol'], true)."\n");
		}

		if(isset($this->levelData[$this->currentLevel]['nextData'])) {
			//$this->saveDebug("Entering nextData {$this->currentSymbol}\n");
			$nextSymbols = $this->levelData[$this->currentLevel]['nextData'];
			//$tmpLevelData = $this->levelData[$this->currentLevel];

			foreach($nextSymbols as $closeSymbol => &$nextSymbolData) {
				$nextRules =& $nextSymbolData['nextRules'];
				foreach($nextRules as &$ruleData) {
					if(!is_array($ruleData['openSymbol'])) {
						//$this->saveDebug("Before Converting Open symbols of {$ruleData['ruleName']}: ".var_export($this->ess[$ruleData['ruleName']]['openSymbol'])."\n");
						$ruleData['openSymbol'] = Array($ruleData['openSymbol'] => 'default');
						//$this->saveDebug("After Converting Open symbols of {$ruleData['ruleName']}: ".var_export($this->ess[$ruleData['ruleName']]['openSymbol'])."\n");
					}

					$openSymbol = null;
					foreach($ruleData['openSymbol'] as $oSymbol => $symbolType) {
						if($oSymbol != $closeSymbol) continue;
						$openSymbol = $oSymbol;
						break;
					}

					if(!is_array($ruleData['closeSymbol'])) $ruleData['closeSymbol'] = Array($ruleData['closeSymbol'] => 'default');

					$cCloseSymbol = null;
					foreach($ruleData['closeSymbol'] as $cSymbol => $symbolType) {
						if($cSymbol != $this->currentSymbol) continue;
						$cCloseSymbol = $cSymbol;
						break;
					}

					if($openSymbol === null || $cCloseSymbol === null) continue;

					$tmpLevel = $this->currentLevel;
					$tmpRuleSet =& $this->currentRuleSet;
					$this->currentLevel++;
					$this->flags['nextData'] = true;

					unset($this->currentRuleSet);
					$this->currentRuleSet =& $ruleData;

					$newLevel = Array();
					$newLevel['levelType'] = $ruleData['ruleName'];
					$newLevel['openSymbol'] = $closeSymbol;
					$newLevel['openSymbolNum'] = $nextSymbolData['occurred'];
					$this->levelData[$this->currentLevel] =& $newLevel;
					$this->checkCloseSymbol();
					unset($this->flags['nextData']);
					if($tmpLevel == $this->currentLevel) {
						$nextLevelData = array_pop($this->levelData[$this->currentLevel]['parts']);
						if(count($this->levelData[$this->currentLevel]['parts']) == 0) unset($this->levelData[$this->currentLevel]['parts']);

						$this->levelData[$this->currentLevel]['closeSymbol'] = $closeSymbol;
						$this->levelData[$this->currentLevel]['closeSymbolNum'] = $nextSymbolData['occurred'];
						$this->saveSymbols();

						$this->saveToParent();

						$newLevel = Array();
						$newLevel['levelType'] = $ruleData['ruleName'];
						$newLevel['openSymbol'] = $closeSymbol;
    						$newLevel['openSymbolNum'] = $nextSymbolData['occurred'];
						$this->levelData[$this->currentLevel] =& $newLevel;

						$this->currentSymbolNum = $nextSymbolData['occurred'];
						$this->currentSymbol = $closeSymbol;
						$this->checkSymbol();
						return;
					}else if($tmpLevel >= $this->currentLevel) {
						unset($this->currentRuleSet);
						$this->currentRuleSet =& $tmpRuleSet;
						unset($this->levelData[$this->currentLevel]);
						$this->currentLevel--;
					}
				}
			}
		}

		foreach($this->currentRuleSet['closeSymbol'] as $closeSymbol => $symbolType) {
			//$this->saveDebug("comparing close symbol {$this->currentSymbol} == {$closeSymbol} ?\n");
			if($this->currentSymbol != $closeSymbol) continue;

			//$this->saveDebug("found Close symbol {$this->levelData[$this->currentLevel]['levelType']} => type -> {$symbolType}\n");

			if($symbolType == 'nextEnd') {
				if(!isset($this->levelData[$this->currentLevel]['nextData'])) {
					$this->levelData[$this->currentLevel]['nextData'] = Array();
				}

				$this->prepareNextEndSymbol();
				$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['occurred'] = $this->currentSymbolNum;
			}

			if($symbolType == 'default') {
				$this->closeRule();
				if(!isset($this->flags['nextData'])) $this->checkSymbol();
				return;
			}
		}

		if(isset($this->currentRuleSet['escapeSymbol']) && !isset($this->levelData[$this->currentLevel]['escaped']) && $this->currentRuleSet['escapeSymbol'] == $this->currentSymbol)
			$this->levelData[$this->currentLevel]['escaped'] = $this->currentSymbolNum;
		else if(isset($this->levelData[$this->currentLevel]['escaped']) && $this->levelData[$this->currentLevel]['escaped'] != $this->currentSymbolNum)
			unset($this->levelData[$this->currentLevel]['escaped']);
	}

	private function prepareNextEndSymbol() {
		if(!isset($this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol])) {
			$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol] = Array();
			$this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['nextRules'] = Array();

			$nextRules =& $this->levelData[$this->currentLevel]['nextData'][$this->currentSymbol]['nextRules'];
			$levelRules =& $this->rulesIndex[$this->levelData[$this->currentLevel-1]['levelType']];

			//$this->saveDebug("Preparing nextRules {$this->levelData[$this->currentLevel-1]['levelType']} -> ".print_r($this->rulesIndex[$this->levelData[$this->currentLevel-1]['levelType']], true));

			foreach($levelRules as $ruleName) {
				if($ruleName == $this->levelData[$this->currentLevel]['levelType']) continue;

				if(!is_array($this->ess[$ruleName]['openSymbol'])) $this->ess[$ruleName]['openSymbol'] = Array($this->ess[$ruleName]['openSymbol'] => 'default');

				foreach($this->ess[$ruleName]['openSymbol'] as $openSymbol => $symbolType) {
					if($this->currentSymbol == $openSymbol)
						$nextRules[] =& $this->ess[$ruleName];
				}
			}
		}
	}

	private function closeRule() {
		$this->levelData[$this->currentLevel]['closeSymbol'] = $this->currentSymbol;
		$this->levelData[$this->currentLevel]['closeSymbolNum'] = $this->currentSymbolNum;
		//$this->saveSymbols();

		//$levelType = $this->levelData[$this->currentLevel]['levelType'];

		$this->saveToParent();

		//unset($this->levelData[$this->currentLevel]['symbolSaved']);
		$this->currentLevel--;
	}

	private function saveToParent() {
		if(isset($this->levelData[$this->currentLevel]['nextData']))
			unset($this->levelData[$this->currentLevel]['nextData']);

		if(!isset($this->levelData[$this->currentLevel-1]['parts']))
			$this->levelData[$this->currentLevel-1]['parts'] = Array();

                $currentItem =& $this->levelData[$this->currentLevel];
                if(isset($currentItem['parts'])) ksort($currentItem['parts'], SORT_NUMERIC);

                $key = $currentItem['openSymbolNum'].'_'.$currentItem['closeSymbolNum'];

		$this->levelData[$this->currentLevel-1]['parts'][$key] =& $this->levelData[$this->currentLevel];

		unset($this->levelData[$this->currentLevel]);
		if(isset($this->ess[$currentItem['levelType']]['levelIndex']) && $this->ess[$currentItem['levelType']]['levelIndex'] === true) {
			$this->removeFromIndex();
		}

		if(isset($this->ess[$currentItem['levelType']]['indexItem']) && $this->ess[$currentItem['levelType']]['indexItem'] === true) {
			$this->indexItem($currentItem);
		}

		//$this->saveDebug(print_r($this->levelData, true));
	}

	private function indexItem(&$itemToIndex) {
		$levelType = $itemToIndex['levelType'];
		if(is_array($this->indexLevels) && isset($itemToIndex) && is_array($itemToIndex)) {
			foreach($this->indexLevels as &$level) {
				if(!isset($level[$levelType])) $level[$levelType] = Array();

				if(!isset($level[$levelType][$itemToIndex['openSymbolNum'].'_'.$itemToIndex['closeSymbolNum']]))
				        $level[$levelType][$itemToIndex['openSymbolNum'].'_'.$itemToIndex['closeSymbolNum']] =& $itemToIndex;
			}
                }
	}

	private function saveSymbols() {
		//$this->saveDebug("Saving symbol {$this->levelData[$this->currentLevel]['levelType']}\n");

		$strStart = $this->levelData[$this->currentLevel]['openSymbolNum'];
		$strEnd = $this->levelData[$this->currentLevel]['closeSymbolNum'];
		//$this->levelData[$this->currentLevel]['value'] = mb_substr($this->expression, $strStart, $strEnd-$strStart+1); // should be changed to zbx_substr

		//$this->saveDebug(print_r($this->levelData, true));
	}

	private function validateFatal() {
		return true;

		/*
		if(count($this->levelData) == 1) return true;

		$this->errors[] = Array('errorCode' => 1,
                                        'errorMsg' => 'Fatal error, '.$this->levelData[count($this->levelData)-1]['levelType'].' ending symbol not found. '.$this->levelData[count($this->levelData)-1]['levelType'].' begins at char '.($this->levelData[count($this->levelData)-1]['openSymbolNum']+1),
					'errStart' => $this->levelData[count($this->levelData)-1]['openSymbolNum'],
					'errEnd' => mb_strlen($this->expression)-1);

		return false;
		*/
	}

	private function validate(&$parent, &$levelData, $index) {
		$skipAfterEmpty = false;
		$values = $this->levelValue($levelData);

		//SDII("Validating {$levelData['levelType']} splited values:\n".var_export($values, true)."\nRules:\n".var_export($this->ess[$levelData['levelType']], true)."\n");
		//$this->saveDebug("Validating {$levelData['levelType']} splited values:\n".var_export($values, true)."\nRules:\n".var_export($this->ess[$levelData['levelType']], true)."\n");

		if(isset($parent['parts']) && is_array($parent['parts'])) {
		        $nextKey = key($parent['parts']);
		        $nextItem =& $parent['parts'][$nextKey];
		        prev($parent['parts']);
		        prev($parent['parts']);
		        $prevKey = key($parent['parts']);
		        $prevItem = $prevKey !== NULL ? $parent['parts'][$prevKey]: false;
		        reset($parent['parts']);
		        $startKey = key($parent['parts']);
		        $startItem =& $parent['parts'][$startKey];
		        end($parent['parts']);
		        $endKey = key($parent['parts']);
		        $endItem = $parent['parts'][$endKey];
                }else{
                        $startKey = $endKey = $nextKey = $prevKey = false;
                        $startItem = $endItem = $nextItem = $prevItem = false;
                }

		if(isset($this->ess[$levelData['levelType']]['allowedSymbolsBefore'])) {
			if(isset($parent['openSymbolNum']) && $startKey == $index && $parent['openSymbolNum'] < $levelData['openSymbolNum'])  {
				$startCut = $parent['openSymbolNum']+1;
				$endCut = $levelData['openSymbolNum'];
			}else if($startKey != $index && $prevItem && isset($prevItem['closeSymbolNum']) && $prevItem['closeSymbolNum'] < $levelData['openSymbolNum']) {
				$startCut = $prevItem['closeSymbolNum']+1;
				$endCut = $levelData['openSymbolNum'];
			}else{
				$startCut = $levelData['openSymbolNum'];
				$endCut = $levelData['openSymbolNum'];
			}

			$beforesymbols = mb_substr($this->expression, $startCut, $endCut-$startCut);
			//$this->saveDebug("before {$levelData['levelType']} value {$value} symbols: ".var_export($beforesymbols, true)."\n");

			if(!preg_match("/^".$this->ess[$levelData['levelType']]['allowedSymbolsBefore']."$/", $beforesymbols)){
				$this->errors[] = Array('errorCode' => 4,
                                                        'errorMsg' => 'Not allowed symbols detected before '.$levelData['levelType'].'. Check expression starting from symbol #'.($startCut+1).' up to symbol #'.$endCut.'. Debug / symbols before: <'.$beforesymbols.'>/ RegExp: '.$this->ess[$levelData['levelType']]['allowedSymbolsBefore'],
							'errStart' => $startCut,
							'errEnd' => $endCut,
							'errValues' => Array($beforesymbols));
			}
		}

		if(isset($this->ess[$levelData['levelType']]['allowedSymbolsAfter'])) {
			if(isset($parent['closeSymbolNum']) && $endKey == $index && $parent['closeSymbolNum'] > $levelData['closeSymbolNum'])  {
				$startCut = $levelData['closeSymbolNum']+1;
				$endCut = $parent['closeSymbolNum'];
			}else if($endKey !== $index && $nextItem && isset($nextItem['openSymbolNum']) && $nextItem['openSymbolNum'] > $levelData['closeSymbolNum']) {
				$startCut = $levelData['closeSymbolNum']+1;
				$endCut = $nextItem['openSymbolNum'];
			}else{
				$startCut = $levelData['closeSymbolNum'];
				$endCut = $levelData['closeSymbolNum'];
			}

			$aftersymbols = mb_substr($this->expression, $startCut, $endCut-$startCut);
			//$this->saveDebug("after {$levelData['levelType']} value {$value} symbols: ".var_export($aftersymbols, true)."\n");

			if(!preg_match("/^".$this->ess[$levelData['levelType']]['allowedSymbolsAfter']."$/", $aftersymbols)){
				$this->errors[] = Array('errorCode' => 5,
                                                        'errorMsg' => 'Not allowed symbols detected after '.$levelData['levelType'].'. Check expression starting from symbol #'.($startCut+1).' up to symbol #'.$endCut.'. Debug / symbols after: <'.$aftersymbols.'>/ RegExp: '.$this->ess[$levelData['levelType']]['allowedSymbolsAfter'],
							'errStart' => $startCut,
							'errEnd' => $endCut,
                                                        'errValues' => Array($aftersymbols));
			}
		}

		if(isset($this->ess[$levelData['levelType']]['isEmpty']) && $this->ess[$levelData['levelType']]['isEmpty'] === true) {
			if(is_array($values) && (count($values) > 1 || (count($values) > 0 && mb_strlen($values[0]['value']) > 0))){
				foreach($values as &$val) {
					$this->errors[] = Array('errorCode' => 3,
								'errorMsg' => $levelData['levelType'].' has unnecessary symbols. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Debug: <'.$val['value'].'>',
								'errStart' => $val['from'],
								'errEnd' => $val['until'],
								'errValues' => Array($val['value']));
				}
			}else{
				$skipAfterEmpty = true;
			}
		}

		if(!$skipAfterEmpty) {
			foreach($values as $val) {
				$notclean = $val['value'];
				if(isset($this->ess[$levelData['levelType']]['ignorSymbols']))
					$val['value'] = preg_replace("/".$this->ess[$levelData['levelType']]['ignorSymbols']."/", '', $val['value']);

				$errRegExps = Array();
				$errValues = Array();

				if(isset($this->ess[$levelData['levelType']]['allowedSymbols'])) {
					$allowedSymbolsValid = false;
					if(!is_array($this->ess[$levelData['levelType']]['allowedSymbols'])) {
						$this->ess[$levelData['levelType']]['allowedSymbols'] = Array($this->ess[$levelData['levelType']]['allowedSymbols']);
					}
					foreach($this->ess[$levelData['levelType']]['allowedSymbols'] as $regexp) {
						if(preg_match("/^".$regexp."$/", $val['value'])) {
							$allowedSymbolsValid = true;
							break;
						}else{
							$allowedSymbolsValid = false;
						}
					}
				}
				$notAllowedSymbolsValid = true;
				if(isset($this->ess[$levelData['levelType']]['notAllowedSymbols'])) {
					if(!is_array($this->ess[$levelData['levelType']]['notAllowedSymbols'])) {
						$this->ess[$levelData['levelType']]['notAllowedSymbols'] = Array($this->ess[$levelData['levelType']]['notAllowedSymbols']);
					}
					foreach($this->ess[$levelData['levelType']]['notAllowedSymbols'] as $regexp) {
						if(preg_match("/".$regexp."/", $val['value'], $matches)) {
							$errRegExps[] = $regexp;
							$errValues[] = $matches[0];
							$notAllowedSymbolsValid = false;
						}
					}
				}

				if((isset($this->ess[$levelData['levelType']]['allowedSymbols']) && !$allowedSymbolsValid) || (isset($this->ess[$levelData['levelType']]['notAllowedSymbols']) && !$notAllowedSymbolsValid)) {
					$this->errors[] = Array('errorCode' => 2,
											'errorMsg' => 'Not allowed symbols or sequence of symbols detected in '.$levelData['levelType'].'. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Debug: '.$notclean.' / '.$val['value'].'/ Error RegExp: '.implode(', ', $errRegExps),
											'errStart' => $val['from'],
											'errEnd' => $val['until'],
											'errValues' => $errValues);
				}

				$errValues = Array();

				$allowedValuesValid = true;
				if(isset($this->ess[$levelData['levelType']]['allowedValues'])) {
					if(!is_array($this->ess[$levelData['levelType']]['allowedValues'])) {
						$this->ess[$levelData['levelType']]['allowedValues'] = Array($this->ess[$levelData['levelType']]['allowedValues']);
					}
					$allowedValuesValid = false;
					if(in_array($val['value'], $this->ess[$levelData['levelType']]['allowedValues'])) {
						$allowedValuesValid = true;
					}

					if(!$allowedValuesValid) $errValues[] = $val['value'];
				}

				$notAllowedValuesValid = true;
				if(isset($this->ess[$levelData['levelType']]['notAllowedValues'])) {
					if(!is_array($this->ess[$levelData['levelType']]['notAllowedValues'])) {
						$this->ess[$levelData['levelType']]['notAllowedValues'] = Array($this->ess[$levelData['levelType']]['notAllowedValues']);
					}
					foreach($this->ess[$levelData['levelType']]['notAllowedValues'] as $notAllowedValue) {
						if($val['value'] == $notAllowedValue) {
							$errValues[] = $notAllowedValue;
							$notAllowedValuesValid = false;
							break;
						}
					}
				}

				if((isset($this->ess[$levelData['levelType']]['allowedValues']) && !$allowedValuesValid) || (isset($this->ess[$levelData['levelType']]['notAllowedValues']) && !$notAllowedValuesValid)) {
					$this->errors[] = Array('errorCode' => 6,
                                                                'errorMsg' => 'Not allowed value detected in '.$levelData['levelType'].'. Check expression starting from symbol #'.($val['from']+1).' up to symbol #'.$val['until'].'. Not allowed values: '.implode(', ', $errValues).'. Debug: '.$notclean.' / '.$val['value'],
								'errStart' => $val['from'],
								'errEnd' => $val['until'],
								'errValues' => $errValues);
				}
			}
		}
		if(isset($this->ess[$levelData['levelType']]['customValidate'])) {
		        if(!is_array($this->ess[$levelData['levelType']]['customValidate']))
		                $this->ess[$levelData['levelType']]['customValidate'] = Array($this->ess[$levelData['levelType']]['customValidate']);

                        foreach($this->ess[$levelData['levelType']]['customValidate'] as &$customFunction) {
                                if(!is_callable($customFunction)) continue;

        			$ret = call_user_func_array($customFunction, Array(&$parent, &$levelData, $index, &$this->expression, &$this->ess[$levelData['levelType']]));

        			if(isset($ret['valid']) && $ret['valid'] === false && isset($ret['errArray']) && is_array($ret['errArray']) && isset($ret['errArray']['errorCode']) && isset($ret['errArray']['errStart']) && isset($ret['errArray']['errEnd'])) {
        				$this->errors[] = $ret['errArray'];
	        		}
                        }
		}

		if(isset($levelData['parts']) && is_array($levelData['parts']))
			foreach($levelData['parts'] as $key => &$level)
				$this->validate($levelData, $level, $key);
	}

	private function levelValue(&$levelData) {
		//$this->saveDebug("Clearing value of {$levelData['levelType']}: ".var_export($levelData['value'], true)."\n");
		//$value = $levelData['value'];
		if (!isset($levelData['levelType'])) {
			$levelData['levelType'] = 'independent';
		}

		$value = '';
		$values = Array();
		$openprepend = (!isset($this->ess[$levelData['levelType']]['inclusive']) || $this->ess[$levelData['levelType']]['inclusive'] !== true) && isset($levelData['openSymbol']) ? mb_strlen($levelData['openSymbol']) : 0;
		$openpostend = (!isset($this->ess[$levelData['levelType']]['inclusive']) || $this->ess[$levelData['levelType']]['inclusive'] !== true) && isset($levelData['closeSymbol'])? mb_strlen($levelData['closeSymbol'])*-1 : 0;
		if(isset($levelData['parts']) && is_array($levelData['parts'])) {
			$prev = NULL;
			end($levelData['parts']);
			$endKey = key($levelData['parts']);
			foreach($levelData['parts'] as $key => &$level) {
				//$this->saveDebug("Test {$level['levelType']} for first: ".var_export(!isset($prev) && ( $level['openSymbolNum'] > $levelData['openSymbolNum']+$openprepend/* || (!isset($levelData['openSymbol']) && $level['openSymbolNum']==$levelData['openSymbolNum'])*/), true)."\n");
				if(!isset($prev) && (
                                        $level['openSymbolNum'] > $levelData['openSymbolNum']+$openprepend/* ||
					(!isset($levelData['openSymbol']) && $level['openSymbolNum']==$levelData['openSymbolNum'])*/
                                )) {
					$val = Array();
					$val['value'] = mb_substr($this->expression, $levelData['openSymbolNum']+$openprepend, $level['openSymbolNum']-($levelData['openSymbolNum']+$openprepend)); // should be changed to zbx_substr
					$val['from'] = $levelData['openSymbolNum']+$openprepend;
					$val['until'] = $level['openSymbolNum'];
					$value .= $val['value'];
					$values[] = $val;
				}

				//$this->saveDebug("Test {$level['levelType']} for middle: ".var_export(isset($prev) && $level['openSymbolNum'] > $prev['closeSymbolNum']+mb_strlen($prev['closeSymbol']), true)."\n");
				if(isset($prev) && $level['openSymbolNum'] > $prev['closeSymbolNum']+mb_strlen($prev['closeSymbol'])) {
					$val = Array();
					$val['value'] = mb_substr($this->expression, $prev['closeSymbolNum']+mb_strlen($prev['closeSymbol']), $level['openSymbolNum']-($prev['closeSymbolNum']+mb_strlen($prev['closeSymbol']))); // should be changed to zbx_substr
					$val['from'] = $prev['closeSymbolNum']+mb_strlen($prev['closeSymbol']);
					$val['until'] = $level['openSymbolNum'];
					$value .= $val['value'];
					$values[] = $val;
				}
				//$this->saveDebug("Test {$level['levelType']} for last: ".var_export($key == count($levelData['parts'])-1 && ($level['closeSymbolNum']+mb_strlen($level['closeSymbol']) < $levelData['closeSymbolNum']+$openpostend/* || (!isset($levelData['closeSymbol']) && $level['closeSymbolNum']+mb_strlen($level['closeSymbol'])==$levelData['closeSymbolNum'])*/), true)."\n");
				if($endKey == $key && ($level['closeSymbolNum']+mb_strlen($level['closeSymbol']) < $levelData['closeSymbolNum']+$openpostend+1/* ||
                                        (!isset($levelData['closeSymbol']) && $level['closeSymbolNum']+mb_strlen($level['closeSymbol'])==$levelData['closeSymbolNum'])*/
                                )) {
					$val = Array();
					$val['value'] = mb_substr($this->expression, $level['closeSymbolNum']+mb_strlen($level['closeSymbol']), $levelData['closeSymbolNum']+$openpostend-($level['closeSymbolNum']+mb_strlen($level['closeSymbol']))+1); // should be changed to zbx_substr
					$val['from'] = $level['closeSymbolNum']+mb_strlen($level['closeSymbol']);
					$val['until'] = $levelData['closeSymbolNum']+$openpostend;
					$value .= $val['value'];
					$values[] = $val;
				}
				$prev =& $level;
			}
		}else {
			$val = Array();
			$val['value'] = mb_substr($this->expression, $levelData['openSymbolNum']+$openprepend, $levelData['closeSymbolNum']+$openpostend-($levelData['openSymbolNum']+$openprepend)+1); // should be changed to zbx_substr
			$val['from'] = $levelData['openSymbolNum']+$openprepend;
			$val['until'] = $levelData['closeSymbolNum']+$openpostend;
			$value .= $val['value'];
			$values[] = $val;
		}

		//$this->saveDebug("Clear value of {$levelData['levelType']}:\n".var_export($values, true)."\n");

		return $values;
	}

	public function getElements($index) {
		return !isset($this->indexes[$index]) ? Array() : $this->indexes[$index];
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

?>
