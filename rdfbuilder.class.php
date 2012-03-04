<?php
require 'curieous.php';

$curieous = new Curieous();

function check($curie){
  global $curieous;
  return $curieous->check($curie);
}
function uri($curie){
  global $curieous;
  return $curieous->uri($curie);
}
function register($prefix, $uri){
  global $curieous;
  return $curieous->register($prefix, $uri);
}


class RdfBuilder {

    var $graph;
    var $vocab_builder;
    var $vocabs_to_generate = array();
    var $dont_check_namespaces = array();

    function __construct(){
      $this->graph = new SimpleGraph();
    }

    function dont_check_namespace($prefix){
      $this->dont_check_namespaces[$prefix] = true;
      
    }

    function create_vocabulary($prefix,$namespace, $label, $creator){
      $this->vocabs_to_generate[$prefix]=$namespace;
      $this->vocab_builder = new RdfBuilder();
      $this->vocab_builder->thing($namespace)
        ->has('vann:preferredNamespaceUri')->l($namespace)
        ->has('vann:preferredNamespacePrefix')->l($prefix)
        ->has('dcterms:description')->l(' ','en')
        ->has('dcterms:creator')->r($creator)
        ->label($label, 'en');
    }

    function vocab_to_turtle(){
      return $this->vocab_builder->turtle();
    }

    function thing($uri){
      return new RdfNode($uri, $this);
    }

    function thing_from_label($ns, $label, $lang=false){
      $uri = $ns . $this->urlize($label);
      $thing = new RdfNode($uri, $this);
      $thing->label($label, $lang);
      return $thing;
    }

    function thing_from_identifier($ns, $identifier){
      $uri = $ns . $this->urlize($identifier);
      $thing = new RdfNode($uri, $this);
      $thing->has('dcterms:identifier')->l($identifier);
      return $thing;
    }

    function turtle(){
      return $this->graph->to_turtle();
    }

    function dump_ntriples(){
      $ntriples = $this->graph->to_ntriples();
      $this->graph = new SimpleGraph();
      return $ntriples;
    }

    function urlize($text, $replacementString='_'){
      return trim(preg_replace('/[^0-9a-zA-Z]+/', $replacementString, $text)); 
    }

    function term_should_be_created($term){
     foreach($this->vocabs_to_generate as $prefix => $ns){
      if(strpos($term, $prefix.':')===0){
        return true;
      } else if(strpos($term, $ns)===0){
          return true;
        }
       }
      return false;
    }

    function term_should_be_checked($curie){
       list($prefix, $localname) = explode(':', $curie);

       if(isset($this->dont_check_namespaces[$prefix]) 
         OR 
         $this->term_should_be_created($curie)){
         return false;
       } else {
        return true;
       }
    }


    function write_vocabulary_to_file($prefix, $file){
      $turtle = $this->vocab_builder->turtle();
      file_put_contents($file, $turtle);
    }


  function get_vocab_builder(){
    return $this->vocab_builder;
  }

}

class RdfNode {

  var $uri, $graph, $property, $last_object, $builder;
    
  function __construct($uri, &$builder){
    $this->uri = $uri;
    $this->graph = $builder->graph;
    $this->builder = $builder;
  }

  function get_uri(){
    return $this->uri;
  }

  function object(){
    return new RdfNode($this->last_object, $this->builder);
  }

  function has($curie){
    if($this->builder->term_should_be_checked($curie)){
      $this->property = check($curie);
    } else {
      $this->property = uri($curie);
    }
    return $this;
  }

  function is($curie){
    return $this->has($curie);
    //to allow the $x->is('ex:foo')->of($y) pattern
  }

  function of($v){
    $this->graph->add_resource_triple($v, $this->property, $this->uri);
    if($this->builder->term_should_be_created($this->property)){
      if(!$range = $this->graph->get_first_resource($v, uri('rdf:type'))){
        $range = uri('rdfs:Resource');
      }
      $this->create_property($this->property, $range);
    }

    $this->last_object = $v;
    return $this;
  }

  function l($v, $lang=false){
    $this->graph->add_literal_triple($this->uri, $this->property, $v, $lang);
    if($this->builder->term_should_be_created($this->property)){
      $this->create_property($this->property, uri('rdfs:Literal'));
    }

    return $this;
  }

  function r($v){
    $this->graph->add_resource_triple($this->uri, $this->property, $v);
    if($this->builder->term_should_be_created($this->property)){
      if(!$range = $this->graph->get_first_resource($v, uri('rdf:type'))){
        $range = uri('rdfs:Resource');
      }
      $this->create_property($this->property, $range);
    }
    $this->last_object = $v;
    return $this;
  }

  function dt($v, $dt){
    if($dt){ 
      $dt = check($dt);
      $range = $dt;
    } else {
      $range = uri('rdfs:Literal');
    }
    if($this->builder->term_should_be_created($this->property)){
      $this->create_property($this->property, $range);
      $this->builder->vocab_builder->thing($this->property)->a('owl:DatatypeProperty');
    }
    $this->graph->add_literal_triple($this->uri, $this->property, $v, 0, $dt);
    return $this;
  }

   function a($class_type){
     if($this->builder->term_should_be_created($class_type)){
        $class_uri = uri($class_type);
        $this->create_class($class_uri);
     } else {
        $class_uri = check($class_type);
     }
    $this->graph->add_resource_triple($this->uri, RDF_TYPE, $class_uri);
    return $this;
  }

  function label($label, $lang=false){
    $this->has('rdfs:label')->l(trim($label), $lang);
    return $this;
  }

  function create_property($p, $range=false){
    if(preg_match('@(.+?[#/])([^#/]+)$@', $p, $m)){
      list($all, $ns, $localname) = $m;
      $label = ucwords(preg_replace('/([a-z])([A-Z])/','$1 $2', str_replace('_',' ', $localname)));
    } else {
      return false;
    }
    $Property = $this->builder->vocab_builder->thing($p);
      $Property->a('rdf:Property')
      ->label($label, 'en')
      ->has('rdfs:comment')->l($label, 'en')
      ->has('rdfs:isDefinedBy')->r($ns)
      ->is('ov:defines')->of($ns)
      ->has('rdfs:range')->r($range);
    if($type = $this->graph->get_first_resource($this->uri, RDF_TYPE)){
      $Property->has('rdfs:domain')->r($type);
    }
  }

  function create_class($class){
    if(preg_match('@(.+?[#/])([^#/]+)$@', $class, $m)){
      list($all, $ns, $localname) = $m;
      $label = preg_replace('/([a-z])([A-Z])/','$1 $2', str_replace('_',' ', $localname));
    } else {
      return false;
    }
      $class = $this->builder->vocab_builder->thing($class)->a('rdfs:Class')
        ->label($label, 'en')
        ->has('rdfs:isDefinedBy')->r($ns)
        ->is('ov:defines')->of($ns)
        ->has('rdfs:comment')->l($label, 'en');

  }


}


?>
