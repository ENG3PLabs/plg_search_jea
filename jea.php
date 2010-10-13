<?php
/**
 * @version		$Id$
 * @package		JEA
 * @copyright	Copyright (C) 2010 Thader Consultores, C.B. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

//$mainframe->registerEvent( 'onSearch', 'plgSearchJea' );
$mainframe->registerEvent( 'onSearchAreas', 'plgSearchJeaAreas' );

JPlugin::loadLanguage( 'plg_search_jea' );

/**
 * @return array An array of search areas
 */
function &plgSearchJeaAreas()
{
	static $areas = array(
		'jea' => 'Establecimientos'
	);
	return $areas;
}

class plgSearchJea extends JPlugin {

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param 	object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.5
	 */

	function plgSearchJea( &$subject )
	{
		parent::__construct( $subject );

		// load plugin parameters
		$this->_plugin = & JPluginHelper::getPlugin( 'search', 'jea' );
		$this->_params = new JParameter( $this->_plugin->params );
				
	}
	
/**
 * Jea Search method
 * The sql must return the following fields that are used in a common display
 * routine: href, title, section, created, text, browsernav
 * @param string target search string
 * @param string matching option, exact|any|all
 * @param string ordering option, newest|oldest|popular|alpha|category
 * @param mixed an array if the search it to be restricted to areas, null if search all
 */
	function onSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		//global $mainframe;

		$db		=& JFactory::getDBO();
		$user	=& JFactory::getUser();

		require_once(JPATH_SITE.DS.'administrator'.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');

		$searchText = $text;
		if (is_array( $areas )) {
			if (!array_intersect( $areas, array_keys( plgSearchJeaAreas() ) )) {
				return array();
			}
		}

		$limit = $this->_params->def( 'search_limit', 50 );

		$text = trim( $text );
		if ($text == '') {
			return array();
		}

		$wheres = array();
		switch ($phrase) {
			case 'exact':
				$text		= $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'p.ref LIKE '.$text;			// Reference
				$wheres2[] 	= 'p.title LIKE '.$text;		// Title
				$where 		= '(' . implode( ') OR (', $wheres2 ) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode( ' ', $text );
				$wheres = array();
				foreach ($words as $word) {
					$word		= $db->Quote( '%'.$db->getEscaped( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'p.ref LIKE '.$word;		// Reference
					$wheres2[] 	= 'p.title LIKE '.$word;	// Title
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		switch ($ordering) {
			case 'oldest':
				$order = 'p.date_insert ASC';
				break;

			case 'popular':
				$order = 'p.hits DESC';
				break;

			case 'alpha':
				$order = 'p.title ASC';
				break;

			case 'category':
				$order = 'p.type_id ASC';
				break;

			case 'newest':
				default:
				$order = 'p.date_insert DESC';
				break;
		}

		$rows = array();

		// search in reference
		if ( $limit > 0 )
		{
			$query = 'SELECT p.title AS title, p.ref AS ref,'
			. ' p.date_insert AS created, p.land_space AS text,'
			. ' t.value AS section,'
			. ' CASE WHEN CHAR_LENGTH(p.alias) THEN CONCAT_WS(":", p.id, p.alias) ELSE p.id END as slug,'
			. ' "2" AS browsernav'
			. ' FROM #__jea_properties AS p'
			. ' INNER JOIN #__jea_types AS t ON t.id=p.type_id'
			. ' WHERE ( '.$where.' )'
			. ' AND p.published = 1'
			. ' GROUP BY p.id'
			. ' ORDER BY '. $order
			;
			$db->setQuery( $query, 0, $limit );
			$list = $db->loadObjectList();
			$limit -= count($list);

			if(isset($list))
			{
				foreach($list as $key => $item)
				{
					$list[$key]->href = 'index.php?option=com_jea&view=properties&id='.$item->slug;		// Mejorar esto y buscar Itemid
				}
			}
			$rows[] = $list;
		}

		$results = array();
		if(count($rows))
		{
			foreach($rows as $row)
			{
				/*$new_row = array();
				foreach($row AS $key => $property) {
					if(searchHelper::checkNoHTML($property, $searchText, array('ref', 'title'))) {
						$new_row[] = $property;
					}
				}
				$results = array_merge($results, (array) $new_row);*/
				//$results = array_merge($results, (array) $row);*/
			}
		}

		//return $results;
		return $list;
	}
}