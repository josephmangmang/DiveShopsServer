<?php

/*
 * Copyright 2017 Joseph Mangmang.
 * Created 15-Feb-2017
 *
 */
require '../../vendor/autoload.php';
include_once __DIR__ . '/../../include/Config.php';
require __DIR__ . '/DatabaseHelper.php';

if (DEBUG) {
    error_reporting(-1);
    ini_set('display_errors', 'On');
}

// Initialize Slim Framework
$app = new \Slim\Slim(array('debug' => DEBUG));

/**
 * Test descrypt method
 */
use Hashids\Hashids;

$app->get('/test/descrypt', function() use($app) {
    $val = $app->request->params('val');
    $hashids = new Hashids('', 20);
    $decode = $hashids->decode($val);
    if (count($decode) > 0) {
        echo $decode[0];
    } else {
        echo -1;
    }
});

/**
 * Test encrypt method
 */
$app->get('/test/encrypt', function() use($app) {
    $val = $app->request->params('val');
    $hashids = new Hashids('', 20);
    echo $hashids->encode($val);
});

/* Done
 * Diver and Dive Shop registration
 * @param email, password, type
 */
$app->post('/register', function() use($app) {
    verifyRequiredParams(array('email', 'password', 'type'));
    $email = $app->request->params('email');
    $password = $app->request->params('password');
    $type = $app->request->params('type');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->register($email, $password, $type);
    echoResponse(200, $response);
});

/* Done
 * Login user
 * @param email, password, type
 */
$app->post('/login', function() use($app) {
    verifyRequiredParams(array('email', 'password'));
    $email = $app->request->params('email');
    $password = $app->request->params('password');

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->login($email, $password);
    echoResponse(200, $response);
});

$app->get('/users/:accountType/:uid', function( $accountType, $uid) use ($app) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getUser($uid, $accountType);
    echoResponse(200, $response);
});

/** NOT YET
 * Get a list of daily trips and filter by dive site
 * Required parameters: 
 * start_Date, end_Date, offset, sort {sort=price or rating, order=ASC or DESC}
 *
 */
