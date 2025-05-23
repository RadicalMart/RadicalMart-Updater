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

use Joomla\Database\DatabaseAwareTrait;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

trait UpdaterDatabaseTrait
{
	use DatabaseAwareTrait;

	/**
	 * Method to check and create table columns and indexes.
	 *
	 * @param   string  $table       Table name.
	 * @param   array   $newColumns  New columns array.
	 * @param   array   $newIndexes  New indexes array.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function databaseCreateColumns(string $table, array $newColumns = [], array $newIndexes = []): void
	{
		$this->ioStyle->text('Create new columns and indexes');

		$db      = $this->getDatabase();
		$columns = array_keys($db->getTableColumns($table));
		$indexes = ArrayHelper::getColumn($db->getTableKeys($table), 'Key_name');

		$this->ioStyle->progressStart(count($newColumns) + count($newIndexes));
		foreach ($newColumns as $column_name => $column_type)
		{
			if (!in_array($column_name, $columns))
			{
				$db->setQuery(
					'alter table ' . $db->quoteName($table) . ' add ' . $db->quoteName($column_name)
					. ' ' . $column_type . ';'
				)->execute();
			}

			$this->ioStyle->progressAdvance();
		}
		foreach ($newIndexes as $index_name => $index_columns)
		{
			if (!in_array($index_name, $indexes))
			{
				$db->setQuery(
					'alter table ' . $db->quoteName($table) . ' add index' . $db->quoteName($index_name)
					. ' (' . implode(', ', $index_columns) . ');')
					->execute();
			}
			$this->ioStyle->progressAdvance();
		}

		$this->ioStyle->progressFinish();
	}

	/**
	 * Method to check and drop table columns and indexes.
	 *
	 * @param   string  $table        Table name.
	 * @param   array   $dropColumns  Drop columns array.
	 * @param   array   $dropIndexes  Drop indexes array.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function databaseDropColumns(string $table, array $dropColumns = [], array $dropIndexes = []): void
	{
		$this->ioStyle->text('Drop columns and indexes');

		$db      = $this->getDatabase();
		$columns = array_keys($db->getTableColumns($table));
		$this->ioStyle->progressStart(count($dropColumns) + count($dropIndexes));
		foreach ($dropColumns as $dropColumn)
		{
			if (in_array($dropColumn, $columns))
			{
				$db->setQuery('alter table ' . $db->quoteName($table) . ' drop column'
					. $db->quoteName($dropColumn))->execute();
			}

			$this->ioStyle->progressAdvance();
		}

		$indexes = ArrayHelper::getColumn($db->getTableKeys($table), 'Key_name');
		foreach ($dropIndexes as $dropIndex)
		{
			if (in_array($dropIndex, $indexes))
			{
				$db->setQuery('drop index ' . $dropIndex . ' on ' . $db->quoteName($table))->execute();
			}

			$this->ioStyle->progressAdvance();
		}

		$this->ioStyle->progressFinish();
	}

	/**
	 * Method to drop  database tables if exists.
	 *
	 * @param   array  $tables  Tables names array.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function databaseDropTables(array $tables = []): void
	{
		$this->ioStyle->text('Drop tables');
		$this->ioStyle->progressStart(count($tables));
		$db = $this->getDatabase();
		foreach ($tables as $table)
		{
			try
			{
				$mappingTable = $db->getTableKeys($table);
			}
			catch (\Throwable $e)
			{
				$mappingTable = [];
			}

			if (!empty($mappingTable))
			{
				$db->dropTable($table);
			}
			$this->ioStyle->progressAdvance();
		}

		$this->ioStyle->progressFinish();
	}

	/**
	 * Method to get select columns array from rudimental columns if existed.
	 *
	 * @param   string  $table       Table name.
	 * @param   array   $rudimental  Rudimental columns.
	 * @param   array   $base        Base columns to add.
	 *
	 * @return bool|array Select columns array if rudimental exists, false if not.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function databaseGetRudimentalSelect(string $table, array $rudimental, array $base = []): bool|array
	{
		$db      = $this->getDatabase();
		$columns = $db->getTableColumns($table);
		$result  = $base;
		foreach ($rudimental as $column)
		{
			if (isset($columns[$column]))
			{
				$result[] = $column;
			}
		}

		if (count($result) === count($base))
		{
			return false;
		}

		return $result;
	}
}