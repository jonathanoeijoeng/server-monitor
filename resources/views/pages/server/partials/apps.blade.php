<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
    <div
        class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div
            class="p-4 border-b border-gray-50 dark:border-zinc-700 bg-orange-50 dark:bg-zinc-800/50 flex justify-between items-center">
            <h3 class="text-xs font-bold dark:text-white uppercase tracking-wider text-zinc-800">Application Health</h3>
            <span class="text-[10px] text-gray-400">Polling: 15s</span>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-zinc-700">
            @foreach($apps as $app)
            <div class="p-4 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-zinc-700/30 transition">
                <div class="flex flex-col">
                    <span class="font-bold text-gray-800 dark:text-zinc-200">{{ $app['name'] }}</span>
                    <span class="text-[10px] text-gray-400 font-mono">{{ str_replace(['https://', 'http://'],
                        '', $app['url']) }}</span>
                </div>
                <div
                    class="flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700">
                    <span
                        class="flex h-2 w-2 rounded-full {{ $app['status'] === 'online' ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]' : 'bg-red-500 animate-pulse' }}"></span>
                    <span
                        class="text-[10px] font-black uppercase {{ $app['status'] === 'online' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $app['status'] }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>


    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
                class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden shadow-sm flex flex-col h-full">
                <div class="p-4 border-b border-gray-50 dark:border-zinc-700 bg-orange-50 dark:bg-zinc-800/50">
                    <h3 class="text-xs font-bold dark:text-white uppercase tracking-wider text-zinc-800">Docker Status
                    </h3>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-center items-start">
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 opacity-70">
                        Docker Instance
                    </span>

                    <div class="flex items-baseline gap-1">
                        <span class="text-4xl font-black text-gray-900 dark:text-white leading-none">
                            {{ explode(' / ', $dockerStats)[0] }}
                        </span>

                        <span class="text-xl font-bold text-gray-400">
                            / {{ explode(' / ', $dockerStats)[1] }}
                        </span>

                        @if((int)explode(' / ', $dockerStats)[0] < (int)explode(' / ', $dockerStats)[1])
                            <div class="ml-3 flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            <div
                class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden shadow-sm">
                <div class="p-4 border-b border-gray-50 dark:border-zinc-700 bg-orange-50 dark:bg-zinc-800/50">
                    <h3 class="text-xs font-bold dark:text-white uppercase tracking-wider text-zinc-800">Network Speed
                    </h3>
                </div>
                <div class="flex justify-between items-center p-4">
                    <div>
                        <div class="text-gray-800 text-xs">Up:</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl font-black text-gray-900 tabular-nums">{{ $upValue }}</span>
                            <span class="text-[10px] font-bold text-gray-400 lowercase">{{ $upUnit }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-800 text-xs">Down:</div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl font-black text-gray-900 tabular-nums">{{ $downValue }}</span>
                            <span class="text-[10px] font-bold text-gray-400 lowercase">{{ $downUnit }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-gray-50 dark:border-zinc-700 bg-orange-50 dark:bg-zinc-800/50">
                <h3 class="text-xs font-bold dark:text-white uppercase tracking-wider text-zinc-800">System Uptime
                </h3>
            </div>
            <div
                class="bg-white dark:bg-zinc-800 p-4 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm">
                <p class="text-xl font-black text-gray-900">{{ $uptime }}</p>
            </div>
        </div>
    </div>
</div>