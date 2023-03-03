<?php

class OpenQR_Customer {
    public $name;
    public $last_name;
    public $email;
    public $phone_Number;

    public function __constructor(){

    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    /**
     * @param mixed $last_name
     */
    public function setLastName($last_name): void
    {
        $this->last_name = $last_name;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getPhoneNumber()
    {
        return $this->phone_Number;
    }

    /**
     * @param mixed $phone_Number
     */
    public function setPhoneNumber($phone_Number): void
    {
        $this->phone_Number = $phone_Number;
    }

}