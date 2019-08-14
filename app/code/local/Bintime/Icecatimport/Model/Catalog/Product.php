<?php
/**
 * Class overrides base Product Model to provide products icecat data
 *  @author Sergey Gozhedrianov <info@bintime.com>
 *
 */
class Bintime_Icecatimport_Model_Catalog_Product extends Mage_Catalog_Model_Product 
{
	public function getName()
	{
		$connection = Mage::getSingleton('core/resource')->getConnection('core_read');
		$manufacturerId = $this->getData(Mage::getStoreConfig('icecat_root/icecat/manufacturer'));
		if (Mage::getStoreConfig('icecat_root/icecat/manufacturer') == 'manufacturer'){
		    $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
		                    ->setEntityTypeFilter($this->getResource()->getTypeId())
		                    ->addFieldToFilter('attribute_code', 'manufacturer');
		    $attribute = $attributes->getFirstItem()->setEntity($this->getResource());
		    $manufacturer = $attribute->getSource()->getOptionText($manufacturerId);
		}
		else {
			$manufacturer = $manufacturerId;
		}
		$tableName = Mage::getSingleton('core/resource')->getTableName('icecatimport/data');
		$mappingTable = Mage::getSingleton('core/resource')->getTableName('icecatimport/supplier_mapping');
		$selectCondition = $connection->select()->
			from(array('connector' => $tableName), new Zend_Db_Expr('connector.prod_name'))
			->joinInner(array('supplier' => $mappingTable), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$connection->quote($manufacturer)}")
			->where('connector.prod_id = ? ', $this->getSku());
		$icecatName = $connection->fetchOne($selectCondition);
		
		return $icecatName ? $icecatName : $this->getData('name');
	}
}
?>