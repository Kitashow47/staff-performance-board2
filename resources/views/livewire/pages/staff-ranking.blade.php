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
                    <td class="border px-4 py-2">{{ $index + 1 }}</td>
                    <td class="border px-4 py-2">{{ $staff['name'] }}</td>
                    <td class="border px-4 py-2">¥{{ number_format($staff['amount']) }}</td>
                    <td class="border px-4 py-2">{{ $staff['count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
