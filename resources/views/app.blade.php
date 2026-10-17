<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'TikPulse') }} - Enterprise MikroTik & Hotspot Management</title>
        <meta name="description" content="Enterprise-Grade Multi-Router MikroTik & Hotspot Voucher Management System">

        <!-- Preload & theme detection (Prevent FOUC) -->
        <script>
            (function() {
                const storedTheme = localStorage.getItem('tikpulse_theme');
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (storedTheme === 'dark' || (!storedTheme && systemDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/main.ts'])
    </head>
    <body class="h-full bg-background text-foreground font-sans antialiased selection:bg-primary selection:text-primary-foreground">
        <div id="app" class="h-full"></div>
    </body>
</html>
