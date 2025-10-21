<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            スタッフ一覧
        </h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto">
        @if (session('error'))
            <div class="bg-red-100 text-red-800 p-3 mb-4 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if (!empty($staffs))
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">スタッフID</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">名前</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">表示フラグ</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">ログイン権限</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">メール</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($staffs as $staff)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $staff['staffId'] ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $staff['staffName'] ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-100">
                                    {{ $staff['displayFlag'] === '1' ? '表示中' : '非表示' }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-100">
                                    @if ($staff['loginStaffFlag'] === '1')
                                        管理者
                                    @elseif ($staff['loginStaffFlag'] === '2')
                                        一般
                                    @else
                                        なし
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $staff['email'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-yellow-100 text-yellow-800 p-4 rounded-lg">
                スタッフデータが見つかりませんでした。
            </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:underline">&larr; ダッシュボードに戻る</a>
        </div>
    </div>
</x-app-layout>
