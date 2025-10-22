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
            Log::error('Failed to decode smaregi token response or access_token is missing.', ['response_body' => $body, 'json_error' => json_last_error_msg()]);
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

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Smaregi API JSON decode error', ['endpoint' => $endpoint, 'response_body' => $body, 'json_error' => json_last_error_msg()]);
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

    /** 💰 取引データ取得（スマレジ仕様準拠） */
    public function fetchTransactions($userId, $days = 30, $limit = 100)
    {
        // 📆 スマレジAPIの仕様に合わせ、日時フォーマットをISO 8601形式に変更
        $to   = now()->toIso8601String();
        $from = now()->subDays($days)->toIso8601String();

        $endpoint = '/pos/transactions';
        $params   = [
            'transaction_date_time-from' => $from,
            'transaction_date_time-to'   => $to,
            'limit'                      => $limit,
        ];

        try {
            $response = $this->get($userId, $endpoint, $params);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error('Smaregi Transactions API Error', [
                'status' => $e->getResponse()->getStatusCode(),
                'body'   => (string)$e->getResponse()->getBody(),
            ]);
            throw $e;
        }

        // ✅ レスポンスデータ整形
        // 取引APIのレスポンスは配列が直接返ってくることを期待する。
        // もしAPIがエラーオブジェクト（例: {"code": "...", "message": "..."}）を返した場合、
        // PHPでは連想配列として解釈されるため、is_array($response) は true になる。
        // そのため、配列であり、かつエラーを示すキー（例: 'code', 'error'）が含まれていないことを確認する。
        if (!is_array($response) || (isset($response['code']) && isset($response['message'])) || (isset($response['error']))) {
            // APIがエラーオブジェクトを返した場合、または期待される配列形式でない場合
            Log::error('Smaregi Transactions API invalid response format or API error.', ['response' => $response]);
            // エラーメッセージにAPIからのレスポンスを含めることで、原因特定に役立てる
            throw new \Exception('Smaregi API returned an unexpected response format or an error: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $transactions = $response;
        Log::info('Smaregi Transactions Fetched', ['count' => count($transactions)]);

        // 💾 DB保存処理
        foreach ($transactions as $tx) {
            // 必須のIDがないデータはスキップ
            if (!isset($tx['transactionHeadId'])) {
                continue;
            }
            DB::table('smaregi_transactions')->updateOrInsert(
                ['transaction_head_id' => $tx['transactionHeadId']],
                [
                    'customer_name'    => $tx['customerName'] ?? null,
                    'staff_name'       => $tx['staffName'] ?? null,
                    'total_amount'     => $tx['totalAmount'] ?? null,
                    'payment_type'     => $tx['paymentType'] ?? null,
                    'transaction_date' => isset($tx['transactionDateTime']) ? Carbon::parse($tx['transactionDateTime']) : null,
                    'raw_data'         => json_encode($tx, JSON_UNESCAPED_UNICODE),
                    'updated_at'       => now(),
                ]
            );
        }

        return count($transactions);
    }


}
