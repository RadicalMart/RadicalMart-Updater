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
use Joomla\Component\RadicalMart\Administrator\Console\AbstractCommand;
use Joomla\Component\RadicalMart\Administrator\Helper\CommandsHelper;
use Joomla\Database\ParameterType;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterDatabaseTrait;
use Joomla\Plugin\RadicalMart\Updater\Traits\UpdaterResaveTrait;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class RadicalMart300 extends AbstractCommand
{
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
		'updateProductsStructure',
		'updateMetasStructure',
		'resaveProducts',
		'resaveMetas'
	];

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
				'categories_additional' => 'text NULL after `category_pathway`',
				'categories_all'        => 'text NULL after `categories_additional`',
				'meta_variability'      => 'int(10) unsigned NOT NULL DEFAULT 0 after `categories_all`',
			],
			[
				'idx_meta_variability' => ['`meta_variability`']
			]
		);

		$this->ioStyle->text('Get total items');
		$this->ioStyle->progressStart(1);
		$total = CommandsHelper::getTotalItems('#__radicalmart_products');
		$this->ioStyle->progressFinish();

		// Paste data to new columns
		$this->ioStyle->text('Past data to new columns');
		$this->ioStyle->progressStart($total);

		if (!$select = $this->databaseGetRudimentalSelect('#__radicalmart_products',
			['categories', 'pathway', 'ordering'], ['id', 'category']))
		{
			$this->ioStyle->progressFinish();

			return;
		}

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

				$this->ioStyle->progressAdvance();
			}

			// Clean RAM
			$db->disconnect();

			if (count($products) < $limit)
			{
				break;
			}
		}
		$this->ioStyle->progressFinish();

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
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function updateMetasStructure(): void
	{
		$this->ioStyle->title('Update meta products structure');

		$this->databaseCreateColumns('#__radicalmart_metas',
			[
				'category_pathway'      => 'int(10) unsigned NOT NULL DEFAULT 0 after `category`',
				'categories_additional' => 'text NULL after `category`',
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
		$this->ioStyle->progressStart(1);
		$total = CommandsHelper::getTotalItems('#__radicalmart_metas');
		$this->ioStyle->progressFinish();

		// Paste data to new columns
		$this->ioStyle->text('Past data to new columns');
		$this->ioStyle->progressStart($total);

		if (!$select = $this->databaseGetRudimentalSelect('#__radicalmart_metas',
			['categories', 'ordering'], ['id', 'category', 'products', 'params', 'created']))
		{
			$this->ioStyle->progressFinish();

			return;
		}

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
				$query        = $db->getQuery(true)
					->update($db->quoteName('#__radicalmart_products'))
					->set($db->quoteName('meta_variability') . ' = ' . $meta->id)
					->whereIn($db->quoteName('id'), $products_ids);
				$db->setQuery($query)->execute();

				$this->ioStyle->progressAdvance();
			}

			// Clean RAM
			$db->disconnect();

			if (count($metas) < $limit)
			{
				break;
			}
		}
		$this->ioStyle->progressFinish();

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
}