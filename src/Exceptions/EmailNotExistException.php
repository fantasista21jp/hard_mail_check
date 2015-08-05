<?php
/**
 * Created by PhpStorm.
 * User: Tom
 * Date: 2015/07/15
 * Time: 15:31
 */
namespace HardMailCheck\Exceptions;

class EmailNotExistException extends HardMailCheckException
{
    protected $message = 'Email is not exist.';
    protected $code = 1004;
    protected $previous = null;

    public function __construct()
    {
        parent::__construct($this->message, $this->code, $this->previous);
    }
}
