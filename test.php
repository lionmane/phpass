<?php
/**
 * Created by PhpStorm.
 * User: mjwunderlich
 * Date: 7/22/17
 * Time: 12:46 PM
 */

//require_once __DIR__ . '/vendor/autoload.php';
include_once "src/webscraper.php";

use MarioWunderlich\GRBJScraper;

$test = new GRBJScraper("http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/");
print_r($test->get_data());