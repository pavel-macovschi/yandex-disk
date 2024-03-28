# Simple implementation of Yandex Disk API


## Installation

You can install the package via composer:

``` bash
composer require impressiveweb/yandex-disk
```

## Usage

### Go to https://yandex.ru/dev/disk/poligon and click on a button to get OAuth token. 

```php
use ImpressiveWeb\YandexDisk\Client;
// Access token.
$accessToken = 'xxxxxxxxxxxxxxxxxxx';
// Path to a specified resource.
$pathPrefix = 'disk:/';

// Client init with an access token.
$client = new Client($accessToken, $pathPrefix);
```

### Go to https://oauth.yandex.ru/client/new and create your first App. After getting client_id and client_secret you can use it in a client initialization constructor.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];
// Path to a specified resource.
$pathPrefix = 'disk:/';

// Get auth url to get refresh_token that will be used later. 
$client->getAuthUrl([
    'redirect_uri' => 'https://your-app'
    // ...other options
]);

// Client init with credentials.
$client = new Client($credentials, $pathPrefix);


// List content for a root directory.
$client->listContent('/');
```

### After you can check some methods.

### Mostly methods of API are similar with an original API. So you can read an offical documentation and apply arguments  



