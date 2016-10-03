<?php

require_once './app/Mage.php';
umask(0);
Mage::app()->setCurrentStore(1);
error_reporting(E_ALL);
set_time_limit(0);

Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND,Mage_Core_Model_App_Area::PART_EVENTS);

$productsArray = array(array("ID","ID2","Item title","Final URL","Image URL","Item subtitle","Item description","Item category","Price","Sale price","Formatted price","Formatted sale price","Contextual keywords","Item address"));

$store = Mage::app()->getStore();

$products = Mage::getModel('catalog/product')
                ->getCollection()
				->addAttributeToFilter('visibility', 4) //ar nera individualiai nematomas
                ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED) //ar yra ijungtas
				->addAttributeToFilter('small_image', array('notnull'=>'','neq'=>'no_selection')) // jei tik turi nuotrauka
                ->addAttributeToSelect('*')
                ->load();
            
$store = Mage::app()->getStore();

$storeAddress = Mage::getStoreConfig('general/store_information/address');       

$base_domain = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);     

$productMediaConfig = Mage::getModel('catalog/product_media_config');  
               
foreach ($products as $product){
	
	$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty();
	
	if ($stock >= 1) { // kiekis lygu arba daugiau uz 1 
	
		$product_id = $product->getId();
	
		$url = $product->getProductUrl();
		
		$product_name = $product->getName();
	
		if($product->getFinalPrice()) {
			$price = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), true);
		} else {
			$price = Mage::helper('tax')->getPrice($product, $product->getPrice(), true);
		}
		
		$categoryIds = $product->getCategoryIds();
		
		$category_name = '';
		
		

		// Checks for category in category tree
        if(count($categoryIds) ){
			$firstCategoryId = null;
			if(is_array($categoryIds) && count($categoryIds)>0){
				reset($categoryIds);
				$last_key = key($categoryIds);
				$categoryIds = array_reverse($categoryIds, true);
				foreach($categoryIds as $key => $categoryId){
					if(!empty($categoryId)){
						$firstCategoryId = $categoryId;
						$_category = Mage::getModel('catalog/category')->load($firstCategoryId);
						$category_name .= $_category->getName(). (($last_key!=$key&&!empty($categoryId))?"/":"");
					}
				}
			} else {
				$firstCategoryId = $categoryIds;
				$_category = Mage::getModel('catalog/category')->load($firstCategoryId);
				$category_name = $_category->getName();
			}
        }
		
		$category_name = ltrim(str_replace("////","/",$category_name),'/');
		
		$image_url = $productMediaConfig->getMediaUrl($product->getSmallImage()); //getImage(), getSmallImage(), getThumbnail()
		
		$product_description = strip_tags($product->getDescription());
		
		$productsArray[] = array(
								 $product->getSku(),
								 $product->getId(),
								 $product->getName(),
								 $url,
								 $image_url,
								 "",
								 $product_description,
								 $category_name,
								 Mage::helper('tax')->getPrice($product, $product->getPrice(), true). " EUR",
								 $price. " EUR",
								 "",
								 "",
								 $product->getMetaKeyword(),
								 $storeAddress
								);						
	}
}
// CSV formavimas
function maybeEncodeCSVField($string) {
	
	return $string; // if xls formater is on
	
    if(strpos($string, ',') !== false || strpos($string, '"') !== false || strpos($string, "\n") !== false) {
        $string = '"' . str_replace('"', '""', $string) . '"';
    }
    return $string;
}

function outputCSV($data) {
    $output = fopen("php://output", "w");
    foreach ($data as $row) {
        fputcsv($output, $row); // here you can change delimiter/enclosure
    }
    fclose($output);
}

include_once("xlsxwriter.class.php");

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="remarketing.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XLSXWriter();
$writer->writeSheet($productsArray);
$writer->writeToStdOut();//like echo $writer->writeToString();

exit();