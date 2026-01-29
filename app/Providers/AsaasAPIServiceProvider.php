<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AsaasAPIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    private static function getBasePath(): string
    {
        return config('asaas.base_url');
    }

    private static function getHeaders(): array
    {
        return [
            'access_token' => config('asaas.api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    private static function getClient(): Client
    {
        return new Client([
            'base_uri' => self::getBasePath(),
            'headers' => self::getHeaders(),
        ]);
    }

    public static function createCustomer(array $data): array
    {
        try {
            $client = self::getClient();
            $response = $client->post('customers', [
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::channel('payments')->error('Asaas Create Customer Error: ' . $e->getMessage(), [
                'data' => $data,
                'response' => $e instanceof \GuzzleHttp\Exception\RequestException && $e->getResponse()
                    ? (string) $e->getResponse()->getBody()
                    : null
            ]);
            throw $e;
        }
    }

    public static function createPayment(array $data): array
    {
        try {
            $client = self::getClient();
            $response = $client->post('payments', [
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::channel('payments')->error('Asaas Create Payment Error: ' . $e->getMessage(), [
                'data' => $data,
                'response' => $e instanceof \GuzzleHttp\Exception\RequestException && $e->getResponse()
                    ? (string) $e->getResponse()->getBody()
                    : null
            ]);
            throw $e;
        }
    }

    public static function getPayment(string $id): array
    {
        try {
            $client = self::getClient();
            $response = $client->get('payments/' . $id);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::channel('payments')->error('Asaas Get Payment Error: ' . $e->getMessage(), [
                'id' => $id,
            ]);
            throw $e;
        }
    }

    public static function getPixIdentification(string $id): array
    {
        try {
            $client = self::getClient();
            $response = $client->get("payments/{$id}/pixQrCode");

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::channel('payments')->error('Asaas Get PIX Info Error: ' . $e->getMessage(), [
                'id' => $id,
            ]);
            throw $e;
        }
    }
}
