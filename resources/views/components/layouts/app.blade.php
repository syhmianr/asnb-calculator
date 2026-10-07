<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Estimate your ASNB dividend and bonus with custom monthly deposits and withdrawals.">
    <title>{{ $title ?? 'ASNB Calculator' }}</title>
    {{-- Apply the saved theme before first paint to avoid a flash. --}}
    <script>
        (function () {
            var mode = 'dark';
            try { mode = localStorage.getItem('theme') || 'dark'; } catch (e) {}
            var dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
    {{ $slot }}
</body>
</html>
