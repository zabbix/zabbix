<?php
include_once('config.php');

// Attempts to create a new entry in the alert queue
if ($json['method'] == 'alertqueue.create') {
  
  // defaults for now
  $default_value_type = 3;
  $default_type = 3;
  $default_history = 3;
  $default_trends = 365;
  $default_status = 0;
  $table_name = "alert_queue";
  
  $data = isset($json['params']['data']) ? $json['params']['data'] : "";
  $host = isset($json['params']['host']) ? $json['params']['host'] : "";
  $item_key = isset($json['params']['item_key']) ? $json['params']['item_key'] : "";
  $item = isset($json['params']['item']) ? $json['params']['item'] : "";  
  $trigger = isset($json['params']['trigger']) ? $json['params']['trigger'] : "";
  
  //  ---- start validation -----
  if ($data == "") {
    dbug("Error: Missing data value");
    sendErrorResponse('235', "Invalid params", "Missing data value");
  }
  if ($host == "") {
    dbug("Error: Missing host value");
    sendErrorResponse('235', "Invalid params", "Missing host value");
  }
  if ($item_key == "") {
    dbug("Error: Missing item key value");
    sendErrorResponse('235', "Invalid params", "Missing item key value");
  }
  if ($item == "") {
    dbug("Error: Missing item value");
    sendErrorResponse('235', "Invalid params", "Missing item value");
  }
  if ($trigger == "") {
    dbug("Error: Missing trigger value");
    sendErrorResponse('235', "Invalid params", "Missing trigger value");
  }
  
  $item = json_decode($item, true);
  if ($item == null) {
    dbug("Error: Unable to parse item JSON");
    sendErrorResponse('235', "Invalid params", "Unable to parse item JSON");
  }
  if (!isset($item["name"]) || strlen($item["name"]) < 1) {
    dbug("Error: Missing item name");
    sendErrorResponse('235', "Invalid params", "Missing item name");
  }
  if (!isset($item["key_"]) || strlen($item["key_"]) < 1) {
    // not necessarily an error, we can use the key provided separately
    $item["key_"] = $item_key;
  }
  if (!isset($item["type"]) || strlen($item["type"]) < 1) {
    dbug("Error: Missing item type, setting to default");
    $item["type"] = $default_type;
  }
  if (!isset($item["value_type"]) || strlen($item["value_type"]) < 1) {
    dbug("Error: Missing item value type, setting to default");
    $item["value_type"] = $default_value_type;
  }
  if (!isset($item["data_type"]) || strlen($item["data_type"]) < 1) {
    dbug("Error: Missing item data type, setting to default");
    $item["data_type"] = $default_data_type;
  }
  if (!isset($item["history"]) || strlen($item["history"]) < 1) {
    dbug("Error: Missing item history value, setting to default");
    $item["history"] = $default_history;
  } else if ($item["history"] > $default_history) {
    dbug("Error: Given item history value [".$item["history"]."] was larger than the default, using default");
    $item["history"] = $default_history;
  }
  if (!isset($item["status"]) || strlen($item["status"]) < 1) {
    dbug("Error: Missing item status value, setting to default");
    $item["status"] = $default_type;
  } else if ($item["status"] < 0) {
    dbug("Error: Invalid item status [".$item["status"]."]");
    sendErrorResponse('235', "Invalid params", "Invalid item status value");
  }
  if (!isset($item["trends"]) || strlen($item["trends"]) < 1) {
    dbug("Error: Missing item trends value, setting to default");
    $item["trends"] = $default_trends;
  } else if ($item["trends"] > $default_trends) {
    dbug("Error: Given item trends value [".$item["trends"]."] was larger than the default, using default");
    $item["trends"] = $default_trends;
  }
  
  $trigger = json_decode($trigger, true);
  if ($trigger == null) {
    dbug("Error: Unable to parse trigger JSON");
    sendErrorResponse('235', "Invalid params", "Unable to parse trigger JSON");
  }
  if (!isset($trigger["description"]) || strlen($trigger["description"]) < 1) {
    dbug("Error: Missing trigger description");
    sendErrorResponse('235', "Invalid params", "Missing trigger description");
  }
  if (!isset($trigger["expression"]) || strlen($trigger["expression"]) < 1) {
    dbug("Error: Missing trigger expression");
    sendErrorResponse('235', "Invalid params", "Missing trigger expression");
  }
  if (!isset($trigger["priority"]) || strlen($trigger["priority"]) < 1) {
    dbug("Error: Missing trigger priority");
    sendErrorResponse('235', "Invalid params", "Missing trigger priority");
  } else if ($trigger["priority"] > 5 || $trigger["priority"] < 0) {
    dbug("Error: Invalid trigger priority [".$trigger["priority"]."]");
    sendErrorResponse('235', "Invalid params", "Invalid trigger priority value");
  }
  //  ---- end validation -----
  
  $db_data = addslashes($data);
  $db_item_key = addslashes($item_key);
  $db_host = addslashes($host);
  
  $query = "SELECT * FROM $table_name ";
  $query .= "WHERE data = '$db_data' ";
  $query .= "AND host = '$db_host' ";
  $query .= "AND item_key = '$db_item_key' ";
  $query .= "AND completed = 0;";
  
  $results = $ldb->get_row($query);
  if (isset($results) && $results->id > 0) {
    $query = "UPDATE $table_name SET count = count + 1 WHERE id = ".$results->id;
    $ldb->query($query);
    Copy_DB_Results($resp, $results);
    $resp['result'][0]["count"]++;
    sendResponse($resp);
  }
  
  $query = "INSERT INTO $table_name (created, data, host, item, item_key, `trigger`, log) VALUES (";
  $query .= "UNIX_TIMESTAMP(), ";
  $query .= "'".addslashes($data)."', ";
  $query .= "'".addslashes($host)."', ";
  $query .= "'".addslashes(json_encode($item))."', ";
  $query .= "'".addslashes($item_key)."', ";
  $query .= "'".addslashes(json_encode($trigger))."', ";
  $query .= "'');";
  if (!$ldb->query($query)) {
    dbug("Error: DB error: ".$ldb->last_error);
    sendErrorResponse('500', "DB Error", $ldb->last_error);
  }
  $query = "SELECT * FROM $table_name WHERE id = ".$ldb->insert_id;
  $results = $ldb->get_row($query);
  if (isset($results) && $results->id > 0) {
    Copy_DB_Results($resp, $results);
    sendResponse($resp);
  }
  dbug("Error: DB error: ".$ldb->last_error);
  sendErrorResponse('500', "DB Error", $ldb->last_error);
}

