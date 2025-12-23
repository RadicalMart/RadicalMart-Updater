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

namespace Joomla\Plugin\RadicalMart\Updater\Traits;

\defined('_JEXEC') or die;

use Joomla\Component\RadicalMart\Administrator\Traits\Command\UtilitiesTrait;
use Joomla\Utilities\ArrayHelper;

trait UpdaterDatabaseTrait
{
	use UtilitiesTrait;

	/**
	 * Method to check and create table columns and indexes.
	 *
	 * @param   string  $table       Table name.
	 * @param   array   $newColumns  New columns array.
	 * @param   array   $newIndexes  New indexes array.
	 *
	 * @since 3.0.0
	 */
	protected function databaseCreateColumns(string $table, array $newColumns = [], array $newIndexes = []): void
	{
		$this->ioStyle->text('Create new columns and indexes');

		$db      = $this->getDatabase();
		$columns = array_keys($db->getTableColumns($table));
		$indexes = ArrayHelper::getColumn($db->getTableKeys($table), 'Key_name');

		$this->startProgressBar(count($newColumns) + count($newIndexes), true);
		foreach ($newColumns as $column_name => $column_type)
		{
			if (!in_array($column_name, $columns))
			{
				$db->setQuery(
					'alter table ' . $db->quoteName($table) . ' add ' . $db->quoteName($column_name)
					. ' ' . $column_type . ';'
				)->execute();
			}

			$this->advanceProgressBar();
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
			$this->advanceProgressBar();
		}

		$this->finishProgressBar();
	}

	/**
	 * Method to check and drop table columns and indexes.
	 *
	 * @param   string  $table        Table name.
	 * @param   array   $dropColumns  Drop columns array.
	 * @param   array   $dropIndexes  Drop indexes array.
	 *
	 * @since 3.0.0
	 */
	protected function databaseDropColumns(string $table, array $dropColumns = [], array $dropIndexes = []): void
	{
		$this->ioStyle->text('Drop columns and indexes');

		$db      = $this->getDatabase();
		$columns = array_keys($db->getTableColumns($table));
		$this->startProgressBar(count($dropColumns) + count($dropIndexes), true);
		foreach ($dropColumns as $dropColumn)
		{
			if (in_array($dropColumn, $columns))
			{
				$db->setQuery('alter table ' . $db->quoteName($table) . ' drop column'
					. $db->quoteName($dropColumn))->execute();
			}

			$this->advanceProgressBar();
		}

		$indexes = ArrayHelper::getColumn($db->getTableKeys($table), 'Key_name');
		foreach ($dropIndexes as $dropIndex)
		{
			if (in_array($dropIndex, $indexes))
			{
				$db->setQuery('drop index ' . $dropIndex . ' on ' . $db->quoteName($table))->execute();
			}

			$this->advanceProgressBar();
		}

		$this->finishProgressBar();
	}

	protected function databaseModifyTableColumns(string $table, array $columns = [])
	{

	}

	/**
	 * Method to drop  database tables if exists.
	 *
	 * @param   array  $tables  Tables names array.
	 *
	 * @since 3.0.0
	 */
	protected function databaseDropTables(array $tables = []): void
	{
		$this->ioStyle->text('Drop tables');
		$this->startProgressBar(count($tables));
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
			$this->advanceProgressBar();
		}

		$this->finishProgressBar();
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
	 * @since 3.0.0
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