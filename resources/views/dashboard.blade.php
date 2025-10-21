<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            ダッシュボード
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto">
        @if (session('status'))
            <div class="bg-green-100 text-green-800 p-3 mb-4 rounded">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 text-red-800 p-3 mb-4 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow">
            <p class="mb-4">スマレジと連携してデータを取得します。</p>

            {{-- スマレジ連携ボタン --}}
            <a href="{{ route('smaregi.connect') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
               スマレジと連携する
            </a>

            {{-- スタッフ・取引取得ボタン --}}
            <div class="mt-4 flex gap-4">
                <a href="{{ route('smaregi.staffs') }}" class="bg-gray-700 text-white px-3 py-2 rounded">
                    スタッフ取得
                </a>
                <a href="{{ route('smaregi.transactions') }}" class="bg-gray-700 text-white px-3 py-2 rounded">
                    取引取得
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
