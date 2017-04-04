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

/**
 * Import
 */
use Hashids\Hashids;

class DatabaseHelper {

    private $conn;

    const ENCRYPT_UID_QUERY = 'SELECT HEX(AES_ENCRYPT(?, _lucid_))';
    const DECRYPT_UID_QUERY = 'SELECT AES_DECRYPT(UNHEX(?), _lucid_)';
    // Table names
    const TABLE_USER = 'user';
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
    // Column names
    const COLUMN_USER_ID = 'user_id';
    const COLUMN_ACCOUNT_TYPE = 'account_type';
    const COLUMN_DIVE_SHOP_ID = 'dive_shop_id';
    const COLUMN_EMAIL = 'email';
    const COLUMN_PASSWORD = 'password';
    const COLUMN_NAME = 'name';
    const COLUMN_CREATE_TIME = 'create_time';
    const COLUMN_DESCRIPTION = 'description';
    const COLUMN_CONTACT_NUMBER = 'contact_number';
    const COLUMN_PRICE_PER_DIVE = 'price_per_dive';
    const COLUMN_SPECIAL_SERVICE = 'special_service';
    const COLUMN_DIVE_SHOP_COURSE_ID = 'dive_shop_course_id';
    const COLUMN_PRICE = 'price';
    const COLUMN_BOAT_ID = 'boat_id';
    const COLUMN_IMAGE = 'image';
    const COLUMN_DAILY_TRIP_ID = 'daily_trip_id';
    const COLUMN_GROUP_SIZE = 'group_size';
    const COLUMN_NUMBER_OF_DIVE = 'number_of_dive';
    const COLUMN_DATE = 'date';
    const COLUMN_PRICE_NOTE = 'price_note';
    const COLUMN_GUIDE_NAME = 'guide_name';
    const COLUMN_COURSE_ID = 'course_id';
    const COLUMN_PHOTO_COVER = 'photo_cover';
    const COLUMN_OFFERED_BY = 'offered_by';
    const COLUMN_DIVE_SITE_ID = 'dive_site_id';
    const COLUMN_ADDRESS = 'address';
    const COLUMN_DIVER_ID = 'diver_id';
    const COLUMN_LATITUDE = 'latitude';
    const COLUMN_LONGTITUDE = 'longitude';
    const COLUMN_DAILY_TRIP_GUIDE_ID = 'daily_trip_guide_id';
    const COLUMN_DAILY_TRIP_BOAT_ID = 'daily_trip_boat_id';
    const COLUMN_DAILY_TRIP_DIVE_SITE_ID = 'daily_trip_dive_site_id';
    const COLUMN_DAILY_TRIP_GUEST_ID = 'daily_trip_guest_id';

    private $hashids;

    public function __construct() {
        require '../../include/DatabaseConnection.php';
        require_once '../../include/Config.php';

        $db = new DatabaseConnection();
        $this->conn = $db->connect();
        $this->hashids = new Hashids('', 20);
    }

