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
    file_put_contents(realpath(basename(__FILE__)).'curies.json',json_encode($this->curies)); 
  }

  private function read_curies_json(){
    $curies = json_decode(file_get_contents(__DIR__.'/curies.json'),1);
    if(is_array($curies)){
      $this->curies = array_merge($curies, $this->curies);
    } else {
      var_dump($curies);
    }
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
    if(isset($this->curies[$curie])){
      return $this->curies[$curie];
    }
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
    $doc_uri = preg_replace('@#.*@','',$uri);
    $this->vocabulary_graph->read_data($doc_uri);
    if($this->vocabulary_graph->has_triples_about($uri)){
      $this->curies[$curie] = $uri;
      return $uri;
    } else {
      echo $this->vocabulary_graph->to_turtle();
      throw new CurieousNonExistentUriException("{$curie} transformed to <{$uri}> did not dereference .");
    }
  }

  function lookup($prefix){
    if(isset($this->prefixes[$prefix])) return $this->prefixes[$prefix];
    $prefix_doc_location = self::prefix_api . $prefix . '.file.json';
    $json_response = $this->http_request_factory->make('GET', $prefix_doc_location)->execute();
    if($json_response->is_success()){
      $json = $json_response->body;
      $uri = json_decode($json)->$prefix;
      $this->prefixes[$prefix]=$uri;
      return $uri;
    } else {
      throw new CurieousHttpFailureException("{$prefix_doc_location}");
    }
  }

}

class CurieousNonExistentUriException extends Exception {}

class CurieousHttpFailureException extends Exception {}

?>
