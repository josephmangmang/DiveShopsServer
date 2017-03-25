<?php

/* 
 * Copyright 2017 Joseph Mangmang.
 * Created 10-Mar-2017
 *
 */
header("HTTP/1.0 404 Not Found");
echo json_encode(array('error' => true, 'message' =>'Not found'));
die;