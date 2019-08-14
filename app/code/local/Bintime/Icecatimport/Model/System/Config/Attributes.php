<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Model_System_Config_Attributes
{
    public function toOptionArray()
    {    
    	$attributesArray = Mage::getResourceModel('eav/entity_attribute_collection')
		->setAttributeSetFilter(Mage::getResourceSingleton('catalog/product')->getEntityType()->getDefaultAttributeSetId());
		$outputAttributeArray = array();
    	foreach($attributesArray as $attribute){
    		$outputAttributeArray[$attribute['attribute_code']]=$attribute['attribute_code'];
    	}
    	ksort($outputAttributeArray);
        return $outputAttributeArray;
    }
}
?>