webservices
===========

Pure PHP SOAP server and client

[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/goetas/webservices/trend.png)](https://bitdeli.com/free "Bitdeli Badge")

```php


$client = new \goetas\webservices\Client("http://example.com/test.wsdl");
$service = $client->getProxy();

$quote = $service->getQuote();


```

