<?php
if ($_SERVER['argv'][1] || $_GET['unit']) {

	$fileUnit = $_SERVER['argv'][1] ? $_SERVER['argv'][1] : $_GET['unit'];
	$fileHudson = $_SERVER['argv'][2] ? $_SERVER['argv'][2] : $_GET['hudson'];

	if (file_exists($fileUnit)) {
		new PHPUnit2Hudson($fileUnit, $fileHudson);
	} else {
		die('phpunit xml file not exists '. $file);
		}

	} else {
	die("determine file. use command: php -f phpunit2hudson.php -- phpunit.xml hudson.xml");
}

class PHPUnit2Hudson {

	private $xml;
	private $cases = array();

	private $countAssertions = 0;
	private $countFailures = 0;
	private $countErrors = 0;
	private $countTime = 0;

	function  __construct($fileUnit, $fileHudson) {
		$oldLevel = error_reporting(0);
		$this->xml = simplexml_load_file($fileUnit);
		error_reporting($oldLevel);

		if (!$this->xml->testsuite)
			die('invalid phpunit xml file');

		foreach($this->xml->testsuite->attributes() as $key => $value) {
			if ($key == 'failures') $this->countFailures = intval($value);
			if ($key == 'errors') $this->countErrors = intval($value);
			if ($key == 'time') $this->countTime = floatval($value);
			if ($key == 'assertions') $this->countAssertions = intval($value);
		}

		$this->getCases($this->xml);

		file_put_contents($fileHudson, $this->composeHudson());
}

function getCases(SimpleXMLElement $node) {
	if (isset($node->testcase))
	foreach ($node->testcase as $case) {
		$this->cases[] = $case;
	} elseif (isset($node->testsuite))
		foreach ($node->testsuite as $suite) {
		$this->getCases($suite);
	}
}

function composeHudson() {
	$xmlHudson = "<testsuites>\n";
	$xmlHudson .= '<testsuite name="Hudson_Suite" file="All.php" tests="'.sizeof($this->cases);
	$xmlHudson .='" assertions="'.$this->countAssertions.'" ';
	$xmlHudson .='failures="'.$this->countFailures.'" ';
	$xmlHudson .='errors="'.$this->countErrors.'" ';
	$xmlHudson .='time="'.$this->countTime.'">'."\n";
	foreach ($this->cases as $case) {
		$xmlHudson .= $case->asXML()."\n";
	}
	$xmlHudson .= "</testsuite></testsuites>";

	return $xmlHudson;
}

}
