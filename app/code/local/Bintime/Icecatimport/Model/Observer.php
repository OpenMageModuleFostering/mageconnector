<?php
/**
 * Class provides category page with images, cron processing
 * 
 *  @author Sergey Gozhedrianov <info@bintime.com>
 *
 */
class Bintime_Icecatimport_Model_Observer
{
    const MODULE_NAME = 'Bintime_Icecatimport';
    private $errorMessage;
    private $connection;
    const DEBUG = false;
    
    private $freeExportURLs = 'http://data.Icecat.biz/export/freexml/files.index.csv';
    private $fullExportURLs = 'http://data.icecat.biz/export/export_urls_rich.txt.gz';
    protected $_freeSupplierMappingUrl = 'http://data.icecat.biz/export/freexml/supplier_mapping.xml';
    protected $_fullSupplierMappingUrl = 'http://data.icecat.biz/export/supplier_mapping.xml';
    //private $freeExportURLs = 'http://data.icecat.biz/export/freeurls/export_urls_rich.txt.gz';
    //protected $_freeSupplierMappingUrl = 'http://data.icecat.biz/export/freeurls/supplier_mapping.xml';
    protected $_connectorDir = '/bintime/icecatimport/';
    public $subscripionLevel;
    protected $_productFile;
    protected $_supplierFile;
    
    public function __construct(){
        $this->subscripionLevel = Mage::getStoreConfig('icecat_root/icecat/icecat_type');
    }

    protected function _construct()
    {
        $this->_init('icecatimport/observer');
    }
    
