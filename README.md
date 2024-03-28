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
// Path to a whole disk.
$pathPrefix = 'disk:/';
// Client init with an access token.
$client = new Client($accessToken, $pathPrefix);
```

### Go to https://oauth.yandex.ru/client/new and create your first App and add necessary permissions. After getting client_id and client_secret you can use it in a client initialization.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];
// If you create you first APP, use that path.
$pathPrefix = 'disk:/Applications/APP'
// Client init with credentials.
$client = new Client($credentials, $pathPrefix);
// Create and proceed an auth url to get a code.
$authUrl = $client->getAuthUrl([
    'redirect_uri' => 'https://your-app'
    // ...other options
]);
// After redirecting to your redirect_uri use code from a query.
// https://your-app?code=xxxxxx

// Code that is taken from a query.
$code = 'xxxxxx';
// Make a request to get an access and a refresh token. 
$data = $client->authCodeAndGetToken($code);
// If it is successful you'll get both tokens.
$accessToken = $data['access_token'];
// Save a refresh token somewhere securely to use it in farther requests.
$refreshToken = $data['refresh_token'];
// Refresh token should be set to make sure access token expiration.
$client->setRefreshToken($refreshToken); 
```

### Methods of API are relatively matching to methods of an original API. So you can read an offical documentation how to use it.



