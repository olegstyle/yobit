<?php

namespace OlegStyle\YobitApi;

use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use OlegStyle\YobitApi\Exceptions\ApiDDosException;
use OlegStyle\YobitApi\Exceptions\ApiDisabledException;
use OlegStyle\YobitApi\Models\CurrencyPair;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Class YobitPublicApi
 * @package OlegStyle\YobitApi
 *
 * @author Oleh Borysenko <olegstyle1@gmail.com>
 */
class YobitPublicApi
{
    const BASE_URI = 'https://yobit.net/api/3/';
    
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var FileCookieJar
     */
    protected $cookies;

    public function __construct()
    {
        $this->userAgent = 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0';
        $this->cookies = new FileCookieJar($this->getCookieFilePath(), true);

        $this->client = new Client([
            'base_uri' => static::BASE_URI,
            'timeout' => 30.0,
            'cookies' => $this->cookies,
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Content-type' => 'application/json',
            ]
        ]);
    }

    protected function getCookieFilePath(): string
    {
        return __DIR__ . '/yobit_cookie.txt';
    }

    /**
     * @throws ApiDDosException|ApiDisabledException
     */
    protected function cloudFlareChallenge(string $url): ?array
    {
        if (!function_exists('shell_exec')) {
            throw new ApiDDosException();
        }

        $result = shell_exec(
            'phantomjs '.
            __DIR__ . '/cloudflare-challenge.js ' .
            ((string) $this->client->getConfig('base_uri')) . $url
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

        $this->cookies = new FileCookieJar($this->getCookieFilePath(), true);

        return $this->sendResponse($url, true);
    }

    /**
     * @throws ApiDisabledException|ApiDDosException
     */
    public function sendResponse(string $url, bool $afterCloudFlare = false): ?array
    {
        try {
            $response = $this->client->get($url, [
                'cookies' => $this->cookies,
            ]);
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
        } catch (RequestException $ex) {
            $response = $ex->getResponse();
        }

        try {
            return $this->handleResponse($response);
        } catch (ApiDDosException $ex) {
            if ($afterCloudFlare) {
                throw $ex;
            }

            return $this->cloudFlareChallenge($url);
        }
    }

    /**
     * @throws ApiDisabledException|ApiDDosException
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

        if (preg_match('/ddos/i', $responseBody)) {
            throw new ApiDDosException($responseBody);
        }

        return json_decode($responseBody, true);
    }

    /**
     * Get info about currencies
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getInfo(): ?array
    {
        return $this->sendResponse('info');
    }

    /**
     * @param CurrencyPair[] $pairs
     * @return string
     */
    protected function prepareQueryForPairs($pairs)
    {
        $query = [];
        foreach ($pairs as $pair) {
            $query[] = "{$pair->from}_{$pair->to}";
        }
        $query = implode('-', $query);

        return $query;
    }

    /**
     * @param CurrencyPair[] $pairs -> example ['ltc' => 'btc']
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getDepths($pairs)
    {
        $query = $this->prepareQueryForPairs($pairs);

        return $this->sendResponse('depth/' . $query);
    }

    /**
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getDepth(string $from, string $to)
    {
        return $this->getDepths([new CurrencyPair($from, $to)]);
    }

    /**
     * @param CurrencyPair[] $pairs -> example ['ltc' => 'btc']
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getTrades(array $pairs)
    {
        $query = $this->prepareQueryForPairs($pairs);

        return $this->sendResponse('trades/' . $query);
    }

    /**
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getTrade(string $from, string $to)
    {
        return $this->getTrades([new CurrencyPair($from, $to)]);
    }

    /**
     * @param CurrencyPair[] $pairs -> example ['ltc' => 'btc']
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getTickers(array $pairs)
    {
        $query = $this->prepareQueryForPairs($pairs);

        return $this->sendResponse('ticker/' . $query);
    }

    /**
     * @return array|null
     *
     * @throws ApiDisabledException|ApiDDosException
     */
    public function getTicker(string $from, string $to)
    {
        return $this->getTickers([new CurrencyPair($from, $to)]);
    }
}
