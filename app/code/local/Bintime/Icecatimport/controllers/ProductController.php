<?php
require_once 'Mage/Catalog/controllers/ProductController.php';
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_ProductController extends Mage_Catalog_ProductController{
	public function viewAction(){
		parent::viewAction();
	}
	
	public function galleryAction(){
		$this->getRequest()->setRouteName('catalog');
		parent::galleryAction();
	}
	
	public function imageAction()
	{
		$this->getRequest()->setRouteName('catalog');
		parent::imageAction();
	}
	
	public function preDispatch()
	{
		parent::preDispatch();
		if ($this->getRequest()->getActionName() == 'view'){
			$this->getRequest()->setRouteName('icecatimport');
		}
	} 
}
?>