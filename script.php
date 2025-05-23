<?php
/*
 * @package     RadicalMart Updater Plugin
 * @subpackage  plg_radicalmart_updater
 * @version     __DEPLOY_VERSION__
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2025 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class,
			new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
				/**
				 * The application object
				 *
				 * @var  AdministratorApplication
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected AdministratorApplication $app;

				/**
				 * The Database object.
				 *
				 * @var   DatabaseDriver
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected DatabaseDriver $db;

				/**
				 * Language constant for errors.
				 *
				 * @var string
				 *
				 * @since __DEPLOY_VERSION__
				 */
				protected string $constant = "";

				/**
				 * Update methods.
				 *
				 * @var  array
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected array $updateMethods = [];

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  $app  The application object.
				 *
				 * @since __DEPLOY_VERSION__
				 */
				public function __construct(AdministratorApplication $app)
				{
					$this->app = $app;
					$this->db  = Factory::getContainer()->get('DatabaseDriver');
				}

				/**
				 * Function called after the extension is installed.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					$this->enablePlugin($adapter);

					return true;
				}

				/**
				 * Function called after the extension is updated.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called after the extension is uninstalled.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called before extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called after extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					$installer = $adapter->getParent();
					if ($type !== 'uninstall')
					{
						// Run updates script
						if ($type === 'update')
						{
							foreach ($this->updateMethods as $method)
							{
								if (method_exists($this, $method))
								{
									$this->$method($adapter);
								}
							}
						}
					}

					return true;
				}

				/**
				 * Enable plugin after installation.
				 *
				 * @param   InstallerAdapter  $adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function enablePlugin(InstallerAdapter $adapter)
				{
					// Prepare plugin object
					$plugin          = new \stdClass();
					$plugin->type    = 'plugin';
					$plugin->element = $adapter->getElement();
					$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
					$plugin->enabled = 1;

					// Update record
					$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
				}

			});
	}
};