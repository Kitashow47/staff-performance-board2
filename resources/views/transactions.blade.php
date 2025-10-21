<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            取引一覧（最新10件）
        </h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-4 text-sm text-gray-600">
                直近の取引データを表示します。
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-gray-800">
                    <thead>
                        <tr>
                            <th class="border-b py-2">取引ID</th>
                            <th class="border-b py-2">日時</th>
                            <th class="border-b py-2">合計金額</th>
                            <th class="border-b py-2">担当スタッフ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $t)
                            <tr>
                                <td class="border-b py-2">{{ $t['transactionHeadId'] ?? '-' }}</td>
                                <td class="border-b py-2">{{ $t['transactionDateTime'] ?? '-' }}</td>
                                <td class="border-b py-2">
                                    @php
                                        $total = $t['transactionTotal'] ?? null;
                                        echo is_numeric($total) ? number_format((float)$total) . ' 円' : '-';
                                    @endphp
                                </td>
                                <td class="border-b py-2">{{ $t['staffName'] ?? ($t['staffId'] ?? '-') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-6 text-gray-500">
                                    取引データがありません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:underline">← ダッシュボードへ戻る</a>
            </div>
        </div>
    </div>
</x-app-layout>
