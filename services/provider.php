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

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\RadicalMart\Updater\Extension\Updater;

return new class implements ServiceProviderInterface {

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @since   3.0.0
	 */
	public function register(Container $container)
	{
		// Register MVCFactory
		$container->registerServiceProvider(new MVCFactory('Joomla\\Component\\RadicalMart'));

		$container->set(PluginInterface::class,
			function (Container $container) {
				// Create plugin class
				$subject = $container->get(DispatcherInterface::class);
				$config  = (array) PluginHelper::getPlugin('radicalmart', 'updater');
				$plugin  = new Updater($subject, $config);

				// Set application
				$app = Factory::getApplication();
				$plugin->setApplication($app);

				// Set database
				$db = $container->get(DatabaseDriver::class);
				$plugin->setDatabase($db);

				// Set MVCFactory
				$mvcFactory = $container->get(MVCFactoryInterface::class);
				$plugin->setMVCFactory($mvcFactory);

				// Load component language
				$app->getLanguage()->load('com_radicalmart', JPATH_ADMINISTRATOR);

				return $plugin;
			}
		);
	}
};
