<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Carbon\Carbon;

class SmaregiService
{
    private $client;
    private $tokenUrl;
    private $apiBase;
    private $clientId;
    private $clientSecret;
    private $contractId;

    public function __construct()
    {
        $this->client       = new Client(['timeout' => 20]);
        $this->tokenUrl     = env('SMAREGI_TOKEN_URL');
        $this->apiBase      = env('SMAREGI_POS_API_BASE');
        $this->clientId     = env('SMAREGI_CLIENT_ID');
        $this->clientSecret = env('SMAREGI_CLIENT_SECRET');
        $this->contractId   = env('SMAREGI_CONTRACT_ID');
    }

    /** 🔑 トークン取得 */
    public function fetchToken($userId)
    {
        $response = $this->client->post($this->tokenUrl, [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type' => 'client_credentials',
                'scope'      => 'pos.staffs:read pos.transactions:read',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        DB::table('smaregi_tokens')->updateOrInsert(
            ['user_id' => $userId],
            [
                'access_token'            => $data['access_token'],
                'expires_in'              => $data['expires_in'] ?? null,
                'access_token_expires_at' => Carbon::now()->addSeconds($data['expires_in'] ?? 0),
                'scope'                   => $data['scope'] ?? null,
                'token_type'              => $data['token_type'] ?? null,
                'updated_at'              => now(),
            ]
        );

        return $data['access_token'];
    }

    /** 📡 API共通GETメソッド */
    public function get($userId, $endpoint, $params = [])
    {
        $token = DB::table('smaregi_tokens')->where('user_id', $userId)->value('access_token');

        try {
            return $this->requestWithToken('GET', $endpoint, $token, $params);
        } catch (ClientException $e) {
            // 401 → トークン再取得してリトライ
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $newToken = $this->fetchToken($userId);
                return $this->requestWithToken('GET', $endpoint, $newToken, $params);
            }
            throw $e;
        }
    }

    /** 実際のAPIリクエスト処理 */
    private function requestWithToken($method, $endpoint, $token, $params = [])
    {
        // ✅ URLに contract_id を含める
        $url = rtrim($this->apiBase, '/') . '/' . $this->contractId . $endpoint;

        $response = $this->client->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'query' => $params,
        ]);

        return json_decode($response->getBody(), true);
    }

    /** 👥 スタッフ一覧取得 */
    public function getStaffs($userId)
    {
        $endpoint = '/pos/staffs';
        return $this->get($userId, $endpoint);
    }

    /** 💰 取引一覧取得（今後拡張予定） */
    public function getTransactions($userId, $params = [])
    {
        $endpoint = '/pos/transactions';
        return $this->get($userId, $endpoint, $params);
    }
}
