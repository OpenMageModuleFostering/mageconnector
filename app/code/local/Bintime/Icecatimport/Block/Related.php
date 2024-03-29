<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Catalog product related items block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Bintime_Icecatimport_Block_Related extends Mage_Catalog_Block_Product_List_Related
{
 protected function _prepareData()
    {
		$product = Mage::registry('product');
		$helper = Mage::helper('icecatimport/getdata');
		$relatedProducts = $helper->getRelatedProducts();
		if(!$relatedProducts)
		{
		 return	parent::_prepareData();
		}
		
		$tmp = Mage::getSingleton('Bintime_Icecatimport_Model_Relatedcollection', $relatedProducts);
		$tmp = $tmp->getCollection();
		$this->_itemCollection = $tmp;
		$this->_addProductAttributesAndPrices($this->_itemCollection);
		Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_itemCollection);
		$this->_itemCollection->load();
		foreach ($this->_itemCollection as $product) {
            $product->setDoNotUseCategoryId(true);
        }
		return $this;
    }

}
