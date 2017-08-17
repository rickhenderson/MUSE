<?php
/**
 * Functions that write to the database.
 */

/**
 * Creates an object in the world
 *
 * @param String $name Name of object
 * @param int $location Location ID of the object (usually user) that this object belongs to, or null for rooms
 * @param String $type Object type
 * @return boolean object id if the object was created
 * @return boolean false if creation failed
 */
function createEntity( $name, $location, $type ) {
	global $wb; // App settings & database

	/* Make null SQL friendly, used for rooms */
	if( is_null( $location ) ) {
		$location ='null';
	}

	$sql = "INSERT INTO {$wb['DB_PREFIX']}_entities
	(name, owner, location, type) values ( '$name', {$_SESSION['userID']}, $location, '$type' );";
	addServerMessageToXML( $sql );
	$result = $wb['db']->query($sql);

	if( $result ) {
		$result = $wb['db']->insert_id;
	} else {
		$result = false;
	}

	return $result;
}

/**
 * Creates an exit object, optionally: links, creates return entrance object, links that back.
 *
 * @param int $locationID
 * @param unknown $exitName
 * @param string $toID
 * @param string $entranceName
 * @return boolean
 */
function createExit( $locationId, $exitName, $toId = null, $entranceName = null ) {
	global $wb; // App settings & database

	/* Create exit. If successful, link to $toId. If a return $entranceName, create exit back. */
	addServerMessageToXML("Started CreateExit with '$locationId', '$exitName', '$toId', '$entranceName'.");
	$result = createEntity( $exitName, $locationId, "exit" );

	if( $result ) {
		addLogToXML("Created exit $exitName.");
		if( !is_null($toId) ) {
			$result = linkExit( $wb['db']->insert_id, $toId );
				
			if( $result && !is_null( $entranceName ) ) {
				// Create an exit and link back
				addServerMessageToXML( "Calling $toId, $entranceName, $locationId ");
				createExit( $toId, $entranceName, $locationId );
			}
		}
	}

	return $result;
}

/**
 * Change the ownership field of $entityId object.
 * 
 * @param unknown $entityId
 * @param unknown $newOwnerId
 * @return unknown Query result
 */
function changeOwner( $entityId, $newOwnerId ) {
	global $wb; // App settings & databae
	
	$sql = "UPDATE {$wb['DB_PREFIX']}_entities
			SET owner = '{$newOwnerId}'
			WHERE id = '$entityId';";
	
	$result = $wb['db']->query( $sql );
	
	return $result;
}
/**
 * Deletes object.
 *
 * @param int $entity id of entity to be deleted
 * @return unknown result of query
 */
