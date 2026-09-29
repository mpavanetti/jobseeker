<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * SSH keys for Git: personal accounts (Profile) and project deploy keys
 * (Project Details) are generated and inspected the same way. Private keys
 * only ever touch a 0700 temporary directory that is removed straight away.
 */
class GitSshKeys
{
    /** A new unencrypted ed25519 private key, or '' when ssh-keygen is unavailable. */
    public function generate($comment)
    {
        $directory = $this->temporaryDirectory('keygen');
        if ($directory === FALSE) {
            return '';
        }
        $path = $directory.DIRECTORY_SEPARATOR.'key';
        $comment = preg_replace('/[^A-Za-z0-9@._:-]/', '', (string) $comment);
        list($status) = $this->run(array('ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', $comment, '-f', $path));
        $privateKey = $status === 0 && is_readable($path) ? (string) file_get_contents($path) : '';
        @unlink($path);
        @unlink($path.'.pub');
        @rmdir($directory);
        return $privateKey;
    }

    /**
     * The public key and fingerprint of an unencrypted private key, or
     * array('error' => ...) when it is not one.
     */
    public function metadata($privateKey)
    {
        if (strpos($privateKey, 'PRIVATE KEY-----') === FALSE) {
            return array('error' => 'invalid key');
        }
        $directory = $this->temporaryDirectory('key');
        if ($directory === FALSE) {
            return array('error' => 'temporary directory');
        }
        $path = $directory.DIRECTORY_SEPARATOR.'key';
        file_put_contents($path, rtrim($privateKey)."\n");
        chmod($path, 0600);
        list($publicStatus, $publicKey) = $this->run(array('ssh-keygen', '-y', '-f', $path));
        list($fingerprintStatus, $fingerprint) = $this->run(array('ssh-keygen', '-lf', $path));
        @unlink($path);
        @rmdir($directory);
        if ($publicStatus !== 0 || $fingerprintStatus !== 0 || $publicKey === '') {
            return array('error' => 'invalid or encrypted key');
        }
        return array('public_key' => $publicKey, 'fingerprint' => $fingerprint);
    }

    private function temporaryDirectory($purpose)
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobseeker-git-'.$purpose.'-'.bin2hex(random_bytes(8));
        return mkdir($directory, 0700) ? $directory : FALSE;
    }

    /** @return array exit status and trimmed standard output */
    private function run(array $arguments)
    {
        $process = proc_open($arguments, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, array('PATH' => '/usr/local/bin:/usr/bin:/bin'));
        if (! is_resource($process)) {
            return array(1, '');
        }
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return array(proc_close($process), trim((string) $output));
    }
}
