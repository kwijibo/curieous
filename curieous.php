<?php

class Curieous {

  const prefix_api = 'http://prefix.cc/';

  var $prefixes = array();
  var $curies = array();
  var $http_request_factory=null;
  var $vocabulary_graph=null;

  function __construct($http_request_factory=null){
    $this->read_curies_json();
    $this->http_request_factory = $http_request_factory;
    if(!$this->http_request_factory) $this->http_request_factory = new HttpRequestFactory();
    $this->vocabulary_graph = new SimpleGraph();
  }

  function __destruct(){
    $this->read_curies_json();
    file_put_contents('curies.json',json_encode($this->curies)); 
  }

  private function read_curies_json(){
    $curies = json_decode(file_get_contents('curies.json'),1);
    $this->curies = array_merge($curies, $this->curies);
  }

  function register($prefix, $uri){
    $this->prefixes[$prefix]=$uri;
  }

  function uri($curie){
    list($prefix, $localname) = explode(':', $curie);
    $uri = $this->lookup($prefix).$localname;
    $this->curies[$curie]=$uri;
    return $uri;
  }

  function check($curie){
    if(isset($this->curies[$curie])) return $this->curies[$curie];
    list($prefix, $localname) = explode(':', $curie);
    $uri = $this->uri($curie);
    if($prefix=='xsd'){
      $datatypes = array('anyURI', 'base64Binary', 'boolean', 'date', 'dateTime', 'decimal', 'double', 'duration', 'float', 'gDay', 'gMonth', 'gYear', 'gYearMonth' , 'gMonthDay' , 'hexBinary', 'integer', 'long', 'int', 'QName', 'string', 'time');
      if(in_array($localname, $datatypes)){
        $this->curies[$curie] = $uri;
        return $uri;
      } else {
        throw new CurieousNonExistentUriException("{$curie} is not an xsd datatype");
      }
    }
    $this->vocabulary_graph->read_data($uri);
    if($this->vocabulary_graph->has_triples_about($uri)){
      $this->curies[$curie] = $uri;
      return $uri;
    } else {
      throw new CurieousNonExistentUriException("{$curie} transformed to <{$uri}> did not dereference .");
    }
  }

  function lookup($prefix){
    if(isset($this->prefixes[$prefix])) return $this->prefixes[$prefix];
    $json = $this->http_request_factory->make('GET', self::prefix_api . $prefix . '.file.json')->execute()->body;
    $uri = json_decode($json)->$prefix;
    $this->prefixes[$prefix]=$uri;
    return $uri;
  }

}

class CurieousNonExistentUriException extends Exception {}


?>