    /** Done
     * Register new User
     */
    public function register($email, $password, $type) {
        $response = array();
        if (!$this->isValidEmail($email)) {
            $response['error'] = true;
            $response['message'] = 'Email address is not valid';
            return $response;
        }
        if (!$this->isValidAccountType($type)) {
            $response['error'] = true;
            $response['message'] = "Uknown account type $type";
            return $response;
        }
        include_once '../../include/PassHash.php';
        $passwordHash = PassHash::hash($password);
        $query = 'INSERT ' . self::TABLE_USER . '(' .
                self::COLUMN_EMAIL . ', ' .
                self::COLUMN_PASSWORD . ',' .
                self::COLUMN_ACCOUNT_TYPE .
                ') VALUES(?, ?, ?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sss', $email, $passwordHash, $type);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt->close();
            switch ($type) {
                case AccountType::DIVE_SHOP:
                    $query = 'INSERT ' . self::TABLE_DIVE_SHOP . '(' . self::COLUMN_USER_ID . ') VALUES(?)';
                    break;
                case AccountType::DIVER:
                    $query = 'INSERT ' . self::TABLE_DIVER . '(' . self::COLUMN_USER_ID . ') VALUES(?)';
                    break;
                default :
                    $response['error'] = true;
                    $response['type'] = $type;
                    $response['message'] = "Unkown account type $type";
                    return $response;
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $response['error'] = false;
                $response['message'] = 'Registration complete';
            } else {
                $response['error'] = true;
                $response['message'] = 'An error occured while registering. ' . $stmt->error;
            }
        } else {
            $response['error'] = true;
            if (strpos($stmt->error, 'Duplicate') !== false) {
                $response['message'] = "Email already in use.";
            } else {
                $response['message'] = DEBUG ? $stmt->error : "An error occured while registering.";
            }
        }
        $stmt->close();
        return $response;
    }

    /**
     * Validate email address
     */
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Done
     * Login validation
     */
    public function login($email, $password) {
        $response = array('error' => true, 'message' => 'An error occured while Loggin in. ');
        if (!$this->isValidEmail($email)) {
            $response['error'] = true;
            $response['message'] = 'Email address is not valid';
            return $response;
        }

        $query = 'SELECT ' .
                self::COLUMN_USER_ID . ',' .
                self::COLUMN_PASSWORD . ',' .
                self::COLUMN_CREATE_TIME . ',' .
                self::COLUMN_ACCOUNT_TYPE . ' FROM ' .
                self::TABLE_USER . ' WHERE ' .
                self::COLUMN_EMAIL . '=?';

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $email);
        if ($stmt->execute()) {
            $stmt->bind_result($userID, $passwordHash, $createdTime, $accountType);
            $stmt->fetch();
            include_once '../../include/PassHash.php';
            if (PassHash::check_password($passwordHash, $password)) {
                $response['error'] = false;
                $response['message'] = 'Success';

                $response['user'] = array(
                    self::COLUMN_USER_ID => $this->hashids->encode($userID),
                    self::COLUMN_EMAIL => $email,
                    self::COLUMN_CREATE_TIME => $createdTime,
                    'auth_key' => 'TODO auth_key',
                    self::COLUMN_ACCOUNT_TYPE => $accountType);
            } else {
                $response['error'] = true;
                $response['message'] = "Email or password does't match";
            }
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Add Dive Shop Daily Trip
     * 
     * @param type $shopUid
     * @param type $tripJson
     * @return array
     */
    public function addDiveTrip($shopUid, $tripJson) {
        $response = array('error' => true, 'message' => 'An error while adding Dive Trip. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return;
        }
        $tripData = json_decode($tripJson, true);
        if (is_null($tripData)) {
            $response['message'] = $response['message'] . 'Invalid Dive Trip data.';
            return $response;
        }
        $checkFields = $this->requiredParams($tripData, array(
            self::COLUMN_NUMBER_OF_DIVE, self::COLUMN_GROUP_SIZE, self::COLUMN_DATE,
            self::COLUMN_PRICE, self::COLUMN_PRICE_NOTE, self::TABLE_DAILY_TRIP_DIVE_SITE,
            self::TABLE_DAILY_TRIP_GUIDE, self::TABLE_DAILY_TRIP_GUEST, self::TABLE_DAILY_TRIP_BOAT
        ));
        if ($checkFields['error']) {
            $response['message'] = $response['message'] . $checkFields['message'];
            return $response;
        }
        $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP . ' (' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_GROUP_SIZE . ',' .
                self::COLUMN_NUMBER_OF_DIVE . ',' .
                self::COLUMN_DATE . ',' .
                self::COLUMN_PRICE . ',' .
                self::COLUMN_PRICE_NOTE . ') VALUES (?,?,?,?,?,?)';
        $dailyTripStmt = $this->conn->prepare($query);
        $dailyTripStmt->bind_param('iiisds', $shopId, $tripData[self::COLUMN_GROUP_SIZE], $tripData[self::COLUMN_NUMBER_OF_DIVE], $tripData[self::COLUMN_DATE], $tripData[self::COLUMN_PRICE], $tripData[self::COLUMN_PRICE_NOTE]);
        if ($dailyTripStmt->execute()) {
            $dailyTripId = $dailyTripStmt->insert_id;

            // insert daily trip dive site
            $dailyTripDiveSites = $tripData[self::TABLE_DAILY_TRIP_DIVE_SITE];
            $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_DIVE_SITE . ' (' .
                    self::COLUMN_DAILY_TRIP_ID . ',' . self::COLUMN_DIVE_SITE_ID . ') VALUES(?,?)';
            $diveSiteStmt = $this->conn->prepare($query);
            foreach ($dailyTripDiveSites as $site) {
                if (array_key_exists(self::COLUMN_DIVE_SITE_ID, $site)) {
                    $diveSiteStmt->bind_param('ii', $dailyTripId, $site[self::COLUMN_DIVE_SITE_ID]);
                    $diveSiteStmt->execute();
                }
            }
            $diveSiteStmt->close();

            // insert daily trip guides
            $dailyTripGuides = $tripData[self::TABLE_DAILY_TRIP_GUIDE];
            $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_GUIDE . ' (' .
                    self::COLUMN_DAILY_TRIP_ID . ',' .
                    self::COLUMN_GUIDE_NAME . ') VALUES(?,?)';
            $guideStmt = $this->conn->prepare($query);
            foreach ($dailyTripGuides as $guide) {
                if (array_key_exists(self::COLUMN_GUIDE_NAME, $site)) {
                    $guideStmt->bind_param('is', $dailyTripId, $guide[self::COLUMN_GUIDE_NAME]);
                    $guideStmt->execute();
                }
            }
            $guideStmt->close();

            // insert daily trip guests
            $dailyTripGuests = $tripData[self::TABLE_DAILY_TRIP_GUEST];
            $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_GUEST . ' (' .
                    self::COLUMN_DAILY_TRIP_ID . ',' .
                    self::COLUMN_DIVER_ID . ') VALUES (?,?)';
            $guestStmt = $this->conn->prepare($query);
            foreach ($dailyTripGuests as $guest) {
                if (array_key_exists(self::COLUMN_DIVER_ID, $site)) {
                    $guestStmt->bind_param('ii', $dailyTripId, $guest[self::COLUMN_DIVER_ID]);
                    $guestStmt->execute();
                }
            }
            $guestStmt->close();

            // insert daily trip boat
            $dailtyTripBoats = $tripData[self::TABLE_DAILY_TRIP_BOAT];
            $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_BOAT . ' (' .
                    self::COLUMN_DAILY_TRIP_ID . ',' .
                    self::COLUMN_BOAT_ID . ') VALUES (?,?)';
            $boatStmt = $this->conn->prepare($query);
            foreach ($dailtyTripBoats as $boat) {
                if (array_key_exists(self::COLUMN_BOAT_ID, $site)) {
                    $boatStmt->bind_param('ii', $dailyTripId, $boat[self::COLUMN_BOAT_ID]);
                    $boatStmt->execute();
                }
            }
            $boatStmt->close();
            $response['error'] = false;
            $response['message'] = 'Daily Trip successfully added.';
        }
        $dailyTripStmt->close();
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
        $query = 'SELECT ' .
                self::COLUMN_DAILY_TRIP_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ', ' .
                self::COLUMN_GROUP_SIZE . ', ' .
                self::COLUMN_NUMBER_OF_DIVE . ', ' .
                self::COLUMN_DATE . ', ' .
                self::COLUMN_PRICE .
                ' FROM ' . self::TABLE_DAILY_TRIP .
                ' ORDER BY ? ? LIMIT ?, ?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssii', $sort, $this->getSortType($order), $offset, $offset + 10);
        if ($stmt->execute()) {
            for ($i = 0; $i < $stmt->num_rows; $i++) {
                $stmt->bind_result($tripId, $shopId, $groupSize, $numberOfDive, $date, $price);
                $stmt->fetch();
                $response[$i] = array('trip_id' => $tripId, 'shop_id' => $shopId, 'group_size' => $groupSize, 'number_of_dive' => $numberOfDive, 'date' => $date, 'price' => $price);
                $response[$i]['guides'] = $this->getGuides($tripId);
                $response[$i]['sites'] = $this->getDiveSitesByTripId($tripId);
                $response[$i]['guest'] = $this->getGuestsByTripId($tripId);
            }
        }
        return $response;
    }

    /*
     * Helper functions/methods
     */

    private function getSortType($sort) {
        switch (strtolower($sort)) {
            case 'asc' :case 'low': case '0': return 'ASC';
                break;
            case 'desc': case 'high': case '1': return 'DESC';
                break;
            default : return 'ASC';
        }
    }

    /**
     * Done
     * Update Dive Shop Daily Trip
     * 
     * @param type $shopUid
     * @param type $tripId
     * @param type $groupSize
     * @param type $numberOfDive
     * @param type $date
     * @param type $price
     * @param type $priceNote
     * @return array
     */
    public function updateDiveShopTrip($shopUid, $tripId, $groupSize, $numberOfDive, $date, $price, $priceNote) {
        $response = array('error' => true, 'message' => 'An error occured while updating Dive Shop Trip. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $query = 'UPDATE ' . self::TABLE_DAILY_TRIP .
                ' SET ' .
                self::COLUMN_GROUP_SIZE . '= ?,' .
                self::COLUMN_NUMBER_OF_DIVE . '=?,' .
                self::COLUMN_DATE . '=?,' .
                self::COLUMN_PRICE . '=?,' .
                self::COLUMN_PRICE_NOTE . '=?' .
                ' WHERE ' .
                self::COLUMN_DAILY_TRIP_ID . '=? AND ' . self::COLUMN_DIVE_SHOP_ID . '=?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iisdsii', $groupSize, $numberOfDive, $date, $price, $priceNote, $tripId, $shopId[0]);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Daily Trip successfully updated. ';
            if ($stmt->affected_rows < 1) {
                $response['message'] = 'Nothing changed on Daily Trip.';
            }
            $response['daily_trip'] = array(
                self::COLUMN_DAILY_TRIP_ID => $tripId,
                self::COLUMN_DIVE_SHOP_ID => $shopId[0],
                self::COLUMN_GROUP_SIZE => $groupSize,
                self::COLUMN_NUMBER_OF_DIVE => $numberOfDive,
                self::COLUMN_DATE => $date,
                self::COLUMN_PRICE => $price,
                self::COLUMN_PRICE_NOTE => $priceNote
            );
        }
        $stmt->close();
        return $response;
    }

    /** Done
     * Get list of course
     * @param type $offset
     * @param type $orderBy
     * @param type $sort
     * @return array 
     */
    public function getCourses($offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC') {
        $response = array('error' => true, 'message' => 'An error occured while getting Course list. ');
        $sort = $this->getSortType($sort);
        if ($orderBy !== self::COLUMN_NAME && $orderBy !== self::COLUMN_OFFERED_BY) {
            $response['message'] = $response['message'] . 'Only order by name or offered_by is allowed.';
            return $response;
        }
        $query = 'SELECT ' .
                self::COLUMN_COURSE_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_PHOTO_COVER . ',' .
                self::COLUMN_OFFERED_BY .
                ' FROM ' . self::TABLE_COURSE .
                " ORDER BY $orderBy $sort LIMIT ?,?";
        $stmt = $this->conn->prepare($query);
        $maxRows = $offset + 10;
        $stmt->bind_param('ii', $offset, $maxRows);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response['error'] = false;
            $response['message'] = 'Success';
            $response['courses'] = array();
            while ($course = $result->fetch_assoc()) {
                array_push($response['courses'], $course);
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /** Done
     * Add new course
     * @param type $name
     * @param type $description
     * @param type $offeredBy
     * @return array
     */
    public function addCourse($name, $description = '', $offeredBy = '') {
        $response = array('error' => true, 'message' => 'An error occured while adding Course. ');
        $stmt = $this->conn->prepare('INSERT INTO ' .
                self::TABLE_COURSE . '(' .
                self::COLUMN_NAME . ',' . self::COLUMN_DESCRIPTION . ',' . self::COLUMN_OFFERED_BY .
                ') VALUES(?, ?, ?)');
        $stmt->bind_param('sss', $name, $description, $offeredBy);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = "$name Successfully added";
        } else if (strpos($stmt->error, 'Duplicate') !== false) {
            $response['message'] = "Dive course $name already exist";
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Update Course
     * @param type $courseId
     * @param type $name
     * @param type $description
     * @param type $offeredBy
     * @return array
     */
    public function updateCourse($courseId, $name, $description, $offeredBy) {
        $response = array('error' => true, 'message' => 'An error occured while updating course. ');
        $stmt = $this->conn->prepare('UPDATE ' .
                self::TABLE_COURSE . ' SET ' .
                self::COLUMN_NAME . '=?,' .
                self::COLUMN_DESCRIPTION . '=?, ' .
                self::COLUMN_OFFERED_BY . '=? WHERE ' .
                self::COLUMN_COURSE_ID . '=?');
        $stmt->bind_param('sssi', $name, $description, $offeredBy, $courseId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = "$name successfully updated.";
            if ($stmt->affected_rows < 1) {
                $response['error'] = true;
                $response['message'] = "Course doesn't exist.";
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Get list of dive site
     * @param type $lat
     * @param type $lng
     * @param type $radius
     * @param type $offset
     * @return array
     */
    public function getDiveSites($lat, $lng, $radius = 25, $offset = '0') {
        $response = array('error' => true, 'message' => 'An error occured while getting list of Dive Site. ');
        if (!ctype_digit($offset)) {
            $response['message'] = $response['message'] . ' Invalid offset "' . $offset . '"';
            return $response;
        }
        $query = 'SELECT ' .
                self::COLUMN_DIVE_SITE_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_ADDRESS . ',' .
                self::COLUMN_LATITUDE . ',' .
                self::COLUMN_LONGTITUDE .
                ', ( 3959 * acos( cos( radians(?) ) * cos( radians( ' .
                self::COLUMN_LATITUDE . ' ) ) * cos( radians( ' .
                self::COLUMN_LONGTITUDE . ' ) - radians(?) ) + sin( radians(?) ) * sin( radians( ' .
                self::COLUMN_LATITUDE . ' ) ) ) ) AS distance FROM ' .
                self::TABLE_DIVE_SITE . ' HAVING distance < ? ORDER BY distance LIMIT ? , ?';

        $stmt = $this->conn->prepare($query);
        $maxRows = $offset + 10;
        $stmt->bind_param('dddiii', $lat, $lng, $lat, $radius, $offset, $maxRows);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
            $response['dive_sites'] = array();
            $result = $stmt->get_result();
            while ($site = $result->fetch_assoc()) {
                array_push($response['dive_sites'], $site);
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Add new Dive Site
     * 
     * @param type $name
     * @param type $description
     * @param type $address
     * @param type $latitude
     * @param type $longitude
     * @return array response
     */
    public function addDiveSite($name, $description, $address, $latitude, $longitude) {
        $response = array('error' => true, 'message' => 'An error occured while adding Dive Site. ');
        $query = 'INSERT INTO ' . self::TABLE_DIVE_SITE . '(' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_ADDRESS . ',' .
                self::COLUMN_LATITUDE . ',' .
                self::COLUMN_LONGTITUDE . ') VALUES(?,?,?,?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssdd', $name, $description, $address, $latitude, $longitude);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = "$name successfully added";
            $response['dive_site'] = array(
                self::COLUMN_DIVE_SITE_ID => $stmt->insert_id,
                self::COLUMN_NAME => $name,
                self::COLUMN_DESCRIPTION => $description,
                self::COLUMN_ADDRESS => $address,
                self::COLUMN_LATITUDE => $latitude,
                self::COLUMN_LONGTITUDE => $longitude
            );
        } else if (strpos($stmt->error, "Duplicate") !== false) {
            $response['message'] = "$name already exist";
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Update Dive Site
     * 
     * @param type $siteId
     * @param type $name
     * @param type $description
     * @param type $address
     * @param type $latitude
     * @param type $longitude
     * @return array
     */
    public function updateDiveSite($siteId, $name, $description, $address, $latitude, $longitude) {
        $response = array('error' => true, 'message' => 'An error occured while updating Dive Site. ');
        $stmt = $this->conn->prepare('UPDATE ' .
                self::TABLE_DIVE_SITE . ' SET ' .
                self::COLUMN_NAME . '=?,' .
                self::COLUMN_DESCRIPTION . '=?,' .
                self::COLUMN_ADDRESS . '=?,' .
                self::COLUMN_LATITUDE . '=?,' .
                self::COLUMN_LONGTITUDE . '=? WHERE ' .
                self::COLUMN_DIVE_SITE_ID . '=?');
        $stmt->bind_param('sssddi', $name, $description, $address, $latitude, $longitude, $siteId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Dive site successfully updated';
            $response['dive_site'] = array(
                self::COLUMN_DIVE_SITE_ID => $siteId,
                self::COLUMN_NAME => $name,
                self::COLUMN_DESCRIPTION => $description,
                self::COLUMN_ADDRESS => $address,
                self::COLUMN_LATITUDE => $latitude,
                self::COLUMN_LONGTITUDE => $longitude
            );
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Get list of Dive Shops
     * @param type $lat
     * @param type $lng
     * @param type $radius
     * @param type $offset
     * @return array
     */
    public function getDiveShops($lat, $lng, $radius, $offset) {
        $response = array('error' => true, 'message' => 'An error occured while getting list of Dive Site. ');
        if (!ctype_digit($offset)) {
            $response['message'] = $response['message'] . ' Invalid offset "' . $offset . '"';
            return $response;
        }
        $query = 'SELECT ' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_CONTACT_NUMBER . ',' .
                self::COLUMN_ADDRESS . ',' .
                self::COLUMN_PRICE_PER_DIVE . ',' .
                self::COLUMN_LATITUDE . ',' .
                self::COLUMN_LONGTITUDE . ',' .
                self::COLUMN_SPECIAL_SERVICE .
                ', ( 3959 * acos( cos( radians(?) ) * cos( radians( ' .
                self::COLUMN_LATITUDE . ' ) ) * cos( radians( ' .
                self::COLUMN_LONGTITUDE . ' ) - radians(?) ) + sin( radians(?) ) * sin( radians( ' .
                self::COLUMN_LATITUDE . ' ) ) ) ) AS distance FROM ' .
                self::TABLE_DIVE_SHOP . ' HAVING distance < ? ORDER BY distance LIMIT ? , ?';
        $stmt = $this->conn->prepare($query);
        $maxRows = $offset + 10;
        $stmt->bind_param('dddiii', $lat, $lng, $lat, $radius, $offset, $maxRows);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
            $response['dive_shops'] = array();
            $result = $stmt->get_result();
            while ($site = $result->fetch_assoc()) {
                $site[self::COLUMN_DIVE_SHOP_ID] = $this->hashids->encode($site[self::COLUMN_DIVE_SHOP_ID]);
                array_push($response['dive_shops'], $site);
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Get Dive Shop informations
     * 
     * @param type $shopUid
     * @return array
     */
    public function getDiveShop($shopUid) {
        $response = array('error' => true, 'message' => 'An error occured while getting Dive Shop. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $query = 'SELECT ' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_CONTACT_NUMBER . ',' .
                self::COLUMN_ADDRESS . ',' .
                self::COLUMN_PRICE_PER_DIVE . ',' .
                self::COLUMN_LATITUDE . ',' .
                self::COLUMN_LONGTITUDE . ',' .
                self::COLUMN_SPECIAL_SERVICE .
                ' FROM ' . self::TABLE_DIVE_SHOP .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $shopId[0]);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response['dive_shop'] = $result->fetch_assoc();
            $response['dive_shop'][self::COLUMN_DIVE_SHOP_ID] = $shopId[0];
            $response['dive_shop']['courses'] = $this->getDiveShopCoursesList($shopId[0]);
            $response['dive_shop']['boats'] = $this->getDiveShopBoats($shopId[0]);
        }
        $stmt->close();
        return $response;
    }

    /**
     * 
     * @param type $shopId
     * @param type $offset
     * @param type $orderBy
     * @param type $sort
     * @return array Boats else return string error message
     */
    private function getDiveShopBoats($shopId, $offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC') {
        $response = array();
        $sort = $this->getSortType($sort);
        $stmt = $this->conn->prepare('SELECT ' .
                self::COLUMN_BOAT_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_BOAT .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=?' .
                " ORDER BY $orderBy $sort LIMIT ?, ?");
        $maxRow = $offset + 10;
        $stmt->bind_param('iii', $shopId, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($boat = $result->fetch_assoc()) {
                $boat[self::COLUMN_DIVE_SHOP_ID] = $shopId[0];
                array_push($response, $boat);
            }
        } else {
            return $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * 
     * @param type $shopId
     * @param type $offset
     * @param type $orderBy
     * @param type $sort
     * @return array Course else string error message
     */
    private function getDiveShopCoursesList($shopId, $offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC') {
        $response = array();
        $sort = $this->getSortType($sort);
        $query = 'SELECT ' .
                self::COLUMN_DIVE_SHOP_COURSE_ID . ',' .
                self::TABLE_DIVE_SHOP_COURSE . '.' . self::COLUMN_COURSE_ID . ',' .
                self::COLUMN_PRICE . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_PHOTO_COVER . ',' .
                self::COLUMN_OFFERED_BY .
                ' FROM ' . self::TABLE_DIVE_SHOP_COURSE .
                ' INNER JOIN ' . self::TABLE_COURSE . ' ON ' .
                self::TABLE_DIVE_SHOP_COURSE . '.' . self::COLUMN_COURSE_ID . '=' . self::TABLE_COURSE . '.' . self::COLUMN_COURSE_ID .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=?' .
                " ORDER BY $orderBy $sort LIMIT ?,?";
        $stmt = $this->conn->prepare($query);
        $maxRows = $offset + 10;
        $stmt->bind_param('iii', $shopId, $offset, $maxRows);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($course = $result->fetch_assoc()) {
                $course[self::COLUMN_DIVE_SHOP_ID] = $shopId[0];
                array_push($response, $course);
            }
        } else {
            return $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done 
     * Get Dive Shop Courses
     * 
     * @param type $shopUid
     * @param type $offset
     * @param type $orderBy
     * @param string $sort
     * @return array
     */
    public function getDiveShopCourses($shopUid, $offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC') {
        $response = array('error' => true, 'message' => 'An error occured while getting Course list. ');
        $sort = $this->getSortType($sort);
        if ($orderBy !== self::COLUMN_NAME && $orderBy !== self::COLUMN_OFFERED_BY) {
            $response['message'] = $response['message'] . 'Only order by name or offered_by is allowed.';
            return $response;
        }
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $response['courses'] = $this->getDiveShopCoursesList($shopId[0], $offset, $orderBy, $sort);
        if (is_array($response['courses'])) {
            $response['error'] = false;
            $response['message'] = 'Success';
        } else {
            $response['message'] = $response['message'] . $response['courses'];
        }
        return $response;
    }

    /**
     * Done
     * Update Dive Shop Course
     * @param type $shopUid
     * @param type $shopCourseId
     * @param type $price
     * @return array
     */
    public function updateDiveShopCourse($shopUid, $shopCourseId, $price) {
        $response = array('error' => true, 'message' => 'An error occured while updating Dive Shop Course. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id';
            return $response;
        }
        $stmt = $this->conn->prepare(
                'UPDATE ' . self::TABLE_DIVE_SHOP_COURSE . ' SET ' .
                self::COLUMN_PRICE . '=? WHERE ' .
                self::COLUMN_DIVE_SHOP_COURSE_ID . '=? AND ' .
                self::COLUMN_DIVE_SHOP_ID . '=?');
        $stmt->bind_param('dii', $price, $shopCourseId, $shopId[0]);
        if ($stmt->execute()) {
            $response['error'] = true;
            if ($stmt->affected_rows < 1) {
                $response['message'] = 'Nothing changed Dive Shop course';
            } else {
                $response['message'] = 'Successfully updated';
            }
        }
        $stmt->close();
        return $response;
    }

    /**
     * 
     * @param type $tripId
     * @return array List of guides
     */
    public function getGuides($tripId) {
        $response = array();
        $stmt = $this->conn->prepare('SELECT ' .
                self::COLUMN_DAILY_TRIP_GUIDE_ID . ',' .
                self::COLUMN_GUIDE_NAME .
                ' FROM ' . self::TABLE_DAILY_TRIP_GUIDE .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($guide = $result->fetch_assoc()) {
                array_push($guides, $guide);
            }
        }
        $stmt->close();
        return $response;
    }

    public function getDiveSitesByTripId($tripId) {
        $response = array();
        $stmt = $this->conn->prepare('SELECT ' . self::COLUMN_DIVE_SHOP . ' FROM ' . self::TABLE_DAILY_TRIP_DIVE_SITE . ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            for ($k = 0; $k < $stmt->rows; $k++) {
                $stmt->bind_result($siteId);
                $stmt->fetch();
                $stmt->prepare('SELECT ' . self::COLUMN_NAME . ',' . self::COLUMN_ADDRESS . ',' . self::COLUMN_DESCRIPTION . ' WHERE ' . self::COLUMN_DIVE_SITE_ID . '=$siteId');
                if ($stmt->execute()) {
                    $sites = array();
                    for ($y = 0; $y < $stmt->rows; $y++) {
                        $stmt->bind_result($siteName, $siteAddress, $siteDescription);
                        $stmt->fetch();
                        $sites[] = array('name' => $siteName, 'address' => $siteAddress, 'description' => $siteDescription);
                    }
                    $response[] = $sites;
                }
            }
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Add new Dive Shop Boat
     * 
     * @param type $shopUid
     * @param type $name
     * @return array
     */
    public function addBoat($shopUid, $name) {
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['error'] = true;
            $response['message'] = 'An error occured while adding Boat. Invalid dive_shop_id';
            return $response;
        }
        $response = array();
        $stmt = $this->conn->prepare('INSERT INTO ' . self::TABLE_BOAT . '(' . self::COLUMN_DIVE_SHOP_ID . ',' . self::COLUMN_NAME . ') VALUES (?,?)');
        $stmt->bind_param('is', $shopId[0], $name);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = $name . ' successfully added';
            $response['boat'] = array(
                self::COLUMN_BOAT_ID => $stmt->insert_id,
                self::COLUMN_DIVE_SHOP_ID => $shopUid,
                self::COLUMN_NAME => $name,
                self::COLUMN_IMAGE => ""
            );
        } else {
            $response['error'] = true;
            $response['message'] = 'An error occured while adding Boat. ' . $stmt->error;
            $response['shop_id'] = $shopUid;
        }
        return $response;
    }

    /**
     * Done
     * Get a list of Dive Shop Boat
     * @param type $shopUid
     * @param type $offset
     * @return array
     */
    public function getBoats($shopUid, $offset = '0') {
        $response = array('error' => true, 'message' => 'An error occured while getting list of boats.');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . ' Invalid dive_shop_id';
            return $response;
        }
        if (!ctype_digit($offset)) {
            $response['message'] = $response['message'] . ' Invalid offset "' . $offset . '"';
            return $response;
        }
        $response['boats'] = $this->getDiveShopBoats($shopId[0], $offset);
        if (is_array($response['boats'])) {
            $response['error'] = false;
            $response['message'] = 'Success';
        } else {
            $response['message'] = $response['message'] . $response['boats'];
        }
        return $response;
    }

    /**
     * Done
     * Update Dive Shop Boat
     * 
     * @param type $boatId
     * @param type $name
     * @return array
     */
    public function updateBoat($shopUid, $boatId, $name) {
        $response = array('error' => true, 'message' => 'An error occured while updating boat. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $stmt = $this->conn->prepare('UPDATE ' .
                self::TABLE_BOAT . ' SET ' .
                self::COLUMN_NAME . '=?  WHERE ' .
                self::COLUMN_DIVE_SHOP_ID . '=?' .
                ' AND ' .
                self::COLUMN_BOAT_ID . '=?');
        $stmt->bind_param('sii', $name, $shopId[0], $boatId);
        if ($stmt->execute()) {
            $stmt->close();
            $response['error'] = false;
            $response['message'] = 'Success';
            $stmt = $this->conn->prepare('SELECT ' .
                    self::COLUMN_BOAT_ID . ',' .
                    self::COLUMN_NAME . ',' .
                    self::COLUMN_IMAGE . ' FROM ' . self::TABLE_BOAT .
                    ' WHERE ' . self::COLUMN_BOAT_ID . '=?');
            $stmt->bind_param('i', $boatId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $response['boat'] = $result->fetch_assoc();
                $response['boat'][self::COLUMN_DIVE_SHOP_ID] = $shopUid;
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    /**
     * Done
     * Delete Dive Shop Boat
     * 
     * @param type $boatId
     * @return array
     */
    public function deleteBoat($shopUid, $boatId) {
        $response = array('error' => true, 'message' => 'An error occured while deleting boat. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $stmt = $this->conn->prepare(
                'DELETE FROM ' . self::TABLE_BOAT .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_BOAT_ID . '=?');
        $stmt->bind_param('ii', $shopId[0], $boatId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Successfully deleted';
            if ($stmt->affected_rows < 1) {
                $response['error'] = true;
                $response['message'] = "Boat doesn't exist";
            }
        } else {
            $response['message'] = $response['message'] . $stmt->error;
        }
        return $response;
    }

    /**
     * Done
     * Add new Dive Shop Course
     * 
     * @param type $shopUid
     * @param type $courseId
     * @param type $price
     * @return array
     */
    public function addDiveShopCourse($shopUid, $courseId, $price) {
        $response = array('error' => true, 'message' => 'An error occured while adding Dive Shop Course. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $query = 'INSERT INTO ' .
                self::TABLE_DIVE_SHOP_COURSE . '(' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_COURSE_ID . ',' .
                self::COLUMN_PRICE . ')' .
                ' VALUES (?,?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iid', $shopId[0], $courseId, $price);
        if ($stmt->execute()) {
            $diveShopCourseId = $stmt->insert_id;
            $stmt->close();
            $response['error'] = false;
            $response['message'] = "Successfully added";
            $response['course'] = array();
            $stmt = $this->conn->prepare('SELECT ' .
                    self::COLUMN_COURSE_ID . ',' .
                    self::COLUMN_NAME . ',' .
                    self::COLUMN_DESCRIPTION . ',' .
                    self::COLUMN_PHOTO_COVER . ',' .
                    self::COLUMN_OFFERED_BY .
                    ' FROM ' . self::TABLE_COURSE . ' WHERE ' . self::COLUMN_COURSE_ID . '=?');
            $stmt->bind_param('i', $courseId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $response['course'] = $result->fetch_assoc();
            }
            $response['course'][self::COLUMN_DIVE_SHOP_COURSE_ID] = $diveShopCourseId;
        } else if (strpos($stmt->error, 'Duplicate') !== false) {
            $response['message'] = $response['message'] . 'Course already exist.';
        } else {
            $response['message'] = $response['message'] . 'Course does not exist';
        }
        return $response;
    }

    function isEmpty($value) {
        return !isset($value) || strlen($value) < 1;
    }

    function requiredParams($array, $params = array()) {
        $response = array('error' => false);
        $error = false;
        $errorFields = '';
        $arraySize = count($params);
        for ($i = 0; $i < $arraySize; $i++) {
            if (array_key_exists($params[$i], $array)) {
                if (is_array($array[$params[$i]])) {
                    if (!isset($array[$params[$i]])) {
                        $error = true;
                        $errorFields .= $params[$i] . ', ';
                    }
                } else {
                    if ($this->isEmpty($array[$params[$i]])) {
                        $error = true;
                        $errorFields .= $params[$i] . ', ';
                    }
                }
            } else {
                $error = true;
                $errorFields .= $params[$i] . ', ';
            }
        }
        if ($error) {
            $response['error'] = true;
            $response['message'] = 'Required field(s) ' . substr($errorFields, 0, -2) . ' is missing or empty.';
        }
        return $response;
    }

    /**
     * Done
     * Get a list of dive shop trips
     * 
     * @param type $shopUid
     * @param type $startDate
     * @param type $endData
     * @param type $offset
     * @param type $sort
     * @param type $order
     * @return array
     */
    public function getDiveShopDiveTrips($shopUid, $startDate, $endData, $offset = 0, $sort = 'ASC', $order = self::COLUMN_DATE) {
        $response = array('error' => true, 'message' => 'An error occured while getting Dive Shop Trip list. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $sort = $this->getSortType($sort);
        $allowedOrderBy = array(self::COLUMN_GROUP_SIZE, self::COLUMN_DATE, self::COLUMN_PRICE);
        if (!in_array($order, $allowedOrderBy)) {
            $order = self::COLUMN_DATE;
        }
        if (!is_numeric($offset)) {
            $response['message'] = $response['message'] . 'Invalid offset, must be integer.';
            return $response;
        }
        $query = 'SELECT ' .
                self::COLUMN_DAILY_TRIP_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_GROUP_SIZE . ',' .
                self::COLUMN_NUMBER_OF_DIVE . ',' .
                self::COLUMN_DATE . ',' .
                self::COLUMN_PRICE . ',' .
                self::COLUMN_PRICE_NOTE .
                ' FROM ' . self::TABLE_DAILY_TRIP .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND timestamp(' . self::COLUMN_DATE . ")" .
                " BETWEEN ? AND ? ORDER BY $order  $sort  LIMIT ?,?";
        $stmt = $this->conn->prepare($query);
        $maxRows = $offset + 10;
        $stmt->bind_param('issii', $shopId[0], $startDate, $endData, $offset, $maxRows);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response['dive_trips'] = array();
            while ($trip = $result->fetch_assoc()) {
                $trip[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                array_push($response['dive_trips'], $trip);
            }
            $response['error'] = false;
            $response['message'] = 'Success';
        }
        $stmt->close();
        return $response;
    }

    public function isValidAccountType($type) {
        return ($type === AccountType::DIVER OR $type === AccountType::DIVE_SHOP);
    }

}

class AccountType {

    const DIVE_SHOP = 'dive_shop';
    const DIVER = 'diver';

}
