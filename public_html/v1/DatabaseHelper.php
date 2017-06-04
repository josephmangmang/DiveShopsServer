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
    const TABLE_GUIDE = 'guide';
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
    const COLUMN_COURSE_ID = 'course_id';
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
    const COLUMN_GUIDE_ID = 'guide_id';

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

    public function getUser($uid, $accountType) {
        $response = array('error' => true, 'message' => 'An error occured while getting user account. ');
        $type = str_replace(' ', '_', strtolower($accountType));
        $userId = $this->hashids->decode($uid);
        if (count($userId) < 1) {
            $response['message'] = $response['message'] . "Invalid account id: $uid";
            return $response;
        }
        if (!$this->isValidAccountType($type)) {
            $response['message'] = $response['message'] . "Invalid account type: $accountType";
            return $response;
        }
        switch ($type) {
            case AccountType::DIVER:
                $query = 'SELECT ' .
                        self::COLUMN_DIVER_ID . ',' .
                        self::COLUMN_NAME .
                        ' FROM ' . self::TABLE_DIVER .
                        ' WHERE ' . self::COLUMN_USER_ID . '=?';
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('i', $userId[0]);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $response[self::TABLE_USER] = $result->fetch_assoc();
                    $response[self::TABLE_USER][self::COLUMN_DIVER_ID] = $this->hashids->encode($response[self::TABLE_USER][self::COLUMN_DIVER_ID]);
                    $response[self::TABLE_USER][self::COLUMN_USER_ID] = $uid;
                    $response['error'] = false;
                    $response['message'] = 'Success';
                }
                break;
            case AccountType::DIVE_SHOP:
                $query = 'SELECT ' .
                        self::COLUMN_DIVE_SHOP_ID . ',' .
                        self::COLUMN_NAME . ',' .
                        self::COLUMN_DESCRIPTION . ',' .
                        self::COLUMN_IMAGE . ',' .
                        self::COLUMN_CONTACT_NUMBER . ',' .
                        self::COLUMN_ADDRESS . ',' .
                        self::COLUMN_PRICE_PER_DIVE . ',' .
                        self::COLUMN_LATITUDE . ',' .
                        self::COLUMN_LONGTITUDE . ',' .
                        self::COLUMN_SPECIAL_SERVICE .
                        ' FROM ' . self::TABLE_DIVE_SHOP .
                        ' WHERE ' . self::COLUMN_USER_ID . '=?';
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('i', $userId[0]);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $response[self::TABLE_DIVE_SHOP] = $result->fetch_assoc();
                    $response[self::TABLE_DIVE_SHOP][self::COLUMN_DIVE_SHOP_ID] = $this->hashids->encode($response[self::TABLE_DIVE_SHOP][self::COLUMN_DIVE_SHOP_ID]);
                    $response[self::TABLE_DIVE_SHOP][self::COLUMN_USER_ID] = $uid;
                    $response[self::TABLE_DIVE_SHOP]['courses'] = $this->getDiveShopCoursesList($userId[0]);
                    $response[self::TABLE_DIVE_SHOP]['boats'] = $this->getDiveShopBoats($userId[0]);
                    $response['error'] = false;
                    $response['message'] = 'Success';
                }
                $stmt->close();
                break;
        }
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
        $response = array('error' => true, 'message' => 'An error occured while adding Dive Trip. ');
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
            self::COLUMN_PRICE, self::COLUMN_PRICE_NOTE, 'daily_trip_dive_sites',
            'daily_trip_guides', 'daily_trip_boats'
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
            $diveSites = $tripData['daily_trip_dive_sites'];
            foreach ($diveSites as $site) {
                if (array_key_exists(self::COLUMN_DIVE_SITE_ID, $site)) {
                    $this->addDailyTripDiveSite($dailyTripId, $site[self::COLUMN_DIVE_SITE_ID]);
                }
            }

            // insert daily trip guides
            $guides = $tripData['daily_trip_guides'];
            foreach ($guides as $guide) {
                if (array_key_exists(self::COLUMN_GUIDE_ID, $guide)) {
                    $this->addDailyTripGuide($dailyTripId, $guide[self::COLUMN_GUIDE_ID]);
                }
            }

            /*
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
             */
            // insert daily trip boat
            $boats = $tripData['daily_trip_boats'];
            foreach ($boats as $boat) {
                if (array_key_exists(self::COLUMN_BOAT_ID, $boat)) {
                    $this->addDailyTripBoat($dailyTripId, $boat[self::COLUMN_BOAT_ID]);
                }
            }
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
                $response[$i]['guides'] = $this->getGuidesByTripId($tripId);
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
     * @param type $dailyTripId
     * @param type $groupSize
     * @param type $numberOfDive
     * @param type $date
     * @param type $price
     * @param type $priceNote
     * @return array
     */
    public function updateDiveShopTrip($shopUid, $dailyTripId, $dailyTripJson) {
        $response = array('error' => true, 'message' => 'An error occured while updating Dive Shop Trip. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $tripData = json_decode($dailyTripJson, true);
        if (is_null($tripData)) {
            $response['message'] = $response['message'] . 'Invalid Dive Trip data.';
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
        $stmt->bind_param('iisdsii', $tripData[self::COLUMN_GROUP_SIZE], $tripData[self::COLUMN_NUMBER_OF_DIVE], $tripData[self::COLUMN_DATE], $tripData[self::COLUMN_PRICE], $tripData[self::COLUMN_PRICE_NOTE], $dailyTripId, $shopId[0]);
        $stmt->execute();
        $stmt->close();
        // boats
        $boats = $tripData['daily_trip_boats'];
        $this->deleteDailyTripBoatsByTrip($dailyTripId);

        foreach ($boats as $boat) {
            if (array_key_exists(self::COLUMN_BOAT_ID, $boat)) {
                $this->addDailyTripBoat($dailyTripId, $boat[self::COLUMN_BOAT_ID]);
            }
        }
        // guides
        $guides = $tripData['daily_trip_guides'];
        $this->deleteDailyTripGuideByTrip($dailyTripId);
        foreach ($guides as $guide) {
            //insert
            if (array_key_exists(self::COLUMN_GUIDE_ID, $guide)) {
                $this->addDailyTripGuide($dailyTripId, $guide[self::COLUMN_GUIDE_ID]);
            }
        }
        // sites
        $sites = $tripData['daily_trip_dive_sites'];
        $this->deleteDailyTripDiveSiteByTrip($dailyTripId);
        foreach ($sites as $site) {
            //insert
            if (array_key_exists(self::COLUMN_DIVE_SITE_ID, $site)) {
                $this->addDailyTripDiveSite($dailyTripId, $site[self::COLUMN_DIVE_SITE_ID]);
            }
        }
        // guest
        // todo not recommended
        // $guests = $tripData['daily_trip_guests'];

        $response[self::TABLE_DAILY_TRIP] = $this->getDiveShopDiveTrip($shopUid, $dailyTripId);
        $response['error'] = false;
        $response['message'] = 'Success';
        return $response;
    }

    /** Done
     * Get list of course
     * @param type $offset
     * @param type $orderBy
     * @param type $sort
     * @return array 
     */
    public function getCourses($offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC', $q) {
        $response = array('error' => true, 'message' => 'An error occured while getting list of course.');


        if ($this->isEmpty($q)) {
            $sort = $this->getSortType($sort);
            if ($orderBy !== self::COLUMN_NAME && $orderBy !== self::COLUMN_OFFERED_BY) {
                $response['message'] = $response['message'] . 'Only order by name or offered_by is allowed.';
                return $response;
            }
            $query = 'SELECT ' .
                    self::COLUMN_COURSE_ID . ',' .
                    self::COLUMN_NAME . ',' .
                    self::COLUMN_DESCRIPTION . ',' .
                    self::COLUMN_IMAGE . ',' .
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
                    $response['courses'][] = $course;
                }
            }
        } else {
            $response['courses'] = $this->getCoursesByName($offset, $q);
            if (is_array($response['courses'])) {
                $response['error'] = false;
                $response['message'] = 'Success';
            } else {
                $response['message'] = $response['message'] . $response['courses'];
            }
        }

        return $response;
    }

    public function getCoursesByName($offset = '0', $q) {
        $response = array();
        $orderBy = self::COLUMN_NAME;
        $sort = 'ASC';
        $name = "%$q%";
        $stmt = $this->conn->prepare(
                'SELECT ' .
                self::COLUMN_COURSE_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE . ',' .
                self::COLUMN_OFFERED_BY .
                ' FROM ' . self::TABLE_COURSE .
                ' WHERE ' . self::COLUMN_NAME . " LIKE ? ORDER BY $orderBy $sort LIMIT ?, ?");
        $maxRow = $offset + 10;
        $stmt->bind_param('sii', $name, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($course = $result->fetch_assoc()) {
                $response[] = $course;
            }
        } else {
            $response = $stmt->error;
            return $response;
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
                $response['dive_sites'][] = $site;
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
                self::COLUMN_IMAGE . ',' .
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
                $response['dive_shops'][] = $site;
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
                self::COLUMN_IMAGE . ',' .
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
            $response['dive_shop'][self::COLUMN_DIVE_SHOP_ID] = $shopUid;
            $response['dive_shop']['courses'] = $this->getDiveShopCoursesList($shopId[0]);
            $response['dive_shop']['boats'] = $this->getDiveShopBoats($shopId[0]);
            $response['dive_shop']['guides'] = $this->getDiveShopGuides($shopId[0]);
            $response['error'] = false;
            $response['message'] = 'Success';
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
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_BOAT .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=?' .
                " ORDER BY $orderBy $sort LIMIT ?, ?");
        $maxRow = $offset + 10;
        $stmt->bind_param('iii', $shopId, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $shopUid = $this->hashids->encode($shopId);
            while ($boat = $result->fetch_assoc()) {
                $boat[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                $response[] = $boat;
            }
        } else {
            return $stmt->error;
        }
        $stmt->close();
        return $response;
    }

    private function getDiveShopBoatsByName($shopId, $offset = 0, $q) {
        $orderBy = self::COLUMN_NAME;
        $sort = 'ASC';
        $name = "%" . $q . "%";

        $response = array();
        $sort = $this->getSortType($sort);
        $query = 'SELECT ' .
                self::COLUMN_BOAT_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_BOAT .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_NAME . " LIKE ? " .
                " ORDER BY $orderBy $sort LIMIT ?, ?";
        $stmt = $this->conn->prepare($query);
        $maxRow = $offset + 10;
        $stmt->bind_param('isii', $shopId, $name, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $shopUid = $this->hashids->encode($shopId);
            while ($boat = $result->fetch_assoc()) {
                $boat[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                $response[] = $boat;
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
                self::COLUMN_IMAGE . ',' .
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
                $course[self::COLUMN_DIVE_SHOP_ID] = $this->hashids->encode($shopId);
                $response[] = $course;
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
    public function updateDiveShopCourse($shopUid, $shopCourseId, $courseId, $price) {
        $response = array('error' => true, 'message' => 'An error occured while updating Dive Shop Course. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id';
            return $response;
        }
        if (!is_numeric($price)) {
            $response['message'] = $response['message'] . 'Invalid price';
            return $response;
        }
        $stmt = $this->conn->prepare(
                'UPDATE ' . self::TABLE_DIVE_SHOP_COURSE . ' SET ' .
                self::COLUMN_PRICE . '=?,' . self::COLUMN_COURSE_ID .
                '=? WHERE ' . self::COLUMN_DIVE_SHOP_COURSE_ID . '=? AND ' .
                self::COLUMN_DIVE_SHOP_ID . '=?');
        $stmt->bind_param('diii', $price, $courseId, $shopCourseId, $shopId[0]);
        if ($stmt->execute()) {
            $response['error'] = false;
            if ($stmt->affected_rows < 1) {
                $response['message'] = 'Nothing changed Dive Shop course';
            } else {
                $response['message'] = 'Successfully updated';
            }
        } else if (strpos($stmt->error, 'Duplicate') !== false) {
            $response['message'] = $response['message'] . 'Course already exist';
        }
        $stmt->close();
        return $response;
    }

    /**
     * 
     * @param type $tripId
     * @return array List of guides
     */
    public function getGuidesByTripId($tripId) {
        $response = array();
        $stmt = $this->conn->prepare('SELECT ' .
                self::COLUMN_DAILY_TRIP_GUIDE_ID . ',' .
                self::COLUMN_NAME .
                ' FROM ' . self::TABLE_DAILY_TRIP_GUIDE .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($guide = $result->fetch_assoc()) {
                $guides[] = $guide;
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
    public function addBoat($shopUid, $name, $description) {
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['error'] = true;
            $response['message'] = 'An error occured while adding Boat. Invalid dive_shop_id';
            return $response;
        }
        $response = array();
        $stmt = $this->conn->prepare('INSERT INTO ' . self::TABLE_BOAT . '(' . self::COLUMN_DIVE_SHOP_ID . ',' . self::COLUMN_NAME . ',' . self::COLUMN_DESCRIPTION . ') VALUES (?,?,?)');
        $stmt->bind_param('iss', $shopId[0], $name, $description);
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
    public function getBoats($shopUid, $offset = '0', $q = '') {
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
        if ($this->isEmpty($q)) {
            $response['boats'] = $this->getDiveShopBoats($shopId[0], $offset);
        } else {
            $response['boats'] = $this->getDiveShopBoatsByName($shopId[0], $offset, $q);
        }
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
    public function updateBoat($shopUid, $boatId, $name, $description) {
        $response = array('error' => true, 'message' => 'An error occured while updating boat. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid Dive Shop id.';
            return $response;
        }
        $stmt = $this->conn->prepare('UPDATE ' .
                self::TABLE_BOAT . ' SET ' .
                self::COLUMN_NAME . '=?, ' .
                self::COLUMN_DESCRIPTION . '=?  WHERE ' .
                self::COLUMN_DIVE_SHOP_ID . '=?' .
                ' AND ' .
                self::COLUMN_BOAT_ID . '=?');
        $stmt->bind_param('ssii', $name, $description, $shopId[0], $boatId);
        if ($stmt->execute()) {
            $stmt->close();
            $response['error'] = false;
            $response['message'] = 'Success';
            $stmt = $this->conn->prepare('SELECT ' .
                    self::COLUMN_BOAT_ID . ',' .
                    self::COLUMN_NAME . ',' .
                    self::COLUMN_DESCRIPTION . ',' .
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
                    self::COLUMN_IMAGE . ',' .
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
    public function getDiveShopDiveTrips($shopUid, $diveSiteId, $startDate, $endData, $offset = 0, $sort = 'ASC', $order = self::COLUMN_DATE) {
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
        $maxRows = $offset + 10;
        if ($this->isEmpty($diveSiteId) || $diveSiteId == -1) {
            $query = 'SELECT ' .
                    self::COLUMN_DAILY_TRIP_ID . ',' .
                    self::COLUMN_DIVE_SHOP_ID . ',' .
                    self::COLUMN_GROUP_SIZE . ',' .
                    self::COLUMN_NUMBER_OF_DIVE . ',' .
                    self::COLUMN_DATE . ',' .
                    self::COLUMN_CREATE_TIME . ',' .
                    self::COLUMN_PRICE . ',' .
                    self::COLUMN_PRICE_NOTE .
                    ' FROM ' . self::TABLE_DAILY_TRIP .
                    ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' .
                    self::COLUMN_DATE . " >= ? AND " . self::COLUMN_DATE . " <= ? ORDER BY $order  $sort  LIMIT ?,?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('issii', $shopId[0], $startDate, $endData, $offset, $maxRows);
        } else {
            $query = 'SELECT ' .
                    'd.' . self::COLUMN_DAILY_TRIP_ID . ',' .
                    'd.' . self::COLUMN_DIVE_SHOP_ID . ',' .
                    'd.' . self::COLUMN_GROUP_SIZE . ',' .
                    'd.' . self::COLUMN_NUMBER_OF_DIVE . ',' .
                    'd.' . self::COLUMN_DATE . ',' .
                    'd.' . self::COLUMN_PRICE . ',' .
                    'd.' . self::COLUMN_PRICE_NOTE .
                    ' FROM ' . self::TABLE_DAILY_TRIP . ' d' .
                    ' INNER JOIN ' . self::TABLE_DAILY_TRIP_DIVE_SITE . ' s' .
                    ' ON d.' . self::COLUMN_DAILY_TRIP_ID . '= s.' . self::COLUMN_DAILY_TRIP_ID .
                    ' WHERE d.' . self::COLUMN_DIVE_SHOP_ID . '=? AND s.' .
                    self::COLUMN_DIVE_SITE_ID . '=? AND ' .
                    self::COLUMN_DATE . ' >= ? AND ' .
                    self::COLUMN_DATE . " <= ? ORDER BY $order  $sort  LIMIT ?,?";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('iissii', $shopId[0], $diveSiteId, $startDate, $endData, $offset, $maxRows);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response['daily_trips'] = array();
            while ($trip = $result->fetch_assoc()) {
                $trip[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                $trip['daily_trip_boats'] = $this->getDailyTripBoats($trip[self::COLUMN_DAILY_TRIP_ID]);
                $trip['daily_trip_guides'] = $this->getDailyTripGuides($trip[self::COLUMN_DAILY_TRIP_ID]);
                $trip['daily_trip_dive_sites'] = $this->getDailyTripDiveSites($trip[self::COLUMN_DAILY_TRIP_ID]);
                $trip['daily_trip_guests'] = $this->getDailyTripGuests($trip[self::COLUMN_DAILY_TRIP_ID]);
                $response['daily_trips'][] = $trip;
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

    public function getDiveSitesByName($searchName, $offset = 0) {
        $response = array('error' => true, 'message' => 'An error occured while getting dive sites. ');
        $name = "%" . $searchName . "%";
        $stmt = $this->conn->prepare('SELECT ' .
                self::COLUMN_DIVE_SITE_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_ADDRESS .
                ' FROM ' . self::TABLE_DIVE_SITE .
                ' WHERE ' . self::COLUMN_NAME . " LIKE ? LIMIT ?,?");
        $maxRows = $offset + 10;
        $stmt->bind_param('sii', $name, $offset, $maxRows);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response['error'] = false;
            $response['message'] = 'Success';
            $response['dive_sites'] = array();
            while ($site = $result->fetch_assoc()) {
                $response['dive_sites'][] = $site;
            }
        }
        return $response;
    }

    public function getDailyTripBoats($tripId) {
        $boats = array();
        $query = 'SELECT ' .
                'd.' . self::COLUMN_DAILY_TRIP_ID . ',' .
                'd.' . self::COLUMN_DAILY_TRIP_BOAT_ID . ',' .
                'd.' . self::COLUMN_BOAT_ID . ',' .
                'b.' . self::COLUMN_DIVE_SHOP_ID . ',' .
                'b.' . self::COLUMN_NAME . ',' .
                'b.' . self::COLUMN_DESCRIPTION . ',' .
                'b.' . self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_DAILY_TRIP_BOAT . ' d' .
                ' INNER JOIN ' . self::TABLE_BOAT . ' b' .
                ' ON d.' . self::COLUMN_BOAT_ID . '= b.' . self::COLUMN_BOAT_ID .
                ' WHERE d.' . self::COLUMN_DAILY_TRIP_ID . '=?';

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($boat = $result->fetch_assoc()) {
                $boat[self::COLUMN_DIVE_SHOP_ID] = $this->hashids->encode($boat[self::COLUMN_DIVE_SHOP_ID]);
                $boats[] = $boat;
            }
        }

        return $boats;
    }

    public function getDailyTripGuides($tripId) {
        $guides = array();
        $query = 'SELECT ' .
                't.' . self::COLUMN_DAILY_TRIP_ID . ',' .
                't.' . self::COLUMN_DAILY_TRIP_GUIDE_ID . ',' .
                'g.' . self::COLUMN_NAME . ',' .
                'g.' . self::COLUMN_DESCRIPTION . ',' .
                'g.' . self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_DAILY_TRIP_GUIDE . ' t' .
                ' INNER JOIN ' . self::TABLE_GUIDE . ' g' .
                ' ON t.' . self::COLUMN_GUIDE_ID . '= g.' . self::COLUMN_GUIDE_ID .
                ' WHERE t.' . self::COLUMN_DAILY_TRIP_ID . '=?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($guide = $result->fetch_assoc()) {
                $guides[] = $guide;
            }
        }
        return $guides;
    }

    public function getDailyTripDiveSites($tripId) {
        $sites = array();
        $query = 'SELECT ' .
                'd.' . self::COLUMN_DAILY_TRIP_DIVE_SITE_ID . ',' .
                'd.' . self::COLUMN_DAILY_TRIP_ID . ',' .
                'd.' . self::COLUMN_DIVE_SITE_ID . ', ' .
                's.' . self::COLUMN_NAME . ',' .
                's.' . self::COLUMN_DESCRIPTION . ',' .
                's.' . self::COLUMN_ADDRESS . ',' .
                's.' . self::COLUMN_LATITUDE . ',' .
                's.' . self::COLUMN_LONGTITUDE .
                ' FROM ' . self::TABLE_DAILY_TRIP_DIVE_SITE . ' d' .
                ' INNER JOIN ' . self::TABLE_DIVE_SITE . ' s' .
                ' ON d.' . self::COLUMN_DIVE_SITE_ID . '= s.' . self::COLUMN_DIVE_SITE_ID .
                ' WHERE d.' . self::COLUMN_DAILY_TRIP_ID . '=?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($site = $result->fetch_assoc()) {
                $sites[] = $site;
            }
        }
        return $sites;
    }

    public function getDailyTripGuests($tripId) {
        $guests = array();
        $query = 'SELECT ' .
                'd.' . self::COLUMN_DAILY_TRIP_GUEST_ID . ',' .
                'd.' . self::COLUMN_DAILY_TRIP_ID . ',' .
                'd.' . self::COLUMN_DIVER_ID . ',' .
                'u.' . self::COLUMN_NAME .
                ' FROM ' . self::TABLE_DAILY_TRIP_GUEST . ' d' .
                ' INNER JOIN ' . self::TABLE_DIVER . ' u' .
                ' ON d.' . self::COLUMN_DIVER_ID . '= u.' . self::COLUMN_DIVER_ID .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $tripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($guest = $result->fetch_assoc()) {
                $guest[self::COLUMN_DIVER_ID] = $this->hashids->encode($guest[self::COLUMN_DIVER_ID]);
                $guests[] = $guest;
            }
        }
        return $guests;
    }

    public function getDiveShopGuide($shopUid, $guideId) {
        $response = array('error' => true, 'message' => 'An error occured while getting guide list. ');

        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid dive shop id';
            return $response;
        }
        $stmt = $this->conn->prepare(
                'SELECT ' .
                self::COLUMN_GUIDE_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_GUIDE .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_GUIDE_ID . '=?');
        $stmt->bind_param('ii', $shopId[0], $guideId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
            $result = $stmt->get_result();
            while ($guide = $result->fetch_assoc()) {
                $guide[self::COLUMN_DIVE_SHOP_ID] = $this->hashids->encode($guide[self::COLUMN_DIVE_SHOP_ID]);
                $response[self::TABLE_GUIDE] = $guide;
            }
        }
        return $response;
    }

    public function getGuides($shopUid, $offset = '0', $q = '') {
        $response = array('error' => true, 'message' => 'An error occured while getting guide list. ');

        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid dive shop id';
            return $response;
        }
        if ($this->isEmpty($q)) {
            $response['guides'] = $this->getDiveShopGuides($shopId[0], $offset);
        } else {
            $response['guides'] = $this->getDiveShopGuidesByName($shopId[0], $offset, $q);
        }
        if (is_array($response['guides'])) {
            $response['error'] = false;
            $response['message'] = 'Success';
        } else {
            $response['message'] = $response['message'] . $response['message'];
        }
        return $response;
    }

    public function addGuide($shopUid, $name, $description) {
        $response = array('error' => true, 'message' => 'An error occurred while adding guide. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid diveshop id';
            return $response;
        }

        $stmt = $this->conn->prepare('INSERT INTO ' .
                self::TABLE_GUIDE . '(' . self::COLUMN_NAME . ','
                . self::COLUMN_DIVE_SHOP_ID . ',' . self::COLUMN_DESCRIPTION . ') VALUES(?,?,?)');
        $stmt->bind_param('si', $name, $shopId[0], $description);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
            $guideId = $stmt->insert_id;
            $stmt->close();
            $stmt = $this->conn->prepare('SELECT ' .
                    self::COLUMN_IMAGE . ' FROM ' . self::TABLE_GUIDE . ' WHERE ' . self::COLUMN_GUIDE_ID . "= $guideId"
            );
            if ($stmt->execute()) {
                $stmt->bind_result($image);
                $stmt->fetch();
                $guide = array(
                    self::COLUMN_GUIDE_ID => $guideId,
                    self::COLUMN_NAME => $name,
                    self::COLUMN_DESCRIPTION => $description,
                    self::COLUMN_IMAGE => $image,
                    self::COLUMN_DIVE_SHOP_ID => $shopUid
                );
                $response[self::TABLE_GUIDE] = $guide;
            }
        }
        return $response;
    }

    public function updateGuide($shopUid, $guideId, $name, $description) {
        $response = array('error' => false, 'message' => 'An error occurred while updating guide. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid dive shop id';
        }
        $stmt = $this->conn->prepare(
                'UPDATE ' . self::TABLE_GUIDE .
                ' SET ' . self::COLUMN_NAME . '=?,' . self::COLUMN_DESCRIPTION . '=? WHERE '
                . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_GUIDE_ID . '=?');
        $stmt->bind_param('sii', $name, $description, $shopId[0], $guideId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
            $stmt->close();
            $stmt = $this->conn->prepare('SELECT ' .
                    self::COLUMN_IMAGE . ' FROM ' . self::TABLE_GUIDE . ' WHERE ' . self::COLUMN_GUIDE_ID . "= $guideId"
            );
            if ($stmt->execute()) {
                $stmt->bind_result($image);
                $stmt->fetch();
                $guide = array(
                    self::COLUMN_GUIDE_ID => $guideId,
                    self::COLUMN_NAME => $name,
                    self::COLUMN_DESCRIPTION => $description,
                    self::COLUMN_IMAGE => $image,
                    self::COLUMN_DIVE_SHOP_ID => $shopUid
                );
                $response[self::TABLE_GUIDE] = $guide;
            }
        }
        return $response;
    }

    public function deleteGuide($shopUid, $guideId) {
        $response = array('error' => true, 'message' => 'An error occurred while deleting guide. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . ' Invalid dive shop id';
            return $response;
        }
        $stmt = $this->conn->prepare('DELETE FROM ' . self::TABLE_GUIDE .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_GUIDE_ID . '=?');
        $stmt->bind_param('ii', $shopId[0], $guideId);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
        }
        return $response;
    }

    public function addDailyTripDiveSite($dailyTripId, $diveSiteId) {
        $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_DIVE_SITE . ' (' .
                self::COLUMN_DAILY_TRIP_ID . ',' . self::COLUMN_DIVE_SITE_ID . ') VALUES(?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $dailyTripId, $diveSiteId);
        $stmt->execute();
        $stmt->close();
    }

    public function addDailyTripGuide($dailyTripId, $guideId) {
        $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_GUIDE . ' (' .
                self::COLUMN_DAILY_TRIP_ID . ',' .
                self::COLUMN_GUIDE_ID . ') VALUES(?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $dailyTripId, $guideId);
        $stmt->execute();
        $stmt->close();
    }

    public function addDailyTripBoat($dailyTripId, $boatId) {
        $query = 'INSERT INTO ' . self::TABLE_DAILY_TRIP_BOAT . ' (' .
                self::COLUMN_DAILY_TRIP_ID . ',' .
                self::COLUMN_BOAT_ID . ') VALUES (?,?)';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $dailyTripId, $boatId);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteDiveShopDailyTrips($shopUid, $dailyTripIds) {
        $response = array('error' => true, 'message' => 'An error occurred while deleting daily trips. ');
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            $response['message'] = $response['message'] . 'Invalid dive shop id.';
            return $response;
        }
        $ids = implode(',', $dailyTripIds);
        $stmt = $this->conn->prepare('DELETE FROM ' .
                self::TABLE_DAILY_TRIP .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' . self::COLUMN_DAILY_TRIP_ID . ' IN (?)');
        $stmt->bind_param('is', $shopId[0], $ids);
        if ($stmt->execute()) {
            $response['error'] = false;
            $response['message'] = 'Success';
        }
        return $response;
    }

    public function updateDailyTripBoat($dailyTripId, $tripBoatId, $boatId) {
        $stmt = $this->conn->prepare('UPDATE ' . self::TABLE_DAILY_TRIP_BOAT .
                ' SET ' . self::COLUMN_BOAT_ID . '=? WHERE ' .
                self::COLUMN_DAILY_TRIP_ID . '=? AND ' . self::COLUMN_DAILY_TRIP_BOAT_ID . '=?');
        $stmt->bind_param('iii', $boatId, $dailyTripId, $tripBoatId);
        $stmt->execute();
    }

    public function updateDailyTripGuide($dailyTripId, $tripGuideId, $guideId) {
        $stmt = $this->conn->prepare('UPDATE ' . self::TABLE_DAILY_TRIP_GUIDE .
                ' SET ' . self::COLUMN_GUIDE_ID . '=? WHERE ' .
                self::COLUMN_DAILY_TRIP_ID . '=? AND ' . self::COLUMN_DAILY_TRIP_GUIDE_ID . '=?');
        $stmt->bind_param('iii', $guideId, $dailyTripId, $tripGuideId);
        $stmt->execute();
    }

    public function updateDailyTripDiveSite($dailyTripId, $tripDiveSiteId, $diveSiteId) {
        $stmt = $this->conn->prepare('UPDATE ' . self::TABLE_DAILY_TRIP_DIVE_SITE .
                ' SET ' . self::COLUMN_DIVE_SITE_ID . '=? WHERE ' .
                self::COLUMN_DAILY_TRIP_ID . '=? AND ' . self::COLUMN_DAILY_TRIP_DIVE_SITE_ID . '=?');
        $stmt->bind_param('iii', $guideId, $dailyTripId, $tripDiveSiteId);
        $stmt->execute();
    }

    public function getDiveShopDiveTrip($shopUid, $dailyTripId) {
        $response = array();
        $shopId = $this->hashids->decode($shopUid);
        if (count($shopId) < 1) {
            return false;
        }
        $query = 'SELECT ' .
                self::COLUMN_DAILY_TRIP_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_GROUP_SIZE . ',' .
                self::COLUMN_NUMBER_OF_DIVE . ',' .
                self::COLUMN_DATE . ',' .
                self::COLUMN_CREATE_TIME . ',' .
                self::COLUMN_PRICE . ',' .
                self::COLUMN_PRICE_NOTE .
                ' FROM ' . self::TABLE_DAILY_TRIP .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' .
                self::COLUMN_DAILY_TRIP_ID . "=?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $shopId[0], $dailyTripId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response = $result->fetch_assoc();
            $response[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
            $response['daily_trip_boats'] = $this->getDailyTripBoats($response[self::COLUMN_DAILY_TRIP_ID]);
            $response['daily_trip_guides'] = $this->getDailyTripGuides($response[self::COLUMN_DAILY_TRIP_ID]);
            $response['daily_trip_dive_sites'] = $this->getDailyTripDiveSites($response[self::COLUMN_DAILY_TRIP_ID]);
            $response['daily_trip_guests'] = $this->getDailyTripGuests($response[self::COLUMN_DAILY_TRIP_ID]);
        }
        return $response;
    }

    public function deleteDailyTripBoatsByTrip($dailyTripId) {
        $stmt = $this->conn->prepare(
                'DELETE FROM ' . self::TABLE_DAILY_TRIP_BOAT .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $dailyTripId);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteDailyTripGuideByTrip($dailyTripId) {
        $stmt = $this->conn->prepare(
                'DELETE FROM ' . self::TABLE_DAILY_TRIP_GUIDE .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $dailyTripId);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteDailyTripDiveSiteByTrip($dailyTripId) {
        $stmt = $this->conn->prepare(
                'DELETE FROM ' . self::TABLE_DAILY_TRIP_DIVE_SITE .
                ' WHERE ' . self::COLUMN_DAILY_TRIP_ID . '=?');
        $stmt->bind_param('i', $dailyTripId);
        $stmt->execute();
        $stmt->close();
    }

    public function getDiveShopGuides($shopId, $offset = 0, $orderBy = self::COLUMN_NAME, $sort = 'ASC') {
        $response = array();
        $stmt = $this->conn->prepare(
                'SELECT ' .
                self::COLUMN_GUIDE_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_GUIDE .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=?' .
                " ORDER BY $orderBy $sort LIMIT ?, ?");
        $maxRow = $offset + 10;
        $stmt->bind_param('iii', $shopId, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $shopUid = $this->hashids->encode($shopId);
            while ($guide = $result->fetch_assoc()) {
                $guide[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                $response[] = $guide;
            }
        } else {
            $response['message'] = $stmt->error;
            return $response;
        }
        $stmt->close();
        return $response;
    }

    public function getDiveShopGuidesByName($shopId, $offset = 0, $q) {
        $response = array();
        $orderBy = self::COLUMN_NAME;
        $sort = 'ASC';
        $name = "%$q%";
        $stmt = $this->conn->prepare(
                'SELECT ' .
                self::COLUMN_GUIDE_ID . ',' .
                self::COLUMN_DIVE_SHOP_ID . ',' .
                self::COLUMN_NAME . ',' .
                self::COLUMN_DESCRIPTION . ',' .
                self::COLUMN_IMAGE .
                ' FROM ' . self::TABLE_GUIDE .
                ' WHERE ' . self::COLUMN_DIVE_SHOP_ID . '=? AND ' .
                self::COLUMN_NAME . " LIKE ? ORDER BY $orderBy $sort LIMIT ?, ?");
        $maxRow = $offset + 10;
        $stmt->bind_param('isii', $shopId, $name, $offset, $maxRow);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $shopUid = $this->hashids->encode($shopId);
            while ($guide = $result->fetch_assoc()) {
                $guide[self::COLUMN_DIVE_SHOP_ID] = $shopUid;
                $response[] = $guide;
            }
        } else {
            $response['message'] = $stmt->error;
            return $response;
        }
        $stmt->close();
        return $response;
    }

}

class AccountType {

    const DIVE_SHOP = 'dive_shop';
    const DIVER = 'diver';

}
