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

use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Registry\Registry;

trait UpdaterParamsTrait
{
	/**
	 * Method to move old params data.
	 *
	 * @param   array  $mapping  Params move mapping [src=>dest].
	 *
	 * @since 3.0.0
	 */
	protected function paramsMoveParams(array $mapping = []): void
	{
		$this->ioStyle->text('Get current params');
		$this->startProgressBar();
		$db        = $this->getDatabase();
		$query     = $db->getQuery(true)
			->select(['extension_id', 'params'])
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_radicalmart'));
		$extension = $db->setQuery($query, 0, 1)->loadObject();
		$this->finishProgressBar();

		$this->ioStyle->text('Move params');
		$extension->params = new Registry($extension->params);
		$this->startProgressBar(count($mapping), true);
		$update = false;
		foreach ($mapping as $src => $dest)
		{
			if (!$extension->params->exists($src) || $extension->params->exists($dest))
			{
				$this->advanceProgressBar();
				continue;
			}

			$update = true;
			$extension->params->set($dest, $extension->params->get($src));
			$extension->params->remove($src);
			$this->advanceProgressBar();

		}
		$this->finishProgressBar();

		if (!$update)
		{
			return;
		}

		$this->ioStyle->text('Save updated params');
		$this->startProgressBar();
		$extension->params = $extension->params->toString();
		$db->updateObject('#__extensions', $extension, 'extension_id');
		ParamsHelper::reset();
		$db->disconnect();
		$this->finishProgressBar();
	}
}