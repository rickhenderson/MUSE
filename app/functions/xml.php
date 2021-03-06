<?php

/*
 * Functions add nodes to XML.
 *
 * This file is part of MUSE.
 *
 * @author Shawn P. Conroy
 */

/**
 * Add list of entities in location to XML.
 *
 * @param string $location Location ID.
 * @param $tag Optional. Changes tag (element) of XML node output.
 */
function addLocationEntitiesToXML( $location, $tag = null ) {
	global $muse; // App settings & database
	
	
	$entities = $muse['db']->query("SELECT * FROM {$muse['DB_PREFIX']}_entities WHERE location = {$location};");
	while ( $entities && $entity = $entities->fetch_assoc() ) {
		$entity['name'] = strtok($entity['name'], ';');
		if( $tag != null ) {
			$nodeTag = $tag;
		} else {
			$nodeTag = $entity['type'];
		}
		getExtendedData( $entity );
		if( !isset($entity['dark']) && $entity['id'] != $_SESSION['userID'] ) {
			addTextElement( $nodeTag, $entity['name'] );
		}
	}
	return;
}


/**
 * Adds a text node to response.
 *
 * @param string $object Node type to create.
 * @param string $text Text message.
 */
function addTextElement( $object, $text ) {
	global $muse; // App settings & database
	
	$element = $muse['xml']->createElement( $object );
	$element->appendChild( $muse['xml']->createTextNode( $text ) );
	$muse['response']->appendChild( $element );
}

/**
 * Add location element to XML.
 *
 * @param $location Location ID.
 * @return void
 */
function addLocationToXML( $location ) {
	$location = getLocation( $location );
	addTextElement( "location", $location['name'] );
	return;
}



?>