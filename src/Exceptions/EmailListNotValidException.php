<?php
/**
 * Created by PhpStorm.
 * User: Tom
 * Date: 2015/07/15
 * Time: 15:31
 */
namespace HardMailCheck\Exceptions;

class EmailListNotValidException extends HardMailCheckException
{
    protected $message = 'EmailList is not valid.';
    protected $code = 2001;
    protected $previous = null;

    public function __construct()
    {
        parent::__construct($this->message, $this->code, $this->previous);
    }
}
