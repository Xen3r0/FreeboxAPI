<?php
include('FreeboxApi.php');

use Xen3r0\Freebox\Api\FreeboxApi;

// Contruct FreeboxAPI object
$freebox = new FreeboxApi();

// Authorize device
while (!$freebox->accessGranted()) {
    $freebox->authorize();
}

echo 'access granted';
