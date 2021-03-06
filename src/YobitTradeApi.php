<?php

namespace OlegStyle\YobitApi;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use OlegStyle\YobitApi\Exceptions\ApiBadResponseException;
use OlegStyle\YobitApi\Exceptions\ApiBlockedException;
use OlegStyle\YobitApi\Exceptions\ApiDDosException;
use OlegStyle\YobitApi\Exceptions\ApiDisabledException;
use OlegStyle\YobitApi\Exceptions\InvalidNonceException;
use OlegStyle\YobitApi\Exceptions\YobitApiException;
use OlegStyle\YobitApi\Models\CurrencyPair;
use Psr\Http\Message\ResponseInterface;

/**
 * Class YobitTradeApi
 * @package OlegStyle\YobitApi
 *
 * @author Oleh Borysenko <olegstyle1@gmail.com>
 */
class YobitTradeApi
{
    const BASE_URI = 'https://yobit.net/tapi/';
    const MAX_INVALID_NONCES = 10;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $publicApiKey;

    /**
     * @var string
     */
    protected $privateApiKey;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var FileCookieJar
     */
    protected $cookies;

    protected $invalidNoncesCount = 0;

    public function __construct(string $publicKey, string $privateKey)
    {
        $this->publicApiKey = $publicKey;
        $this->privateApiKey = $privateKey;
        $this->userAgent = 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0';
        // check valid cookies
        $this->updateCookies();

        $this->client = new Client([
            'base_uri' => $this->getBaseUri(),
            'cookies' => $this->cookies,
            'timeout' => 30.0,
            'headers' => [
                'User-Agent' => $this->userAgent,
            ]
        ]);
    }

    public function getBaseUri(): string
    {
        return static::BASE_URI;
    }

    protected function getCookieFileName(): string
    {
        return 'yobit_cookie_' . hash('sha512', $this->publicApiKey . $this->privateApiKey) . '.txt';
    }

    protected function getCookieFilePath(): string
    {
        return __DIR__ . '/' . $this->getCookieFileName();
    }

    protected function updateCookies()
    {
        $path = $this->getCookieFilePath();
        try {
            $this->cookies = new FileCookieJar($path, true);
        } catch (\Exception $ex) {
            unlink($this->getCookieFilePath());
            $this->cookies = new FileCookieJar($path, true);
        }
    }

    /**
     * @throws YobitApiException
     */
    protected function cloudFlareChallenge(array $post): ?array
    {
        if (!function_exists('shell_exec')) {
            throw new ApiDDosException();
        }

        $result = shell_exec(
            'phantomjs '.
            __DIR__ . '/cloudflare-challenge.js ' .
            ((string) $this->client->getConfig('base_uri')) . // url
            ' "' . $this->arrayToQueryString($post) . '"' .  // post params
            ' "' . $this->arrayToQueryString([  // header param
                "Sign" => $this->generateSign($post),
                "Key" => $this->publicApiKey
            ]) . '"'
        );
        if ($result === null) {
            throw new ApiDDosException();
        }

        $result = json_decode($result, true);
        foreach ($result as &$el) {
            $newArray = [];
            foreach ($el as $key => $value) {
                $newArray[ucfirst($key)] = $value;
            }
            $el = $newArray;
        }
        $result = json_encode($result);
        file_put_contents($this->getCookieFilePath(), $result);

        $this->updateCookies();

        return $this->getResponse('', $post, true);
    }

    public function getNonceFileName()
    {
        return 'yobit_nonce_' . hash('sha512', $this->publicApiKey . $this->privateApiKey) . '.txt';
    }

    public function getNonceFilePath()
    {
        return __DIR__ . '/' . $this->getNonceFileName();
    }

    protected function getNextNonce(): string
    {
        $noncePath = $this->getNonceFilePath();
        if (file_exists($noncePath)) {
            $nonce = (int) file_get_contents($noncePath);
        } else {
            $nonce = 0;
        }
        $nonce += 1;
        file_put_contents($noncePath, $nonce);

        return $nonce;
    }

