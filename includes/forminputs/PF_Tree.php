<?php
/**
 * A class that defines a tree - and can populate it based on either
 * wikitext or a category structure.
 *
 * @ingroup PFFormInput
 *
 * @author Yaron Koren
 * @author Amr El-Absy
 */
class PFTree {
	public $title;
	public $children;
	public $depth;
	public $top_category;
	/**
	 * @var array[]
	 * @phan-var list<array>
	 */
	public $tree_array;
	public $current_values;

	public function __construct( $depth, $cur_values ) {
		$this->depth = $depth;
		$this->current_values = $cur_values;
		$this->title = "";
		$this->children = [];
	}

	public function addChild( $child ) {
		$this->children[] = $child;
	}

	/**
	 * This Function takes the wikitext-styled bullets as a parameter, and converts it into
	 * an array which is used within the class to modify the data passed to JS.
	 * @param string $wikitext
	 */
	public function getTreeFromWikiText( $wikitext ) {
		$lines = explode( "\n", $wikitext );
		$full_tree = [];
		$temporary_values = [];
		foreach ( $lines as $line ) {
			$numBullets = 0;
			for ( $i = 0; $i < strlen( $line ) && $line[$i] == '*'; $i++ ) {
				$numBullets++;
			}
			$lineText = trim( substr( $line, $numBullets ) );
			$full_tree[] = [ 'level' => $numBullets, "text" => $lineText ];

			if ( in_array( $lineText, $this->current_values ) && !in_array( $lineText, $temporary_values ) ) {
				$temporary_values[] = $lineText;
			}
		}
		$this->tree_array = $full_tree;
		$this->current_values = $temporary_values;
		$this->configArray();
		$this->setParentsId();
		$this->setChildren();
	}

	/**
	 * This function sets an ID for each element to be used in the function setParentsId()
	 * so that every child can know its parent
	 * This function also determine whether or not the node will be opened, depending on
	 * the attribute $depth.
	 * This function also determine whether or not the node is selected.
	 */
	private function configArray() {
		for ( $i = 0; $i < count( $this->tree_array ); $i++ ) {
			$this->tree_array[$i]['node_id'] = $i;
			if ( $this->tree_array[$i]['level'] <= $this->depth ) {
				$this->tree_array[$i]['state']['opened'] = true;
			}
			if ( in_array( $this->tree_array[$i]['text'], $this->current_values ) ) {
				$this->tree_array[$i]['state']['selected'] = true;
			}
		}
	}

	/**
	 * For the tree array that was generated from wikitext, the node doesn't know its parent
	 * although it's easy to know for the human.
	 * This function searches for the nodes and get the closest node of the parent level, and
	 * sets it as a parent.
	 * The parent ID will be used in the function setChildren() that adds every child to its
	 * parent's attribute "children"
	 */
	private function setParentsId() {
		$numNodes = count( $this->tree_array );
		for ( $i = $numNodes - 1; $i >= 0; $i-- ) {
			for ( $j = $i; $j >= 0; $j-- ) {
				if ( $this->tree_array[$i]['level'] - $this->tree_array[$j]['level'] == 1 ) {
					$this->tree_array[$i]['parent_id'] = $this->tree_array[$j]['node_id'];
					break;
				}
			}
		}
	}

	/**
	 * This function convert the attribute $tree_array from its so-called flat structure
	 * into tree-like structure, as every node has an attribute called "children" that holds
	 * the children of this node.
	 * The attribute "children" is important because it is used in the library jsTree.
	 */
	private function setChildren() {
		for ( $i = count( $this->tree_array ) - 1; $i >= 0; $i-- ) {
			for ( $j = $i; $j >= 0; $j-- ) {
				if ( isset( $this->tree_array[$i]['parent_id'] ) ) {
					if ( $this->tree_array[$i]['parent_id'] == $this->tree_array[$j]['node_id'] ) {
						if ( isset( $this->tree_array[$j]['children'] ) ) {
							array_unshift( $this->tree_array[$j]['children'], $this->tree_array[$i] );
						} else {
							$this->tree_array[$j]['children'][] = $this->tree_array[$i];
						}
						unset( $this->tree_array[$i] );
					}
				}
			}
		}
	}

