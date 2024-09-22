<?php

namespace MailerLite\Includes\Classes;

abstract class Singleton
{

    /**
     * Class instance
     * @var $instance
     */
    protected static $instance;

    private function __construct()
    {
    }

    /**
     * Get the instance of the class
     * @return mixed
     */
    public static function getInstance()
    {
        if ( ! static::$instance instanceof static) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    final protected function __clone()
    {
    }
}