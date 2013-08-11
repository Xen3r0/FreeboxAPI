<?php
include("class.FreeboxAPI.php");

// Contruct FreeboxAPI object
$freebox = new FreeboxAPI();

// Authorize device
while(!$freebox->access_granted()) {
	$freebox->authorize();
}

echo 'access granted';
?>