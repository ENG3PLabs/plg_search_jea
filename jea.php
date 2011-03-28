<?php
/**
 * @version		$Id: jea.php 1.5.2 2010-03-28
 * @package		JEA
 * @copyright	Copyright (C) 2010 Thader Consultores, C.B. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

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
		$sref = $this->_params->def( 'search_ref', 1 );
		$stitle = $this->_params->def( 'search_title', 1 );
		$sdescription = $this->_params->def( 'search_description', 0 );
		
		$text = trim( $text );
		if ($text == '') {
			return array();
		}

		$wheres = array();
		switch ($phrase) {
			case 'exact':
				$text		= $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
				$wheres2 	= array();
				if ($sref == 1) $wheres2[] 	= 'p.ref LIKE '.$text;						// Reference
				if ($stitle == 1) $wheres2[] 	= 'p.title LIKE '.$text;				// Title
				if ($sdescription == 1) $wheres2[] 	= 'p.description LIKE '.$text;		// Description
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
					if ($sref == 1) $wheres2[] 	= 'p.ref LIKE '.$word;						// Reference
					if ($stitle == 1) $wheres2[] 	= 'p.title LIKE '.$word;				// Title
					if ($sdescription == 1) $wheres2[] 	= 'p.description LIKE '.$word;		// Description
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

		if ( $limit > 0 )
		{
			$query = 'SELECT p.title AS title, p.ref AS ref,'
			. ' p.date_insert AS created, p.description AS text, p.type_id AS type,'
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
					$component =& JComponentHelper::getComponent('com_jea');

					$menus	= &JApplication::getMenu('site', array());
					$mitems	= $menus->getItems('componentid', $component->id);

					$match = null;

					foreach($mitems as $mitem)							// To search the Itemid
					{
						if (@$mitem->query['view'] == 'properties') {
							$params = @$mitem->params;					// Params from the menu item.
							$exploded = explode("\n",$params);
							$type_id = ltrim($exploded[1],'type_id=');	// In $exploded[1] is the type_id of the menu item
							if ($type_id == $item->type) {
								$match = $mitem;
								break;
							}
						}
					}
					
					$link = 'index.php?option=com_jea&view=properties&id='.$item->slug;
					
					if(isset($match)) {									// In case it doesn't match, it puts the Itemid configured in the mod_search
						$link .= '&Itemid='.$match->id;
					}
					
					$list[$key]->href = JRoute::_($link);
				}
			}
			$rows[] = $list;
		}

		return $list;
	}
}