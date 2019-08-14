<?php
/**
 * Class provides category page with images, cron processing
 * 
 *  @author Sergey Gozhedrianov <info@bintime.com>
 *
 */
class Bintime_Icecatimport_Model_Observered
{
   public function refill()
   {
      $_product = Mage::registry('product');
	  
	  $iceCatModel = Mage::getSingleton('icecatimport/import');

	  $helper = Mage::helper('icecatimport/getdata');
	  $helper->getProductDescription($_product);
		if($helper->hasError())
		{
				return;
		}
	  $_product->setShortDescription($iceCatModel->getShortProductDescription());	
	  $_product->setDescription(str_replace("\\n", "<br>",$iceCatModel->getFullProductDescription()));	
	  //$_product->setName($iceCatModel->getProductName());
	  $_product->setName($iceCatModel->getProductTitle());
	  $_product->setMetaTitle($iceCatModel->getProductTitle());
	  //$_product->setMetaTitle($iceCatModel->getProductName());

   }
}
?>
