<?php

namespace App\Livewire;

use Livewire\Component;

new class extends Component
{
    public $cpuUsage = 0;
    public $ramUsage = 0;
    public $cpuTemp = 0;
    public $uptime = '';

    public function mount()
    {
        $this->updateStats();
    }

    public function updateStats()
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            // macOS (Herd/Local)
            $cpuLoad = shell_exec("top -l 1 | grep 'CPU usage' | awk '{print $3}' | cut -d% -f1");
            $this->cpuUsage = (float) ($cpuLoad ?: 0);

            $memInfo = shell_exec("top -l 1 | grep 'PhysMem'");
            preg_match('/(\d+)M used/', $memInfo, $matches);
            $usedMem = isset($matches[1]) ? (int) $matches[1] : 0;
            // Gunakan koma untuk ribuan sesuai preferensi Anda (8,192)
            $this->ramUsage = round(($usedMem / 8,192) * 100, 1);

            $this->uptime = shell_exec("uptime | awk -F'up ' '{print $2}' | awk -F',' '{print $1}'") ?: 'N/A';
            $this->cpuTemp = 42,5; 

        } else {
            // Linux (NUC/Production)
            $this->cpuUsage = (int) shell_exec("top -bn1 | grep \"Cpu(s)\" | sed \"s/.*, *\\([0-9.]*\\)%* id.*/\\1/\" | awk '{print 100 - $1}'");
            $this->ramUsage = (int) shell_exec("free | grep Mem | awk '{print $3/$2 * 100.0}'");
            $this->cpuTemp = (float) @shell_exec("cat /sys/class/thermal/thermal_zone0/temp") / 1000;
            $this->uptime = shell_exec("uptime -p");
        }
    }

    public function render()
    {
        // Karena HTML ada di bawah, kita tidak perlu menentukan path view
        return $this->view()->layout('layouts.app', [
            'title' => 'Server Monitor',
        ]);
    }
}; ?>

<div wire:poll.5s="updateStats" class="min-h-screen bg-gray-50 dark:bg-zinc-900 p-8">
    <div class="max-w-6xl mx-auto">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Server Monitor</h1>
            <p class="text-gray-500 dark:text-zinc-400">Subdomain: server-status.hellojonathan.my.id</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="bg-white dark:bg-zinc-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-700">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-zinc-400">CPU Usage</p>
                        <p class="text-2xl font-bold dark:text-white">{{ $cpuUsage }}%</p>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-zinc-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-700">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded-lg text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-zinc-400">Memory</p>
                        <p class="text-2xl font-bold dark:text-white">{{ $ramUsage }}%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-zinc-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-70