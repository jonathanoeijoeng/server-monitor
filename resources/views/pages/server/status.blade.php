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
    public $downValue = "0";
    public $downUnit = "kb/s";
    public $upValue = "0";
    public $upUnit = "kb/s";
    private $storageKey = 'network_stats';
    
    public $selectedApp = 'expense-tracker';
    public $lastCommandOutput = '';

    public $apps = [
        'expense-tracker' => [
            'name' => 'Expenses Tracker',
            'path' => '/var/www/expense-tracker',
            'url'  => 'https://expense.hellojonathan.my.id',
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

            $downBps = rand(50000, 1500000); // 50KB - 1.5MB
            $upBps = rand(10000, 300000);    // 10KB - 300KB
            
            $this->downloadSpeed = $this->formatBytes($downBps) . '/s';
            $this->uploadSpeed = $this->formatBytes($upBps) . '/s';

            $response = shell_exec("curl --unix-socket /var/run/docker.sock http://localhost/containers/json");
    
            if ($response) {
                $containers = json_decode($response, true);
                $this->activeContainers = is_array($containers) ? count($containers) : 0;
            } else {
                $this->activeContainers = 0;
            }
        } else {
            // Mac Mock for testing
            $this->cpuUsage = rand(10, 90);
            $this->ramUsage = rand(20, 80);
            $this->cpuTemp = rand(30, 85);
            $this->diskFreeGB = 65;
            $this->diskUsage = 35; // 35% terpakai
            $this->uptime = 'up 2 hours, 30 minutes'; $this->activeContainers = 3;

            $downBps = rand(50000, 1500000); 
            $upBps = rand(10000, 300000);
        }

        $down = $this->splitBytes($downBps);
        $this->downValue = $down['value'];
        $this->downUnit = $down['unit'] . '/s';

        $up = $this->splitBytes($upBps);
        $this->upValue = $up['value'];
        $this->upUnit = $up['unit'] . '/s';

        $this->dispatch('stats-updated', 
            cpu: $this->cpuUsage, 
            ram: $this->ramUsage,
            diskUsed: $this->diskUsage,
            cpuTemp: $this->cpuTemp,
        );

        $this->dispatch('network-updated', down: $this->downValue . $this->downUnit, up: $this->upValue . $this->upUnit);

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

    private function splitBytes($bytes) {
        if ($bytes < 1024) {
            return ['value' => number_format($bytes, 0), 'unit' => 'B'];
        }
        if ($bytes < 1048576) {
            return ['value' => number_format($bytes / 1024, 1), 'unit' => 'KB'];
        }
        return ['value' => number_format($bytes / 1048576, 1), 'unit' => 'MB'];
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
        return $this->view()->layout('layouts.fullscreen');
    }
} ?>

