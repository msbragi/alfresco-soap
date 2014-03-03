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

class ContentData extends BaseObject {
	private $_isPopulated = FALSE;
	private $_isDirty = FALSE;

	private $_node;
	private $_property;

	private $_mimetype;
	private $_size;
	private $_encoding;
	private $_url;
	private $_newContent;
	private $_newFileContent;

	public function __construct($node, $property, $mimetype = NULL, $encoding = NULL, $size = -1) {
		$this->_node = $node;
		$this->_property = $property;
		$this->_mimetype = $mimetype;
		$this->_encoding = $encoding;
		if ($size != -1) {
			$this->size = $size;
		}
		$this->_isPopulated = FALSE;
	}

	public function setPropertyDetails($node, $property) {
		$this->_node = $node;
		$this->_property = $property;
	}

	public function __toString() {
		$this->populateContentData();
		return "mimetype=" . $this->mimetype . "|encoding=" . $this->encoding . "|size=" . $this->size;
	}

	public function getNode() {
		return $this->_node;
	}

	public function getProperty() {
		return $this->_property;
	}

	public function getIsDirty() {
		return $this->_isDirty;
	}

	public function getMimetype() {
		$this->populateContentData();
		return $this->_mimetype;
	}

	public function setMimetype($mimetype) {
		$this->populateContentData();
		$this->_mimetype = $mimetype;
	}

	public function getSize() {
		$this->populateContentData();
		return $this->_size;
	}

	public function getEncoding() {
		$this->populateContentData();
		return $this->_encoding;
	}

	public function setEncoding($encoding) {
		$this->populateContentData();
		$this->_encoding = $encoding;
	}

	public function getUrl() {
		// TODO what should be returned if the content has been updated??

		$this->populateContentData();
		$result = NULL;
		if ($this->_url !== NULL) {
			$result = $this->_url . '?ticket=' . $this->_node->session->ticket;
		}
		return $result;
	}

	public function getGuestUrl() {
		// TODO what should be returned if the content has been updated??

		$this->populateContentData();
		$result = NULL;
		if ($this->_url !== NULL) {
			$result = $this->_url . '?guest=true';
		}
		return $result;
	}

	public function getContent() {
		$this->populateContentData();

		$result = NULL;
		if ($this->_isDirty) {
			if ($this->_newFileContent !== NULL) {
				$handle = fopen($this->_newFileContent, 'rb');
				$result = stream_get_contents($handle);
				fclose($handle);
			} else {
				if ($this->_newContent !== NULL) {
					$result = $this->_newContent;
				}
			}
		} else {
			if ($this->getUrl() !== NULL) {
				$handle = fopen($this->getUrl(), 'rb');
				$result = stream_get_contents($handle);
				fclose($handle);
			}
		}
		return $result;
	}

	public function setContent($content) {
		$this->populateContentData();
		$this->_isDirty = TRUE;
		$this->_newContent = $content;
	}

	public function writeContentFromFile($fileName) {
		$this->populateContentData();
		$this->_isDirty = TRUE;
		$this->_newFileContent = $fileName;
	}

	public function readContentToFile($fileName) {
		$handle = fopen($fileName, "wb");
		fwrite($handle, $this->getContent());
		fclose($handle);
	}

	public function onBeforeSave(&$statements, $where) {
		if ($this->_isDirty == TRUE) {
			// Check mimetype has been set
			if ($this->_mimetype == NULL) {
				throw new Exception("A mime type for the content property " . $this->_property . " on node " . $this->_node->__toString() . " must be set");
			}

			// If a file has been specified then read content from there
			//$content = null;
			if ($this->_newFileContent != NULL) {
				// Upload the content to the repository
				$contentData = upload_file($this->node->session, $this->_newFileContent, $this->_mimetype, $this->_encoding);

				// Set the content property value
				$this->addStatement(
					$statements,
					'update',
					array(
						'property' => array(
							'name' => $this->property,
							'isMultiValue' => FALSE,
							'value' => $contentData
						)
					) + $where
				);
			} else {
				// Add the writeContent statement
				$this->addStatement(
					$statements,
					'writeContent',
					array(
						'property' => $this->_property,
						'content' => $this->_newContent,
						'format' => array(
							'mimetype' => $this->_mimetype,
							'encoding' => $this->_encoding
						)
					) + $where
				);
			}
		}
	}

	public function onAfterSave() {
		$this->_isDirty = FALSE;
		$this->_isPopulated = FALSE;
		$this->_mimetype = NULL;
		$this->__size = NULL;
		$this->__encoding = NULL;
		$this->__url = NULL;
		$this->__newContent = NULL;
	}

	private function populateContentData() {
		//echo "isPopulated:".$this->_isPopulated."; node:".$this->_node."; property:".$this->_property."<br>";
		if (!$this->_isPopulated && $this->_node !== NULL && $this->_property !== NULL && !$this->_node->isNewNode) {
			$result = $this->_node->session->contentService->read(array(
				"items" => array(
					"nodes" => array(
						"store" => $this->_node->store->__toArray(),
						"uuid" => $this->_node->id)),
				"property" => $this->_property));
			if (isset($result->content)) {
				if (isset($result->content->length)) {
					$this->_size = $result->content->length;
				}
				if (isset($result->content->format->mimetype)) {
					$this->_mimetype = $result->content->format->mimetype;
				}
				if (isset($result->content->format->encoding)) {
					$this->_encoding = $result->content->format->encoding;
				}
				if (isset($result->content->url)) {
					$this->_url = $result->content->url;
				}
			}

			$this->_isPopulated = TRUE;
		}
	}

	private function addStatement(&$statements, $statement, $body) {
		$result = array();
		if (array_key_exists($statement, $statements)) {
			$result = $statements[$statement];
		}
		$result[] = $body;
		$statements[$statement] = $result;
	}
}

?>