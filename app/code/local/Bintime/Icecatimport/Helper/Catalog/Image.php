<?php
/**
 * Overloaded catalog helper to substitute magento images
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Helper_Catalog_Image extends Mage_Catalog_Helper_Image
{

	public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile=null)
    {
    	if ($attributeName == 'image' && $imageFile == null ) {
    		$icecatHelper = Mage::helper('icecatimport/getdata')->getProductDescription($product);
    		if (!$icecatHelper->hasError()){
    			$imageFile = $icecatHelper->getLowPicUrl();
    			
    		}
    	}
    	if ($attributeName == 'small_image' && $imageFile == null) {
    		$imageFile = Mage::helper('icecatimport/image')->getImage($product);
    	}
    	
    	return parent::init($product, $attributeName, $imageFile);
	}

	public function __toString()
	{
		$url = parent::__toString();
		if ( $this->getImageFile() && strpos( $this->getImageFile(), 'icecat.biz') && strpos($url, 'placeholder') ) {
			$url = $this->getImageFile();
		}
		return $url;
	}
}
?>