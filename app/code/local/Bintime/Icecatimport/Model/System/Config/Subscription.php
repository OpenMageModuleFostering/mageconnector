<?php
/**
 * 
 *  @author Sergey Gozhedrianov <sergy.gzh@gmail.com>
 *
 */
class Bintime_Icecatimport_Model_System_Config_Subscription
{
    public function toOptionArray()
    {    
    	$paramsArray = array(
    		'free' => 'OpenICEcat',
    		'full' => 'Full ICEcat'
    	);
        return $paramsArray;
    }
}
?>