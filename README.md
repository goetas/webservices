# goetas-webservices Webservices SOAP Server


Pure PHP SOAP server.

Features: 
 - PSR7 standard request/response
 - Type hinted
 - Framework friendly 
 
## Installation


There is one recommended way to install xsd2php via [Composer](https://getcomposer.org/):


Add the dependency to your ``composer.json`` file:

```js
  "require": {
      ..
      "goetas-webservices/soap-server":"^0.1",
      "jms/serializer": "serializer-master-dev as 1.0",
      ..
  },
  "require-dev": {
        ..
        "goetas-webservices/xsd2php":"^0.1",
        ..
  },
  "repositories": [{
      "type": "vcs",
      "url": "https://github.com/goetas/serializer.git"
  }],
```

This package requires a patched version of JMS Serializer.
In the last year the activity of JMS serializer was very low and some features
required by this project was rejected or not yet reviewed ( [#222](https://github.com/schmittjoh/serializer/pull/222) )


## Concept

This package assumes that you have already a [WSDL](https://en.wikipedia.org/wiki/Web_Services_Description_Language) file for your webservice.



- Suppose that we want to write a server implementation for the following WSDL (stored into `/usr/local/test.wsdl`)

```xml
<definitions name="HelloService"
   targetNamespace="http://www.examples.com/Hello"
   xmlns="http://schemas.xmlsoap.org/wsdl/"
   xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
   xmlns:tns="http://www.examples.com/Hello"
   xmlns:xsd="http://www.w3.org/2001/XMLSchema">
 
   <message name="SayHelloRequest">
      <part name="firstName" type="xsd:string"/>
   </message>
	
   <message name="SayHelloResponse">
      <part name="greeting" type="xsd:string"/>
   </message>

   <portType name="Hello_PortType">
      <operation name="sayHello">
         <input message="tns:SayHelloRequest"/>
         <output message="tns:SayHelloResponse"/>
      </operation>
   </portType>

   <binding name="Hello_Binding" type="tns:Hello_PortType">
      <soap:binding style="rpc"
         transport="http://schemas.xmlsoap.org/soap/http"/>
      <operation name="sayHello">
         <soap:operation soapAction="sayHello"/>
         <input>
            <soap:body
               encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
               namespace="urn:examples:helloservice"
               use="encoded"/>
         </input>
         <output>
            <soap:body
               encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
               namespace="urn:examples:helloservice"
               use="encoded"/>
         </output>
      </operation>
   </binding>

   <service name="Hello_Service">
      <documentation>WSDL File for HelloService</documentation>
      <port binding="tns:Hello_Binding" name="Hello_Port">
         <soap:address
            location="http://www.examples.com/SayHello/" />
      </port>
   </service>
</definitions>
```

- Suppose we have the following `composer.json`

```json

{
  "minimum-stability":"dev",
  "require": {
    "goetas-webservices/soap-server": "^0.1",
    "jms/serializer": "serializer-master-dev as 1.0",
    "zendframework/zend-diactoros": "^1.3"
  },
  "require-dev": {
    "goetas-webservices/xsd2php": "^0.1"
  },
  "repositories": [{
      "type": "vcs",
      "url": "https://github.com/goetas/serializer.git"
  }],
}


```

- Suppose we will use `Greetings\Work` as PHP namespace
- Suppose we will store php classes into `/usr/local/php_classes`
- Suppose we will store jms metadata into `/usr/local/jms_metadata`

- Generate php classes and jms metadata from your WSDL file using [xsd2php](https://github.com/goetas-webservices/xsd2php) running:
    
```sh
bin/xsd2php convert:php /usr/local/test.wsdl  --ns-map='http://www.examples.com/Hello;Greetings/Work/' --soap-messages
bin/xsd2php convert:jms /usr/local/test.wsdl  --ns-map='http://www.examples.com/Hello;Greetings/Work/' --soap-messages

```

This will generate the following classes: 


- Write the server class:

```php
namespace Greetings\Server;

class HelloServer 
{
    
    public function sayHello($name)
    {
        return "hello $name";
    }
    
    public function sayHelloCool(UserInfo $info, $place)
    {
        $greeting = new CoolGreeting();
        $greeting->setUserInfo($info);
        $greeting->setPlace($place);
        
        return $greeting;
    }
}
```


3. Handle incoming requestes (in this case a simple `index.php`) 

```php

// load composer autoloader
$autoloader = require __DIR__ . 'vendor/autoload.php';

// instruct composer how to load php classes for "Greetings\Work'" namespace
$autoloader->add('Greetings\Work', '/usr/local/php_classes');

$soapFactory = new SoapFactory();

// associate the XML namespace with the PHP namespace
$soapFactory->addNamespace('http://www.examples.com/Hello', 'Greetings\Work');

// instruct JMS where to find metadata files 
$soapFactory->addMetadata('Greetings\Work', 'Greetings\Work', '/usr/local/jms_metadata');

// get a soap server
$soapServer = $soapFactory->getServer();

// instantiate your server implementation
$handler = new Greetings\Server\HelloServer();

// create your PSR7 request (many php frameworks already offer prs7 requestes)
$request = Zend\Diactoros\ServerRequestFactory::fromGlobals();

// run the server
$response = $soapServer->handle($request, $handler);

// send bach the response to the client
$emitter = Zend\Diactoros\Response\SapiEmitter();
$emitter->emit($response);

```