    /**
     * root method for uploading images to DB
     */
    public function load(){
        $loadUrl = $this->getLoadURL();
        $loadSupplierUrl = $this->getLoadSupplierURL();
	
        ini_set('max_execution_time', 0);      
        try {
            $this->_productFile = $this->_prepareFile(basename($loadUrl));
            $this->_supplierFile = $this->_prepareFile(basename($loadSupplierUrl));
            $this->log("Data file downloading started");
            $this->log('Import started');
			$this->log($this->_productFile);       
            $this->downloadFile($this->_productFile, $loadUrl);

            $this->log("Start of supplier mapping file download");
            $this->downloadFile($this->_supplierFile, $loadSupplierUrl);
            $this->XMLfile = Mage::getBaseDir('var') . $this->_connectorDir . basename($loadUrl, ".gz");
    
            $this->log("Start Unzipping Data File");
            $this->unzipFile(); 
            $this->log("Start File Processing");
            
            $this->_loadSupplierListToDb();
            $this->loadFileToDb();
            $this->log('Import finished');
            $this->log("File Processed Succesfully");
        } catch( Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * parse given XML to SIMPLE XML
     * @param string $stringXml
     */
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
    
    /**
     * Upload supplier mapping list to Database
     */
   
    protected function _loadSupplierListToDb()
    {
        $connection = $this->getDbConnection();
        $mappingTable = Mage::getSingleton('core/resource')->getTableName('icecatimport/supplier_mapping');
        try {
            $connection->beginTransaction();
            $xmlString = file_get_contents($this->_supplierFile);
            $xmlDoc = $this->_parseXml($xmlString);
            if ($xmlDoc) {
                $this->query("DROP TABLE IF EXISTS `".$mappingTable."_temp`");
                $this->query("
                        CREATE TABLE `".$mappingTable."_temp` (
                            `supplier_id` int(11) NOT NULL,
                            `supplier_symbol` varchar(255) DEFAULT NULL,
                             KEY `supplier_id` (`supplier_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 
                        ");

                $supplierList = $xmlDoc->SupplierMappings->SupplierMapping;
                $this->log('Start load suppliers');
                foreach ($supplierList as $supplier) {
                    $supplierSymbolList = $supplier->Symbol;
                    $supplierId 	= $supplier['supplier_id'];
		    $supplierName 	= $supplier['name'];
		    $connection->insert($mappingTable."_temp", array('supplier_id' => $supplierId, 'supplier_symbol' => $supplierName));
                    foreach($supplierSymbolList as $symbol) {
                        $symbolName = (string)$symbol;
                        $connection->insert($mappingTable."_temp", array('supplier_id' => $supplierId, 'supplier_symbol' => $symbolName));
                    }
                }
                $this->log('Suppliers loaded');

                $this->query("DROP TABLE IF EXISTS `".$mappingTable."_old`");
                                $this->query("rename table `".$mappingTable."` to `".$mappingTable."_old`, `".$mappingTable."_temp` to ".$mappingTable);

                $connection->commit();
            } else {
                $this->log('Unable to process supplier file');
                throw new Exception('Unable to process supplier file');
            }
        } catch (Exception $e) {
            $connection->rollBack();
            $this->log("Icecat Import Terminated: {$e->getMessage()}");
            throw new Exception("Icecat Import Terminated: {$e->getMessage()}");
        }
    }
    
    /**
     * retrieve URL of data file that corresponds ICEcat account
     */
    private function getLoadURL(){
        if ($this->subscripionLevel === 'full'){
            return $this->fullExportURLs;
        }
        else {
            return $this->freeExportURLs;
        }
    }
    
    /**
     * retrieve URL of supplier data file that corresponds ICEcat account
     */
    private function getLoadSupplierURL(){
        if ($this->subscripionLevel === 'full'){
            return $this->_fullSupplierMappingUrl;
        }
        else {
            return $this->_freeSupplierMappingUrl;
        }
    }
    
    /**
     * return error messages
     */
    public function getErrorMessage(){
        $this->log_error($this->errorMessage);
        return $this->errorMessage;
    }
    
    /**
     * getImage URL from DB
     * @param string $productSku
     * @param string $productManufacturer
     */
    public function getImageURL($productSku, $productManufacturer){
	//$this->log('Manufacturer - '.$productManufacturer);
        $connection = $this->getDbConnection();
        try {          
            $tableName = Mage::getSingleton('core/resource')->getTableName('icecatimport/data');
            $mappingTable = Mage::getSingleton('core/resource')->getTableName('icecatimport/supplier_mapping');
            $selectCondition = $connection->select()
                        ->from(array('connector' => $tableName), new Zend_Db_Expr('connector.high_res_img'))
                        ->joinInner(array('supplier' => $mappingTable), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$this->connection->quote($productManufacturer)}")
                        ->where('connector.prod_id = ? ', $productSku);
            
            $imageURL = $connection->fetchOne($selectCondition);
            if (empty($imageURL)){
                $selectCondition = $connection->select()
                            ->from(array('connector' => $tableName), new Zend_Db_Expr('connector.high_res_img'))
                            ->joinInner(array('supplier' => $mappingTable), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$this->connection->quote($productManufacturer)}")
                            ->where('connector.m_prod_id = ? ', $productSku);
                $imageURL = $connection->fetchOne($selectCondition);
                if (empty($imageURL)){
                    $this->errorMessage = "Given product id is not present in database";
                    $this->log_error($this->errorMessage);
                    return false;
                }elseif( Mage::getStoreConfig('advanced/modules_disable_output/'.self::MODULE_NAME) ){
                    return false;
                }
            }
            return $imageURL;
        } catch (Exception $e) {
            $this->errorMessage = "DB ERROR: {$e->getMessage()}";
            $this->log_error($this->errorMessage);
            return false;
        }
    }
    /**
     * getImage URL from DB
     * @param string $productSku
     * @param string $productManufacturer
     */
    public function getImageLowURL($productSku, $productManufacturer){
	//$this->log('Manufacturer - '.$productManufacturer);
        $connection = $this->getDbConnection();
        try {          
            $tableName = Mage::getSingleton('core/resource')->getTableName('icecatimport/data');
            $mappingTable = Mage::getSingleton('core/resource')->getTableName('icecatimport/supplier_mapping');
            $selectCondition = $connection->select()
                        ->from(array('connector' => $tableName), new Zend_Db_Expr('connector.low_res_img'))
                        ->joinInner(array('supplier' => $mappingTable), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$this->connection->quote($productManufacturer)}")
                        ->where('connector.prod_id = ? ', $productSku);
            
            $imageURL = $connection->fetchOne($selectCondition);
            if (empty($imageURL)){
                $selectCondition = $connection->select()
                            ->from(array('connector' => $tableName), new Zend_Db_Expr('connector.low_res_img'))
                            ->joinInner(array('supplier' => $mappingTable), "connector.supplier_id = supplier.supplier_id AND supplier.supplier_symbol = {$this->connection->quote($productManufacturer)}")
                            ->where('connector.m_prod_id = ? ', $productSku);
                $imageURL = $connection->fetchOne($selectCondition);
                if (empty($imageURL)){
                    $this->errorMessage = "Given product id is not present in database";
                    $this->log_error($this->errorMessage);
                    return false;
                }elseif( Mage::getStoreConfig('advanced/modules_disable_output/'.self::MODULE_NAME) ){
                    return false;
                }
            }
            return $imageURL;
        } catch (Exception $e) {
            $this->errorMessage = "DB ERROR: {$e->getMessage()}";
            $this->log_error($this->errorMessage);
            return false;
        }
    }
    
    public function getIcecatProductId($prod_id){
        $connection = $this->getDbConnection();
        $product_id = null;
        try {          
            $tableName = Mage::getSingleton('core/resource')->getTableName('icecatimport/data');
            $selectCondition = $connection->select()
                        ->from(array('connector' => $tableName), 'product_id')
                        ->where('connector.prod_id = ? ', $prod_id)
                        ->orwhere('connector.m_prod_id = ? ', $prod_id);
            $product_id = $connection->fetchOne($selectCondition);
        } catch (Exception $e) {
            $this->errorMessage = "DB ERROR: {$e->getMessage()}";
             $this->log_error($this->errorMessage);
            return false;
        }
        return $product_id;
    }
    
    /**
     * Singletong for DB connection
     */
    private function getDbConnection(){
        if ($this->connection){
            return $this->connection;
        }
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        return $this->connection;
    }
    
    /**
     * Upload Data file to DP
     */
    
    private function loadFileToDb(){
        $connection = $this->getDbConnection();
        $tableName = Mage::getSingleton('core/resource')->getTableName('icecatimport/data');
        try {
            $connection->beginTransaction();
            //$fileHandler = fopen($this->XMLfile, "r"); //if ($fileHandler) {
            if ( file_exists($this->XMLfile) ) {
                $this->query("DROP TABLE IF EXISTS `".$tableName."_temp`");  // bintime_connector_data
                /*
                $this->query("
                        CREATE TABLE `".$tableName."_temp` (
                            `product_id` int(11) DEFAULT NULL,
                            `prod_id` varchar(255) NOT NULL,
                            `m_prod_id` varchar(255) NOT NULL,
                            `supplier_id` int(11) DEFAULT NULL,
                            `prod_name` varchar(255) DEFAULT NULL,
                            `prod_img` varchar(255) DEFAULT NULL,
                            KEY `PRODUCT_MPN` (`prod_id`),
                            KEY `supplier_id` (`supplier_id`),
                            KEY `product_id` (`product_id`),
                            KEY `prod_id` (`prod_id`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                ");
                */
                //$this->query("DROP TABLE IF EXISTS `icecat_load_data`");
                if( $this->subscripionLevel === 'full'){
                    $this->query("CREATE TABLE `".$tableName."_temp` (
                                          `product_id` int(11),
                                          `prod_id` varchar(255),
                                          `quality` varchar(50),
                                          `url` varchar(50),
                                          `supplier_id` int(11) default null,
                                          `high_res_img` varchar(255) default null,
                                          `low_res_img` varchar(255) default null,
                                          `thumbnail_img` varchar(255) default null,
                                          `uncatid` int(11),
                                          `category_id` int(11),
                                          `m_prod_id` varchar(50),
                                          `ean_upcs` varchar(50),
                                          `model_name` varchar(255) default null,
                                          `original_supplier_id` int(11),
                                          `product_view` varchar(255),
                                          `on_market` varchar(50),
                                          `country_market_set` varchar(50),
                                          `updated` int(11),
                                            key `product_mpn` (`prod_id`),
                                            key `supplier_id` (`supplier_id`)
                                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                                ");
                }else{
                    $this->query("CREATE TABLE `".$tableName."_temp` (
                                          `path` varchar(50),
                                          `product_id` int(11),
                                          `updated` int(11),
                                          `quality` varchar(50),
                                          `supplier_id` int(11) DEFAULT NULL,
                                          `prod_id` varchar(255),
                                          `catid` int(11),
                                          `m_prod_id` varchar(50),
                                          `ean_upc` varchar(50),
                                          `on_market` varchar(50),
                                          `country_market` varchar(50),
                                          `model_name` varchar(255) DEFAULT NULL,
                                          `product_view` varchar(50),
                                          `high_res_img` varchar(255) DEFAULT NULL,
                                          `high_pic_size` int(11),
                                          `high_pic_width` int(11),
                                          `high_pic_height` int(11),
                                          `m_supplier_id` int(11),
                                          `m_supplier_name` varchar(50),
                                            KEY `PRODUCT_MPN` (`prod_id`),
                                            KEY `supplier_id` (`supplier_id`)
                                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                                ");
                }
            $this->query("LOAD DATA LOCAL INFILE '".$this->XMLfile."' 
                               INTO TABLE `".$tableName."_temp` 
                               FIELDS TERMINATED BY '\t' 
                               LINES TERMINATED BY '\n' IGNORE 1 LINES ");
/*
if( $this->subscripionLevel === 'full'){
    $this->query("INSERT INTO `{$tableName}_temp` select product_id, prod_id, m_prod_id, supplier_id, model_name, High_res_img from `icecat_load_data`");
}else{
    $this->query("INSERT INTO `{$tableName}_temp` select product_id, prod_id, m_prod_id, supplier_id, model_name, high_pic from `icecat_load_data`");
}
*/
                /*
                while (!feof($fileHandler)) {
                    $row = fgets($fileHandler);
                    $oneLine = explode("\t", $row);
                    //if ($oneLine[0]!= 'product_id' && $oneLine[0]!= ''){
                    if ($oneLine[0]!= 'path' && $oneLine[0]!= ''){
                        try{
                            $connection->insert($tableName."_temp", array('prod_id' => $oneLine[5], 'prod_img' => $oneLine[13], 'prod_name' => $oneLine[11], 'supplier_id' => $oneLine[4]));
                            //$connection->insert($tableName."_temp", array('prod_id' => $oneLine[1], 'prod_img' => $oneLine[6], 'prod_name' => $oneLine[12], 'supplier_id' => $oneLine[4]));
                        
                        }

                        catch(Exception $e){
                            $this->log("connector issue: {$e->getMessage()}");
                        }
    
                    }
                }
                */
                $this->query("DROP TABLE IF EXISTS `".$tableName."_old`");
                $this->query("rename table `".$tableName."` to `".$tableName."_old`, `".$tableName."_temp` to ".$tableName);

                if( Mage::getStoreConfig('icecat_root/icecat/productname') == '1' ){
                    // import product names
                    $productNameAttributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product', 'name');
                    $query = "UPDATE `catalog_product_entity_varchar`AS cpv 
                                INNER JOIN catalog_product_entity AS e
                                    ON cpv.entity_id = e.entity_id
                                INNER JOIN {$tableName} b 
                                    ON e.sku = b.prod_id
                                SET cpv.value = b.model_name
                                WHERE cpv.attribute_id = {$productNameAttributeId}";
                    $this->query($query);
                }
                $connection->commit();
                //fclose($fileHandler);
            }
        } catch (Exception $e) {
            $connection->rollBack();
            $this->log("Icecat Import Terminated: {$e->getMessage()}");
            throw new Exception("Icecat Import Terminated: {$e->getMessage()}");
        }
    }
    
    /**
     * unzip Uploaded file
     */
    private function unzipFile(){
        $gz = gzopen ( $this->_productFile, 'rb' );
        
        if (file_exists($this->XMLfile)){
            unlink($this->XMLfile);
        }
        
        $fileToWrite = @fopen($this->XMLfile, 'w+');
        
        if (!$fileToWrite){
            $this->errorMessage = 'Unable to open output txt file. Please remove all *.txt files from '.
            Mage::getBaseDir('var'). $this->_connectorDir .'folder';
            $this->log_error($this->errorMessage);
            return false;
        }
        while (!gzeof($gz)) {
            $buffer = gzgets($gz, 100000);
            fputs($fileToWrite, $buffer) ;
        }
        gzclose ($gz);
        fclose($fileToWrite);
    }
    
    /**
     * Process downloading files
     * @param string $destinationFile
     * @param string $loadUrl
     */
    private function downloadFile($destinationFile, $loadUrl){
        $userName = Mage::getStoreConfig('icecat_root/icecat/login');
        $userPass = Mage::getStoreConfig('icecat_root/icecat/password');
        $fileToWrite = fopen($destinationFile, 'w+');
        
        try{
    //    echo "downloadFile function\n";
            $webClient = new Zend_Http_Client();
    //echo "Zend_Http_Client()\n";
            $webClient->setUri($loadUrl);
    //echo "setUri".$loadUrl."\n";
            $webClient->setConfig(array('maxredirects' => 0,  'timeout'      => 60, 'adapter'=>'Zend_Http_Client_Adapter_Curl'));
            $webClient->setMethod(Zend_Http_Client::GET);
    //echo "setMethod\n";
            $webClient->setHeaders('Content-Type: text/csv; charset=UTF-8');
    //echo "setHeaders\n";
	//echo "$userName\n";
	//echo "$userPass\n";
            $webClient->setAuth($userName, $userPass, Zend_Http_CLient::AUTH_BASIC);
    //echo "setAuth\n";
//    var_dump($webClient);

            $response = $webClient->request();
//    var_dump($response);
    //if($response == NULL) echo "Response empty!\n";
    //echo "request()\n";
            if ($response->isError()){
          //  echo "error 1\n";
                $this->log('ERROR Occured. Response Status: '.$response->getStatus()."Response Message: ".$response->getMessage());
                throw new Exception('\nERROR Occured. \nResponse Status: '.$response->getStatus()."\nResponse Message: ".$response->getMessage());
            }
        }
        catch (Exception $e) {
        //echo "error 2\n";
                $this->log("Warning: cannot connect to ICEcat. {$e->getMessage()}");
                throw new Exception("Warning: cannot connect to ICEcat. {$e->getMessage()}");
            }
        //echo "downloadFile function end\n";
        $resultString = $response->getBody();
        fwrite($fileToWrite, $resultString);
        fclose($fileToWrite);
    }
    
    /**
     * Prepares file and folder for futur download
     * @param string $fileName
     */
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
    
    public function query($query) {
        $connection = $this->getDbConnection();
        if( self::DEBUG ){
            $this->log($query);
        }
        try{
            $connection->query($query);
        }catch(Exception $e){
            $this->log($e->getMessage());
        }
    }
    
    public function log($query){
        mage::log($query, null, 'icecatimport.log');
        echo date("Y-m-d H:i:s")." - ". $query . "\n";
    }
    
    public function log_error($query){
        mage::log($query, null, 'icecat_errors.log');
    }
}
?>
