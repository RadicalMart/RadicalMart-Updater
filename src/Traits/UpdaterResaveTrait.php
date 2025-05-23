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

namespace Joomla\Plugin\RadicalMart\Updater\Traits;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper;
use Joomla\Component\RadicalMart\Administrator\Traits\LoadSuperUserIdentityCommandTrait;
use Joomla\Component\RadicalMart\Site\Helper\RouteHelper;
use Joomla\Component\RadicalMart\Site\Mapping\CategoryMapping;
use Joomla\Component\RadicalMart\Site\Mapping\ProductMapping;
use Joomla\Database\DatabaseAwareTrait;

trait UpdaterResaveTrait
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;
	use LoadSuperUserIdentityCommandTrait;

	/**
	 * Method to resave RadicalMart items.
	 *
	 * @param   string  $table      Items database table name.
	 * @param   string  $modelName  Admin model name.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function resaveItems(string $table, string $modelName): void
	{
		$this->loadSuperUserIdentity();

		$this->ioStyle->text('Get total items');
		$this->ioStyle->progressStart(1);
		$total = CommandsHelper::getTotalItems($table);
		$this->ioStyle->progressFinish();

		$this->ioStyle->text('Items advance');
		$progress = $this->ioStyle->createProgressBar($total);
		$progress->setFormat(' Time: %elapsed:6s% | Remaining: %remaining% '
			. PHP_EOL . ' %current%/%max% [%bar%]%percent:3s%% ');
		$progress->start();

		$last  = 0;
		$limit = 100;
		while (true)
		{
			$pks = CommandsHelper::getNextPrimaryKeys($table, $last, $limit);
			if (empty($pks))
			{
				break;
			}

			foreach ($pks as $pk)
			{
				$last = (int) $pk;

				/** @var AdminModel $adminModel */
				$adminModel = $this->getMVCFactory()->createModel($modelName, 'Administrator',
					['ignore_request' => true]);
				$adminModel->setState('save.task', 'save');

				$item = $adminModel->getItem($pk);
				if (empty($item) || empty($item->id))
				{
					continue;
				}

				$data   = (array) $item;
				$result = $adminModel->save($data);
				if ($result === false)
				{
					$message = [];
					foreach ($model->getErrors() as $error)
					{
						$message[] = ($error instanceof \Exception) ? $error->getMessage() : $error;
					}

					throw new \Exception(implode(PHP_EOL, $message));
				}

				$item  = null;
				$data  = null;
				$model = null;

				$progress->advance();
			}

			// Clean RAM
			$this->getDatabase()->disconnect();
			ParamsHelper::reset();
			CategoryMapping::reset();
			ProductMapping::reset();
			RouteHelper::reset();
			UserHelper::reset();

			if (count($pks) < $limit)
			{
				break;
			}
		}

		$progress->finish();
		$this->ioStyle->newLine(2);
	}
}