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
$content = $test->get_content();
$results = $test->get_article_links($content);
//foreach ($results as $link) {
//    $article = $test->scrape_article($results[0]);
//    print_r($article);
//}

foreach ($results as $link) {
    if (preg_match('/.*blog.*/', $link)) {
        echo "Skipping blog entry: $link\n";
        continue;
    }

    echo "Loading article entry: $link\n";
    $article = $test->scrape_article($link);
    print_r($article);

    echo "\n\n";
}
