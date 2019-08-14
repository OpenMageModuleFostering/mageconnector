<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Helper_Image extends Mage_Core_Helper_Abstract
{
	public function getImage($_product){
		$sku = $_product->getData(Mage::getStoreConfig('icecat_root/icecat/sku_field'));
		
		$manufacturerId = $_product->getData(Mage::getStoreConfig('icecat_root/icecat/manufacturer'));
		if (Mage::getStoreConfig('icecat_root/icecat/manufacturer') == 'manufacturer'){
		    $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
		                    ->setEntityTypeFilter($_product->getResource()->getTypeId())
		                    ->addFieldToFilter('attribute_code', 'manufacturer');
		    $attribute = $attributes->getFirstItem()->setEntity($_product->getResource());
		    $manufacturer = $attribute->getSource()->getOptionText($manufacturerId);
		}
		else {
			$manufacturer = $manufacturerId;
		}
		$this->observer = Mage::getSingleton('icecatimport/observer');
		$url = $this->observer->getImageURL($sku, $manufacturer);
		return 	$url;
	}
}
?>