function destroyEntity( $entity ) {
	global $wb; // App settings & database

	$result = $wb['db']->query("DELETE FROM {$wb['DB_PREFIX']}_entities
	WHERE id='{$entity['id']}';");
		
	// Delete on the extended information table
	if( $result ) {
		$sql = "DELETE FROM {$wb['DB_PREFIX']}_extended
		WHERE entity_id='{$entity['id']}';";
		addServerMessageToXML( $sql);
		$result = $wb['db']->query($sql);
	}

	return $result;
}


/**
* Moves world object to the users location (inventory). Also adds appropriate
* drop log and client response.
*
* @param int $entity id of entity to drop
* @return unknown result of query
*/
function dropEntity( $entity ) {
global $wb; // App settings & database

if( $result = moveEntity( $entity, $_SESSION['location'] ) ) {
if( isset( $entity['drop'] ) ) {
addLogToXML( $entity['drop'] );
} else {
addLogToXML("Dropped ". $entity['name'] );
	}

	if ( isset( $entity['odrop'] ) ) {
			insertLog("user", $_SESSION['userID'], $_SESSION['location'],
			$_SESSION["username"]." ".$entity['odrop']);
	} else {
	insertLog("user", $_SESSION['userID'], $_SESSION['location'],
			$_SESSION['username']." dropped ". $entity['name'] );
	}
	}

	return $result;
}

/**
 * Inserts $message in to the log so that other users can be notified of the action.
 *
 * When an action happens in $location the $message is delivered to everyone currently
 * in that location. For example, when someone speaks, or drops something or any other
 * change. Also, if $location is someone's user ID it will be a whisper only that person
 * can hear.
 *
 * @param String $type Usually 'user', as in log message for and about users. Rather than 'server' for admin logs?
 * @param int $userID User ID tied to the log
 * @param int $location Location ID for that the log is relevant for
 * @param String $message The message, including droped, says taken, et cetera
 */
function insertLog( $type, $userID, $location, $message ) {
	global $wb; // App settings & database
	$message = $wb['db']->real_escape_string($message);
	$sql = "INSERT INTO {$wb['DB_PREFIX']}_logs
	(type, user_id, location, message) values ( '$type', '$userID', '$location', '$message' );";
	addServerMessageToXML($sql);
	return $wb['db']->query( $sql );

}

/**
 *  linkExit links exit $exitId to $toID
 */

function linkExit(  $exitId, $toID ) {
	global $wb; // App settings & database

	$exit = getEntity( "#".$exitId, null, "exit" );
	$result = setExtendedData( $exit, "link", $toID );
	return $result;
}

/**
 * Changes object/entity location to the new location.
 *
 * @param int $entity id of the object to move (eg., item or player)
 * @param int $locationId id of the location to move to
 * @return query result
 */
function moveEntity( $entity, $locationId ) {
	global $wb; // App settings & database

	$sql = "UPDATE {$wb['DB_PREFIX']}_entities
	SET location = $locationId
	WHERE id = {$entity['id']};";
	addServerMessageToXML($sql);
	$result = $wb['db']->query($sql);

	return $result;
}


/**
 * Sets the a field (eg. description) of an entity
 */
function setEntityField( $field, $entityID, $value) {
	global $wb; // App settings & database

	$result = $wb['db']->query("UPDATE {$wb['DB_PREFIX']}_entities
	SET $field = '$value'
	WHERE id = $entityID;");
	return $result;
}

/**
 * Set extended data (example, lock).
 *
 * @param unknown $entity
 * @param unknown $name
 * @param unknown $value
 * @return unknown
 */
function setExtendedData( $entity, $name, $value ) {
	global $wb;

	/* Update existing attribute, or create new attribute */
	if( isset( $entity[ $name ] ) ) {
		// Update existing data
		$sql = "UPDATE {$wb['DB_PREFIX']}_extended
			SET `value` = '$value'
			WHERE entity_id = '{$entity['id']}'
			AND `name` = '$name';";
		addServerMessageToXML( $sql );
		$result = $wb['db']->query($sql);
		
		if( $result ) {
			addLogToXML("Updated $name on {$entity['name']}.");
		} else {
			addLogToXML("Failed to set $name.");
		}
	} else {
		// Insert new data
		$sql = "INSERT INTO {$wb['DB_PREFIX']}_extended
			(entity_id, name, value) VALUES
			('{$entity['id']}', '$name', '$value');";
		addServerMessageToXML( $sql );
		$result = $wb['db']->query($sql);
		if( $result ) {
			addLogToXML("Set $name on {$entity['name']}.");
		} else {
			addLogToXML("Failed to set $name.");
		}
	}

	return $result;

}

/**
* Move object to user's inventory (i.e., to the user object). Also,
* adds appropriate messages to log and client response.
*
* @param unknown $entity
* @return unknown
*/
function takeEntity( $entity ) {
	global $wb; // App settings & database

	if( $result = moveEntity( $entity, $_SESSION['userID'] ) ) {
	if( isset( $entity['success'] ) ) {
	addLogToXML( $entity['success'] );
		} else {
	addLogToXML("You took ". $entity['name'] );
	}

			if ( isset( $entity['osuccess'] ) ) {
			insertLog("user", $_SESSION['userID'], $_SESSION['location'],
			$_SESSION['username']." ".$entity['osuccess']);
			} else {
			insertLog("user", $_SESSION['userID'], $_SESSION['location'],
			$_SESSION['username']." took ". $entity['name'] );
			}
	}
	return $result;
}



?>