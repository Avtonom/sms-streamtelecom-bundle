Avtonom SMS notifications bundle by Stream Telecom
===================================

Provider to send SMS notifications for Symfony2 bundle for PHP by Stream Telecom (stream-telecom.ru). Use KPhoenSmsSenderBundle for Symfony and Carpe-Hora/SmsSender.

Page bundle: https://github.com/Avtonom/sms-streamtelecom-bundle

## Features

* Get the session ID (receiving the token, authorization)
* Sending a single SMS message (without taking into account the recipient's time zone)
* Getting user balance
* Validation of input data through a standard functional forms.

Maybe in the future:
* Security (blocking) from overly frequent messaging.

### HttpAdapters ###

_HttpAdapters_ are responsible to get data from remote APIs.

Currently, there are the following adapters:

* `CurlHttpAdapter` for [cURL](http://php.net/manual/book.curl.php);  (recommended)
* `BuzzHttpAdapter` for [Buzz](https://github.com/kriswallsmith/Buzz), a
  lightweight PHP 5.3 library for issuing HTTP requests; (For additional installation of this dependence)


#### To Install

Run the following in your project root, assuming you have composer set up for your project

```sh

composer.phar require avtonom/sms-streamtelecom-bundle ~1.1

```

Switching `~1.1` for the most recent tag.

Add the bundle to app/AppKernel.php

```php

$bundles(
    ...
       new Avtonom\Sms\StreamtelecomBundle\AvtonomSmsStreamtelecomBundle(),
    ...
);

```

Configuration options (config.yaml):

``` yaml

k_phoen_sms_sender:
    pool:         ~   # right now, only "memory" is supported
    providers:    [streamtelecom]
    factories:    [ "%kernel.root_dir%/../src/Avtonom/Sms/StreamtelecomBundle/Resources/config/provider_factories.xml" ]

    streamtelecom:
        login:     %sms.provider.streamtelecom.login%
        password:  %sms.provider.streamtelecom.password%
        originators:  %sms.provider.streamtelecom.originators%

```

Configuration options (parameters.yaml):

``` yaml

parameters:
    sms.provider.streamtelecom.login: ~
    sms.provider.streamtelecom.password: ~
    sms.provider.streamtelecom.originators: [] # Leave an empty array if there is no strict checking the sender's name
    
```

Create a logger named "avtonom_sms.logger". Cample code (services.yml): 

``` yaml

services:
    avtonom_sms.logger:
        public: true
        class: Symfony\Bridge\Monolog\Logger
        arguments: [sms]
    
```

#### Use

``` php

try {
    $sendResult = $this->get('sms.sender')->send('0642424242', 'It\'s the answer.', 'Kévin');
} catch(\SmsSender\Exception\WrappedException $e){
    if($e->getWrappedException() && $e->getWrappedException() instanceof \SmsSender\Exception\AdapterException){
        $smsException = new \Exception($e->getWrappedException()->getMessage(), $e->getWrappedException()->getCode(), $e);
        if($e->getWrappedException()->getData()){
            var_dump($e->getWrappedException()->getData()); // request data
        }

    }
    throw $smsException;
}
```

### Need Help?

1. Create an issue if you've found a bug,