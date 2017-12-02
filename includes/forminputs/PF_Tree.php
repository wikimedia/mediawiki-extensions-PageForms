<?php
/**
 * A class that defines a tree - and can populate it based on either
 * wikitext or a category structure.
 *
 * @ingroup PFFormInput
 *
 * @author Yaron Koren
 */
class PFTree {
	public $title;
	public $children;

	public function __construct( $curTitle ) {
		$this->title = $curTitle;
		$this->children = array();
	}

	public function addChild( $child ) {
		$this->children[] = $child;
	}

	/**
	 * Turn a manually-created "structure", defined as a bulleted list
	 * in wikitext, into a tree. This is based on the concept originated
	 * by the "menuselect" input type in the Semantic Forms Inputs
	 * extension - the difference here is that the text is manually
	 * parsed, instead of being run through the MediaWiki parser.
	 * @param string $wikitext
	 * @return self
	 */
	public static function newFromWikiText( $wikitext ) {
		// The top node, called "Top", will be ignored, because
		// we'll set "hideroot" to true.
		$fullTree = new PFTree( 'Top' );
		$lines = explode( "\n", $wikitext );
		foreach ( $lines as $line ) {
			$numBullets = 0;
			for ( $i = 0; $i < strlen( $line ) && $line[$i] == '*'; $i++ ) {
				$numBullets++;
			}
			if ( $numBullets == 0 ) {
				continue;
			}
			$lineText = trim( substr( $line, $numBullets ) );
			$curParentNode = $fullTree->getLastNodeForLevel( $numBullets );
			$curParentNode->addChild( new PFTree( $lineText ) );
		}
		return $fullTree;
	}

	public function getLastNodeForLevel( $level ) {
		if ( $level <= 1 || count( $this->children ) == 0 ) {
			return $this;
		}
		$lastNodeOnCurLevel = end( $this->children );
		return $lastNodeOnCurLevel->getLastNodeForLevel( $level - 1 );
	}

	/**
	 * @param string $top_category
	 * @return mixed
	 */
	public static function newFromTopCategory( $top_category ) {
		$pfTree = new PFTree( $top_category );
		$defaultDepth = 20;
		$pfTree->populateChildren( $defaultDepth );
		return $pfTree;
	}

	/**
	 * Recursive function to populate a tree based on category information.
	 */
	private function populateChildren( $depth ) {
		if ( $depth == 0 ) {
			return;
		}
		$subcats = self::getSubcategories( $this->title );
		foreach ( $subcats as $subcat ) {
			$childTree = new PFTree( $subcat );
			$childTree->populateChildren( $depth - 1 );
			$this->addChild( $childTree );
		}
	}

	/**
	 * Gets all the subcategories of the passed-in category.
	 *
	 * @TODO This might not belong in this class.
	 *
	 * @param Title $title
	 * @return array
	 */
	private static function getSubcategories( $categoryName ) {
		$dbr = wfGetDb( DB_SLAVE );

		$tables = array( 'page', 'categorylinks' );
		$fields = array( 'page_id', 'page_namespace', 'page_title',
			'page_is_redirect', 'page_len', 'page_latest', 'cl_to',
			'cl_from' );
		$where = array();
		$joins = array();
		$options = array( 'ORDER BY' => 'cl_type, cl_sortkey' );

		$joins['categorylinks'] = array( 'JOIN', 'cl_from = page_id' );
		$where['cl_to'] = str_replace( ' ', '_', $categoryName );
		$options['USE INDEX']['categorylinks'] = 'cl_sortkey';

		$tables = array_merge( $tables, array( 'category' ) );
		$fields = array_merge( $fields, array( 'cat_id', 'cat_title', 'cat_subcats', 'cat_pages', 'cat_files' ) );
		$joins['category'] = array( 'LEFT JOIN', array( 'cat_title = page_title', 'page_namespace' => NS_CATEGORY ) );

		$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $joins );
		$subcats = array();

		foreach ( $res as $row ) {
			$t = Title::newFromRow( $row );
			if ( $t->getNamespace() == NS_CATEGORY ) {
				$subcats[] = $t->getText();
			}
		}
		return $subcats;
	}

}
