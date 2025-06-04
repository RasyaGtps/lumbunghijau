<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.x/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow p-4 flex justify-between items-center">
        <div class="text-xl font-bold">Dashboard</div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-red-600 hover:underline">Logout</button>
        </form>
    </nav>

    <main class="p-6">
        <h2 class="text-2xl font-semibold mb-4">Welcome, {{ auth()->user()->name }}!</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-4 rounded shadow">
                <h3 class="text-lg font-semibold">Total Users</h3>
                <p class="text-3xl">{{ \App\Models\User::count() }}</p>
            </div>
            <div class="bg-white p-4 rounded shadow">
                <h3 class="text-lg font-semibold">Total Transactions</h3>
                <p class="text-3xl">{{ \App\Models\Transaction::count() }}</p>
            </div>
            <div class="bg-white p-4 rounded shadow">
                <h3 class="text-lg font-semibold">Total Balance History</h3>
                <p class="text-3xl">{{ \App\Models\BalanceHistory::count() }}</p>
            </div>
        </div>
    </main>
</body>
</html>
