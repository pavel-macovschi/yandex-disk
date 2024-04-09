<?php

namespace ImpressiveWeb\YandexDisk;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use ImpressiveWeb\YandexDisk\Exception\BadRequestException;

/**
 * @see https://yandex.com/dev/disk/poligon/
 */
class Client
{
    private const API_ENDPOINT = 'https://cloud-api.yandex.net/v1/disk/';

    private const API_AUTH_URL = 'https://oauth.yandex.ru/';

    private const CODE_STATUSES = [
        400, // Wrong data.
        401, // Not authorized.
        403, // API is not available.
        404, // Resource not found.
        405, // Method not allowed.
        406, // Resource cannot be presented in a requested format.
        409, // Path "{path}" does not exist.
        412, // Precondition failed.
        413, // File upload is not available. File is too large.
        423, // Under construction. Now you can only view and download files.
        429, // Too much requests.
        503, // Service unavailable.
        507, // Disk space is not enough.
    ];

    private GuzzleClient $client;

    private string $clientId;

    private string $clientSecret;

    private ?string $accessToken = null;

    private string $refreshToken;

    private ?int $accessTokenAddedAt = null;

    private ?int $accessTokenExpiresIn = null;

    /**
     * @param string|array|null $authCredentials
     * @param string $pathPrefix
     * @param int $itemsLimit
     * @throws Exception
     */
    public function __construct(
        string|array $authCredentials = null,
        private string $pathPrefix = 'disk:/',
        private readonly int $itemsLimit = 20
    ) {
        if (is_array($authCredentials)) {
            if (empty($authCredentials['client_id']) || empty($authCredentials['client_secret'])) {
                throw new Exception('You need to set client_id and client_secret');
            }

            $this->clientId = $authCredentials['client_id'];
            $this->clientSecret = $authCredentials['client_secret'];
        }

        if (is_string($authCredentials)) {
            $this->accessToken = $authCredentials;
        }

        $this->client = new GuzzleClient(['base_uri' => self::API_ENDPOINT]);
    }

