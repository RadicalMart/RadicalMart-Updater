<?php
/*
 * @package     RadicalMart Updater Plugin
 * @subpackage  plg_radicalmart_updater
 * @version     3.0.0.1
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2025 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

namespace Joomla\Plugin\RadicalMart\Updater\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

class Updater extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  3.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalMartRegisterCLICommands' => 'onRadicalMartRegisterCLICommands'
		];
	}

	/**
	 * Listener for `onRadicalMartRegisterCLICommands` event.
	 *
	 * @param   array     $commands  Updated commands array.
	 * @param   Registry  $params    RadicalMart params.
	 *
	 * @since 3.0.0
	 */
	public function onRadicalMartRegisterCLICommands(array &$commands, Registry $params): void
	{
		$files = Folder::files(Path::clean(JPATH_PLUGINS . '/radicalmart/updater/src/Console'), '.php');
		foreach ($files as $file)
		{
			if ($file === 'AbstractCommand.php')
			{
				continue;
			}

			$commands[] = 'Joomla\\Plugin\\RadicalMart\\Updater\\Console\\' . File::stripExt($file);
		}
	}
}