if ($json['method'] == 'alertqueue.size') {
  // incomplete items in the alert queue
  $query = 'SELECT count(id) AS count FROM alert_queue WHERE completed=0 and item_id=0;';
  $results = $ldb->get_row($query);

  if (property_exists($results, count)) {
    print $results->count;
  }
  else {
    sendErrorResponse('500', "DB Error", "Query returned no results");
  }

}

if ($json['method'] == 'alertqueue.old') {
  // incomplete items over 1 day old
  $query = 'SELECT count(id) AS count  FROM alert_queue WHERE completed=0 and (UNIX_TIMESTAMP() - item_created) > 86400;';
  $results = $ldb->get_row($query);

  if (property_exists($results, count)) {
    print $results->count;
  }
  else {
    sendErrorResponse('500', "DB Error", "Query returned no results");
  }

}

function Copy_DB_Results(&$resp, $results) {
  $resp['result'][0]["id"] = $results->id;
  $resp['result'][0]["created"] = $results->created;
  $resp['result'][0]["data"] = stripslashes($results->data);
  $resp['result'][0]["host"] = stripslashes($results->host);
  $resp['result'][0]["item"] = stripslashes($results->item);
  $resp['result'][0]["item_key"] = stripslashes($results->item_key);
  $resp['result'][0]["item_id"] = $results->item_id;
  $resp['result'][0]["trigger"] = stripslashes($results->trigger);
  $resp['result'][0]["trigger_id"] = $results->trigger_id;
  $resp['result'][0]["item_created"] = $results->item_created;
  $resp['result'][0]["trigger_created"] = $results->trigger_created;
  $resp['result'][0]["sender_success"] = $results->sender_success;
  $resp['result'][0]["completed"] = $results->completed;
  $resp['result'][0]["result"] = $results->result;
  $resp['result'][0]["log"] = stripslashes($results->log);
  $resp['result'][0]["count"] = $results->count;
}

?>
