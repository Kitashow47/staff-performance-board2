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

    /** 🔑 アクセストークン取得 */
    public function fetchToken($userId)
    {
        $response = $this->client->post($this->tokenUrl, [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type' => 'client_credentials',
                'scope'      => 'pos.staffs:read pos.transactions:read',
            ],
        ]);

        $data = json_decode((string)$response->getBody(), true);
        if (!isset($data['access_token'])) {
            throw new \Exception('Smaregi access token fetch failed.');
        }

        DB::table('smaregi_tokens')->updateOrInsert(
            ['user_id' => $userId],
            [
                'access_token'            => $data['access_token'],
                'expires_in'              => $data['expires_in'] ?? null,
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 0),
                'updated_at'              => now(),
            ]
        );

        return $data['access_token'];
    }

    /** 📡 共通GETメソッド */
    public function get($userId, $endpoint, $params = [])
    {
        $token = DB::table('smaregi_tokens')->where('user_id', $userId)->value('access_token');

        try {
            return $this->requestWithToken('GET', $endpoint, $token, $params);
        } catch (ClientException $e) {
            // 401 の場合だけトークン更新して再試行
            if ($e->getResponse()?->getStatusCode() === 401) {
                $newToken = $this->fetchToken($userId);
                return $this->requestWithToken('GET', $endpoint, $newToken, $params);
            }
            throw $e;
        }
    }

    /** 実際のAPI呼び出し */
    private function requestWithToken($method, $endpoint, $token, $params = [])
    {
        $url = rtrim($this->apiBase, '/') . '/' . $this->contractId . $endpoint;

        $response = $this->client->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept'        => 'application/json',
            ],
            'query' => $params,
        ]);

        $data = json_decode((string)$response->getBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Smaregi API returned invalid JSON.');
        }

        return $data;
    }

    /** 👥 スタッフ一覧 */
    public function getStaffs($userId)
    {
        return $this->get($userId, '/pos/staffs');
    }

    /**
     * 💰 取引取得（with_details 安全対応版）
     * 他ページ互換性維持のため、シグネチャはそのまま。
     */
    public function fetchTransactions(
        $userId,
        ?string $from = null,
        ?string $to = null,
        int $page = 1,
        int $limit = 100,
        bool $withDetails = false
    ) {
        $endpoint = '/pos/transactions';
        $params = [
            'transaction_date_time-from' => ($from ? Carbon::parse($from) : now()->subDays(30))->toIso8601String(),
            'transaction_date_time-to'   => ($to ? Carbon::parse($to) : now())->toIso8601String(),
            'limit'                      => $limit,
            'page'                       => $page,
            'sort'                       => 'updDateTime',
            'with_details'               => $withDetails ? 'all' : 'none',
        ];

        try {
            $response = $this->get($userId, $endpoint, $params);
        } catch (ClientException $e) {
            $body = (string)$e->getResponse()?->getBody();

            // サンドボックス等で all が禁止される場合は none で自動リトライ
            if (str_contains($body, 'with_details') && str_contains($body, 'none以外')) {
                Log::warning('[SmaregiService] with_details=all が禁止 → none にフォールバックして再試行');
                $params['with_details'] = 'none';
                try {
                    $response = $this->get($userId, $endpoint, $params);
                } catch (\Throwable $e2) {
                    Log::error('[SmaregiService] フォールバック後も失敗', ['error' => $e2->getMessage()]);
                    return [];
                }
            } else {
                Log::error('Smaregi Transactions API Error', [
                    'status' => $e->getResponse()?->getStatusCode(),
                    'body'   => $body,
                ]);
                // ここで再throwせず安全終了（他ページ影響なし）
                return [];
            }
        }

        // レスポンス構造を吸収
        $transactions = $response['items'] ?? (is_array($response) ? $response : []);
        if (!is_array($transactions)) {
            Log::error('Smaregi invalid response format', ['response' => $response]);
            return [];
        }

        // 降順ソート（UI側の期待に合わせる）
        $transactions = collect($transactions)
            ->sortByDesc('updDateTime')
            ->values()
            ->all();

        /**
         * ✅ DB保存：details 付きの既存 raw_data がある場合は、
         * details を含まない新データでは上書きしない（安全・非破壊）
         */
        foreach ($transactions as $tx) {
            if (!isset($tx['transactionHeadId'])) {
                continue;
            }

            // 既存 raw_data の details 有無を確認
            $existingRaw = DB::table('smaregi_transactions')
                ->where('transaction_head_id', $tx['transactionHeadId'])
                ->value('raw_data');

            $existing = $existingRaw ? json_decode($existingRaw, true) : null;
            $hasExistingDetails = isset($existing['details']) && is_array($existing['details']) && count($existing['details']) > 0;

            $hasNewDetails = isset($tx['details']) && is_array($tx['details']) && count($tx['details']) > 0;

            // 既存に details があり、新データに details がない時は raw_data を上書きしない
            $rawDataToSave = $existingRaw;
            if ($hasNewDetails || !$hasExistingDetails) {
                // 新に details がある、または既存に details が無い → 新データで保存
                $rawDataToSave = json_encode($tx, JSON_UNESCAPED_UNICODE);
            } else {
                // 既存 details を保持
                Log::info('[SmaregiService] details なしのため既存 raw_data を保持', [
                    'transactionHeadId' => $tx['transactionHeadId'],
                ]);
            }

            DB::table('smaregi_transactions')->updateOrInsert(
                ['transaction_head_id' => $tx['transactionHeadId']],
                [
                    'customer_name'    => $tx['customerName'] ?? ($existing['customerName'] ?? null),
                    'staff_name'       => $tx['staffName'] ?? ($existing['staffName'] ?? null),
                    'staff_id'         => $tx['staffId'] ?? ($existing['staffId'] ?? null),
                    'total_amount'     => $tx['total'] ?? ($existing['total'] ?? null),
                    'payment_type'     => $tx['creditDivision'] ?? ($existing['creditDivision'] ?? null),
                    'transaction_date' => isset($tx['transactionDateTime'])
                        ? Carbon::parse($tx['transactionDateTime'])
                        : (isset($existing['transactionDateTime']) ? Carbon::parse($existing['transactionDateTime']) : null),
                    'raw_data'         => $rawDataToSave,
                    'updated_at'       => now(),
                ]
            );
        }

        return $transactions;
    }
}
