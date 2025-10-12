<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'KoolReport' }}</title>

    <!-- Include KoolReport's default CSS first -->
    {!! \KoolReport\Core\Utility::get("ResourceManager")->publishAssetFolder("core", "css") !!}

    <!-- Include our Filament theme overrides -->
    <link rel="stylesheet" href="{{ asset('css/koolreport-filament-theme.css') }}">

    <script>
        // Theme detection and synchronization with Filament
        function detectAndApplyTheme() {
            let theme = localStorage.getItem('theme') || 'system';
            let isDark = false;

            if (theme === 'dark') {
                isDark = true;
            } else if (theme === 'system') {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Apply theme on load
        detectAndApplyTheme();

        // Listen for system preference changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', detectAndApplyTheme);

        // Listen for theme changes from Filament
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') {
                detectAndApplyTheme();
            }
        });
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
    <div class="report-container">
        @yield('content')
    </div>

    <!-- Include KoolReport's default JS -->
    {!! \KoolReport\Core\Utility::get("ResourceManager")->publishAssetFolder("core", "js") !!}
</body>
</html>