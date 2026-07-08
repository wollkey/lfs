<?php

declare(strict_types=1);

namespace App\Letterboxd\Scraper;

use App\Letterboxd\Exception\AuthenticationException;
use App\Letterboxd\Exception\HttpException;
use App\Letterboxd\Exception\NotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use Symfony\Component\DomCrawler\Crawler;

final class GuzzleHttpClient implements HttpClient
{
    private const string BASE_URI = 'https://letterboxd.com/';
    private const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';
    private const string CSRF_COOKIE = 'com.xk72.webparts.csrf';

    private readonly Client $client;
    private readonly CookieJar $cookies;
    private bool $authenticated = false;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly float $delay = 2.0,
        ?callable $handler = null,
    ) {
        $this->cookies = new CookieJar(true);

        $config = [
            'base_uri' => self::BASE_URI,
            'cookies' => $this->cookies,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
            ],
        ];

        if ($handler !== null) {
            $config['handler'] = HandlerStack::create($handler);
        }

        $this->client = new Client($config);
    }

    public function get(string $path): string
    {
        $this->ensureAuthenticated();

        if ($this->delay > 0.0) {
            usleep((int) ($this->delay * 1_000_000));
        }

        try {
            return (string) $this->client->get($path)->getBody();
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new NotFoundException('Not found: '.$path, previous: $e);
            }

            throw new HttpException('Request failed: '.$path, previous: $e);
        } catch (GuzzleException $e) {
            throw new HttpException('Request failed: '.$path, previous: $e);
        }
    }

    private function ensureAuthenticated(): void
    {
        if ($this->authenticated) {
            return;
        }

        $this->authenticate();
        $this->authenticated = true;
    }

    private function authenticate(): void
    {
        $csrf = $this->fetchCsrfToken();

        try {
            $response = $this->client->post('user/login.do', [
                'form_params' => [
                    '__csrf' => $csrf,
                    'authenticationCode' => '',
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'headers' => [
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'Accept-Language' => 'en-US,en;q=0.9,ru;q=0.8',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Origin' => self::BASE_URI,
                    'Referer' => self::BASE_URI,
                ],
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Login request failed', previous: $e);
        }

        $result = json_decode((string) $response->getBody(), true);
        if (!is_array($result) || ($result['result'] ?? null) !== 'success') {
            throw new AuthenticationException($this->loginError($result));
        }
    }

    private function fetchCsrfToken(): string
    {
        try {
            $html = (string) $this->client->get('')->getBody();
        } catch (GuzzleException $e) {
            throw new AuthenticationException('Could not reach Letterboxd for a CSRF token', previous: $e);
        }

        $value = $this->cookies->getCookieByName(self::CSRF_COOKIE)?->getValue();
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $input = new Crawler($html)->filter('input[name="__csrf"]');
        $value = $input->count() > 0 ? $input->attr('value') : null;
        if (!is_string($value) || $value === '') {
            throw new AuthenticationException('CSRF token not found');
        }

        return $value;
    }

    private function loginError(mixed $result): string
    {
        if (is_array($result) && is_array($result['messages'] ?? null)) {
            $messages = array_filter($result['messages'], 'is_string');
            if ($messages !== []) {
                return 'Login failed: '.implode('; ', $messages);
            }
        }

        return "Login failed for user {$this->username}";
    }
}
