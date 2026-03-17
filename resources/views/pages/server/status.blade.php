<?php

use Livewire\Component;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public $cpuUsage = 0;
    public $ramUsage = 0;
    public $diskUsage = 0;
    public $diskFreeGB = 0;
    public $cpuTemp = 0;
    public $uptime = '';
    public $activeContainers = 0;
    
    public $selectedApp = 'expense-tracker';
    public $lastCommandOutput = '';

    public $apps = [
        'expense-tracker' => [
            'name' => 'Expenses Tracker',
            'path' => '/var/www/expense-tracker',
            'url'  => 'https://tracker.hellojonathan.my.id',
            'status' => 'checking...'
        ],
        'portfolio' => [
            'name' => 'Portfolio Site',
            'path' => '/var/www/portfolio',
            'url'  => 'https://hellojonathan.my.id',
            'status' => 'checking...'
        ]
    ];

    public function mount()
    {
        $this->updateStats();
    }

    public function updateStats()
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            // Ubuntu NUC Stats
            $this->cpuUsage = (int) shell_exec("top -bn1 | grep \"Cpu(s)\" | sed \"s/.*, *\\([0-9.]*\\)%* id.*/\\1/\" | awk '{print 100 - $1}'");
            $this->ramUsage = (int) shell_exec("free | grep Mem | awk '{print $3/$2 * 100.0}'");
            
            // Disk Space & Free GB
            $this->diskUsage = (int) shell_exec("df -h / | grep / | awk '{print $5}' | cut -d% -f1");
            $this->diskFreeGB = round(disk_free_space("/") / 1024 / 1024 / 1024, 1);
            
            $this->cpuTemp = (float) @shell_exec("cat /sys/class/thermal/thermal_zone0/temp") / 1000;
            $this->uptime = shell_exec("uptime -p");
            $this->activeContainers = (int) shell_exec("docker ps -q | wc -l");
        } else {
            // Mac Mock for testing
            $this->cpuUsage = 12.5; $this->ramUsage = 65.0; $this->diskUsage = 40; $this->diskFreeGB = 450.5;
            $this->uptime = 'up 2 hours, 30 minutes'; $this->activeContainers = 3;
        }

        // App Health Check
        foreach ($this->apps as $key => $app) {
            try {
                $response = Http::timeout(2)->get($app['url']);
                $this->apps[$key]['status'] = $response->ok() ? 'online' : 'error';
            } catch (\Exception $e) {
                $this->apps[$key]['status'] = 'offline';
            }
        }
    }

    public function runAction($action)
    {
        $app = $this->apps[$this->selectedApp];
        $path = $app['path'];
        $output = "Executing $action on {$app['name']}...\n";

        if ($action === 'pull') {
            $result = Process::path($path)->run('git pull origin main');
            $output .= $result->output() ?: $result->errorOutput();
            Process::path($path)->run('php artisan optimize:clear');
        } 
        
        if ($action === 'perms') {
            $cmds = ["sudo chown -R www-data:www-data $path", "sudo find $path -type d -exec chmod 775 {} \;", "sudo find $path -type f -exec chmod 664 {} \;"];
            foreach ($cmds as $cmd) {
                $res = Process::run($cmd);
                $output .= $res->successful() ? "OK: $cmd\n" : "FAIL: $cmd\n";
            }
        }
        $this->lastCommandOutput = $output;
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['title' => 'Server Monitor']);
    }
} ?>

