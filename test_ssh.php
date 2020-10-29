#!/usr/bin/env php
<?php
require 'SSH.class.php';


$params = [
    'host'     => 'change me',
    'username' => 'change me',
    'password' => 'change me',
];
$count = 1;
$retry_num = is_null($argv[1]) ? 1 : $argv[1];
while($count <= $retry_num) {
    echo ("=========Test count: {$count}==========". PHP_EOL);
    try {
        $sftp = new SSH($params);
        $sftp->login();

        if ($sftp->isConnected()) {
            echo ('Success Connection.'. PHP_EOL);
        }
    } catch (Exception $e) {
        echo ($e->getMessage() . PHP_EOL);
    } finally {
        if (!$sftp->isConnected()) {
            echo ('Connection disconnected.'. PHP_EOL);
        } else {
            echo ('Still connected.'. PHP_EOL);
        }
    }
$count++;
sleep(2);
}
