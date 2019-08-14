<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_ImageController extends Mage_Core_Controller_Front_Action{
	
	public function viewAction(){
		$result = Mage::getModel('icecatimport/observer')->load();
	}
} 
?>