<div class="w-full mx-auto bg-white p-12">
    <header
        class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight">System Monitor</h1>
        </div>

        <div class="flex gap-3 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Target App:</span>
                <select wire:model.live="selectedApp"
                    class="bg-transparent border-none p-0 text-sm font-bold text-orange-600 focus:ring-0 cursor-pointer">
                    @foreach($apps as $key => $app)
                    <option value="{{ $key }}">{{ $app['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="group relative">
                <button wire:click="runAction('perms')" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-orange-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm cursor-pointer">
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

    <div wire:poll.2s="updateStats" class="grid grid-cols-5 gap-6">

        <div class="col-span-2 p-6 bg-orange-50 rounded-3xl border border-orange-100 shadow-sm">
            <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">CPU Load</p>

            <div class="flex items-baseline mb-4 gap-3">
                <div class="flex items-baseline">
                    <h1 class="text-6xl font-black text-gray-900">{{ $cpuUsage }}</h1>
                    <span class="text-2xl font-bold text-[#E3833C] ml-2">%</span>
                </div>

                <div class="flex items-baseline pb-1 gap-2">
                    <div class="text-gray-400">temp:</div>
                    <div class="text-xl font-bold lowercase 
                        {{ $cpuTemp < 50 ? 'text-green-500' : ($cpuTemp < 75 ? 'text-yellow-500' : 'text-red-500') }}">
                        {{ $cpuTemp }}°c
                    </div>
                </div>
            </div>

            <div wire:ignore class="bg-white rounded-2xl p-4 shadow-inner" style="height: 150;">
                <canvas id="cpuChart"></canvas>
            </div>
        </div>

        <div class="col-span-2 p-6 bg-orange-50 rounded-3xl border border-orange-100 shadow-sm">
            <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">RAM Usage</p>
            <div class="flex items-baseline mb-4">
                <h1 class="text-6xl font-black text-gray-900">{{ $ramUsage }}</h1>
                <span class="text-2xl font-bold text-[#E3833C] ml-2">%</span>
            </div>

            <div wire:ignore class="bg-white rounded-2xl p-4 shadow-inner" style="height: 150;">
                <canvas id="ramChart"></canvas>
            </div>
        </div>

        <div class="p-6 bg-orange-50 rounded-3xl border border-orange-100 shadow-sm">
            <p class="text-gray-500 text-xs font-bold uppercase tracking-widest mb-1">Disk Storage</p>
            <div class="flex items-baseline mb-4">
                <h1 class="text-6xl font-black text-gray-900">{{ $diskUsage }}</h1>
                <span class="text-2xl font-bold text-[#E3833C] ml-2">% Used</span>
            </div>

            <div wire:ignore class="bg-white rounded-2xl p-4 shadow-inner flex justify-center" style="height: 150px;">
                <canvas id="diskChart"></canvas>
            </div>
        </div>

    </div>

    @include('pages.server.partials.apps')

</div>

<script>
    let cpuChart = null;
    let ramChart = null;
    let diskChart = null;
    const mainColor = '#E3833C';

    // Fungsi inisialisasi tetap sama
    const setupCharts = () => {
        const cpuCanvas = document.getElementById('cpuChart');
        const ramCanvas = document.getElementById('ramChart');
        const diskCanvas = document.getElementById('diskChart');

        if (cpuCanvas && !cpuChart) {
            cpuChart = new Chart(cpuCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        borderColor: '#E3833C',
                        backgroundColor: 'rgba(227, 131, 60, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    scales: { y: { min: 0, max: 100 }, x: { display: false } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // INIT RAM (PASTIKAN BAGIAN INI ADA DAN SAMA)
        if (ramCanvas && !ramChart) {
            ramChart = new Chart(ramCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        borderColor: mainColor, // Samakan warna agar seragam #E3833C
                        backgroundColor: 'rgba(227, 131, 60, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    scales: { y: { min: 0, max: 100 }, x: { display: false } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // INIT DISK (Doughnut)
        if (diskCanvas && !diskChart) {
            diskChart = new Chart(diskCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Used', 'Free'],
                    datasets: [{
                        data: [0, 100], 
                        backgroundColor: ['#E3833C', '#f3f4f6'], // Oranye & Abu-abu
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%', // Agar tengahnya bolong (modern look)
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    };

    setupCharts();

    const setupDiskChart = () => {
    const diskCanvas = document.getElementById('diskChart');
    if (diskCanvas && !diskChart) {
            diskChart = new Chart(diskCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Used', 'Free'],
                    datasets: [{
                        data: [0, 100], // Start empty
                        backgroundColor: ['#E3833C', '#f3f4f6'], // Oranye vs Abu-abu muda
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%', // Membuat lubang di tengah lebih besar agar clean
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    };

    setupDiskChart();

    // LISTENER YANG LEBIH KUAT
    $wire.on('stats-updated', (data) => {
        // DEBUG: Lihat di console apakah data muncul setiap 2 detik
        // console.log('Data masuk:', data);

        // Terkadang v4 mengirim data sebagai parameter pertama (objek)
        // Jika data.cpu undefined, coba data[0].cpu
        const cpuVal = data.cpu !== undefined ? data.cpu : (data[0] ? data[0].cpu : 0);
        const ramVal = data.ram !== undefined ? data.ram : (data[0] ? data[0].ram : 0);

        if (cpuChart) {
            cpuChart.data.datasets[0].data.push(cpuVal);
            cpuChart.data.datasets[0].data.shift();
            cpuChart.update('none');
        }

        // Update RAM
        if (ramChart) {
            ramChart.data.datasets[0].data.push(ramVal);
            ramChart.data.datasets[0].data.shift();
            ramChart.update('none');
        }

        if (diskChart) {
            const used = data.diskUsed !== undefined ? data.diskUsed : (data[0] ? data[0].diskUsed : 0);
            const free = data.diskFree !== undefined ? data.diskFree : (data[0] ? data[0].diskFree : 100);
            
            diskChart.data.datasets[0].data = [used, free];
            diskChart.update(); // Untuk Pie/Doughnut tidak perlu 'none' agar transisinya halus
        }
    });
</script>