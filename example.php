<?php
define('MORIARTY_ARC_DIR', '../arc/');
require '../moriarty/httprequestfactory.class.php';
require '../moriarty/simplegraph.class.php';
require 'curieous.php';
define('MORIARTY_HTTP_CACHE_DIR', 'cache/');

$curie = new Curieous();
var_dump($curie->uri('foaf:Agent')); //exists
var_dump($curie->check('xsd:integer')); //exists but not as rdf
//doesn't exist yet
$curie->register('sns', 'http://sns.linkedscotland.org/def/');
var_dump($curie->uri('sns:Scotland')); //registered so still works
var_dump($curie->check('skos:Concept')); //works
var_dump($curie->check('dc:title')); //works
var_dump($curie->check('foaf:title')); //throws exception 


?>
