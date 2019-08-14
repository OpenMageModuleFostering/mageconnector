<?php
/**
 * class overrides category getProductCollection function to provide products with needed attributes
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Model_Catalog_Category extends Mage_Catalog_Model_Category
{
	public function getProductCollection() 
	{
		$collection = parent::getProductCollection();
		$collection->addAttributeToSelect(Mage::getStoreConfig('icecat_root/icecat/manufacturer'));
		return $collection;
	}
}
?>