<?php

class cItemKey{

	private $key;
	private $maxLength = 255;

	// variables required for parsing
	private $currentByte = 0;
	private $nestLevel;
	private $state;
	private $currParamNo;
	// key info (is available after parsing)
	private $keyLength;
	private $keyByteCnt;
	private $isValid = true;        // let's hope for the best :)
	private $error = '';            // if key is invalid
	private $parameters = array();  // array of key parameters
	private $keyId = '';            // main part of the key (for 'key[1, 2, 3]' key id would be 'key')

	/**
	 * Parse key and determine if it is valid
	 * @param string $key
	 */
	public function __construct($key){

		$this->key = $key;

		// get key length
		$this->keyLength = zbx_strlen($this->key);
		$this->keyByteCnt = strlen($this->key);

		// checking if key is not too large or empty
		$this->checkLength();

		if($this->isValid){
			// getting key id out of the key
			$this->parseKeyId();
			// and parameters ($currentByte now points to start of parameters)
			$this->parseKeyParameters();
		}
	}


	/**
	 * Check if key is empty or too large
	 * @return void
	 */
	private function checkLength(){
		// empty string
		if($this->keyLength == 0){
			$this->isValid = false;
			$this->error = _("Key cannot be empty.");

		}
		// key is larger then allowed?
		else if($this->keyLength > $this->maxLength){
			$this->isValid = false;
			$this->error = sprintf(_("Key is too large: maximum %d characters."), $this->maxLength);
		}
	}


	/**
	 * Get the key id and put $currentByte after it
	 * @return void
	 */
	private function parseKeyId(){
		// checking every byte, one by one, until first 'not key_id' char is reached
		for($this->currentByte = 0; $this->currentByte < $this->keyByteCnt; $this->currentByte++) {
			if(!isKeyIdChar($this->key[$this->currentByte])) {
				break; // $this->currentByte now points to a first 'not a key name' char
			}
			$this->keyId .= $this->key[$this->currentByte];
		}
	}


