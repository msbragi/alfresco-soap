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

use RuntimeException;

class Functions {

    /**
     * Uploads a file into content store and returns the content data string which
     * can be used to populate a content property.
     *
     * @param $session the session
     * @param string $filePath the file location
     * @param string $mimetype
     * @param string $encoding
     * @return String the content data that can be used to update the content property
     */
    public static function upload_file($session, $filePath, $mimetype = NULL, $encoding = NULL) {
    	$result = NULL;

    	// Check for the existance of the file
    	if (!file_exists($filePath)) {
    		throw new RuntimeException("The file " . $filePath . "does no exist.", 1322830381);
    	}

    	// Get the file name and size
    	$fileName = basename($filePath);
    	$fileSize = filesize($filePath);

    	// Get the address and the
    	$host = $session->repository->host;
    	$port = $session->repository->port;

    	// Get the IP address for the target host
    	$address = gethostbyname($host);

    	// Create a TCP/IP socket
    	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    	if ($socket === FALSE) {
    		throw new RuntimeException("socket_create() failed: reason: " . socket_strerror(socket_last_error()), 1322830331);
    	}

    	// Connect the socket to the repository
    	$result = socket_connect($socket, $address, $port);
    	if ($result === FALSE) {
    		throw new RuntimeException("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)), 1322830336);
    	}

    	// Write the request header onto the socket
    	$url = '/alfresco/upload/' . urlencode($fileName) . '?ticket=' . $session->ticket;
    	if ($mimetype !== NULL) {
    		// Add mimetype if specified
    		$url .= "&mimetype=" . $mimetype;
    	}
    	if ($encoding !== NULL) {
    		// Add encoding if specified
    		$url .= "&encoding=" . $encoding;
    	}
    	$in = "PUT " . $url . " HTTP/1.1\r\n" .
    		"Content-Length: " . $fileSize . "\r\n" .
    		"Host: " . $address . ":" . $port . "\r\n" .
    		"Connection: Keep-Alive\r\n" .
    		"\r\n";
    	socket_write($socket, $in, strlen($in));

    	// Write the content found in the file onto the socket
    	$handle = fopen($filePath, "r");
    	while (!feof($handle)) {
    		$content = fread($handle, 1024);
    		socket_write($socket, $content, strlen($content));
    	}
    	fclose($handle);

    	// Read the response
    	$recv = socket_read($socket, 2048, PHP_BINARY_READ);
    	$index = strpos($recv, "contentUrl");
    	if ($index !== FALSE) {
    		$result = substr($recv, $index);
    	}

    	// Close the socket
    	socket_close($socket);

    	return $result;
    }

}