	/**
	 * This Function takes the Top Category name as a parameter, and generate
	 * tree_array, which is used within the class to modify the data passed to JS.
	 * @param string $top_category
	 * @param bool $hideroot
	 */
	public function getFromTopCategory( $top_category, $hideroot ) {
		$this->top_category = $top_category;
		$this->populateChildren();

		$this->tree_array[0]['text'] = $top_category;
		if ( in_array( $top_category, $this->current_values ) ) {
			$this->tree_array[0]['state']['selected'] = true;
		}
		$children = $this->children;
		$this->tree_array[0]['level'] = 1;
		$this->tree_array[0]['state']['opened'] = true;

		$children = self::addSubCategories( $children, 2, $this->depth, $this->current_values );

		$this->tree_array[0]['children'] = $children;

		$this->current_values = self::getCurValues( $this->tree_array );

		if ( $hideroot ) {
			$this->tree_array = $this->tree_array[0]['children'];
		}
	}

	/**
	 * This function handles adding the children of the nodes in the Top Category tree.
	 * Also, it determines whether or not the node is selected depending on $cur_values
	 * @param array $children
	 * @param int $level
	 * @param int $depth
	 * @param array $cur_values
	 * @return array
	 */
	public static function addSubCategories( $children, $level, $depth, $cur_values ) {
		$newChildren = [];
		foreach ( $children as $child ) {
			$is_selected = false;
			if ( $cur_values !== null ) {
				if ( in_array( $child->title, $cur_values ) ) {
					$is_selected = true;
					unset( $cur_values[ array_search( $child->title, $cur_values ) ] );
				}
			}

			$newChild = [
				'text' => $child->title,
				'level' => $level,
				'children' => self::addSubCategories( $child->children, $level + 1, $depth, $cur_values )
			];
			$newChild['state']['opened'] = $level <= $depth;
			if ( $is_selected ) {
				$newChild['state']['selected'] = true;
			}
			$newChildren[] = $newChild;
		}
		return $newChildren;
	}

	private static function getCurValues( $tree ) {
		$cur_values = [];
		foreach ( $tree as $node ) {
			if ( isset( $node['state']['selected'] ) && $node['state']['selected'] ) {
				$cur_values[] = $node['text'];
			}
			if ( isset( $node['children'] ) ) {
				$children = self::getCurValues( $node['children'] );
				$cur_values = array_merge( $cur_values, $children );
			}
		}
		return $cur_values;
	}

	/**
	 * Recursive function to populate a tree based on category information.
	 */
	private function populateChildren() {
		$subcats = self::getSubcategories( $this->top_category );
		foreach ( $subcats as $subcat ) {
			$childTree = new PFTree( $this->depth, $this->current_values );
			$childTree->top_category = $subcat;
			$childTree->title = $subcat;
			$childTree->populateChildren();
			$this->addChild( $childTree );
		}
	}

	/**
	 * Gets all the subcategories of the passed-in category.
	 *
	 * @todo This might not belong in this class.
	 *
	 * @param string $categoryName
	 * @return array
	 */
	private static function getSubcategories( $categoryName ) {
		$dbr = wfGetDB( DB_REPLICA );

		$tables = [ 'page', 'categorylinks' ];
		$fields = [ 'page_id', 'page_namespace', 'page_title',
			'page_is_redirect', 'page_len', 'page_latest', 'cl_to',
			'cl_from' ];
		$where = [];
		$joins = [];
		$options = [ 'ORDER BY' => 'cl_type, cl_sortkey' ];

		$joins['categorylinks'] = [ 'JOIN', 'cl_from = page_id' ];
		$where['cl_to'] = str_replace( ' ', '_', $categoryName );
		$options['USE INDEX']['categorylinks'] = 'cl_sortkey';

		$tables = array_merge( $tables, [ 'category' ] );
		$fields = array_merge( $fields, [ 'cat_id', 'cat_title', 'cat_subcats', 'cat_pages', 'cat_files' ] );
		$joins['category'] = [ 'LEFT JOIN', [ 'cat_title = page_title', 'page_namespace' => NS_CATEGORY ] ];

		$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $joins );
		$subcats = [];

		foreach ( $res as $row ) {
			$t = Title::newFromRow( $row );
			if ( $t->getNamespace() == NS_CATEGORY ) {
				$subcats[] = $t->getText();
			}
		}
		return $subcats;
	}
}
