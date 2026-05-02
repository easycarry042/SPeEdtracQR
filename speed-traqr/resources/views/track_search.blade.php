<!DOCTYPE html>
<html>
<head>
    <title>Track Document - SPeED TraQR</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h1 class="text-2xl font-bold mb-6 text-center">Track Your Document</h1>
            <form action="{{ route('track.search') }}" method="GET" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tracking Number</label>
                    <input type="text" name="tracking_number" value="{{ request('tracking_number') }}" placeholder="e.g. SPD-20260425-XXXXXX" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">Track</button>
            </form>
            @if(request('tracking_number') && !isset($document))
                <div class="mt-4 text-red-600 text-center">No document found with that tracking number.</div>
            @endif
            <div class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('dashboard') }}" class="text-blue-600">← Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>