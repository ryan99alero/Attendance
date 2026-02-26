<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Error') - {{ config('app.name', 'Attend') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
        }
    </script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-16 sm:px-6 lg:px-8">
        <div class="w-full max-w-lg">
            {{-- Logo/Brand --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ config('app.name', 'Attend') }}
                </h1>
            </div>

            {{-- Error Card --}}
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden">
                {{-- Header with status code --}}
                <div class="px-6 py-8 bg-gradient-to-r from-red-500 to-red-600 text-center">
                    <span class="text-6xl font-bold text-white opacity-80">
                        @yield('code', '500')
                    </span>
                    <h2 class="mt-2 text-xl font-semibold text-white">
                        @yield('title', 'Error')
                    </h2>
                </div>

                {{-- Content --}}
                <div class="px-6 py-8">
                    <p class="text-gray-600 dark:text-gray-300 text-center mb-6">
                        @yield('message', 'An unexpected error occurred.')
                    </p>

                    {{-- Reference Code --}}
                    @if(isset($referenceCode))
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-2">
                            Error Reference Code
                        </p>
                        <div class="flex items-center justify-center gap-2">
                            <code id="reference-code" class="text-lg font-mono font-semibold text-gray-900 dark:text-white bg-gray-200 dark:bg-gray-600 px-3 py-1 rounded">
                                {{ $referenceCode }}
                            </code>
                            <button
                                onclick="copyReferenceCode()"
                                class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                                title="Copy to clipboard"
                            >
                                <svg id="copy-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg id="check-icon" class="w-5 h-5 hidden text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
                            Please provide this code when contacting support
                        </p>
                    </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="{{ url()->previous() }}"
                           class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Go Back
                        </a>
                        <a href="{{ url('/') }}"
                           class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Home
                        </a>
                    </div>
                </div>

                {{-- Footer with timestamp --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                        {{ now()->format('F j, Y \a\t g:i A T') }}
                    </p>
                </div>
            </div>

            {{-- Support Link --}}
            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Need help? <a href="mailto:support@example.com" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Contact Support</a>
            </p>
        </div>
    </div>

    <script>
        function copyReferenceCode() {
            const code = document.getElementById('reference-code').textContent.trim();
            navigator.clipboard.writeText(code).then(() => {
                document.getElementById('copy-icon').classList.add('hidden');
                document.getElementById('check-icon').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('copy-icon').classList.remove('hidden');
                    document.getElementById('check-icon').classList.add('hidden');
                }, 2000);
            });
        }
    </script>
</body>
</html>
