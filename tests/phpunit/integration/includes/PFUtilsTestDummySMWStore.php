<?php

if ( class_exists( '\SMW\Store' ) && !class_exists( 'PFUtilsTestDummySMWStore' ) ) {
	class PFUtilsTestDummySMWStore extends \SMW\Store {
		public function getSemanticData( \SMW\DIWikiPage $subject, $filter = false ) {
			return null;
		}

		public function getPropertyValues( $subject, \SMW\DIProperty $property, $requestoptions = null ) {
			return [];
		}

		public function getPropertySubjects( \SMW\DIProperty $property, $value, $requestoptions = null ) {
			return [];
		}

		public function getAllPropertySubjects( \SMW\DIProperty $property, $requestoptions = null ) {
			return [];
		}

		public function getProperties( \SMW\DIWikiPage $subject, $requestOptions = null ) {
			return [];
		}

		public function getInProperties( \SMWDataItem $object, $requestOptions = null ) {
			return [];
		}

		public function deleteSubject( \Title $subject ) {
		}

		protected function doDataUpdate( \SMW\SemanticData $data ) {
			return true;
		}

		public function changeTitle( \Title $oldtitle, \Title $newtitle, $pageid, $redirid = 0 ) {
		}

		public function getQueryResult( \SMWQuery $query ) {
			return null;
		}

		public function getPropertiesSpecial( $requestoptions = null ) {
			return [];
		}

		public function getUnusedPropertiesSpecial( $requestoptions = null ) {
			return [];
		}

		public function getWantedPropertiesSpecial( $requestoptions = null ) {
			return [];
		}

		public function getStatistics() {
			return [];
		}

		public function setup( $verbose = true ) {
			return true;
		}

		public function drop( $verbose = true ) {
			return true;
		}

		public function refreshData( &$index, $count, $namespaces = false, $usejobs = true ): \SMW\SQLStore\Rebuilder\Rebuilder {
			return new class extends \SMW\SQLStore\Rebuilder\Rebuilder {
				public function __construct() {
				}
			};
		}
	}
}
