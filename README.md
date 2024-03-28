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
// Path and access to a specified resource that depends on what will be used.
// Access to a whole disk.
$pathPrefix = 'disk:/';
// If you create you first APP, use path for that APP.
$pathPrefix = 'disk:/Applications/APP' 
// Client init with an access token.
$client = new Client($accessToken, $pathPrefix);
```

### Go to https://oauth.yandex.ru/client/new and create your first App and add necessary permissions. After getting client_id and client_secret you can use it in a client initialization constructor.

```php
// Auth credentials.
$credentials = [
    'client_id' => 'xxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxxxxxxxxxxxxx',
];
// Client init with credentials.
$client = new Client($credentials, $pathPrefix);
// Path and access to a specified resource that depends on what will be used.
// Access to a whole disk.
$pathPrefix = 'disk:/';
// If you create you first APP, use path for that APP.
$pathPrefix = 'disk:/Applications/APP'
// Create and proceed to auth url to get a code.
$authUrl = $client->getAuthUrl([
    'redirect_uri' => 'https://your-app'
    // ...other options
]);
// Code that is taken from a url.
$code = 'xxxxxx';
// Make request to get access and refresh tokens. 
$data = $client->authCodeAndGetToken($code);
// If it is successful you'll get both tokens.
$accessToken = $data['access_token'];
// Save a refresh token somewhere securely to use it in farther requests.
$refreshToken = $data['refresh_token'];
// Refresh token should be set to make sure access token expiration.
$client->setRefreshToken($refreshToken); 
```

### After you can check some methods.

### Mostly methods of API are similar with an original API. So you can read an offical documentation and apply arguments  



