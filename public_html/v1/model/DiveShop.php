<?php

/*
 * Copyright 2017 Joseph Mangmang.
 * Created 20-Feb-2017
 *
 */

/**
 * Description of DiveShop
 *
 * @author kali_root
 */
class DiveShop {

    /**
     *
     * @var string
     */
    public $uid;

    /**
     *
     * @var string
     */
    public $email;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var long
     */
    public $createdTime;

    /**
     *
     * @var string
     */
    public $description;

    /**
     *
     * @var double
     */
    public $pricePerDive;

    /**
     *
     * @var string
     */
    public $specialService;

    public function __construct($uid, $name, $email, $description, $createdTime, $pricePerDive, $specialService) {
        $this->uid = $uid;
        $this->name = $name;
        $this->email = $email;
        $this->description = $description;
        $this->createdTime = $createdTime;
        $this->pricePerDive = $pricePerDive;
        $this->specialService = $specialService;
    }
    
}