	/**
	 * Parse key parameters and put them into $this->parameters array
	 * @return void
	 */
	private function parseKeyParameters(){

		// no function specified?
		if ($this->currentByte == $this->keyByteCnt) {
			// ok then, nothing to parse
		}
		// function with parameter, e.g. system.run[...]
		else if($this->key[$this->currentByte] == '[') {

			$this->state = 0;   // 0 - initial
								// 1 - inside quoted param
								// 2 - inside unquoted param
			$this->nestLevel = 0;
			$this->currParamNo = 0;

			// for every byte, starting after '['
			for($this->currentByte++; $this->currentByte < $this->keyByteCnt; $this->currentByte++) {
				switch($this->state){
					// initial state
					case 0:
						if($this->key[$this->currentByte] == ',') {
							$this->emptyParam();
							if($this->nestLevel == 0){
								$this->currParamNo++;
								// empty parameter
								$this->emptyParam();
							}
							else{
								$this->addCurrentByteToParam();
							}
						}
						// Zapcat: '][' is treated as ','
						else if($this->key[$this->currentByte] == ']' && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == '[' && $this->nestLevel == 0) {
							if($this->nestLevel == 0){
								$this->currParamNo++;
							}
							else{
								$this->addCurrentByteToParam();
							}
							$this->currentByte++;
						}
						// entering quotes
						else if($this->key[$this->currentByte] == '"') {
							$this->state = 1;
							// in key[["a"]] param is "a"
							if($this->nestLevel != 0){
								$this->addCurrentByteToParam();
							}
							else{
								$this->emptyParam();
							}
						}
						// next nesting level
						else if($this->key[$this->currentByte] == '[') {
							if($this->nestLevel > 0){
								$this->addCurrentByteToParam();
							}
							$this->nestLevel++;
						}
						// one of the nested sets ended
						else if($this->key[$this->currentByte] == ']' && $this->nestLevel != 0) {

							$this->nestLevel--;

							if($this->nestLevel > 0){
								$this->addCurrentByteToParam();
							}

							// skipping spaces
							while(isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ' ') {
								$this->currentByte++;
								if($this->nestLevel > 0){
									$this->addCurrentByteToParam();
								}
							}
							// all nestings are closed correctly
							if ($this->nestLevel == 0 && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ']' && !isset($this->key[$this->currentByte+2])) {
								return;
							}

							if((!isset($this->key[$this->currentByte+1]) || $this->key[$this->currentByte+1] != ',')
								&& !($this->nestLevel !=0 && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ']')) {
								$this->isValid = false;
								$this->error = sprintf(_('incorrect syntax near \'%1$s\''), $this->key[$this->currentByte]);
								return;
							}
						}
						// looks like we have reaches final ']'
						else if($this->key[$this->currentByte] == ']' && $this->nestLevel == 0) {
							if (isset($this->key[$this->currentByte+1])){
								// nothing else is allowed after final ']'
								$this->isValid = false;
								$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte+1]);
								return;
							}
							else {
								// with 'key[a,]' the last param considered empty
								if($this->key[$this->currentByte-1] == ','){
									$result['parameters'][$this->currParamNo] = '';
								}
								// no symbols after the final ']' - everything is ok
								$this->emptyParam();
								return;
							}
						}
						else if($this->key[$this->currentByte] != ' ') {
							$this->state = 2;
							// this is a first symbol of unquoted param
							$this->addCurrentByteToParam();
						}
						else if($this->nestLevel > 0){
							$this->addCurrentByteToParam();
						}

					break;

					// quoted
					case 1:
						// ending quote is reached
						if($this->key[$this->currentByte] == '"' && $this->key[$this->currentByte-1] != '\\'){
							// skipping spaces
							while(isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ' ') {
								$this->currentByte++;
								if($this->nestLevel > 0){
									$this->addCurrentByteToParam();
								}
							}

							// Zapcat
							if($this->nestLevel == 0 && isset($this->key[$this->currentByte+1]) && isset($this->key[$this->currentByte+2]) && $this->key[$this->currentByte+1] == ']' && $this->key[$this->currentByte+2] == '['){
								$this->state = 0;
								break;
							}

							if ($this->nestLevel == 0 && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ']' && !isset($this->key[$this->currentByte+2])){
								return;
							}
							else if($this->nestLevel == 0 && $this->key[$this->currentByte+1] == ']' && isset($this->key[$this->currentByte+2])){
								// nothing else is allowed after final ']'
								$this->isValid = false;
								$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte+1]);
								return;
							}

							if ((!isset($this->key[$this->currentByte+1]) || $this->key[$this->currentByte+1] != ',') //if next symbol is not ','
								&& !($this->nestLevel != 0 && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == ']'))
							{
								// nothing else is allowed after final ']'
								$this->isValid = false;
								$this->error = sprintf(_('incorrect syntax near \'%1$s\' at position %2$d'), $this->key[$this->currentByte], $this->currentByte);
								return;
							}

							// in key[["a"]] param is "a"
							if($this->nestLevel != 0){
								$this->addCurrentByteToParam();
							}

							$this->state = 0;
						}
						//escaped quote (\")
						else if($this->key[$this->currentByte] == '\\' && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] == '"') {
							// $this->currentByte++;
						}
						else{
							$this->addCurrentByteToParam();
						}
					break;

					// unquoted
					case 2:
						// Zapcat
						if($this->nestLevel == 0 && $this->key[$this->currentByte] == ']' && isset($this->key[$this->currentByte+1]) && $this->key[$this->currentByte+1] =='[' ){
							$this->currentByte--;
							$this->state = 0;
						}
						else if($this->key[$this->currentByte] == ',' || ($this->key[$this->currentByte] == ']' && $this->nestLevel != 0)) {
							$this->currentByte--;
							$this->state = 0;
						}
						else if($this->key[$this->currentByte] == ']' && $this->nestLevel == 0) {
							if (isset($this->key[$this->currentByte+1])){
								// nothing else is allowed after final ']'
								$this->isValid = false;
								$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte+1]);
								return;
							}
							else {
								return;
							}
						}
						else{
							$this->addCurrentByteToParam();
						}
					break;
				}
			}
			$this->isValid = false;
			$this->error = _('Invalid item key format.');
		}
		else {
			$this->isValid = false;
			$this->error = _('Invalid item key format.');
		}
	}

	
	/**
	 * Adds a current byte to currently parsed parameter
	 * If parameter does not exist, it is created
	 * @return void
	 */
	private function addCurrentByteToParam(){
		if(!isset($this->parameters[$this->currParamNo])){
			$this->parameters[$this->currParamNo] = $this->key[$this->currentByte];
		}
		else{
			$this->parameters[$this->currParamNo] .= $this->key[$this->currentByte];
		}
	}


	private function emptyParam(){
		if (!isset($this->parameters[$this->currParamNo])){
			$this->parameters[$this->currParamNo] = '';
		}
	}


	/**
	 * Is key valid?
	 * @return bool
	 */
	public function isValid(){
		return $this->isValid;
	}

	/**
	 * Get the error if key is invalid
	 * @return string
	 */
	public function getError(){
		return $this->error;
	}

	public function getParameters(){
		return $this->parameters;
	}

	public function getKeyId(){
		return $this->keyId;
	}
}

?>
