<?php
require 'vendor/autoload.php';

use League\Flysystem\Sftp\SftpAdapter;

$params = [
    'host'     => 'change me',
    'port'     => '22',
    'username' => 'change me',
    'password' => 'change me',
];

$count = 1;
$retry_num = is_null($argv[1]) ? 1 : $argv[1];
while($count <= $retry_num) {
    echo ("=========Test count: {$count}==========". PHP_EOL);
    try {
        $sftp_adapter = new SftpAdapter($params);
        $sftp_adapter->connect();

        if ($sftp_adapter->isConnected()) {
            echo ('Success Connection.'. PHP_EOL);
        }
    } catch (Exception $e) {
        echo ($e->getMessage() . PHP_EOL);
    } finally {
        $sftp_adapter->disconnect();
        if (!$sftp_adapter->isConnected()) {
            echo ('Connection disconnected.'. PHP_EOL);
        } else {
            echo ('Still connected.'. PHP_EOL);
        }
    }
$count++;
sleep(2);
}
