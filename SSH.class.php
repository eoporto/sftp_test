<?php
class SSH
{
    /**
	 * @var resource
	 **/
    private $_connection;

    /**
	 * Possible directives for ssh2
	 * @var Array
	 **/
    private $_directives = array(
        'host'        => null,
        'username'    => null,
        'password'    => null,
        'pubkey'    => null,
        'privkey'    => null,
        'passphrase'    => null,
        'port'        => 22,
        'debug'        => false
    );

    /**
	 * @param array $overrides for the directives
	 **/
    public function __construct(array $overrides)
    {
        foreach ($this->_directives as $k => $v) {
            if (isset($overrides[$k])) {
                $this->_directives[$k] = $overrides[$k];
            }
        }
    }

    private function report($message)
    {
        if ($this->_directives['debug'] === TRUE) {
            echo $message, PHP_EOL;
        }
    }

    private function reportConnection($directive, $result)
    {
        $this->report("Checking $directive if enabled.");
        if (isset($this->_directives[$directive])) {
            $this->report("Yes. Connecting with $directive...");
            if ($result === TRUE) {
                $this->report("Success!");

                return true;
            } else {
                $this->report("Failed.");

                return false;
            }
        }

        $this->report("Disabled. Skipping.");

        return false;
    }

    /**
	 * Connect using ssh2_auth_none
	 * @return bool
	 **/
    public function connectWithUsername()
    {
        return $this->reportConnection(
            'username',
            (isset($this->_directives['username'])
                ? ssh2_auth_none($this->_connection, $this->_directives['username'])
                : false
            )
        );
    }

    /**
	 * Connect using ssh2_auth_password
	 * @return bool
	 **/
    public function connectWithPassword()
    {
        return $this->reportConnection(
            'password',
            (isset($this->_directives['password'])
                ? ssh2_auth_password(
                    $this->_connection,
                    $this->_directives['username'],
                    $this->_directives['password'])
                : false
            )
        );
    }

    /**
	 * Connect using ssh2_auth_pubkey_file
	 * @return bool
	 **/
    public function connectWithPubKeyFile()
    {
        return $this->reportConnection(
            'pubkey',
            (isset($this->_directives['pubkey'])
                ? ssh2_auth_pubkey_file(
                    $this->_connection,
                    $this->_directives['username'],
                    $this->_directives['pubkey'],
                    $this->_directives['privkey'],
                    $this->_directives['passphrase'])
                : false
            )
        );
    }

    public static function disconnected($reason, $message, $language)
    {
        printf("Server disconnected with reason code %d and message: %s\n", $reason, $message);
    }

    /**
	 * @return bool
	 * @throws Exception
	 **/
    public function login()
    {
        $this->_connection = ssh2_connect(
            $this->_directives['host'],
            $this->_directives['port'],
            array(),
            array('disconnect' => array(__CLASS__, 'disconnected')));
        if ($this->_connection === FALSE) {
            throw new Exception("SSH: Can't connect to {$this->_directives['username']}@{$this->_directives['host']}");
        }

        if ($this->connectWithUsername()) return true;
        if ($this->connectWithPassword()) return true;
        if ($this->connectWithPubKeyFile()) return true;

        throw new Exception("SSH: Can't connect to {$this->_directives['username']}@{$this->_directives['host']}");
    }

    public function isConnected()
    {
        return is_resource($this->_connection);
    }

    /**
     * Download file copy from sftp
     *
     * @param  String $from - remote source file name
     * @param  String $to   - local file name destination
     * @return void
     */
    public function receive($from, $to)
    {
        if (!$this->isConnected()) {
            $this->login();
        }

        $sftp = ssh2_sftp($this->_connection);

        $user = $this->_directives['username'];

        $remote_file = new SplFileObject("ssh2.sftp://$sftp/$user/$from", 'r');
        $local_file  = new SplFileObject($to, 'w');

        while (!$remote_file->eof()) {
            $local_file->fwrite($remote_file->fgets());
        }

        touch($local_file->getPathname(), $remote_file->getMTime(), $remote_file->getATime());
    }

    /**
     * @param $filename
     * @return bool
     * @throws Exception
     */
    public function fileExists($filename)
    {
        if (!$this->isConnected()) {
            $this->login();
        }

        $sftp = ssh2_sftp($this->_connection);

        $user = $this->_directives['username'];

        try {
            new SplFileObject("ssh2.sftp://$sftp/$user/$filename", 'r');
            return true;
        } catch (RuntimeException $err) {
            return false;
        }
    }

    /**
	 * Copies a local file to a remote destination
	 *
	 * @param String $source_file - local file to copy
	 * @param String $remote_file - destination file
	 * @return void
	 **/
    public function send($source_file, $remote_file)
    {
        if (!$this->isConnected()) {
            $this->login();
        }

            $sftp = ssh2_sftp($this->_connection);
            $user = $this->_directives['username'];
            $remote_stream = @fopen("ssh2.sftp://" . intval($sftp) . "/{$user}/{$remote_file}", "w");

            try {
                if (!$remote_stream) {
                    throw new Exception("Unable to open remote file: {$remote_file} [User {$user}]");
                }

                $data_to_send = @file_get_contents($source_file);

                if (false === $data_to_send) {
                    throw new Exception("Unable to open local file: {$source_file}");
                }

                if (@fwrite($remote_stream, $data_to_send) === false) {
                    throw new Exception("Unable to send data to file: {$remote_file}");
                }

                @fclose($remote_stream);

            } catch (Exception $e) {
                error_log('Exception: ' . $e->getMessage());
            }
    }

    /**
	 * Execute remote action
	 * @param string $action - the command to execute
	 * @return SSH_Stream
	 **/
    public function exec($action)
    {
        $this->report("Executing $action");

        $res = ssh2_exec($this->_connection, $action);
        if (!is_resource($res)) {
            throw new Exception("Failed to execute command!");
        }

        return new SSH_Stream($res);
    }

    /**
	 * Creates a folder to a remote destination
	 *
	 * @param String $folder_name - the remote folder name
	 * @param String $folder_dir - the remote folder directory
	 * @return void
	 **/
    public function createRemoteFolder($folder_name, $folder_dir = null)
    {
        if (!$this->isConnected()) {
            $this->login();
        }

        $sftp = ssh2_sftp($this->_connection);
        $user = $this->_directives['username'];

        $stream = file_exists("ssh2.sftp://" . intval($sftp) . "/{$folder_dir}");

        if (!$stream && $folder_dir) {
            throw new Exception("Unable to create remote folder {$folder_name}. {$folder_dir} does not exists.");
        }

        $folder_dir = $folder_dir ? trim($folder_dir, '/').'/' : null;
        mkdir("ssh2.sftp://" . intval($sftp) . "/{$user}/{$folder_dir}{$folder_name}");
    }

}
