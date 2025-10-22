<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmaregiService
{
    private const SMAREGI_TOKENS_TABLE = 'smaregi_tokens';
    private const SMAREGI_TRANSACTIONS_TABLE = 'smaregi_transactions';

    private HttpFactory $http;
    private array $config;

    public function __construct(HttpFactory $http)
    {
        $this->http = $http;
        $this->config = config('smaregi', []);
    }

    /** 🔑 トークン取得 */
    public function fetchAndSaveToken(int $userId): string
    {
        try {
            $response = $this->http->asForm()
                ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
                ->post($this->config['token_url'], [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'pos.staffs:read pos.transactions:read pos.products:read', // 商品取得スコープも追加
                ]);

            $response->throw(); // 失敗したら例外をスロー

            $data = $response->json();
            $accessToken = $data['access_token'];

            DB::table(self::SMAREGI_TOKENS_TABLE)->updateOrInsert(
                ['user_id' => $userId],
                [
                    'access_token'            => $accessToken,
                    'expires_in'              => $data['expires_in'] ?? null,
                    'access_token_expires_at' => Carbon::now()->addSeconds($data['expires_in'] ?? 0),
                    'scope'                   => $data['scope'] ?? null,
                    'updated_at'              => now(),
                ]
            );

            return $accessToken;
        } catch (RequestException $e) {
            Log::error('Failed to fetch Smaregi access token.', [
                'status' => $e->response?->status(),
                'response' => $e->response?->body(),
            ]);
            throw new \Exception('スマレジのアクセストークン取得に失敗しました。', $e->getCode(), $e);
        }
    }

    /**
     * 認証済みAPIクライアントを生成
     * @throws \Exception
     */
    private function createAuthenticatedClient(int $userId): PendingRequest
    {
        $token = DB::table(self::SMAREGI_TOKENS_TABLE)->where('user_id', $userId)->value('access_token');

        if (!$token) {
            $token = $this->fetchAndSaveToken($userId);
        }

        return $this->http
            ->baseUrl(rtrim($this->config['pos_api_base'], '/') . '/' . $this->config['contract_id'])
            ->withToken($token)
            ->acceptJson()
            ->timeout(30)
            // 401エラーの場合、トークンを再取得して1回だけリトライ
            ->retry(1, 0, function ($exception, $request) use ($userId) {
                if ($exception instanceof RequestException && $exception->response->status() === 401) {
                    Log::info('Smaregi token expired. Retrying with new token.');
                    $newToken = $this->fetchAndSaveToken($userId);
                    $request->withToken($newToken);
                    return true; // リトライ実行
                }
                return false; // リトライしない
            });
    }

    /** 👥 スタッフ一覧取得 */
    public function getStaffs(int $userId): array
    {
        $response = $this->createAuthenticatedClient($userId)->get('/pos/staffs');
        $response->throw();
        // スタッフAPIはレスポンスが 'data' キーでラップされている場合がある
        return $response->json('data') ?? $response->json();
    }

    /** 💰 取引データ取得 */
    public function fetchTransactions(int $userId, int $days = 30, int $limit = 100): array
    {
        $params = [
            'transaction_date_time-from' => now()->subDays($days)->toIso8601String(),
            'transaction_date_time-to'   => now()->toIso8601String(),
            'limit'                      => $limit,
            'with_details'               => 'all', // 取引明細も同時に取得
        ];

        try {
            $response = $this->createAuthenticatedClient($userId)->get('/pos/transactions', $params);
            $response->throw();
            return $response->json();
        } catch (RequestException $e) {
            Log::error('Smaregi Transactions API Error', [
                'status' => $e->response?->status(),
                'body'   => $e->response?->body(),
            ]);
            throw $e;
        }
    }

    /**
     * 取得した取引データをステージングテーブルに保存する
     * @param array $transactions
     * @return int 保存した件数
     */
    public function storeTransactionsToStaging(array $transactions): int
    {
        $storedCount = 0;
        foreach ($transactions as $tx) {
            if (!is_array($tx) || !isset($tx['transactionHeadId'])) {
                continue;
            }

            try {
                DB::table(self::SMAREGI_TRANSACTIONS_TABLE)->updateOrInsert(
                    ['transaction_head_id' => $tx['transactionHeadId']],
                    [
                        'customer_name'    => $tx['customerName'] ?? null,
                        'staff_name'       => $tx['staffName'] ?? null,
                        'total_amount'     => $tx['totalAmount'] ?? null,
                        'payment_type'     => $tx['details'][0]['paymentMethod'] ?? null, // 簡易的に最初の支払方法を取得
                        'transaction_date' => isset($tx['transactionDateTime']) ? Carbon::parse($tx['transactionDateTime']) : null,
                        'raw_data'         => json_encode($tx, JSON_UNESCAPED_UNICODE),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]
                );
                $storedCount++;
            } catch (Throwable $e) {
                Log::error('Failed to store transaction to staging table.', [
                    'transactionHeadId' => $tx['transactionHeadId'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Smaregi Transactions Stored', ['count' => $storedCount]);
        return $storedCount;
    }
}
