<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">スタッフ別売上ランキング</h1>

    <div class="mb-4">
        <button class="border rounded px-3 py-1">月次</button>
    </div>

    <table class="min-w-full border">
        <thead>
            <tr>
                <th class="border px-4 py-2">順位</th>
                <th class="border px-4 py-2">スタッフ名</th>
                <th class="border px-4 py-2">売上金額</th>
                <th class="border px-4 py-2">件数</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($staffSales as $index => $staff)
                <tr>
                    <td class="border px-4 py-2 text-center">{{ $index + 1 }}</td>

                    <!-- 🔽 スタッフ名クリックで詳細ページへ遷移 -->
                    <td class="border px-4 py-2">
                        <a href="{{ route('staff.detail', ['id' => $staff['id']]) }}"
                           class="text-blue-600 hover:underline">
                            {{ $staff['name'] }}
                        </a>
                    </td>

                    <td class="border px-4 py-2 text-right">¥{{ number_format($staff['amount']) }}</td>
                    <td class="border px-4 py-2 text-center">{{ $staff['count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
