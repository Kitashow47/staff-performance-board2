<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['access_token'])) {
            Log::error('Failed to decode smaregi token response or access_token is missing.', [
                'response_body' => $body,
                'json_error' => json_last_error_msg()
            ]);
            throw new \Exception('Failed to fetch Smaregi access token: Invalid response format.');
        }

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
        $url = rtrim($this->apiBase, '/') . '/' . $this->contractId . $endpoint;

        $response = $this->client->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'query' => $params,
        ]);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Smaregi API JSON decode error', [
                'endpoint' => $endpoint,
                'response_body' => $body,
                'json_error' => json_last_error_msg()
            ]);
            throw new \Exception('Smaregi API returned invalid JSON.');
        }
        return $data;
    }

    /** 👥 スタッフ一覧取得 */
    public function getStaffs($userId)
    {
        $endpoint = '/pos/staffs';
        return $this->get($userId, $endpoint);
    }

    /** 💰 取引データ取得（POS API準拠） */
    public function fetchTransactions($userId, ?string $from = null, ?string $to = null, int $page = 1, int $limit = 100)
    {
        $endpoint = '/pos/transactions';

        // ✅ ISO8601形式（スマレジ仕様）
        $params = [
            'transaction_date_time-from' => ($from ? Carbon::parse($from) : now()->subDays(30))->toIso8601String(),
            'transaction_date_time-to'   => ($to ? Carbon::parse($to) : now())->toIso8601String(),
            'limit'                      => $limit,
            'page'                       => $page,
            'sort'                       => 'updDateTime', // 降順指定は不可
        ];

        try {
            $response = $this->get($userId, $endpoint, $params);
        } catch (ClientException $e) {
            Log::error('Smaregi Transactions API Error', [
                'status' => $e->getResponse()?->getStatusCode(),
                'body'   => (string)$e->getResponse()?->getBody(),
            ]);
            throw $e;
        }

        // ✅ スマレジの複数件取得APIはトップレベル配列で返却
        if (isset($response['items'])) {
            $transactions = $response['items'];
        } elseif (is_array($response) && isset($response[0]['transactionHeadId'])) {
            $transactions = $response;
        } else {
            Log::error('Smaregi Transactions API invalid response format.', ['response' => $response]);
            throw new \Exception('Smaregi API returned an unexpected response format: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        // ✅ Laravel側で降順ソート
        $transactions = collect($transactions)
            ->sortByDesc('updDateTime')
            ->values()
            ->all();

        // 💾 DB保存処理
        foreach ($transactions as $tx) {
            if (!isset($tx['transactionHeadId'])) {
                continue;
            }
            DB::table('smaregi_transactions')->updateOrInsert(
                ['transaction_head_id' => $tx['transactionHeadId']],
                [
                    'customer_name'    => $tx['customerName'] ?? null,
                    'staff_name'       => $tx['staffName'] ?? null,
                    'total_amount'     => $tx['total'] ?? null,
                    'payment_type'     => $tx['creditDivision'] ?? null,
                    'transaction_date' => isset($tx['transactionDateTime'])
                        ? Carbon::parse($tx['transactionDateTime'])
                        : null,
                    'raw_data'         => json_encode($tx, JSON_UNESCAPED_UNICODE),
                    'updated_at'       => now(),
                ]
            );
        }

        return $transactions;
    }
}