<div wire:poll.15s="updateStats" class="min-h-screen bg-gray-50 dark:bg-zinc-900 p-8 mt-6">
    <div class="max-w-6xl mx-auto">
        <header
            class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight">System Monitor</h1>
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Target App:</span>
                    <select wire:model.live="selectedApp"
                        class="bg-transparent border-none p-0 text-sm font-bold text-blue-600 focus:ring-0 cursor-pointer">
                        @foreach($apps as $key => $app)
                        <option value="{{ $key }}">{{ $app['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-3">
                <div class="group relative">
                    <button wire:click="runAction('perms')" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm cursor-pointer">
                        <span wire:loading.remove wire:target="runAction('perms')">FIX PERMISSION</span>
                        <span wire:loading wire:target="runAction('perms')">WORKING...</span>
                    </button>
                    <div
                        class="absolute bottom-full mb-2 hidden group-hover:block w-64 p-2 bg-zinc-900 text-[10px] text-zinc-400 font-mono rounded-lg shadow-xl border border-zinc-700 z-50">
                        <p class="text-blue-400 mb-1 font-bold">// Commands:</p>
                        sudo chown -R www-data:www-data<br>
                        sudo find . -type d -exec chmod 775<br>
                        sudo find . -type f -exec chmod 664
                    </div>
                </div>

                <div class="group relative">
                    <button wire:click="runAction('pull')" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-zinc-800 dark:bg-white dark:text-zinc-900 text-white rounded-xl text-xs font-bold hover:opacity-90 transition shadow-sm cursor-pointer">
                        <span wire:loading.remove wire:target="runAction('pull')">GIT PULL</span>
                        <span wire:loading wire:target="runAction('pull')">PULLING...</span>
                    </button>
                    <div
                        class="absolute bottom-full right-0 mb-2 hidden group-hover:block w-48 p-2 bg-zinc-900 text-[10px] text-zinc-400 font-mono rounded-lg shadow-xl border border-zinc-700 z-50">
                        <p class="text-green-400 mb-1 font-bold">// Commands:</p>
                        git pull origin main<br>
                        php artisan optimize:clear
                    </div>
                </div>
            </div>
        </header>

        @if($lastCommandOutput)
        <div
            class="mb-6 p-4 rounded-xl bg-zinc-950 text-green-400 text-[11px] font-mono border border-zinc-800 shadow-xl overflow-x-auto whitespace-pre">
            <div class="flex gap-1.5 mb-2 opacity-50">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
            </div>
            {{ $lastCommandOutput }}
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div
                class="bg-white dark:bg-zinc-800 p-6 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm">
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">CPU Load</p>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="flex items-baseline gap-2">
                        <p class="text-4xl font-black dark:text-white">{{ number_format($cpuUsage, 1, ',', '.') }}%</p>
                        <span class="text-[10px] font-bold text-gray-500">{{ $cpuTemp }}°C</span>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $cpuUsage > 85 ? 'bg-red-100 text-red-600' : ($cpuUsage > 60 ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600') }}">
                        {{ $cpuUsage > 85 ? 'Critical' : ($cpuUsage > 60 ? 'Warning' : 'Healthy') }}
                    </span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-zinc-700 h-2 rounded-full mt-4 overflow-hidden">
                    <div class="h-full transition-all duration-700 {{ $cpuUsage > 85 ? 'bg-red-500' : ($cpuUsage > 60 ? 'bg-yellow-400' : 'bg-green-500') }}"
                        style="width: {{ $cpuUsage }}%"></div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-zinc-800 p-6 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm">
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Memory Usage</p>
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-4xl font-black dark:text-white">{{ number_format($ramUsage, 1, ',', '.') }}%</p>
                    <span
                        class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $ramUsage > 90 ? 'bg-red-100 text-red-600' : ($ramUsage > 70 ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600') }}">
                        {{ $ramUsage > 90 ? 'Critical' : ($ramUsage > 70 ? 'Warning' : 'Healthy') }}
                    </span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-zinc-700 h-2 rounded-full mt-4 overflow-hidden">
                    <div class="h-full transition-all duration-700 {{ $ramUsage > 90 ? 'bg-red-500' : ($ramUsage > 70 ? 'bg-yellow-400' : 'bg-green-500') }}"
                        style="width: {{ $ramUsage }}%"></div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-zinc-800 p-6 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm">
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Disk Space</p>
                <div class="flex items-baseline justify-between gap-2">
                    <div class="flex items-baseline gap-2">
                        <p class="text-4xl font-black dark:text-white">{{ $diskUsage }}%</p>
                        <span class="text-[10px] text-gray-400 font-mono    ">Free: {{ number_format($diskFreeGB, 1,
                            ',', '.') }} GB</span>
                    </div>
                    <span
                        class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $diskUsage > 90 ? 'bg-red-100 text-red-600' : ($diskUsage > 80 ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600') }}">
                        {{ $diskUsage > 90 ? 'Full' : ($diskUsage > 80 ? 'Warning' : 'Healthy') }}
                    </span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-zinc-700 h-2 rounded-full mt-4 overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all duration-700" style="width: {{ $diskUsage }}%">
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div
                class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden shadow-sm">
                <div
                    class="p-4 border-b border-gray-50 dark:border-zinc-700 bg-gray-50/50 dark:bg-zinc-800/50 flex justify-between items-center">
                    <h3 class="text-xs font-bold dark:text-white uppercase tracking-wider">Application Health</h3>
                    <span class="text-[10px] text-gray-400">Polling: 15s</span>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-zinc-700">
                    @foreach($apps as $app)
                    <div
                        class="p-4 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-zinc-700/30 transition">
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

            <div class="flex flex-col gap-6">
                <div
                    class="bg-white dark:bg-zinc-800 p-6 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Docker Status</p>
                        <p class="text-2xl font-black dark:text-white">{{ $activeContainers }} <span
                                class="text-sm font-normal text-gray-500">Containers</span></p>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-zinc-800 p-6 rounded-2xl border border-gray-100 dark:border-zinc-700 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">System Uptime</p>
                    <p class="text-xl font-black dark:text-white">{{ $uptime }}</p>
                </div>
            </div>
        </div>
    </div>
</div>