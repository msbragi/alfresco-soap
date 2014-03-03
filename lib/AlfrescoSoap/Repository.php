<?php
/*
 * Copyright (C) 2005-2011 Alfresco Software Limited.
 *
 * This file is part of Alfresco
 *
 * Alfresco is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Alfresco is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Alfresco. If not, see <http://www.gnu.org/licenses/>.
 */

namespace AlfrescoSoap;

use AlfrescoSoap\WebService\WebServiceFactory;

if (!isset($_SESSION)) {
	// Start the session
	session_start();
}

class Repository extends BaseObject {
	private $_connectionUrl;
	private $_host;
	private $_port;

	public function __construct($connectionUrl = "http://localhost:8080/alfresco/api") {
		$this->_connectionUrl = $connectionUrl;
		$parts = parse_url($connectionUrl);
		$this->_host = $parts["host"];
		$this->_port = $parts["port"];
	}

	public function getConnectionUrl() {
		return $this->_connectionUrl;
	}

	public function getHost() {
		return $this->_host;
	}

	public function getPort() {
		return $this->_port;
	}

	public function authenticate($userName, $password) {
		// TODO need to handle exceptions!

		$authenticationService = WebServiceFactory::getAuthenticationService($this->_connectionUrl);
		try {
			$result = $authenticationService->startSession(array(
				"username" => $userName,
				"password" => $password
			));
		} catch (SoapFault $e) {
			throw new RuntimeException('Could not authenticate user "' . $userName . '"', 1326448905);
		}

		// Get the ticket and sessionId
		$ticket = $result->startSessionReturn->ticket;
		$sessionId = $result->startSessionReturn->sessionid;

		// Store the session id for later use
		if ($sessionId !== NULL) {
			$sessionIds = NULL;
			if (!isset($_SESSION['sessionIds'])) {
				$sessionIds = array();
			} else {
				$sessionIds = $_SESSION['sessionIds'];
			}
			$sessionIds[$ticket] = $sessionId;
			$_SESSION['sessionIds'] = $sessionIds;
		}

		return $ticket;
	}

	public function createSession($ticket = NULL) {
		$session = NULL;

		if ($ticket === NULL) {
			// TODO get ticket from some well known location ie: the $_SESSION
		} else {
			// TODO would be nice to be able to check that the ticket is still valid!

			// Create new session
			$session = new Session($this, $ticket);
		}

		return $session;
	}

	/**
	 * For a given ticket, returns the related session id, NULL if one can not be found.
	 *
	 * @param $ticket
	 */
	public static function getSessionId($ticket) {
		$result = NULL;
		if (isset($_SESSION['sessionIds'])) {
			$result = $_SESSION['sessionIds'][$ticket];
		}
		return $result;
	}
}

?>