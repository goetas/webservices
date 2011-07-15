<?php
//header("Content-type:text/plain");
use goetas\webservices\Client;
use goetas\xml\wsdl\Wsdl;

include '../autoload.php';

include '../../xsd/autoload.php';
include '../../wsdl/autoload.php';
include '../../xml/autoload.php';

spl_autoload_register(function($cname){
	if(strpos($cname, "hunderttausend\\flickr\\")===0){
		include "/mnt/md1/data/www/xschema2php".DIRECTORY_SEPARATOR.str_replace("\\",DIRECTORY_SEPARATOR,$cname).".php";
	}
});
//$ws = new Wsdl("http://www.swsoft.com/webservices/vza/4.0.0/VZA.wsdl");
//$ws = new Wsdl("http://hotelbooking-service.mercuriosistemi.com/?wsdl");


ini_set("soap.wsdl_cache_ttl", 0);
ini_set("soap.wsdl_cache_enabled ", 0);





$ws = new Wsdl("http://www.hunderttausend.de/shared/webservice/Flickr.asmx?WSDL");




$c = new Client($ws);


$p = $c->getProxy();

$search = new \hunderttausend\flickr\GetPhotosbyTag();
$search->setTags("city");
$search->setPage(1);
$search->setPerPage(10);

$v = $p->GetPhotosbyTag($search);
print_r($v);

foreach ($v->getGetPhotosbyTagResult()->getItems() as $item){
	echo "<img src='".$item->getSmallUrl()."'/> \n";
}
//print_r($v);




/*
 * $c = new Client("wsdl");

$c->addProxyObject('', '', '', new MioClient());



$obj = 


$lciente = $obj->getCliente(1024);*/
 
?>