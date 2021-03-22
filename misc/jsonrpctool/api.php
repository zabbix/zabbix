<?php
/* This tool is not officially supported by Zabbix SIA. */
/* If you would like to improve something, patches welcome. */
?>
<?php
function do_post_request($url, $data){

	$header = "Content-type: application/json-rpc\r\n";
	$header .= "Content-Length: ".strlen($data)."\r\n";
	$header .= "\r\n";

	$params = [
		'http' => [
			'method' => 'post',
			'content' => $data,
			'header' => $header
		]
	];

	$ctx = stream_context_create($params);

	$fp = @fopen($url, 'rb', false, $ctx);
	if(!$fp){
		throw new Exception("Problem with $url, $php_errormsg");
	}

	$response = @stream_get_contents($fp);

	fclose($fp);

	if($response === false) {
		throw new Exception("Problem reading data from $url, $php_errormsg");
	}

	return $response;
}

?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>JSONRPC Zabbix API</title>
</head>
	<!-- Dependencies -->
	<script src="http://yui.yahooapis.com/2.8.2r1/build/yahoo/yahoo-min.js"></script>
	<!-- Source file -->
	<script src="http://yui.yahooapis.com/2.8.2r1/build/json/json-min.js"></script>

	<script src="http://yandex.st/highlightjs/5.16/highlight.min.js"></script>
	<link rel="stylesheet" href="http://yandex.st/highlightjs/5.16/styles/idea.min.css">

<?php
	$_REQUEST['path'] = isset($_REQUEST['path']) ? $_REQUEST['path'] : 'trunk';
	$user = isset($_REQUEST['user']) ? $_REQUEST['user'] : 'Admin';
	$pswd = isset($_REQUEST['pswd']) ? $_REQUEST['pswd'] : 'zabbix';
	$url = 'http://'.$_REQUEST['path'].'/api_jsonrpc.php';
?>
<body>
<form method="post">
	<label>Path: <input type="text" name="path" value="<?php print($_REQUEST['path']);?>"/></label><br />
	<label>User: <input type="text" name="user" value="<?php print($user);?>"/></label><br />
	<label>Pass: <input type="password" name="pswd" value="<?php print($pswd); ?>"/></label><br />
	<label>Method: <input type="text" name="apimethod" size="40" value="<?php print(isset($_REQUEST['apimethod']) ? $_REQUEST['apimethod'] : '');?>"/></label><br />
	Params: <textarea name="apiparams" cols="100" rows="20"><?php print(isset($_REQUEST['apiparams']) ? $_REQUEST['apiparams'] : '');?></textarea><br />
	<input type="submit" value="OK" name="apicall" />
	<br />
</form>
<?php

if(isset($_REQUEST['apicall'])){
	$data = [
		'jsonrpc' => '2.0',
		'method' => 'user.login',
		'params' => ['user'=>$user, 'password'=>$pswd],
		'id'=> 1
	];

	$data = json_encode($data);

	$response = do_post_request($url, $data);

	$json_decoded = json_decode($response, true);
	$auth = $json_decoded['result'];
?>
<span style="font-weight: bolder;">AUTH</span>
<div style="color: darkgreen; border: 2px solid darkblue;">
	<div>
		<span style="color: blue;">request:</span>
		<pre><code class="javascript"><?php print($data); ?></code></pre>
	</div>
	<div>
		<span style="color: blue;">response:</span>
		<pre><code class="javascript"><?php print($response); ?></code></pre>
	</div>
</div>

<?php
	$data = [
		'jsonrpc' => '2.0',
		'method' => $_REQUEST['apimethod'],
		'params' => json_decode($_REQUEST['apiparams'], true),
		'auth' => $auth,
		'id'=> 2
	];
	$data = json_encode($data);
	$response = do_post_request($url, $data);
?>

<span style="font-weight: bolder;">API call</span>
<div style="color: darkgreen; border: 2px solid darkblue;">
	<div>
		<span style="color: blue;">request:</span>
		<pre><code id="data" class="javascript"><?php print($data); ?></code></pre>
	</div>
	<div>
		<span style="color: blue;">response:</span>
		<pre><code id="resp" class="javascript"><?php print($response); ?></code></pre>
	</div>
</div>

<script type="text/javascript">
var j = YAHOO.lang.JSON.parse(document.getElementById("resp").innerHTML);
document.getElementById("resp").innerHTML = YAHOO.lang.JSON.stringify(j,function(key, value){return value;}, 4);
var j = YAHOO.lang.JSON.parse(document.getElementById("data").innerHTML);
document.getElementById("data").innerHTML = YAHOO.lang.JSON.stringify(j,function(key, value){return value;}, 4);

hljs.initHighlightingOnLoad();
</script>
<?php
}
?>

</body>
</html>