    public function setPathPrefix(string $pathPrefix): void
    {
        $this->pathPrefix = $pathPrefix;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    protected function setAccessTokenAddedAt(int $accessTokenAddedAt): void
    {
        $this->accessTokenAddedAt = $accessTokenAddedAt;
    }

    protected function setAccessTokenExpiresIn(int $accessTokenExpiresIn): void
    {
        $this->accessTokenExpiresIn = $accessTokenExpiresIn;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $token): self
    {
        $this->accessToken = $token;

        return $this;
    }

    /**
     * Fetch a disk meta information.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/capacity
     *
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function discInfo(array $fields = []): array
    {
        $params = ['fields' => implode(',', $fields)];

        return $this->makeRequest('GET', params: $params);
    }

    /**
     * Add meta information for a specified resource.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/meta-add
     *
     * @param string $path
     * @param array $metaProperties
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function addMetadata(string $path, array $metaProperties, array $fields = []): array
    {
        $params = [
            'query' => [
                'path'   => $this->normalizePath($path),
                'fields' => implode(',', $fields),
            ],
            'body'  => json_encode(['custom_properties' => $metaProperties])
        ];

        return $this->makeRequest('PATCH', 'resources', $params);
    }

    /**
     * Returns the metadata for a file or a folder.
     * If resources have some other sub-resources they will be described as well.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/meta
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param int $offset
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @param string $sort Sort field: name | path | created | modified | size
     * @param bool $deep
     * @return array
     * @throws GuzzleException
     */
    public function listContent(
        string $path = '/',
        array $fields = [],
        int $limit = 20,
        int $offset = 0,
        bool $previewCrop = false,
        string $previewSize = 'S',
        string $sort = 'name',
        bool $deep = false
    ): array {
        $params = [
            'path'         => $this->normalizePath($path),
            'fields'       => implode(',', $fields),
            'limit'        => $this->getLimit($limit),
            'offset'       => $offset,
            'preview_crop' => $previewCrop,
            'preview_size' => $previewSize,
            'sort'         => $sort
        ];

        if ($deep) {
            $items = $this->makeRequest('GET', 'resources', $params);

            foreach ($items['_embedded']['items'] as $item) {
                if ('dir' === $item['type']) {
                    $path = self::trimPath(
                        str_replace($this->pathPrefix, '', $item['path'])
                    );

                    // Recursive reading.
                    $deepItems = $this->listContent($path, ...array_slice(func_get_args(), 1));

                    foreach ($deepItems['_embedded']['items'] as $deepItem) {
                        $items['_embedded']['items'][] = $deepItem;
                    }
                }
            }

            return $items;
        }

        return $this->makeRequest('GET', 'resources', $params);
    }

    /**
     * Returns a list of files.
     *
     * @see https://yandex.ru/dev/disk/api/reference/all-files.html
     *
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param array $mediaTypes 'audio','backup','book','compressed','data','development','diskimage','document','encoded','executable','flash','font','image','settings','spreadsheet','text','unknown','video','web'
     * @param int $offset
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @param string $sortField Example sorting by size field: (sortField: 'size' - ASC | sortField: '-size' - DESC)
     * @return array
     * @throws GuzzleException
     */
    public function listFiles(
        array $fields = [],
        int $limit = 20,
        array $mediaTypes = [],
        int $offset = 0,
        bool $previewCrop = false,
        string $previewSize = 'S',
        string $sortField = 'name',
    ): array {
        $params = [
            'fields'       => implode(',', $fields),
            'limit'        => $this->getLimit($limit),
            'media_type'   => implode(',', $mediaTypes),
            'preview_size' => $previewSize,
            'preview_crop' => $previewCrop,
            'sort'         => $sortField,
            'offset'       => $offset,
        ];

        return $this->makeRequest('GET', 'resources/files', $params);
    }

    /**
     * Returns a list of recently uploaded files.
     *
     * @see https://yandex.ru/dev/disk/api/reference/recent-upload.html
     *
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param array $mediaTypes 'audio','backup','book','compressed','data','development','diskimage','document','encoded','executable','flash','font','image','settings','spreadsheet','text','unknown','video','web'
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @return array
     * @throws GuzzleException
     */
    public function listFilesRecentlyAdded(
        array $fields = [],
        int $limit = 20,
        array $mediaTypes = [],
        bool $previewCrop = false,
        string $previewSize = 'S'
    ): array {
        $params = [
            'media_type'   => implode(',', $mediaTypes),
            'fields'       => implode(',', $fields),
            'preview_size' => $previewSize,
            'preview_crop' => $previewCrop,
            'limit'        => $this->getLimit($limit),
        ];

        return $this->makeRequest('GET', 'resources/last-uploaded', $params);
    }

    /**
     * Add a new folder.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/create-folder
     *
     * @param string $path
     * @return array
     * @throws GuzzleException
     */
    public function addDirectory(string $path): array
    {
        $params = [
            'path' => $this->normalizePath($path),
        ];

        return $this->makeRequest('PUT', 'resources', $params);
    }

    /**
     * Delete a folder or a file at a given path.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/delete
     *
     * @param string $path
     * @param string $md5
     * @param array $fields Attributes list to be returned.
     * @param bool $permanently
     * @param bool $async
     * @return array
     * @throws GuzzleException
     */
    public function remove(
        string $path,
        string $md5 = '',
        array $fields = [],
        bool $permanently = false,
        bool $async = false
    ): array {
        $params = [
            'path'        => $this->normalizePath($path),
            'md5'         => $md5,
            'fields'      => implode(',', $fields),
            'permanently' => $permanently,
            'async'       => $async,
        ];

        return $this->makeRequest('DELETE', 'resources', $params);
    }

    /**
     * Move a file or a folder to a different location.
     *
     * @see https://yandex.ru/dev/disk/api/reference/move.html
     *
     * @param string $from
     * @param string $to
     * @param bool $overwrite
     * @param bool $async
     * @return array
     * @throws GuzzleException
     */
    public function move(string $from, string $to, bool $overwrite = false, bool $async = false): array
    {
        $params = [
            'from'        => $this->normalizePath($from),
            'path'        => $this->normalizePath($to),
            'overwrite'   => $overwrite,
            'force_async' => $async,
        ];

        return $this->makeRequest('POST', 'resources/move', $params);
    }

    /**
     * Copy a file or a folder.
     *
     * @see https://yandex.ru/dev/disk/api/reference/copy.html
     *
     * @param string $from
     * @param string $to
     * @param bool $overwrite
     * @param bool $async
     * @return array
     * @throws GuzzleException
     */
    public function copy(string $from, string $to, bool $overwrite = false, bool $async = false): array
    {
        $params = [
            'from'        => $this->normalizePath($from),
            'path'        => $this->normalizePath($to),
            'overwrite'   => $overwrite,
            'force_async' => $async,
        ];

        return $this->makeRequest('POST', 'resources/copy', $params);
    }

    /**
     * Get a download url.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/content
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function getDownloadUrl(string $path, array $fields = []): array
    {
        $params = [
            'path'   => $this->normalizePath($path),
            'fields' => implode(',', $fields),
        ];

        return $this->makeRequest('GET', 'resources/download', $params);
    }

    /**
     * Get an upload url.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/upload#url-request
     *
     * @param string $path Path on a disk where a file will be uploaded.
     * @param bool $overwrite
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function getUploadUrl(string $path, bool $overwrite = false, array $fields = []): array
    {
        $params = [
            'path'      => $this->normalizePath($path),
            'overwrite' => $overwrite,
            'fields'    => implode(',', $fields),
        ];

        return $this->makeRequest('GET', 'resources/upload', $params);
    }

    /**
     * @param string $path
     * @param string $mode
     * @return mixed
     */
    public function getStream(string $path, string $mode = 'r'): mixed
    {
        return Utils::tryFopen($path, $mode);
    }

    /**
     * Upload a file or a folder at a given path.
     *
     * @see https://yandex.ru/dev/disk/api/reference/upload.html#response-upload
     *
     * @param string $toPath Path on a disk where file will be uploaded.
     * @param $contents
     * @param bool $overwrite
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function upload(string $toPath, $contents, bool $overwrite = false): ResponseInterface
    {
        $data = $this->getUploadUrl($toPath, $overwrite, ['href']);
        $url = $data['href'];

        $params = array_merge(
            [
                'query' => [
                    'path' => self::trimPath($toPath),
                    'url' => $url,
                ],
            ],
            [
                'body' => $contents,
            ]
        );

        // There is no need to pass access token on upload process.
        $result = $this->client->put($url, $params);

        if (is_resource($contents)) {
            fclose($contents);
            unset($contents);
        }

        return $result;
    }

    /**
     * Get a list of published resources.
     *
     * @see https://yandex.ru/dev/disk/api/reference/recent-public.html
     *
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param int $offset
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @param string $type
     * @return array
     * @throws GuzzleException
     */
    public function listPublishedResources(
        array $fields = [],
        int $limit = 20,
        int $offset = 0,
        bool $previewCrop = false,
        string $previewSize = 'S',
        string $type = ''
    ): array {
        $params = [
            'fields'       => implode(',', $fields),
            'limit'        => $this->getLimit($limit),
            'offset'       => $offset,
            'preview_crop' => $previewCrop,
            'preview_size' => $previewSize,
            'type'         => $type,
        ];

        return $this->makeRequest('GET', 'resources/public', $params);
    }

    /**
     * Publish a specified resource.
     *
     * @see https://yandex.ru/dev/disk/api/reference/publish.html#publish
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function resourcePublish(string $path, array $fields = []): array
    {
        $params = [
            'path'   => $this->normalizePath($path),
            'fields' => implode(',', $fields),
        ];

        return $this->makeRequest('PUT', 'resources/publish', $params);
    }

    /**
     * Unpublish a specified resource.
     *
     * @see https://yandex.ru/dev/disk/api/reference/publish.html#unpublish-q
     *
     * @throws GuzzleException
     */
    public function resourceUnpublish(string $path, array $fields = []): array
    {
        $params = [
            'path'   => $this->normalizePath($path),
            'fields' => implode(',', $fields),
        ];

        return $this->makeRequest('PUT', 'resources/unpublish', $params);
    }

    /**
     * Get a file or a catalogue metadata.
     *
     * @param string $publicKeyOrUrl
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param int $offset
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @param string $sort
     * @return array
     * @throws GuzzleException
     */
    public function resourceMetadata(
        string $publicKeyOrUrl,
        string $path = '',
        array $fields = [],
        int $limit = 20,
        int $offset = 0,
        bool $previewCrop = false,
        string $previewSize = 'S',
        string $sort = ''
    ): array {
        $params = [
            'public_key'   => $publicKeyOrUrl,
            'path'         => $path,
            'fields'       => implode(',', $fields),
            'limit'        => $this->getLimit($limit),
            'offset'       => $offset,
            'preview_crop' => $previewCrop,
            'preview_size' => $previewSize,
            'type'         => $sort,
        ];

        return $this->makeRequest('GET', 'public/resources', $params);
    }

    /**
     * Get a published resource download url.
     *
     * @param string $publicKeyOrUrl
     * @param array $fields Attributes list to be returned.
     * @param string $path
     * @return array
     * @throws GuzzleException
     */
    public function resourceDownloadUrl(
        string $publicKeyOrUrl,
        array $fields = [],
        string $path = '',
    ): array {
        $params = [
            'public_key' => $publicKeyOrUrl,
            'fields'     => implode(',', $fields),
            'path'       => $path,
        ];

        return $this->makeRequest('GET', 'public/resources/download', $params);
    }

    /**
     * Save a resource into specified folder.
     *
     * @param string $publicKeyOrUrl
     * @param string $fromPath
     * @param string $name
     * @param string $savePath
     * @param array $fields Attributes list to be returned.
     * @param bool $forceAsync
     * @return array
     * @throws GuzzleException
     */
    public function resourceSaveToDisk(
        string $publicKeyOrUrl,
        string $fromPath = '',
        string $savePath = '',
        string $name = '',
        array $fields = [],
        bool $forceAsync = false
    ): array {
        $params = [
            'public_key'  => $publicKeyOrUrl,
            'path'        => $fromPath,
            'save_path'   => $this->normalizePath($savePath),
            'name'        => $name,
            'fields'      => implode(',', $fields),
            'force_async' => $forceAsync,
        ];

        return $this->makeRequest('POST', 'public/resources/save-to-disk', $params);
    }

    /**
     * Listing a trash content.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/meta
     *
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @param int $limit
     * @param int $offset
     * @param bool $previewCrop
     * @param string $previewSize S | M | L | XL | XXL | XXXL | 120 | 120x120 | 120x160
     * @param string $sort created | deleted
     * @return array
     * @throws GuzzleException
     */
    public function trashListContent(
        string $path = '/',
        array $fields = [],
        int $limit = 20,
        int $offset = 0,
        bool $previewCrop = false,
        string $previewSize = 'S',
        string $sort = 'created'
    ): array {
        $params = [
            'path'         => $this->normalizePath($path),
            'fields'       => implode(',', $fields),
            'preview_size' => $previewSize,
            'preview_crop' => $previewCrop,
            'limit'        => $this->getLimit($limit),
            'offset'       => $offset,
            'sort'         => $sort,
        ];

        return $this->makeRequest('GET', 'trash/resources', $params);
    }

    /**
     * Restore a specified resource from a trash.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/trash-restore
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @param bool $forceAsync
     * @param string $name A new name of recovered resource
     * @param bool $overwrite
     * @return array
     * @throws GuzzleException
     */
    public function trashContentRestore(
        string $path,
        array $fields = [],
        bool $forceAsync = false,
        string $name = '',
        bool $overwrite = false
    ): array {
        $params = [
            'path'       => self::trimPath($path),
            'fields'     => implode(',', $fields),
            'forceAsync' => $forceAsync,
            'name'       => $name,
            'overwrite'  => $overwrite,
        ];

        return $this->makeRequest('PUT', 'trash/resources/restore', $params);
    }

    /**
     * Remove a resource from a trash.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/trash-delete
     *
     * @param string $path
     * @param array $fields Attributes list to be returned.
     * @param bool $forceAsync
     * @return array
     * @throws GuzzleException
     */
    public function trashContentDelete(string $path, array $fields = [], bool $forceAsync = false): array
    {
        $params = [
            'path'       => self::trimPath($path),
            'fields'     => implode(',', $fields),
            'forceAsync' => $forceAsync,
        ];

        return $this->makeRequest('DELETE', 'trash/resources', $params);
    }

    /**
     * Clear the whole trash.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/trash-delete
     *
     * @param array $fields Attributes list to be returned.
     * @param bool $forceAsync
     * @return array
     * @throws GuzzleException
     */
    public function trashClear(array $fields = [], bool $forceAsync = false): array
    {
        $params = [
            'path'        => '/',
            'fields'      => implode(',', $fields),
            'force_async' => $forceAsync,
        ];

        return $this->makeRequest('DELETE', 'trash/resources', $params);
    }

    /**
     * Get an operation status.
     *
     * @see https://yandex.ru/dev/disk-api/doc/ru/reference/operations
     *
     * @param int $id
     * @param array $fields Attributes list to be returned.
     * @return array
     * @throws GuzzleException
     */
    public function statusOperation(int $id, array $fields = []): array
    {
        $params = [
            'operation_id' => $id,
            'fields'       => implode(',', $fields),
        ];

        return $this->makeRequest('GET', 'disk/operations', $params);
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        if ('/' === $path) {
            return $path;
        }

        $path = trim($path, '/ ');

        if ($this->pathPrefix) {
            $path = trim($this->pathPrefix, '/') . '/' . $path;
        }

        return $path;
    }

    /**
     * Trim path helper.
     *
     * @param string $path
     * @return string
     */
    private static function trimPath(string $path): string
    {
        return trim($path, '/\\');
    }

    /**
     * @param string $method
     * @param string $subdomain
     * @param array $params
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    private function makeRequest(string $method, string $subdomain = '', array $params = []): mixed
    {
        // Check if an access token is expired.
        if (isset($this->refreshToken) && $this->isExpired()) {
            $this->refreshAccessToken();
        }

        try {
            if (isset($params['headers'])) {
                $options = ['headers' => array_merge($this->getHeaders(), $params['headers'])];
                // Remove headers for query.
                unset($params['headers']);
            } else {
                $options = ['headers' => $this->getHeaders()];
            }

            if (isset($params['query'])) {
                $options = array_merge($options, $params);
            } else {
                $options['query'] = $params;
            }

            if (!empty($subdomain)) {
                $subdomain .= '/';
            }

            $response = $this->client->request($method, $subdomain, $options);
        } catch (ClientException $e) {
            throw $this->handleException($e);
        }

        return $this->decodeContents($response) ?? [];
    }

    /**
     * @param ClientException $exception
     * @return Exception
     */
    private function handleException(ClientException $exception): Exception
    {
        $response = $exception->getResponse();
        $statusCode = $response->getStatusCode();
        $body = $this->decodeContents($response);
        $message = $body['description'];

        if (in_array($statusCode, self::CODE_STATUSES)) {
            return new BadRequestException($message);
        }

        return $exception;
    }

    /**
     * @see https://yandex.ru/dev/id/doc/ru/codes/code-url
     *
     * @param array $options extra query parameters
     */
    public function getAuthUrl(array $options = []): string
    {
        $params = [
            'response_type' => 'code',
            'client_id'     => $this->clientId,
        ];

        $params = http_build_query(
            array_merge($params, $options)
        );

        return self::API_AUTH_URL . 'authorize?' . $params;
    }

    /**
     * @see https://yandex.ru/dev/id/doc/ru/codes/code-url#token
     *
     * Returns access and refresh tokens.
     *
     * @param string $code
     * @param string $deviceId
     * @param string $deviceName
     * @param string $codeVerifier
     * @return mixed
     * @throws GuzzleException
     */
    public function authCodeAndGetToken(
        string $code,
        string $deviceId = '',
        string $deviceName = '',
        string $codeVerifier = ''
    ): mixed {
        $params = [
            'auth'        => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'device_id'     => $deviceId,
                'device_name'   => $deviceName,
                'code_verifier' => $codeVerifier,
            ]
        ];

        try {
            $response = $this->client->post(self::API_AUTH_URL . 'token', $params);
            $data = $this->decodeContents($response);
        } catch (ClientException $e) {
            echo $e->getMessage();
        }

        return $data;
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => "OAuth $this->accessToken"
        ];
    }

    /**
     * Determine maximum limit of items if it's set in a client.
     *
     * @param $limit
     * @return int
     */
    private function getLimit($limit): int
    {
        return max($limit, $this->itemsLimit);
    }

    /**
     * @param ResponseInterface $response
     * @return mixed
     */
    private function decodeContents(ResponseInterface $response): mixed
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @see https://yandex.ru/dev/id/doc/ru/tokens/refresh-client
     *
     * @throws GuzzleException
     */
    private function refreshAccessToken(): void
    {
        $params = [
            'auth'        => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->refreshToken,
            ]
        ];

        try {
            $response = $this->client->post(self::API_AUTH_URL . 'token', $params);
            $data = $this->decodeContents($response);
            $this->setAccessToken($data['access_token']);
            $this->setRefreshToken($data['refresh_token']);
            $this->setAccessTokenExpiresIn($data['expires_in']);
            $this->setAccessTokenAddedAt(time());
        } catch (ClientException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @return bool
     */
    private function isExpired(): bool
    {
        $expiresIn = $this->accessTokenAddedAt + $this->accessTokenExpiresIn;

        return time() > $expiresIn;
    }
}
