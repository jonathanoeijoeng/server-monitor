<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorServerHealth extends Command
{
    protected $signature = 'monitor:health';
    protected $description = 'Monitor CPU Temp and RAM usage with duration-based alerts';

    public function handle()
    {
        // 1. Ambil Data (Pastikan Thermal Zone 4 untuk NUC)
        $tempRaw = (float) @shell_exec("cat /sys/class/thermal/thermal_zone4/temp") / 1000;

        // Ambil RAM (Menggunakan command 'free')
        $freeOutput = shell_exec("free | grep Mem");
        preg_match_all('/\d+/', $freeOutput, $matches);
        $totalRam = $matches[0][0];
        $usedRam = $matches[0][1];
        $ramPercent = ($usedRam / $totalRam) * 100;
        $ramUsage = (int) shell_exec("free | grep Mem | awk '{print $3/$2 * 100.0}'");
        // $tempRaw = 85.5;
        // $ramPercent = 90.0;

        // Log::info("Monitor sedang mengecek... Temp: $tempRaw");
        // $this->sendAlerts('CPU Temperature (TEST)', $tempRaw, 99, '°C');

        // 2. Threshold Konfigurasi
        $limits = [
            'cpu' => ['value' => $tempRaw, 'limit' => 10, 'label' => 'CPU Temperature', 'unit' => '°C'],
            'ram' => ['value' => $ramPercent, 'limit' => 0, 'label' => 'RAM Usage', 'unit' => '%']
        ];

        foreach ($limits as $key => $data) {
            $cacheKey = "alert_start_{$key}";
            $cooldownKey = "alert_cooldown_{$key}";

            if ($data['value'] > $data['limit']) {
                // Catat waktu mulai jika belum ada
                if (!Cache::has($cacheKey)) {
                    Cache::put($cacheKey, now(), now()->addHours(1));
                }

                $startedAt = Cache::get($cacheKey);
                $duration = now()->diffInSeconds($startedAt);

                // Jika sudah lebih dari 30 detik (Saran: 30-60 detik lebih stabil)
                if ($duration >= 30 && !Cache::has($cooldownKey)) {
                    $this->sendAlerts($data['label'], $data['value'], $duration, $data['unit']);
                    Cache::put($cooldownKey, true, now()->addMinutes(30)); // Cooldown 30 menit
                }
            } else {
                // Reset jika sudah kembali normal
                Cache::forget($cacheKey);
                Cache::forget($cooldownKey);
            }
        }
    }

    protected function sendAlerts($label, $value, $duration, $unit)
    {
        $formattedValue = number_format($value, 1, ',', '.');
        $serverName = "Home Server"; // Bisa diganti dengan nama dinamis jika perlu

        // --- TELEGRAM MESSAGE ---
        $telegramMsg = "🚨 *SERVER ALERT: {$serverName}*\n\n"
            . "Type: *{$label}*\n"
            . "Current Value: *{$formattedValue}{$unit}*\n"
            . "Duration: *>{$duration} seconds*\n"
            . "Status: *CRITICAL*\n\n"
            . "Please check your Docker containers immediately.";

        // Kirim Telegram (Gunakan token/ID yang sudah Anda punya)
        $response = Http::post("https://api.telegram.org/bot" . config('services.telegram.token') . "/sendMessage", [
            'chat_id' => config('services.telegram.chat_id'),
            'text' => $telegramMsg,
            'parse_mode' => 'Markdown'
        ]);

        // Tambahkan ini untuk melihat error aslinya
        if ($response->failed()) {
            Log::error("Telegram Gagal: " . $response->status() . " | " . $response->body());
        } else {
            Log::info("Telegram Berhasil Terkirim!");
        }

        // --- EMAIL MESSAGE ---
        $emailSubject = "[ALERT] Critical {$label} on {$serverName}";
        $emailBody = "Detailed Server Report:\n"
            . "--------------------------\n"
            . "Server: {$serverName}\n"
            . "Metric: {$label}\n"
            . "Value: {$formattedValue}{$unit}\n"
            . "Threshold Exceeded for: {$duration} seconds\n"
            . "Time: " . now()->toDateTimeString() . "\n"
            . "--------------------------\n"
            . "This is an automated message from your Intel NUC Monitor.";

        // Gunakan Mail::raw(...) atau Mail::to(...)->send(...) sesuai setup mail Anda
        Mail::raw($emailBody, function ($message) use ($emailSubject) {
            $message->to('jonathan.oeijoeng@gmail.com')->subject($emailSubject);
        });

        Log::warning("Alert Sent: {$label} reached {$formattedValue}{$unit}");
    }
}
