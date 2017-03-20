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

/*
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

/*
 * Login user
 * @param email, password, type
 */
$app->post('/login', function() use($app) {
    verifyRequiredParams(array('email', 'password', 'type'));
    $email = $app->request->params('email');
    $password = $app->request->params('password');
    $type = $app->request->params('type');

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->login($email, $password, $type);
    echoResponse(200, $response);
});

/**
 * Add new Daily Trip
 * 
 * Required Parameters:
 * shop_id, group_size, number_of_dives, date, price, price_note
 * 
 */
$app->post('/trips', function() use($app) {
    $requiredParams = array('shop_id', 'group_size', 'number_of_dives', 'date', 'price', 'price_note');
    verifyRequiredParams($requiredParams);
    $shopUid = $app->request->params($requiredParams[0]);
    $groupSize = $app->request->params($requiredParams[1]);
    $numberOfDives = $app->request->params($requiredParams[2]);
    $date = $app->request->params($requiredParams[3]);
    $price = $app->request->params($requiredParams[4]);
    $priceNote = $app->request->params($requiredParams[5]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveTrip($shopUid, $groupSize, $numberOfDives, $date, $price, $priceNote);
    echoResponse(200, $response);
});

/**
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

/**
 * Update selected dive trip
 * 
 * 'group_size'     int         max diver,
 * 'number_of_dive' int         number of dive,
 * 'date'           long        timestamp of dive,
 * 'price'          double      price of the trip,
 * 'price_note'     string      special notes for the listed price,
 * 'guides',        string[]    lisst of guides
 * 'sites'          int[]       List of dive site
 */
$app->put('/trips/:tripId', function($tripId) use($app) {
    $requiredParams = array('group_size', 'number_of_dive', 'date', 'price', 'price_note', 'guides', 'sites');
    verifyRequiredParams($requiredParams);
    $groupSize = $app->request->put($requiredParams[0]);
    $numberOfDive = $app->request->put($requiredParams[1]);
    $date = $app->request->put($requiredParams[2]);
    $price = $app->request->put($requiredParams[3]);
    $priceNote = $app->request->put($requiredParams[4]);
    $guides = $app->request->put($requiredParams[5]);
    $sites = $app->request->put($requiredParams[6]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveTrip($tripId, $groupSize, $numberOfDive, $date, $price, $priceNote, $guides, $sites);
    echoResponse(200, $response);
});

/**
 * Get a list of Course
 * sort by price/rating
 * order by ASC/DESC
 */
$app->get('/courses', function() use($app) {
    $requiredParams = array('offset', 'sort', 'order');
    verifyRequiredParams($requiredParams);
    $offset = $app->request->params($requiredParams[0]);
    $sort = $app->request->params($requiredParams[1]);
    $order = $app->request->params($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getCourses($offset, $sort, $order);
    echoResponse(200, $response);
});

/**
 * Add new Course
 * Only DiveTym Admin can add new course
 */
$app->post('/courses', function() use($app) {
    $requiredParams = array('name', 'description', 'offered_by');
    verifyRequiredParams($requiredParams);
    $name = $app->request->params($requiredParams[0]);
    $description = $app->request->params($requiredParams[1]);
    $offeredBy = $app->request->params($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addCourse($name, $description, $offeredBy);
    echoResponse(200, $response);
});

/**
 * Update selected course
 */
$app->put('/courses/:courseId', function($courseId) use($app) {
    $requiredParams = array('name', 'description', 'offered_by');
    $name = $app->request->params($requiredParams[0]);
    $description = $app->request->params($requiredParams[1]);
    $offeredBy = $app->request->params($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateCourse($courseId, $name, $description, $offeredBy);
    echoResponse(200, $response);
});


/**
 * Get a list of Dive Site base on location
 */
$app->get('/sites', function() use($app) {
    $requiredParams = array('location', 'offset');
    verifyRequiredParams($requiredParams);
    $location = $app->request->params($requiredParams[0]);
    $offset = $app->request->params($requiredParams[1]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveSite($location, $offset);
    echoResponse(200, $response);
});

/**
 * Add new Dive Site
 */
$app->post('/sites', function() use($app) {
    $requiredParams = array('name', 'description', 'location');
    verifyRequiredParams($requiredParams);
    $name = $app->request->params($requiredParams[0]);
    $description = $app->request->params($requiredParams[1]);
    $location = $app->request->params($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveSite($name, $description, $location);
    echoResponse(200, $response);
});

/**
 * Update Dive Site
 */
$app->put('/sites/:siteId', function($siteId) use($app) {
    $requiredParams = array('name', 'description', 'location');
    verifyRequiredParams($requiredParams);
    $name = $app->request->put($requiredParams[0]);
    $description = $app->request->put($requiredParams[1]);
    $location = $app->request->put($requiredParams[2]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveSite($siteId, $name, $description, $location);
    echoResponse(200, $response);
});

/**
 * Get a list of Dive Shop
 */
$app->get('/shops', function() use($app) {
    $requiredParams = array('location', 'offset', 'sort', 'order');
    verifyRequiredParams($requiredParams);
    $location = $app->request->params($requiredParams[0]);
    $offset = $app->request->params($requiredParams[1]);
    $sort = $app->request->params($requiredParams[2]);
    $order = $app->request->params($requiredParams[3]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShops($location, $offset, $sort, $order);
    echoResponse(200, $response);
});

/**
 * Get dive shop informations
 */
$app->get('/shops/:shopUid', function($shopUid) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShop($shopUid);
    echoResponse(200, $response);
});

/**
 * Get dive shop courses
 */
$app->get('/shops/:shopUid/courses', function($shopUid) use ($app) {
    $requiredParams = array('offset', 'sort', 'order');
    verifyRequiredParams($requiredParams);
    $offset = $app->request->params($requiredParams[0]);
    $sort = $app->request->params($requiredParams[1]);
    $order = $app->request->params($requiredParams[2]);
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopCourses($shopUid, $offset, $sort, $order);
    echoResponse(200, $response);
});

/**
 * Update Dive Shop course
 */
$app->put('/shops/:shopUid/courses/:shopCourseId', function($shopUid, $shopCourseId) use($app) {
    $requiredParams = array('price');
    verifyRequiredParams($requiredParams);
    $price = $app->request->put($requiredParams[0]);
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateDiveShopCourse($shopUid, $shopCourseId, $price);
    echoResponse(200, $response);
});
/**
 * Add course on dive shop
 */
$app->post('/shops/:shopUid/courses', function($shopUid) use($app) {
    $requiredParams = array('course_id', 'price');
    verifyRequiredParams($requiredParams);
    $courseId = $app->request->params($requiredParams[0]);
    $price = $app->request->params($requiredParams[1]);

    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addDiveShopCourse($shopUid, $courseId, $price);
    echoResponse(200, $response);
});
/**
 * Get list of Dive Shop Dive Trips
 */
$app->get('/shops/:shopUid/trips', function($shopUid) use ($app) {
    $requiredParams = array('start_date', 'end_date', 'offset', 'sort', 'order');
    verifyRequiredParams($requiredParams);
    $startDate = $app->request->params($requiredParams[0]);
    $endData = $app->request->params($requiredParams[1]);
    $offset = $app->request->params($requiredParams[2]);
    $sort = $app->request->params($requiredParams[3]);
    $order = $app->request->params($requiredParams[4]);
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getDiveShopDiveTrips($shopUid, $startDate, $endData, $offset, $sort, $order);
    echoResponse(200, $response);
});

/**
 * Add new boat
 */
$app->post('/shops/:shopUid/boats', function ($shopUid) use ($app) {
    verifyRequiredParams(array('name'));
    $name = $app->request->params('name');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->addBoat($shopUid, $name);
    echoResponse(200, $response);
});

/**
 * Get a list of boats
 */
$app->get('/shops/:shopUid/boats', function($shopUid) use ($app) {

    $offset = $app->request->params('offset');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->getBoats($shopUid, $offset);
    echoResponse(200, $response);
});

/**
 * Update boat
 */
$app->put('/boats/:boatId', function($boatId) use ($app) {
    verifyRequiredParams(array('name'));
    $name = $app->request->put('name');
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->updateBoat($boatId, $name);
    echoResponse(200, $response);
});
/**
 * Delete boat
 */
$app->delete('/boats/:boatId', function($boatId) use ($app) {
    $databaseHelper = new DatabaseHelper();
    $response = $databaseHelper->deleteBoat($boatId);
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

/*
 * Show not found message when someone browse to a 
 * wrong url
 * 
 */
$app->notFound(function() {
    $response = array('error' => true, 'message' => 'Url not found');
    echoResponse(404, $response);
});

// Don't forget to run the Slim :)
$app->run();



