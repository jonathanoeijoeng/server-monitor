<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark>

<head>
    <meta charset=" UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Server Monitor</title>
<link rel="icon" type="image/svg+xml"
    href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23E3833C'/><text y='.75em' x='5' font-family='monospace' font-weight='bold' font-size='80' fill='white'>_</text></svg>" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
{{-- <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
<style>
    /* Sembunyikan scrollbar tapi tetap bisa scroll jika perlu */
    body::-webkit-scrollbar {
        display: none;
    }

    body {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
</head>

<body class="h-full text-white antialiased">

    {{ $slot }}

    @livewireScripts
</body>

</html>