<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Model_Import extends Mage_Core_Model_Abstract {
	
	private $productDescriptionList = array();
	private $productDescription;
	private $fullProductDescription;
	private $lowPicUrl;
	private $highPicUrl;
	private $errorMessage;
	private $galleryPhotos = array();
	private $productName;
	private $relatedProducts = array();
	private $errorSystemMessage; //depricated
	
	protected function _construct()
    {
        $this->_init('icecatimport/import');
    }
	
	public function getProductDescription($productId, $vendorName, $locale, $userName, $userPass){
		if (null === $this->simpleDoc){
			$dataUrl = 'http://data.icecat.biz/xml_s3/xml_server3.cgi';
			if (empty($productId)) {
				$this->errorMessage = 'Given product has no MPN (SKU)';
				return false;
			}
			if (empty($vendorName)){
				$this->errorMessage = "Given product has no manufacturer specified.";
				return false;
			}
			if (empty($locale)) {
				$this->errorMessage = "Please specify product description locale";
				return false;
			}
			if ( empty($userName)) {
				$this->errorMessage = "No ICEcat login provided";
				return false;
			}
			 if (empty($userPass)){
				$this->errorMessage = "No ICEcat password provided";
				return false;
			}
			
			$getParamsArray = array("prod_id" => $productId,
									"lang" =>$locale,
									"vendor" => $vendorName,
									"output" =>'productxml'
			);
			Varien_Profiler::start('Bintime FILE DOWNLOAD:');
			try{
				$webClient = new Zend_Http_Client();
				$webClient->setUri($dataUrl);
				$webClient->setMethod(Zend_Http_Client::GET);
				$webClient->setHeaders('Content-Type: text/xml; charset=UTF-8');
				$webClient->setParameterGet($getParamsArray);
				$webClient->setAuth($userName, $userPass, Zend_Http_CLient::AUTH_BASIC);
				$response = $webClient->request();
				if ($response->isError()){
					$this->errorMessage = 'Response Status: '.$response->getStatus()." Response Message: ".$response->getMessage();
					return false;
				}
			}
			catch (Exception $e) {
				$this->errorMessage = "Warning: cannot connect to ICEcat. {$e->getMessage()}";
				return false;
			}
			 Varien_Profiler::stop('Bintime FILE DOWNLOAD:');
			$resultString = $response->getBody();
			
			if(!$this->parseXml($resultString)){
				return false;
			}
			
			if ($this->checkIcecatResponse($productId, $vendorName)){
				return false;
			}
			
			$this->loadProductDescriptionList();
			$this->loadOtherProductParams();
			$this->loadGalleryPhotos();
			 Varien_Profiler::start('Bintime FILE RELATED');
			$this->loadRelatedProducts();
			 Varien_Profiler::stop('Bintime FILE RELATED');
		}
		return true;
	}
	
	public function getSystemError(){
		return $this->errorSystemMessage;
	}
	
	public function getProductName(){
		return $this->productName;
	}
	
	public function getGalleryPhotos(){
		return $this->galleryPhotos;
	}
	
	private function loadGalleryPhotos(){
		$galleryPhotos = $this->simpleDoc->Product->ProductGallery->ProductPicture;
		if (!count($galleryPhotos)){
			return false;
		}
		foreach($galleryPhotos as $photo){
			$picHeight = (int)$photo["PicHeight"];
			$picWidth = (int)$photo["PicWidth"];
			$thumbUrl = (string)$photo["ThumbPic"];
			$picUrl = (string)$photo["Pic"];
			
			array_push($this->galleryPhotos, array(
										'height' => $picHeight,
										'width' => $picWidth,
										'thumb' => $thumbUrl,
										'pic' => $picUrl
										));
		}
	}
	
	public function getErrorMessage(){
		return $this->errorMessage;
	}
	
	private function checkIcecatResponse($productId, $vendorName){
		$errorMessage = $this->simpleDoc->Product['ErrorMessage'];
		if ($errorMessage != ''){
			if (preg_match('/^No xml data/', $errorMessage)){
				$this->errorSystemMessage = $errorMessage;
				return true;
			}
			if (preg_match('/^The specified vendor does not exist$/', $errorMessage)) {
								$this->errorSystemMessage = $errorMessage;
				return true;
			}
			$this->errorMessage = "Ice Cat Error: ".$errorMessage;
			return true;
		}
		return false;
	}
	
	public function getProductDescriptionList(){
		return $this->productDescriptionList;
	}
	
	
	public function getShortProductDescription(){
		return $this->productDescription;
	}
	
	public function getFullProductDescription(){
		return $this->fullProductDescription;
	}
	
	public function getLowPicUrl(){
		return $this->highPicUrl;
	}
	
	public function getRelatedProducts(){
		return $this->relatedProducts;
	}
	
	public function getVendor(){
		return $this->vendor;
	}
	
	public function getMPN(){
		return $this->productId;
	}
	
	public function getEAN(){
		return $this->EAN;
	}
	
	private function loadRelatedProducts(){
		$relatedProductsArray = $this->simpleDoc->Product->ProductRelated;
		if(count($relatedProductsArray)){
			foreach($relatedProductsArray as $product){
				$productArray = array();
				$productNS = $product->Product;
				$productArray['name'] = (string)$productNS['Name'];
				$productArray['thumb'] = (string)$productNS['ThumbPic'];
				$mpn = (string)$productNS['Prod_id'];
				$productSupplier = $productNS->Supplier;
				$productSupplierId = (int)$productSupplier['ID'];
				$productArray['supplier_thumb'] = 'http://images2.icecat.biz/thumbs/SUP'.$productSupplierId.'.jpg';
				$productArray['supplier_name'] = (string)$productSupplier['Name'];
				
				$this->relatedProducts[$mpn] = $productArray;
			}
		}
	}
	
	private function loadProductDescriptionList(){
		$descriptionArray = array();
		
		$specGroups = $this->simpleDoc->Product->CategoryFeatureGroup;
		$specFeatures = $this->simpleDoc->Product->ProductFeature;
		foreach($specFeatures as $feature){
				$id = (int)$feature['CategoryFeatureGroup_ID'];
				$featureText = (string)$feature["Presentation_Value"];
				$featureName = (string)$feature->Feature->Name["Value"];
				foreach($specGroups as $group){
					$groupId = (int)$group["ID"];
					if ($groupId == $id){
						$groupName = (string) $group->FeatureGroup->Name["Value"];
						$rating = (int)$group['No'];
						$descriptionArray[$rating][$groupName][$featureName] = $featureText;
						break;
					}
				}
		}
		krsort($descriptionArray);
		$this->productDescriptionList = $descriptionArray;
	}
	
	private function loadOtherProductParams(){
		$this->productDescription = (string) $this->simpleDoc->Product->ProductDescription['ShortDesc'];
		$this->fullProductDescription = (string)$this->simpleDoc->Product->ProductDescription['LongDesc'];
		$productTag = $this->simpleDoc->Product;
		$this->lowPicUrl = (string)$productTag["LowPic"];
		$this->highPicUrl = (string)$productTag["HighPic"];
		$this->productName = (string)$productTag["Name"];
		$this->productId = (string)$productTag['Prod_id'];
		$this->vendor = (string)$productTag->Supplier['Name'];
		$this->EAN = (string)$productTag->EANCode['EAN'];
	}
	
	private function parseXml($stringXml){
		libxml_use_internal_errors(true);
		$this->simpleDoc = simplexml_load_string($stringXml);
		if ($this->simpleDoc){
			return true;
		}
		$this->simpleDoc = simplexml_load_string(utf8_encode($stringXml));
		if ($this->simpleDoc){
			return true;
		}	
		return false;
	}
}
?>