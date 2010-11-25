<?php

require('include/func.inc.php');
require('include/items.inc.php');
require('include/defines.inc.php');

if (isset($_GET['key']) && $_GET['key']!='') {
	list($is_valid, $message) = check_item_key($_GET['key']);
	echo "<b>".$_GET['key']."</b><br />".$message."<br /><br />";
}


?>
<form action="<?php echo $_SERVER['REQUEST_URI']?>" method="get">
	<textarea name="key" style="width:100%" cols="3"></textarea><br />
	<input type="submit" value="check">
</form>