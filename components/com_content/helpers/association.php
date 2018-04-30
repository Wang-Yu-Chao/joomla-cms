<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('ContentHelper', JPATH_ADMINISTRATOR . '/components/com_content/helpers/content.php');
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');
JLoader::register('CategoryHelperAssociation', JPATH_ADMINISTRATOR . '/components/com_categories/helpers/association.php');

/**
 * Content Component Association Helper
 *
 * @since  3.0
 */
abstract class ContentHelperAssociation extends CategoryHelperAssociation
{
	/**
	 * Cached array of the content item id.
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $filters = array();

	/**
	 * Method to get the associations for a given item
	 *
	 * @param   integer  $id    Id of the item
	 * @param   string   $view  Name of the view
	 *
	 * @return  array   Array of associations for the item
	 *
	 * @since  3.0
	 */
	public static function getAssociations($id = 0, $view = null)
	{
		$jinput = JFactory::getApplication()->input;
		$view   = $view === null ? $jinput->get('view') : $view;
		$id     = empty($id) ? $jinput->getInt('id') : $id;
		$user   = JFactory::getUser();
		$groups = implode(',', $user->getAuthorisedViewLevels());

		if ($view === 'article')
		{
			if ($id)
			{
				if (!isset(static::$filters[$id])) 
				{
					$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $id);

					$return = array();

					foreach ($associations as $tag => $item)
					{
						if ($item->language != JFactory::getLanguage()->getTag())
						{
							$arrId   = explode(':', $item->id);
							$assocId = $arrId[0];

							$db    = JFactory::getDbo();
							$query = $db->getQuery(true)
								->select($db->quoteName('state'))
								->from($db->quoteName('#__content'))
								->where($db->quoteName('id') . ' = ' . (int) $assocId)
								->where($db->quoteName('access') . ' IN (' . $groups . ')');
							$db->setQuery($query);

							$result = (int) $db->loadResult();

							if ($result > 0)
							{
								$return[$tag] = ContentHelperRoute::getArticleRoute($item->id, (int) $item->catid, $item->language);
							}
						}

						static::$filters[$id] = $return;
					}

					if (count($associations) === 0)
					{
						static::$filters[$id] = array();
					}
				}

				return static::$filters[$id];
			}
		}

		if ($view === 'category' || $view === 'categories')
		{
			return self::getCategoryAssociations($id, 'com_content');
		}

		return array();
	}

	/**
	 * Method to display in frontend the associations for a given article
	 *
	 * @param   integer  $id  Id of the article
	 *
	 * @return  array   An array containing the association URL and the related language object
	 *
	 * @since  3.7.0
	 */
	public static function displayAssociations($id)
	{
		$return = array();

		if ($associations = self::getAssociations($id, 'article'))
		{
			$levels    = JFactory::getUser()->getAuthorisedViewLevels();
			$languages = JLanguageHelper::getLanguages();

			foreach ($languages as $language)
			{
				// Do not display language when no association
				if (empty($associations[$language->lang_code]))
				{
					continue;
				}

				// Do not display language without frontend UI
				if (!array_key_exists($language->lang_code, JLanguageHelper::getInstalledLanguages(0)))
				{
					continue;
				}

				// Do not display language without specific home menu
				if (!array_key_exists($language->lang_code, JLanguageMultilang::getSiteHomePages()))
				{
					continue;
				}

				// Do not display language without authorized access level
				if (isset($language->access) && $language->access && !in_array($language->access, $levels))
				{
					continue;
				}

				$return[$language->lang_code] = array('item' => $associations[$language->lang_code], 'language' => $language);
			}
		}

		return $return;
	}
}
