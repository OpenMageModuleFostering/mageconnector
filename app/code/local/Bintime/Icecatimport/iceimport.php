<?php

require "../../../../Mage.php";
Mage::app();
echo "\n".date("H:i:s")."   - start\n";
Mage::getModel('Bintime_Icecatimport_Model_Observer')->load();
echo "\n".date("H:i:s")."   - end\n";