    public function arrayToQueryString(array $array): string
    {
        return http_build_query(array_filter($array), '', '&');
    }

    protected function generateSign(array $post): string
    {
        $sign = $this->arrayToQueryString($post);
        $sign = hash_hmac('sha512', $sign, $this->privateApiKey);

        return $sign;
    }

    /**
     * @throws YobitApiException
     */
    public function getResponse(string $method, array $post = [], ?bool $retry = false): array
    {
        if (!$retry) {
            $post['method'] = $method;
            $post['nonce'] = $this->getNextNonce();
        }

        try {
            $response = $this->client->post('', [
                'base_uri' => $this->getBaseUri(),
                'form_params' => $post,
                'cookies' => $this->cookies,
                'headers' => [
                    "Sign" => $this->generateSign($post),
                    "Key" => $this->publicApiKey,
                ],
            ]);
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
        } catch (RequestException $ex) {
            $response = $ex->getResponse();
        }

        try {
            $result = $this->handleResponse($response);
            $this->invalidNoncesCount = 0;
            return $result;
        } catch (ApiDDosException $ex) {
            if ($retry) {
                throw $ex;
            }

            return $this->cloudFlareChallenge($post);
        } catch (ApiBlockedException $ex) {
            if ($retry) {
                throw $ex;
            }
            unlink($this->getCookieFilePath());
            $this->updateCookies();

            return $this->getResponse($post['method'], $post, true);
        } catch (InvalidNonceException $ex) {
            if (static::MAX_INVALID_NONCES > $this->invalidNoncesCount) {
                $this->invalidNoncesCount += 1;

                return $this->getResponse($post['method'], $post);
            } else {
                $this->invalidNoncesCount = 0;

                throw $ex;
            }
        }
    }

    /**
     * @throws ApiDisabledException|ApiDDosException|ApiBadResponseException
     * @throws InvalidNonceException|ApiBlockedException
     */
    public function handleResponse(?ResponseInterface $response): ?array
    {
        if ($response === null) {
            throw new ApiDisabledException();
        }

        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() === 503) { // cloudflare ddos protection
            throw new ApiDDosException($responseBody);
        }

        if (preg_match('/ddos/i', $responseBody) || preg_match('/enable cookies/i', $responseBody)) {
            throw new ApiDDosException($responseBody);
        }

        if (preg_match('/cloudflare/i', $responseBody) && preg_match('/access denied/i', $responseBody)) {
            throw new ApiBlockedException($responseBody);
        }

        $result = json_decode($responseBody, true);
        if ($result === null) {
            throw new ApiBadResponseException($responseBody);
        }

        if (!isset($result['success']) || $result['success'] != 1) {
            if (isset($result['error']) && preg_match('/invalid nonce/i', $result['error'])) {
                throw new InvalidNonceException($responseBody);
            }

            throw new ApiBadResponseException($responseBody);
        }

        if (!isset($result['return'])) {
            return [];
        }

        return $result['return'];
    }

    /**
     * @throws YobitApiException
     */
    public function getInfo(): array
    {
        return $this->getResponse('getInfo');
    }

    /**
     * @throws YobitApiException
     */
    public function getActiveOrders(CurrencyPair $pair): array
    {
        return $this->getResponse('ActiveOrders', [
            'pair' => (string) $pair
        ]);
    }

    /**
     * @throws YobitApiException
     */
    public function trade(CurrencyPair $pair, string $type, float $rate, float $amount): array
    {
        return $this->getResponse('Trade', [
            'pair' => (string) $pair,
            'type' => $type,
            'rate' => $rate,
            'amount' => $amount,
        ]);
    }

    /**
     * @throws YobitApiException
     */
    public function cancelOrder(int $orderId): array
    {
        return $this->getResponse('CancelOrder', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * @throws YobitApiException
     */
    public function getOrderInfo(int $orderId): array
    {
        return $this->getResponse('OrderInfo', [
            'order_id' => $orderId,
        ]);
    }
}
