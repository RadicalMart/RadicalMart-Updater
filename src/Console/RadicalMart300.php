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

namespace Joomla\Plugin\RadicalMart\Updater\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Database\ParameterType;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterDatabaseTrait;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterParamsTrait;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterResaveTrait;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class RadicalMart300 extends AbstractCommand
{
	use UpdaterParamsTrait;
	use UpdaterDatabaseTrait;
	use UpdaterResaveTrait;

	/**
	 * The default command name
	 *
	 * @var    string|null
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'radicalmart:updater:3.0.0';

	/**
	 * Command text title for configure.
	 *
	 * @var   string
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected string $commandText = 'Radicalmart Updater: Update to 3.0.0 version';

	/**
	 * Command description for configure help block.
	 *
	 * @var   string
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected string $commandDescription = 'run script for update RadicalMart from 2.0.0 to 3.0.0';

	/**
	 * Command methods for step by step run.
	 *
	 * @var  array
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected array $methods = [
		'updateAlphaStructures',
		'updateComponentParams',
		'updateUsersColumns',
		'updateProductsStructure',
		'updateMetasStructure',
		'updateCategoriesStructure',
		'updateFieldsStructure',
		'updateMenuItems',
		'resaveProducts',
		'resaveMetas',
	];

	/**
	 * Method to update alpha database structure.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateAlphaStructures(): void
	{
		$this->ioStyle->title('Update for Alpha versions');

		// Add columns to products database
		$this->databaseCreateColumns('#__radicalmart_categories_items',
			[
			],
			[
				'idx_category_ordering_asc'  => ['`category_id`', '`ordering` asc'],
				'idx_category_ordering_desc' => ['`category_id`', '`ordering` desc'],
			]
		);
	}

	/**
	 * Method to update radicalmart params.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateComponentParams(): void
	{
		$this->ioStyle->title('Update RadicalMart Params');

		$this->paramsMoveParams([
			'user_login_code'    => 'login_code',
			'user_login_timeout-> login_code_timeout',
			'user_login_length'  => 'login_code_length',
			'user_login_symbols' => 'login_code_symbols',
			'user_ip'            => 'privacy_ip',
			'user_client'        => 'privancy_client',
			'user_menu'          => 'user_menu_additional',
		]);
	}

	/**
	 * Method to remove unsigned from created_by and modified_by.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateUsersColumns(): void
	{
		$this->ioStyle->title('Update users columns');

		$tables = [
			'#__radicalmart_products',
			'#__radicalmart_metas',
			'#__radicalmart_orders',
		];

		$db = $this->getDatabase();
		foreach ($tables as $table)
		{
			$this->ioStyle->text('Update `' . $table . '` table');
			$columns = $db->getTableColumns($table);
			$this->ioStyle->text('Update columns');
			$this->startProgressBar(2);
			foreach (['created_by', 'modified_by'] as $column)
			{
				if (!isset($columns[$column]) || !str_contains($columns[$column], 'unsigned'))
				{
					$this->advanceProgressBar();
					continue;
				}
				$db->setQuery('alter table ' . $db->quoteName($table) . 'modify ' . $db->quoteName($column)
					. ' int(10) default 0 not null')->execute();
				$this->advanceProgressBar();
			}

			$this->finishProgressBar();
			$indexes = $db->getTableKeys($table);
			foreach ($indexes as $index)
			{
				if ($index->Key_name === 'idx_createdby')
				{
					$this->databaseDropColumns($table, [], ['idx_createdby']);
					$this->databaseCreateColumns($table, [], ['idx_created_by' => ['`created_by`']]);
					break;
				}
			}
			$this->advanceProgressBar();
		}

		$db->disconnect();
	}

	/**
	 * Method to update products database structure.
	 *
	 * @throws \Exception
	 *
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateProductsStructure(): void
	{
		$this->ioStyle->title('Update products structure');

		// Add columns to products database
		$this->databaseCreateColumns('#__radicalmart_products',
			[
				'category_pathway'      => 'int(10) unsigned NOT NULL DEFAULT 0 after `category`',
				'category_route'        => 'int(10) unsigned NOT NULL DEFAULT 0 after `category_pathway`',
				'categories_additional' => 'text NULL after `category_route`',
				'categories_all'        => 'text NULL after `categories_additional`',
				'meta_variability'      => 'int(10) unsigned NOT NULL DEFAULT 0 after `categories_all`',
				'changelogs'            => 'json NULL after `params`',
			],
			[
				'idx_meta_variability' => ['`meta_variability`']
			]
		);

		$this->ioStyle->text('Get total items');
		$this->startProgressBar();
		$total = CommandsHelper::getTotalItems('#__radicalmart_products');
		$this->finishProgressBar();
		if ($total === 0)
		{
			$this->ioStyle->note('Products not found');
		}

		if (!$select = $this->databaseGetRudimentalSelect('#__radicalmart_products',
			['categories', 'pathway', 'ordering'], ['id', 'category']))
		{
			$this->ioStyle->note('Products structure is correct');

			return;
		}

		// Paste data to new columns
		$this->ioStyle->text('Past data to new columns');
		$this->startProgressBar($total, true);

		$db    = $this->getDatabase();
		$last  = 0;
		$limit = 100;
		while (true)
		{
			$query    = $db->getQuery(true)
				->select($select)
				->from($db->quoteName('#__radicalmart_products'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER)
				->order('id asc');
			$products = $db->setQuery($query, 0, $limit)->loadObjectList();
			if (empty($products))
			{
				break;
			}

			foreach ($products as $product)
			{
				$product->id       = (int) $product->id;
				$product->category = (int) $product->category;
				$last              = $product->id;

				$updateProduct = false;
				$update        = new \stdClass();
				$update->id    = $product->id;

				if (in_array('pathway', $select) && !empty($product->pathway))
				{
					$updateProduct            = true;
					$update->category_pathway = $product->pathway;
				}

				if (in_array('categories', $select) && !empty($product->categories))
				{
					$updateProduct                 = true;
					$product->categories           = ArrayHelper::toInteger(explode(',', $product->categories));
					$update->categories_additional = [];
					foreach ($product->categories as $category)
					{
						if ($category === 0 || $category === $product->category)
						{
							continue;
						}
						$update->categories_additional[] = $category;
					}

					$update->categories_additional = implode(',', $update->categories_additional);
				}

				if ($updateProduct)
				{
					$db->updateObject('#__radicalmart_products', $update, 'id');
				}

				if (in_array('ordering', $select))
				{
					$query = $db->getQuery(true)
						->select('item_id')
						->from($db->quoteName('#__radicalmart_categories_items'))
						->where($db->quoteName('category_id') . ' = 1')
						->where($db->quoteName('type') . ' = ' . $db->quote('product'))
						->where($db->quoteName('item_id') . ' = :product_id')
						->bind(':product_id', $product->id, ParameterType::INTEGER);
					$exist = $db->setQuery($query, 0, 1)->loadResult();
					if (!$exist)
					{
						$insert              = new \stdClass();
						$insert->item_id     = $product->id;
						$insert->type        = 'product';
						$insert->category_id = 1;
						$insert->state       = 0;
						$insert->ordering    = $product->ordering;

						$db->insertObject('#__radicalmart_categories_items', $insert);
					}
				}

				$this->advanceProgressBar();
			}

			// Clean RAM
			$this->cleanRadicalMartRAM();

			if (count($products) < $limit)
			{
				break;
			}
		}
		$this->finishProgressBar();

		// Drop columns in products database
		$this->databaseDropColumns('#__radicalmart_products',
			[
				'categories',
				'pathway',
				'ordering',
				'ordering_price'
			],
			[
				'idx_pathway',
				'idx_ordering',
				'idx_ordering_price'
			]
		);

		// Drop `#__radicalmart_products_categories` mapping table
		$this->databaseDropTables(['#__radicalmart_products_categories']);
	}

	/**
	 * Method to update metas database structure.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateMetasStructure(): void
	{
		$this->ioStyle->title('Update meta products structure');

		$this->databaseCreateColumns('#__radicalmart_metas',
			[
				'category_pathway'      => 'int(10) unsigned NOT NULL DEFAULT 0 after `category`',
				'category_route'        => 'int(10) unsigned NOT NULL DEFAULT 0 after `category_pathway`',
				'categories_additional' => 'text NULL after `category_route`',
				'categories_all'        => 'text NULL after `categories_additional`',
				'created_by'            => 'int(10) unsigned  NOT NULL DEFAULT 0 after `created`',
				'modified'              => 'datetime NULL  after `created_by`',
				'modified_by'           => 'int(10) unsigned  NOT NULL DEFAULT 0 after `modified`',
				'fulltext'              => 'mediumtext NULL after `introtext`',
				'search_text'           => 'longtext NULL after `fulltext`',
				'stock'                 => 'json NULL after `prices`',
				'in_stock'              => 'tinyint(3) NOT NULL DEFAULT 1 after `stock`',
				'fields'                => 'json NULL after `in_stock`',
			],
			[
				'idx_in_stock' => ['`in_stock`']
			]
		);

		$this->ioStyle->text('Get total items');
		$this->startProgressBar();
		$total = CommandsHelper::getTotalItems('#__radicalmart_metas');
		$this->finishProgressBar();

		if ($total === 0)
		{
			$this->ioStyle->note('Meta products not found');
		}

		if (!$select = $this->databaseGetRudimentalSelect('#__radicalmart_metas',
			['categories', 'ordering'], ['id', 'category', 'products', 'params', 'created']))
		{
			$this->ioStyle->note('Metas structure is correct');

			return;
		}

		// Paste data to new columns
		$this->ioStyle->text('Past data to new columns');
		$this->startProgressBar($total, true);

		$db    = $this->getDatabase();
		$last  = 0;
		$limit = 100;
		while (true)
		{
			$query = $db->getQuery(true)
				->select($select)
				->from($db->quoteName('#__radicalmart_metas'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER)
				->order('id asc');
			$metas = $db->setQuery($query, 0, $limit)->loadObjectList();
			if (empty($metas))
			{
				break;
			}

			foreach ($metas as $meta)
			{
				$meta->id       = (int) $meta->id;
				$meta->category = (int) $meta->category;
				$meta->products = (new Registry($meta->products))->toArray();
				$meta->params   = new Registry($meta->params);
				$last           = $meta->id;

				$updateMeta = false;
				$update     = new \stdClass();
				$update->id = $meta->id;

				if (in_array('categories', $select) && !empty($meta->categories))
				{
					$updateMeta                    = true;
					$meta->categories              = ArrayHelper::toInteger(explode(',', $meta->categories));
					$update->categories_additional = [];
					foreach ($meta->categories as $category)
					{
						if ($category === 0 || $category === $meta->category)
						{
							continue;
						}
						$update->categories_additional[] = $category;
					}

					$update->categories_additional = implode(',', $update->categories_additional);
				}

				if (empty($meta->created))
				{
					$updateMeta    = true;
					$meta->created = (new Date(time()))->toSql();
				}

				$variability_fields = $meta->params->get('variability_fields', false);
				if (is_object($variability_fields))
				{
					$variability_fields_aliases = [];
					foreach ((array) $variability_fields as $field)
					{
						if (isset($field->alias))
						{
							$variability_fields_aliases[] = $field->alias;
						}
					}

					if (count($variability_fields_aliases) > 0)
					{
						$query                  = $db->getQuery(true)
							->select('id')
							->from($db->quoteName('#__radicalmart_fields'))
							->whereIn($db->quoteName('alias'), $variability_fields_aliases, ParameterType::STRING);
						$variability_fields_ids = $db->setQuery($query)->loadColumn();

						if (count($variability_fields_ids) > 0)
						{
							$updateMeta     = true;
							$update->params = $meta->params;
							$update->params->set('variability_fields', $variability_fields_ids);
							$update->params = $update->params->toString();
						}
					}
				}


				if ($updateMeta)
				{
					$db->updateObject('#__radicalmart_metas', $update, 'id');
				}

				if (in_array('ordering', $select))
				{
					$item_id = $meta->id * -1;
					$query   = $db->getQuery(true)
						->select('item_id')
						->from($db->quoteName('#__radicalmart_categories_items'))
						->where($db->quoteName('category_id') . ' = 1')
						->where($db->quoteName('type') . ' = ' . $db->quote('meta'))
						->where($db->quoteName('item_id') . ' = :meta_id')
						->bind(':meta_id', $item_id, ParameterType::INTEGER);
					$exist   = $db->setQuery($query, 0, 1)->loadResult();
					if (!$exist)
					{
						$insert              = new \stdClass();
						$insert->item_id     = $item_id;
						$insert->type        = 'meta';
						$insert->category_id = 1;
						$insert->state       = 0;
						$insert->ordering    = $meta->ordering;

						$db->insertObject('#__radicalmart_categories_items', $insert);
					}
				}

				$products_ids = ArrayHelper::toInteger(ArrayHelper::getColumn($meta->products, 'id'));
				if (count($products_ids) > 0)
				{
					$query = $db->getQuery(true)
						->update($db->quoteName('#__radicalmart_products'))
						->set($db->quoteName('meta_variability') . ' = ' . $meta->id)
						->whereIn($db->quoteName('id'), $products_ids);
					$db->setQuery($query)->execute();
				}

				$this->advanceProgressBar();
			}

			$this->cleanRadicalMartRAM();

			if (count($metas) < $limit)
			{
				break;
			}
		}
		$this->finishProgressBar();

		// Drop columns in products database
		$this->databaseDropColumns('#__radicalmart_metas',
			[
				'categories',
				'ordering',
				'ordering_price'
			],
			[
				'idx_ordering',
				'idx_ordering_price'
			]
		);

		// Drop `#__radicalmart_meta_categories` mapping table
		$this->databaseDropTables(['#__radicalmart_metas_categories']);
	}

	/**
	 * Method to update categories database structure.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateCategoriesStructure(): void
	{
		$this->ioStyle->title('Update categories structure');
		$correct = true;

		$this->ioStyle->text('Check columns types');
		$this->startProgressBar();
		$db      = $this->getDatabase();
		$table   = '#__radicalmart_categories';
		$columns = $db->getTableColumns($table);
		$this->finishProgressBar();

		$update = [];
		if ($columns['fields'] !== 'text')
		{
			$update[] = 'alter table ' . $db->quoteName($table) . 'modify ' . $db->quoteName('fields')
				. ' text null';
		}

		if (count($update) > 0)
		{
			$correct = false;
			$this->ioStyle->text('Update columns');
			$this->startProgressBar(count($update));
			foreach ($update as $query)
			{
				$db->setQuery($query)->execute();
				$this->advanceProgressBar();
			}
			$this->finishProgressBar();
			$db->disconnect();
		}

		$this->databaseCreateColumns($table,
			[
				'totals' => 'json NULL after `fields`',
			]
		);

		$this->ioStyle->text('Get total items');
		$this->startProgressBar();
		$total = CommandsHelper::getTotalItems($table);
		$this->finishProgressBar();
		if ($total === 0)
		{
			$this->ioStyle->note('Categories not found');
		}
		$select = ($total > 0) ?
			$this->databaseGetRudimentalSelect('#__radicalmart_categories',
				['total_products', 'total_metas'], ['id', 'totals']) : false;
		if (!empty($select))
		{
			$this->ioStyle->text('Past data to new columns');
			$this->startProgressBar($total, true);
			$correct = false;
			$last    = 0;
			$limit   = 100;
			while (true)
			{
				$query      = $db->getQuery(true)
					->select($select)
					->from($db->quoteName($table))
					->where($db->quoteName('id') . ' > :last')
					->bind(':last', $last, ParameterType::INTEGER)
					->order('id asc');
				$categories = $db->setQuery($query, 0, $limit)->loadObjectList();
				if (empty($categories))
				{
					break;
				}

				foreach ($categories as $category)
				{
					$update         = new \stdClass();
					$update->id     = (int) $category->id;
					$last           = $update->id;
					$updateCategory = false;

					// Update totals
					if (in_array('total_products', $select) || in_array('total_metas', $select))
					{
						$updateCategory = true;

						$category->totals = new Registry($category->totals);
						if (in_array('total_products', $select))
						{
							$category->totals->set('products', $category->total_products);
						}
						if (in_array('total_metas', $select))
						{
							$category->totals->set('metas', $category->total_metas);
						}

						$update->totals = $category->totals->toString();
					}

					if ($updateCategory)
					{
						$db->updateObject('#__radicalmart_categories', $update, 'id');
					}

					$this->advanceProgressBar();
				}

				// Clean RAM
				$this->cleanRadicalMartRAM();

				if (count($categories) < $limit)
				{
					break;
				}
			}
			$this->finishProgressBar();

			// Drop columns in products database
			$this->databaseDropColumns($table,
				[
					'total_products',
					'total_metas',
				],
				[
					'idx_total_products',
					'idx_total_metas',
				]
			);
		}

		if ($correct)
		{
			$this->ioStyle->note('Categories structure is correct');
		}
	}

	/**
	 * Method to update fields database structure.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateFieldsStructure(): void
	{
		$this->ioStyle->title('Update fields structure');

		$this->ioStyle->text('Get columns');
		$this->startProgressBar();
		$db      = $this->getDatabase();
		$table   = '#__radicalmart_fields';
		$columns = $db->getTableColumns($table);
		$this->finishProgressBar();

		if (!isset($columns['fieldset']))
		{
			$this->ioStyle->note('Fields structure is correct');

			return;
		}

		$this->databaseCreateColumns($table,
			[
				'fieldset_administrator' => 'int(11) unsigned NOT NULL DEFAULT 0 after `plugin`',
				'fieldset_site'          => 'int(11) unsigned NOT NULL DEFAULT 0 after `fieldset_administrator`',
			],
			[
				'idx_fieldset_administrator' => ['`fieldset_administrator`'],
				'idx_fieldset_site'          => ['`fieldset_site`'],
			]
		);

		$this->ioStyle->text('Update data');
		$this->startProgressBar();
		$query = $db->getQuery(true)
			->update($db->quoteName($table))
			->set($db->quoteName('fieldset_site') . ' = ' . $db->quoteName('fieldset'))
			->set($db->quoteName('fieldset_administrator') . ' = ' . $db->quoteName('fieldset'));
		$db->setQuery($query)->execute();
		$this->finishProgressBar();

		$this->databaseDropColumns($table, ['fieldset'], ['idx_fieldset']);

		$db->disconnect();
	}

	/**
	 * Method to resave products.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function resaveMetas(): void
	{
		$this->ioStyle->title('Resave meta products items');

		$this->resaveItems('#__radicalmart_metas', 'Meta');
	}

	/**
	 * Method to change menu items types.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateMenuItems(): void
	{
		$this->ioStyle->title('Update menu products items');

		$this->ioStyle->text('Get total items');
		$this->startProgressBar(1, true);
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(id)')
			->from($db->quoteName('#__menu'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_radicalmart%'))
			->where($db->quoteName('link') . ' LIKE ' . $db->quote('%view=categories%'));
		$total = $db->setQuery($query)->loadResult();
		$this->finishProgressBar();

		if ($total === 0)
		{
			$this->ioStyle->note('Legacy menu items not found!');

			return;
		}

		$this->ioStyle->text('Change views');
		$this->startProgressBar($total, true);
		$last  = 0;
		$limit = 1;
		while (true)
		{
			$query = $db->createQuery()
				->select(['id', 'link', 'params'])
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_radicalmart%'))
				->where($db->quoteName('link') . ' LIKE ' . $db->quote('%view=categories%'))
				->where($db->quoteName('id') . ' > :last')
				->bind(':last', $last, ParameterType::INTEGER);

			$items = $db->setQuery($query, 0, $limit)->loadObjectList();
			$count = count($items);
			if ($count === 0)
			{
				break;
			}
			foreach ($items as $item)
			{
				$last = (int) $item->id;

				$uri = Uri::getInstance($item->link);
				$uri->setVar('view', 'category');
				$layout = $uri->getVar('layout', 'categories');
				$uri->setVar('layout', $layout);
				$item->link = $uri->toString();

				$item->params = new Registry($item->params);
				$item->params->set('view_categories_layout', $layout);
				$item->params->set('view_products_layout', '_:default');
				$item->params = $item->params->toString();

				$db->updateObject('#__menu', $item, 'id');

				$this->advanceProgressBar();
			}

			$db->disconnect();
			if ($count < $limit)
			{
				break;
			}
		}
		$this->finishProgressBar();
	}

	/**
	 * Method to resave products.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function resaveProducts(): void
	{
		$this->ioStyle->title('Resave products items');

		$this->resaveItems('#__radicalmart_products', 'Product');
	}
}