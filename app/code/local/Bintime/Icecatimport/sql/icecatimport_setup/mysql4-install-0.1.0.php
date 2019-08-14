<?php
$installer = $this;
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */

$installer->startSetup();

$installer->run("
	DROP TABLE IF EXISTS bintime_connector_data;
	CREATE TABLE {$this->getTable('bintime_connector_data')} (
		`prod_id` varchar(255) NOT NULL,
		`prod_img` varchar(255),
		KEY `PRODUCT_MPN` (`prod_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bintime Connector product image table';
	
	DROP TABLE IF EXISTS bintime_vendor_mapping;
	CREATE TABLE {$this->getTable('bintime_supplier_mapping')} (
		`supplier_id` int(11) NOT NULL,
		`supplier_symbol` VARCHAR(255),
		KEY `supplier_id` (`supplier_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bintime Connector vendor mapping table';
	
");
$installer->endSetup();
