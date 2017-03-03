<?php

/**
 * Copyright 2017 Joseph Mangmang.
 * Created 15-Feb-2017
 *
 */

/**
 * Helper class for manipulating data
 * in the Database
 *
 * @author kali_root
 */
class DatabaseHelper {

    private $conn;
    const ENCRYPT_UID_QUERY = 'SELECT HEX(AES_ENCRYPT(?, _lucid_)';
    const DECRYPT_UID_QUERY = 'SELECT AES_DECRYPT(UNHEX(?), _lucid_)';
    
    // Table names
    const TABLE_DIVE_SHOP = 'dive_shop';
    const TABLE_DIVE_SHOP_COURSE = 'dive_shop_course';
    const TABLE_BOAT = 'boat';
    const TABLE_DAILY_TRIP = 'daily_trip';
    const TABLE_DAILY_TRIP_BOAT = 'daily_trip_boat';
    const TABLE_DAILY_TRIP_GUIDE = 'daily_trip_guide';
    const TABLE_DAILY_TRIP_DIVE_SITE = 'daily_trip_dive_site';
    const TABLE_DAILY_TRIP_GUEST = 'daily_trip_guest';
    const TABLE_DIVER = 'diver';
    const TABLE_DIVE_SITE = 'dive_site';
    const TABLE_COURSE = 'course';

    public function __construct() {
        require '../../include/DatabaseConnection.php';
        $db = new DatabaseConnection();
        $this->conn = $db->connect();
    }

    /**
     * Register new User
     */
    public function register($email, $password, $type) {
        $response = array();
        if (!$this->emailValid($email)) {
            $response['error'] = true;
            $response['message'] = 'Email address is not valid';
            return $response;
        }
        $passwordHash = PassHash::hash($password);
        $insertQuery;
        $updateQuery;
        switch ($type) {
            case AccountType::DIVE_SHOP:
                $insertQuery = 'INSERT ' . self::TABLE_DIVE_SHOP . '(email, password) VALUES(?, ?)';
                $updateQuery = 'UPDATE ' . self::TABLE_DIVE_SHOP . ' SET uid = (' . self::ENCRYPT_UID_QUERY . ') WHERE dive_shop_id = ?';
                break;
            case AccountType::DIVER:
                $insertQuery = 'INSERT ' . self::TABLE_DIVER . '(email, password) VALUES(?, ?)';
                $updateQuery = 'UPDATE ' . self::TABLE_DIVER . ' SET uid = (' . self::ENCRYPT_UID_QUERY . ') WHERE diver_id = ?';
                break;
            default :
                $response['error'] = true;
                $response['message'] = 'Account type not valid ' . $type;
                return $response;
        }
        $stmt = $this->conn->prepare($insertQuery);
        $stmt->bind_param('ss', $email, $passwordHash);
        if ($stmt->execute()) {
            $id = $stmt->inset_id;
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param('ii', $id, $id);
            if ($stmt->execute()) {
                $response['error'] = false;
                $response['message'] = 'Registration complete';
            } else {
                $response['error'] = true;
                $response['message'] = 'Cannot generate unique identifier';
            }
        } else {
            $response['error'] = true;
            $response['message'] = "An error occured while registering. " . $stmt->error;
        }
        return $response;
    }

    /**
     * Validate email address
     */
    private function emailValid($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Login validation
     */
    public function login($email, $password, $type) {
        $response = array();
        switch ($type) {
            case AccountType::DIVE_SHOP:
                $query = 'SELECT uid, password, name, create_time FROM ' . self::TABLE_DIVE_SHOP . ' WHERE email = ?';
                break;
            case AccountType::DIVER:
                $query = 'SELECT uid, password, name, create_time FROM ' . self::TABLE_DIVER . ' WHERE email = ?';
                break;
            default :
                $response['error'] = true;
                $response['message'] = 'Account type not valid ' . $type;
                return $response;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $email);
        if ($stmt->execute()) {
            $stmt->bind_result($uid, $passwordHash, $name, $createTime);
            $stmt->fetch();
            if (PassHash::check_password($passwordHash, $password)) {
                $response['error'] = false;
                $response['message'] = 'Success';
                $response['user'] = array('uid' => $uid, 'name' => $name, 'createTime' => $createTime);
            } else {
                $response['error'] = true;
                $response['message'] = "Email or password does't match";
            }
        }
        return $response;
    }

    /**
     * Create Daily Trips for Dive shops
     * @param shop_id The uid of the dive shop. An encrypted hex from dive shop id
     * @param group_size,
     * @param number_of_dives, 
     * @param date, 
     * @param price, 
     * @param price_note
     */
    public function addDiveTrip($shopId, $groupSize, $numberOfDives, $date, $price, $priceNote) {
        $response = array();
        $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP . '(dive_shop_id, group_size, $number_of_dive, date, price, price_note) VALUES(' . self::DECRYPT_UID_QUERY . ',?,?,?,?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('siiids', $shopId, $groupSize, $numberOfDives, $date, $price, $priceNote);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'New dive trip successfully created';
        } else {
            $response['error'] = true;
            $response['message'] = 'Cannot add dive trip. ' . $stmt->error;
        }
        return $response;
    }

    /**
     * Get a list of dive trips
     * 
     * @param type $startDate
     * @param type $endDate
     * @param type $offset
     * @param type $sort PRICE or RATING
     * @param type $order
     */
    public function getDiveTrips($startDate, $endDate, $offset = 0, $sort = 'price', $order = 'ASC') {
        $response = array();
        $query = 'SELECT daily_trip_id, dive_shop_id, group_size, number_of_dive, date, price FROM ' . self::TABLE_DAILY_TRIP . ' ORDER BY ? ? LIMIT ?, ?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssii', $sort, $this->getOrderType($order), $offset, $offset + 10);
        if ($stmt->execute()) {
            $stmt->bind_result($tripId, $shopId, $groupSize, $numberOfDive, $date, $price);
            $stmt->fetch();
            $stmt->prepare('SELECT ');
            // TODO 
        }
    }

    /*
     * Helper functions/methods
     */

    private function getOrderType($order) {
        switch (strtolower($order)) {
            case 'asc' :case 'low': case 0: return 'ASC';
                break;
            case 'desc': case 'high': case 1: return 'DESC';
                break;
            default : return 'ASC';
        }
    }

    // Todo helper methods
    public function updateDiveTrip($tripId, $groupSize, $numberOfDive, $date, $price, $priceNote, $guides, $sites) {
        
    }

    public function getCourses($offset, $sort, $order) {
        
    }

    public function addCourse($name, $description, $offeredBy) {
        
    }

    public function updateCourse($courseId, $name, $description, $offeredBy) {
        
    }

    public function getDiveSite($location, $offset) {
        
    }

    public function addDiveSite($name, $description, $location) {
        
    }

    public function updateDiveSite($siteId, $name, $description, $location) {
        
    }

    public function getDiveShops($location, $offset, $sort, $order) {
        
    }

    public function getDiveShop($shopUid) {
        
    }

    public function getDiveShopCourses($shopUid, $offset, $sort, $order) {
        
    }

    public function updateDiveShopCourse($shopUid, $shopCourseId, $price) {
        
    }

}

class AccountType {

    const DIVE_SHOP = 0;
    const DIVER = 1;

}
