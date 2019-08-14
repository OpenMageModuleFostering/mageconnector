<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Model_Observer
{
	private $errorMessage;
	private $connection;
	
	private $freeExportURLs = 'http://data.icecat.biz/export/freeurls/export_urls_rich.txt.gz';
	private $fullExportURLs = 'http://data.icecat.biz/export/export_urls_rich.txt.gz';
	protected $_supplierMappingUrl = 'http://data.icecat.biz/export/freeurls/supplier_mapping.xml';
	protected $_connectorDir = '/bintime/icecatimport/';
	protected $_productFile;
	protected $_supplierFile;
	
	protected function _construct()
    {
        $this->_init('icecatimport/observer');
    }
    
	public function load(){
		
		$loadUrl = $this->getLoadURL();
		ini_set('max_execution_time', 0);
		
		try {
			$this->_productFile = $this->_prepareFile(basename($loadUrl));
			$this->_supplierFile = $this->_prepareFile(basename($this->_supplierMappingUrl));
			echo "Start download of datafiles<br>";
			
			$this->downloadFile($this->_productFile, $loadUrl);
			echo "Start of supplier mapping file download";
			$this->downloadFile($this->_supplierFile, $this->_supplierMappingUrl);
			$this->XMLfile = Mage::getBaseDir('var') . $this->_connectorDir . basename($loadUrl, ".gz");
			echo "Start Unzipping Data File<br>";
			$this->unzipFile();
			echo "Start File Processing<br>";
			
			$this->_loadSupplierListToDb();
			$this->loadFileToDb();
			
			echo "File Processed Succesfully<br>";
		} catch( Exception $e) {
			echo $e->getMessage();
			Mage::log($e->getMessage());
		}
	}
	
	protected function _parseXml($stringXml){
		libxml_use_internal_errors(true);
		$simpleDoc = simplexml_load_string($stringXml);
		if ($simpleDoc){
			return $simpleDoc;
		}
		$simpleDoc = simplexml_load_string(utf8_encode($stringXml));
		if ($simpleDoc){
			return $simpleDoc;
		}	
		return false;
	}
	
	protected function _loadSupplierListToDb()
	{
		$connection = $this->getDbConnection();
		try {
			$connection->beginTransaction();
			Mage::log($this->_supplierFile);
			$xmlString = file_get_contents($this->_supplierFile);
			$xmlDoc = $this->_parseXml($xmlString);
			if ($xmlDoc) {
				$connection->truncate('bintime_supplier_mapping');
				$supplierList = $xmlDoc->SupplierMappings->SupplierMapping;
				foreach ($supplierList as $supplier) {
					$supplierSymbolList = $supplier->Symbol;
					$supplierId = $supplier['supplier_id'];
					foreach($supplierSymbolList as $symbol) {
						Mage::log($supplierId . " " . $symbol);
						$symbolName = (string)$symbol;
						$connection->insert('bintime_supplier_mapping', array('supplier_id' => $supplierId, 'supplier_symbol' => $symbolName));
					}
				}
				$connection->commit();
			} else {
				throw new Exception('Unable to process supplier file');
			}
		} catch (Exception $e) {
			$connection->rollBack();
			throw new Exception("Icecat Import Terminated: {$e->getMessage()}");
		}
	}
	
	private function getLoadURL(){
		$subscripionLevel = Mage::getStoreConfig('icecat_root/icecat/icecat_type');
		if ($subscripionLevel === 'full'){
			return $this->fullExportURLs;
		}
		else {
			return $this->freeExportURLs;
		}
	}
	
	public function getErrorMessage(){
		return $this->errorMessage;
	}
	
	public function getImageURL($productSku, $productManufacturer){
		$connection = $this->getDbConnection();
		try {
			$selectCondition = $connection->select()
						->from(array('connector' => 'bintime_connector_data'), new Zend_Db_Expr('connector.prod_img'))
						->joinInner(array('supplier' => 'bintime_supplier_mapping'), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$this->connection->quote($productManufacturer)}")
						->where('connector.prod_id = ? ', $productSku);
			$imageURL = $connection->fetchOne($selectCondition);
			if (empty($imageURL)){
				$this->errorMessage = "Given product id is not present in database";
				return false;
			}
			return $imageURL;
		} catch (Exception $e) {
			$this->errorMessage = "DB ERROR: {$e->getMessage()}";
			return false;
		}
	}
	
	private function getDbConnection(){
		if ($this->connection){
			return $this->connection;
		}
		$this->connection = Mage::getSingleton('core/resource')->getConnection('core_read');
		return $this->connection;
	}
	
	private function loadFileToDb(){
		$connection = $this->getDbConnection();
		try {
			$connection->beginTransaction();
			$fileHandler = fopen($this->XMLfile, "r");
			if ($fileHandler) {
				$connection->truncate('bintime_connector_data');
				while (!feof($fileHandler)) {
					$row = fgets($fileHandler);
					$oneLine = explode("\t", $row);
					if ($oneLine[0]!= 'product_id' && $oneLine[0]!= ''){
						try{
							$connection->insert('bintime_connector_data', array('prod_id' => $oneLine[1], 'prod_img' => $oneLine[6], 'prod_name' => $oneLine[12], 'supplier_id' => $oneLine[13]));
						}
						catch(Exception $e){
							Mage::log("connector issue: {$e->getMessage()}");
						}
					}
				}
				$connection->commit();
				fclose($fileHandler);
			}
		} catch (Exception $e) {
			$connection->rollBack();
			throw new Exception("Icecat Import Terminated: {$e->getMessage()}");
		}
	}
	
	private function unzipFile(){
		$gz = gzopen ( $this->_productFile, 'rb' );
		
		if (file_exists($this->XMLfile)){
			unlink($this->XMLfile);
		}
		
		$fileToWrite = @fopen($this->XMLfile, 'w+');
		
		if (!$fileToWrite){
			$this->errorMessage = 'Unable to open output txt file. Please remove all *.txt files from '.
			Mage::getBaseDir('var'). $this->_connectorDir .'folder';
			return false;
		}
		while (!gzeof($gz)) {
			$buffer = gzgets($gz, 100000);
			fputs($fileToWrite, $buffer) ;
		}
		gzclose ($gz);
		fclose($fileToWrite);
	}
	
	private function downloadFile($destinationFile, $loadUrl){
		$userName = Mage::getStoreConfig('icecat_root/icecat/login');
		$userPass = Mage::getStoreConfig('icecat_root/icecat/password');
		$fileToWrite = @fopen($destinationFile, 'w+');
		
		try{
			$webClient = new Zend_Http_Client();
			$webClient->setUri($loadUrl);
			$webClient->setMethod(Zend_Http_Client::GET);
			$webClient->setHeaders('Content-Type: text/xml; charset=UTF-8');
			$webClient->setAuth($userName, $userPass, Zend_Http_CLient::AUTH_BASIC);
			$response = $webClient->request();
			if ($response->isError()){
				throw new Exception('<br>ERROR Occured.<br>Response Status: '.$response->getStatus()."<br>Response Message: ".$response->getMessage());
			}
		}
		catch (Exception $e) {
				throw new Exception("Warning: cannot connect to ICEcat. {$e->getMessage()}");
			}
		$resultString = $response->getBody();
		fwrite($fileToWrite, $resultString);
		fclose($fileToWrite);
	}
	
	protected function _prepareFile($fileName){
		$varDir =  Mage::getBaseDir('var') . $this->_connectorDir;
		$filePath = $varDir . $fileName;
		if (!is_dir($varDir)){
			mkdir($varDir, 0777, true);
		}
		
		if (file_exists($filePath)){
			unlink($filePath);
		}
		
		return $filePath;
	}
}
?>