<?php
/*
 * @package     RadicalMart Updater Plugin
 * @subpackage  plg_radicalmart_updater
 * @version     __DEPLOY_VERSION__
 * @author      RadicalMart Team - radicalmart.ru
 * @copyright   Copyright (c) 2026 RadicalMart. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://radicalmart.ru/
 */

namespace Joomla\Plugin\RadicalMart\Updater\Console;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper;
use Joomla\Database\ParameterType;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterDatabaseTrait;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterParamsTrait;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterResaveTrait;
use Joomla\Registry\Registry;

class RadicalMart3027 extends AbstractCommand
{
	use UpdaterParamsTrait;
	use UpdaterDatabaseTrait;
	use UpdaterResaveTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  3.0.0
	 */
	protected static $defaultName = 'radicalmart:updater:3.0.27';

	/**
	 * Command text title for configure.
	 *
	 * @var   string
	 *
	 * @since 3.0.0
	 */
	protected string $commandText = 'Radicalmart Updater: Fix for 3.0.27 version';

	/**
	 * Command description for configure help block.
	 *
	 * @var   string
	 *
	 * @since 3.0.0
	 */
	protected string $commandDescription = 'run script for fix RadicalMart from 3.0.26';

	/**
	 * Command methods for step by step run.
	 *
	 * @var  array
	 *
	 * @since 3.0.0
	 */
	protected array $methods = [
		'fixUsers',
	];

	/**
	 * Method to fix users after bug.
	 *
	 * @throws \Exception
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function fixUsers(): void
	{
		$this->ioStyle->title('Fix users');

		$this->ioStyle->text('Get total items');
		$this->startProgressBar();
		$total = CommandsHelper::getTotalItems('#__users');
		$this->finishProgressBar();

		// Paste data to new columns
		$this->ioStyle->text('Fix users');
		$this->startProgressBar($total, true);
		$db    = $this->getDatabase();
		$last  = 0;
		$limit = 100;
		while (true)
		{
			$query = $db->createQuery()
				->select(['id', 'name', 'username', 'email'])
				->from($db->quoteName('#__users'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER)
				->order('id asc');
			$users = $db->setQuery($query, 0, $limit)->loadObjectList();
			if (empty($users))
			{
				break;
			}
			foreach ($users as $user)
			{
				$last = (int) $user->id;
				if (!str_contains($user->email, '_rm_ace') && !str_contains($user->username, '_rm_ace'))
				{

					$this->advanceProgressBar();
					continue;
				}
				if ($last !== 17641)
				{
					continue;
				}

				$query              = $db->getQuery(true)
					->select(['id', 'user', 'contacts'])
					->from($db->quoteName('#__radicalmart_customers'))
					->where($db->quoteName('id') . ' = :last')
					->bind(':last', $last, ParameterType::INTEGER);
				$customer           = $db->setQuery($query, 0, 1)->loadObject();
				$customer->user     = new Registry($customer->user);
				$customer->contacts = new Registry($customer->contacts);

				$query        = $db->getQuery(true)
					->select(['user_id', 'phone'])
					->from($db->quoteName('#__radicalmart_users_phones'))
					->where($db->quoteName('user_id') . ' = :last')
					->bind(':last', $last, ParameterType::INTEGER);
				$phone_record = $db->setQuery($query, 0, 1)->loadObject();

				if (empty($customer->contacts->get('email')) && empty($phone_record))
				{
					$this->advanceProgressBar();
					continue;
				}

				$updateUser = [];
				if (!empty($customer->contacts->get('email')))
				{
					$updateUser['email'] = $customer->contacts->get('email');
				}


				if (empty($phone_record) && !empty($customer->contacts->get('phone')))
				{
					$updateUser['phone'] = $customer->contacts->get('phone');
				}

				if (!empty($updateUser))
				{
					$updateUser['first_name']  = $customer->contacts->get('first_name');
					$updateUser['second_name'] = $customer->contacts->get('second_name');
					$updateUser['last_name']   = $customer->contacts->get('last_name');

					UserHelper::saveData('com_radicalmart', $customer->id, $updateUser);
				}

				$this->advanceProgressBar();
			}

			// Clean RAM
			$this->cleanRadicalMartRAM();

			if (count($users) < $limit)
			{
				break;
			}
		}

		$this->finishProgressBar();
	}
}