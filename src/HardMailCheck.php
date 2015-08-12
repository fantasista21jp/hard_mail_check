<?php
namespace HardMailCheck;

use HardMailCheck\Exceptions\FromEmailNotSetException;
use HardMailCheck\Exceptions\FromEmailNotValidException;
use HardMailCheck\Exceptions\EmailNotValidException;
use HardMailCheck\Exceptions\MailServerNotExistException;
use HardMailCheck\Exceptions\EmailNotExistException;
use HardMailCheck\Exceptions\EmailListNotSetException;
use HardMailCheck\Exceptions\EmailListNotValidException;

/**
 * Class HardMailCheck
 * @package HardMailCheck
 */
class HardMailCheck
{
    protected $configs = [
        'rfcCheck' => false,
    ];
    protected $emailList = [];
    protected $mailServerList = [];
    protected $errorEmailList = [];
    protected $blockEmailList = [];

    public function __construct(array $configs = [])
    {
        $this->setConfigs($configs);
    }

    /**
     * @param array $configs
     */
    public function setConfigs(array $configs = [])
    {
        if (!empty($configs)) {
            $this->configs = array_merge($this->configs, $configs);
        }
    }

    /**
     * @param array $emailList
     */
    public function setEmailList(array $emailList = [])
    {
        $this->emailList = $emailList;
        $this->setMxRecordList($emailList);
    }

    /**
     * @param array $emailList
     */
    public function setMxRecordList(array $emailList = [])
    {
        if (!empty($emailList)) {
            foreach ($emailList as $email) {
                if (empty($email)) {
                    continue;
                }

                $domain = $this->getDomainByEmail($email);
                if (!$domain || isset($this->mailServerList[$domain])) {
                    continue;
                }

                $this->mailServerList[$domain] = $this->getMailServerByDomain($domain);
            }

        } else {
            $this->mailServerList = [];
        }
    }

    /**
     * @param $email
     */
    public function setErrorEmailList($email)
    {
        $this->errorEmailList[] = $email;
    }

    /**
     * @return array
     */
    public function getErrorEmailList()
    {
        return $this->errorEmailList;
    }

    /**
     * @param $email
     */
    public function setBlockEmailList($email)
    {
        $this->blockEmailList[] = $email;
    }

    /**
     * @return array
     */
    public function getBlockEmailList()
    {
        return $this->blockEmailList;
    }

    /**
     * @return array
     */
    public function resetErrorEmailList()
    {
        $this->errorEmailList = [];
    }

    /**
     * @param $fromEmail
     * @throws EmailListNotSetException
     * @throws EmailListNotValidException
     * @throws FromEmailNotSetException
     * @throws FromEmailNotValidException
     */
    public function checkEmailList($fromEmail)
    {
        if (empty($fromEmail)) {
            throw new FromEmailNotSetException();
        }
        if (!$this->validEmail($fromEmail)) {
            throw new FromEmailNotValidException();
        }
        if (empty($this->emailList)) {
            throw new EmailListNotSetException();
        }

        $this->resetErrorEmailList();
        foreach ($this->emailList as $email) {
            try {
                $this->checkEmail($email, $fromEmail);

            } catch (\HardMailCheck\Exceptions\HardMailCheckException $e) {
                $this->setErrorEmailList($email);
            }
        }
        if (!empty($this->errorEmailList)) {
            throw new EmailListNotValidException();
        }

        return;
    }

    /**
     * @param $email
     * @param $fromEmail
     * @throws EmailNotExistException
     * @throws EmailNotValidException
     * @throws FromEmailNotSetException
     * @throws FromEmailNotValidException
     * @throws MailServerNotExistException
     */
    public function checkEmail($email, $fromEmail)
    {
        if (empty($fromEmail)) {
            throw new FromEmailNotSetException();
        }
        if (!$this->validEmail($fromEmail)) {
            throw new FromEmailNotValidException();
        }

        if ($this->configs['rfcCheck']) {
            $validEmailFunc = 'validEmailRfc';
        } else {
            $validEmailFunc = 'validEmail';
        }
        if (!$this->{$validEmailFunc}($email)) {
            throw new EmailNotValidException();
        }

        $domain = $this->getDomainByEmail($email);
        if (isset($this->mailServerList[$domain])) {
            $mailServer = $this->mailServerList[$domain];
        } else {
            $mailServer = $this->getMailServerByDomain($domain);
        }
        if (empty($mailServer)) {
            throw new MailServerNotExistException();
        }

        if (!$this->existsEmail($email, $mailServer, $fromEmail)) {
            throw new EmailNotExistException();
        }

        return;
    }

    /**
     * @param $email
     * @return bool
     */
    public function validEmail($email) {
        $pattern = '/^[a-z0-9\._-]{3,30}@(?:[a-z0-9][-a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,4})$/i';

        return preg_match($pattern, $email);
    }

    /**
     * @param $email
     * @return bool
     */
    public function validEmailRfc($email) {
        $pattern = '/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9][-a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,4})$/i';

        return preg_match($pattern, $email);
    }

    /**
     * @param $email
     * @param $mailServer
     * @param $fromEmail
     * @return bool
     */
    public function existsEmail($email, $mailServer, $fromEmail) {
        $errno = null;
        $errstr = null;
        $socket = fsockopen($mailServer, 25, $errno, $errstr, 60);
        if (!$socket) {
            return false;
        }
        $buffer = fgets($socket);
        if (!preg_match('/^220 /', $buffer)) {
            if (preg_match('/^421 /', $buffer)) {
                $this->setBlockEmailList($email);
            }
            fclose($socket);
            return false;
        }

        $command = 'HELO ' . $mailServer . "\r\n";
        fwrite($socket, $command);
        $buffer = fgets($socket);
        if (!preg_match('/^250 /', $buffer)) {
            fclose($socket);
            return false;
        }

        $command = 'MAIL FROM: <' . $fromEmail . ">\r\n";
        fwrite($socket, $command);
        $buffer = fgets($socket);
        if (!preg_match('/^250 /', $buffer)) {
            fclose($socket);
            return false;
        }

        $command = 'RCPT TO: <' . $email . ">\r\n";
        fwrite($socket, $command);
        $buffer = fgets($socket);
        if (!preg_match('/^250 /', $buffer)) {
            fclose($socket);
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * @param $email
     * @return string
     */
    public function getDomainByEmail($email)
    {
        $domain = '';

        $pattern = '/^([a-z0-9\._-]{3,30})@((?:[a-z0-9][-a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,4}))$/i';
        if (preg_match($pattern, $email, $matches)) {
            $domain = $matches[2];
        }

        return $domain;
    }

    /**
     * @param $domain
     * @return string
     */
    public function getMailServerByDomain($domain)
    {
        $mailServer = '';
        $dnsResults = dns_get_record($domain, DNS_MX);
        if (!empty($dnsResults)) {
            $pri = 65535;
            foreach ($dnsResults as $dnsResult) {
                if ($pri < $dnsResult['pri']) {
                    continue;
                }
                $pri = $dnsResult['pri'];
                $mailServer = $dnsResult['target'];
            }
        }

        return $mailServer;
    }
}
