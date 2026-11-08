@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $branding->name() }}</title>
    <style>:root { --brand-primary: {{ $branding->primaryColor() }}; --brand-sidebar: {{ $branding->sidebarColor() }}; }</style>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center px-4">
    <main class="w-full max-w-md text-center">
        <x-brand-mark size="h-14 w-14" class="mx-auto mb-6" />
        {{ $slot }}
    </main>
</body>
</html>