$app->get('/trips', function() use($app) {
    $requiredParams = array('start_date', 'end_date', 'offset', 'sort', 'order');
    verifyRequiredParams($requiredParams);
    $startDate = $app->request->params($requiredParams[0]);
    $endDate = $app->request->params($requiredParams[1]);
    $offset = $app->request->params($requiredParams[2]);
    $sort = $app->request->params($requiredParams[3]);
    $order = $app->request->params($requiredParams[4]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveTrips($startDate, $endDate, $offset, $sort, $order);
    echoResponse(200, $response);
});

/** Done
 * Get a list of Course
 * sort by price/rating
 * order by ASC/DESC
 */
$app->get('/courses', function() use($app) {
    $requiredParams = array('offset', 'sort', 'order');
    $offset = $app->request->params($requiredParams[0]);
    $sort = $app->request->params($requiredParams[1]);
    $order = $app->request->params($requiredParams[2]);
    if (isEmpty($offset)) {
        $offset = "0";
    }
    if (isEmpty($sort)) {
        $sort = 'ASC';
    }
    if (isEmpty($order)) {
        $order = 'name';
    }
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getCourses($offset, $order, $sort);
    echoResponse(200, $response);
});

/** Done
 * Add new Course
 * Only DiveTym Admin can add new course
 */
$app->post('/courses', function() use($app) {
    $requiredParams = array('name', 'description', 'offered_by');
    verifyRequiredParams(array('name', 'offered_by'));
    $name = $app->request->params($requiredParams[0]);
    $description = $app->request->params($requiredParams[1]);
    $offeredBy = $app->request->params($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addCourse($name, $description, $offeredBy);
    echoResponse(200, $response);
});

/** Done
 * Update selected course
 */
$app->put('/courses/:courseId', function($courseId) use($app) {
    $requiredParams = array('name', 'description', 'offered_by');
    verifyRequiredParams($requiredParams);
    $name = $app->request->put($requiredParams[0]);
    $description = $app->request->put($requiredParams[1]);
    $offeredBy = $app->request->put($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateCourse($courseId, $name, $description, $offeredBy);
    echoResponse(200, $response);
});


/** Done
 * Get a list of Dive Site base on location
 */
$app->get('/sites', function() use($app) {
    $lat = $app->request->params('lat');
    $lng = $app->request->params('lng');
    $radius = $app->request->params('radius');
    $offset = $app->request->params('offset');
    if (!isset($offset) || strlen($offset) <= 0) {
        $offset = '0';
    }
    if (!isset($radius) || strlen($radius)) {
        $radius = 25;
    }
    $databaseHelper = new DatabaseHelper();
    if (isEmpty($lat) && isEmpty($lng)) {
        verifyRequiredParams(array('q'));
        $searchName = $app->request->params('q');
        $response = $databaseHelper->getDiveSitesByName($searchName, $offset);
    } else {
        $response = $databaseHelper->getDiveSites($lat, $lng, $radius, $offset);
    }
    echoResponse(200, $response);
});

/** Done
 * Add new Dive Site
 */
$app->post('/sites', function() use($app) {
    $requiredParams = array('name', 'description', 'address', 'latitude', 'longitude');
    verifyRequiredParams($requiredParams);
    $name = $app->request->params($requiredParams[0]);
    $description = $app->request->params($requiredParams[1]);
    $address = $app->request->params($requiredParams[2]);
    $latitude = $app->request->params($requiredParams[3]);
    $longitude = $app->request->params($requiredParams[4]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveSite($name, $description, $address, $latitude, $longitude);
    echoResponse(200, $response);
});

/** Done
 * Update Dive Site
 */
$app->put('/sites/:siteId', function($siteId) use($app) {
    $requiredParams = array('name', 'description', 'address', 'latitude', 'longitude');
    verifyRequiredParams($requiredParams);
    $name = $app->request->put($requiredParams[0]);
    $description = $app->request->put($requiredParams[1]);
    $address = $app->request->put($requiredParams[2]);
    $latitude = $app->request->put($requiredParams[3]);
    $longitude = $app->request->put($requiredParams[4]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveSite($siteId, $name, $description, $address, $latitude, $longitude);
    echoResponse(200, $response);
});

/** Done
 * Get a list of Dive Shop
 */
$app->get('/diveshops', function() use($app) {
    verifyRequiredParams(array('lat', 'lng'));
    $lat = $app->request->params('lat');
    $lng = $app->request->params('lng');
    $radius = $app->request->params('radius');
    $offset = $app->request->params('offset');
    if (!isset($offset) || strlen($offset) <= 0) {
        $offset = '0';
    }
    if (!isset($radius) || strlen($radius)) {
        $radius = 25;
    }
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShops($lat, $lng, $radius, $offset);
    echoResponse(200, $response);
});

/** Done
 * Get dive shop informations
 */
$app->get('/diveshops/:shopUid', function($shopUid) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShop($shopUid);
    echoResponse(200, $response);
});

/** Done
 * Get dive shop courses
 */
$app->get('/diveshops/:shopUid/courses', function($shopUid) use ($app) {
    $requiredParams = array('offset', 'sort', 'order');
    $offset = $app->request->params($requiredParams[0]);
    $sort = $app->request->params($requiredParams[1]);
    $order = $app->request->params($requiredParams[2]);
    if (isEmpty($offset)) {
        $offset = '0';
    }
    if (isEmpty($sort)) {
        $sort = 'ASC';
    }
    if (isEmpty($order)) {
        $order = 'name';
    }
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopCourses($shopUid, $offset, $order, $sort);
    echoResponse(200, $response);
});

/** Done
 * Update Dive Shop course
 */
$app->put('/diveshops/:shopUid/courses/:shopCourseId', function($shopUid, $shopCourseId) use($app) {
    $requiredParams = array('price');
    verifyRequiredParams($requiredParams);
    $price = $app->request->put($requiredParams[0]);
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveShopCourse($shopUid, $shopCourseId, $price);
    echoResponse(200, $response);
});
/** Done
 * Add course on dive shop
 */
$app->post('/diveshops/:shopUid/courses', function($shopUid) use($app) {
    $requiredParams = array('course_id', 'price');
    verifyRequiredParams($requiredParams);
    $courseId = $app->request->params($requiredParams[0]);
    $price = $app->request->params($requiredParams[1]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveShopCourse($shopUid, $courseId, $price);
    echoResponse(200, $response);
});


/** Done
 * Add new boat
 */
$app->post('/diveshops/:shopUid/boats', function ($shopUid) use ($app) {
    verifyRequiredParams(array('name', 'description'));
    $name = $app->request->params('name');
    $description = $app->request->params('description');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addBoat($shopUid, $name, $description);
    echoResponse(200, $response);
});

/** Done
 * Get a list of boats
 */
$app->get('/diveshops/:shopUid/boats', function($shopUid) use ($app) {

    $offset = $app->request->params('offset');
    if (!isset($offset) || strlen($offset) <= 0) {
        $offset = '0';
    }
    $q = $app->request->params('q');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getBoats($shopUid, $offset, $q);
    echoResponse(200, $response);
});

/** Done
 * Update boat
 */
$app->put('/diveshops/:shopUid/boats/:boatId', function($shopUid, $boatId) use ($app) {
    verifyRequiredParams(array('name', 'description'));
    $name = $app->request->put('name');
    $description = $app->request->put('description');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateBoat($shopUid, $boatId, $name, $description);
    echoResponse(200, $response);
});

/** Done
 * Delete boat
 */
$app->delete('/diveshops/:shopUid/boats/:boatId', function($shopUid, $boatId) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->deleteBoat($shopUid, $boatId);
    echoResponse(200, $response);
});

/** Done
 * Get list of Dive Shop Dive Trips
 */
$app->get('/diveshops/:shopUid/trips', function($shopUid) use ($app) {
    verifyRequiredParams(array('start_date', 'end_date'));
    $startDate = $app->request->params('start_date');
    $endData = $app->request->params('end_date');
    $offset = $app->request->params('offset');
    $sort = $app->request->params('sort');
    $order = $app->request->params('order');
    $diveSiteId = $app->request->params('dive_site_id');
    if (isEmpty($offset)) {
        $offset = 0;
    }
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopDiveTrips($shopUid, $diveSiteId, $startDate, $endData, $offset, $sort, $order);
    echoResponse(200, $response);
});

/** Done
 * Add new Daily Trip
 * 
 * Required Parameters:
 * json body 
 */
$app->post('/diveshops/:shopUid/trips', function($shopUid) use($app) {
    $tripJson = $app->request->getBody();
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveTrip($shopUid, $tripJson);
    echoResponse(200, $response);
});

/** Done
 * Update selected dive trip
 * 
 * 'group_size'     int         max diver,
 * 'number_of_dive' int         number of dive,
 * 'date'           long        timestamp of dive,
 * 'price'          double      price of the trip,
 * 'price_note'     string      special notes for the listed price,
 */
$app->put('/diveshops/:shopUid/trips/:tripId', function($shopUid, $tripId) use($app) {
    $requiredParams = array('group_size', 'number_of_dive', 'date', 'price', 'price_note');
    verifyRequiredParams($requiredParams);
    $groupSize = $app->request->put($requiredParams[0]);
    $numberOfDive = $app->request->put($requiredParams[1]);
    $date = $app->request->put($requiredParams[2]);
    $price = $app->request->put($requiredParams[3]);
    $priceNote = $app->request->put($requiredParams[4]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveShopTrip($shopUid, $tripId, $groupSize, $numberOfDive, $date, $price, $priceNote);
    echoResponse(200, $response);
});

$app->get('/diveshops/:shopUid/guides', function($shopUid) use($app) {
    $q = $app->request->params('q');
    $offset = $app->request->params('offset');
    if (isEmpty($offset)) {
        $offset = '0';
    }
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopGuides($shopUid, $offset, $q);
    echoResponse(200, $response);
});

    $app->get('/diveshops/:shopUid/guides/:guideId', function($shopUid, $guideId) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopGuide($shopUid, $guideId);
    echoResponse(200, $response);
});

$app->post('/diveshops/:shopUid/guides', function($shopUid) use($app) {
    verifyRequiredParams(array('name'));
    $name = $app->request->params('name');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addGuide($shopUid, $name);
    echoResponse(200, $response);
});

$app->put('/diveshops/:shopUid/guides/:guideId', function($shopUid, $guideId) use($app) {
    verifyRequiredParams(array('name'));
    $name = $app->request->put('name');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateGuide($shopUid, $guideId, $name);
    echoResponse(200, $response);
});

$app->delete('/diveshops/:shopUid/guides/:guideId', function($shopUid, $guideId) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->deleteGuide($shopUid, $guideId);
    echoResponse(200, $response);
});

/**
 * Verify required parameters before accessing it.
 * 
 */
function verifyRequiredParams($requiredParams) {
    $error = false;
    $requestParams = array();
    $requestParams = $_REQUEST;
    $errorFields = '';
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request->getBody(), $requestParams);
    }
    foreach ($requiredParams as $field) {
        if (!isset($requestParams[$field]) || strlen($requestParams[$field]) <= 0) {
            $error = true;
            $errorFields .= $field . ', ';
        }
    }
    if ($error) {
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response['error'] = true;
        $response['message'] = 'Required field(s) ' . substr($errorFields, 0, -2) . ' is missing or empty.';
        echoResponse(400, $response);
        $app->stop();
    }
}

/*
 * Helper method to print response to json
 * 
 */

function echoResponse($statusCode, $response) {
    $app = \Slim\Slim::getInstance();
    $app->status($statusCode);
    $app->contentType('application/json');
    echo json_encode($response);
}

/**
 * Check variable if empty  or unset
 * @param type $value
 * @return BOOLEAN true if empty else false
 */
function isEmpty($value) {
    return !isset($value) || strlen($value) < 1;
}

/*
 * Show not found message when someone browse to a 
 * wrong url
 * 
 */
$app->notFound(function() {
    $response = array('error' => true, 'message' => 'Not found');
    echoResponse(404, $response);
});

// Don't forget to run the Slim :)
$app->run();



