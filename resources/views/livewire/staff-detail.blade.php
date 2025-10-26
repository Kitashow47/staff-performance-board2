<div class="p-8 space-y-8 bg-gray-50 min-h-screen">
    <!-- 見出し -->
    <div>
        <h1 class="text-2xl font-bold mb-2">
            {{ $staffName }} さんの売上詳細（{{ strtoupper($period) }}）
        </h1>
        <div class="space-x-2">
            @foreach (['day' => '日次', 'week' => '週次', 'month' => '月次'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    class="px-4 py-1 rounded border transition
                    @if($period === $key)
                        bg-white text-blue-600 border-blue-400 font-semibold
                    @else
                        bg-gray-100 text-gray-700 hover:bg-gray-200
                    @endif">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- 売上概要 -->
    <div class="grid grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-sm text-gray-500">売上合計</div>
            <div class="text-2xl font-bold mt-1">¥{{ number_format($totalSales) }}</div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-sm text-gray-500">取引件数</div>
            <div class="text-2xl font-bold mt-1">{{ $totalCount }}件</div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-sm text-gray-500">平均単価</div>
            <div class="text-2xl font-bold mt-1">¥{{ number_format($avgUnitPrice) }}</div>
        </div>
    </div>

    <!-- カテゴリ別売上 -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">カテゴリ別売上</h2>
        @if (count($salesByCategory) > 0)
            <table class="min-w-full border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border px-4 py-2 text-left">カテゴリ名</th>
                        <th class="border px-4 py-2 text-right">件数</th>
                        <th class="border px-4 py-2 text-right">売上合計</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesByCategory as $item)
                        <tr>
                            <td class="border px-4 py-2">{{ $item['name'] }}</td>
                            <td class="border px-4 py-2 text-right">{{ $item['count'] }}</td>
                            <td class="border px-4 py-2 text-right">¥{{ number_format($item['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-gray-500 text-center py-6">データなし</div>
        @endif
    </div>

    <!-- 商品別売上 -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">商品別売上（上位5件）</h2>
        @if (count($salesByProduct) > 0)
            <table class="min-w-full border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border px-4 py-2 text-left">商品名</th>
                        <th class="border px-4 py-2 text-right">件数</th>
                        <th class="border px-4 py-2 text-right">売上合計</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($salesByProduct, 0, 5) as $item)
                        <tr>
                            <td class="border px-4 py-2">{{ $item['name'] }}</td>
                            <td class="border px-4 py-2 text-right">{{ $item['count'] }}</td>
                            <td class="border px-4 py-2 text-right">¥{{ number_format($item['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-gray-500 text-center py-6">データなし</div>
        @endif
    </div>
</div>
