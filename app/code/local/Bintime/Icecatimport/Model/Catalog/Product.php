<?php
/**
 * Class overrides base Product Model to provide products icecat data
 *  @author Sergey Gozhedrianov <info@bintime.com>
 *
 */
class Bintime_Icecatimport_Model_Catalog_Product extends Mage_Catalog_Model_Product 
{

    public function getImage()
    {
        if(!parent::getImage()||parent::getImage() == 'no_selection')
        {
            return "true";
        }
        else  
            return parent::getImage();
    }
   
 
    
    
     /**
     * Entity code.
     * Can be used as part of method name for entity processing
     */
    const ENTITY                 = 'catalog_product';

    const CACHE_TAG              = 'catalog_product';
    protected $_cacheTag         = 'catalog_product';
    protected $_eventPrefix      = 'catalog_product';
    protected $_eventObject      = 'product';
    protected $_canAffectOptions = false;

    /**
     * Product type instance
     *
     * @var Mage_Catalog_Model_Product_Type_Abstract
     */
    protected $_typeInstance            = null;

    /**
     * Product type instance as singleton
     */
    protected $_typeInstanceSingleton   = null;

    /**
     * Product link instance
     *
     * @var Mage_Catalog_Model_Product_Link
     */
    protected $_linkInstance;

    /**
     * Product object customization (not stored in DB)
     *
     * @var array
     */
    protected $_customOptions = array();

    /**
     * Product Url Instance
     *
     * @var Mage_Catalog_Model_Product_Url
     */
    protected $_urlModel = null;

    protected static $_url;
    protected static $_urlRewrite;

    protected $_errors = array();

    protected $_optionInstance;

    protected $_options = array();

    /**
     * Product reserved attribute codes
     */
    protected $_reservedAttributes;

    /**
     * Flag for available duplicate function
     *
     * @var boolean
     */
    protected $_isDuplicable = true;
    


    /**
     * Initialize resources
     */

    /**
     * Get product name
     *
     * @return string
     */
    /* 
    public function getName()
    {
        return $this->_getData('name');
        //return parent::getName();
        //return 'overriden name!!!';
    }
    */ 
    /**
     * Get collection instance
     *
     * @return object
     */

   

}
?>
