<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.x/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white p-8 rounded shadow">
        <h1 class="text-2xl font-bold mb-6">Login</h1>
        @if($errors->any())
            <div class="mb-4 text-red-600">
                {{ $errors->first() }}
            </div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label class="block mb-2 font-semibold">Email</label>
            <input type="email" name="email" required class="w-full p-2 border rounded mb-4" value="{{ old('email') }}" />

            <label class="block mb-2 font-semibold">Password</label>
            <input type="password" name="password" required class="w-full p-2 border rounded mb-4" />

            <label class="inline-flex items-center mb-4">
                <input type="checkbox" name="remember" class="mr-2" />
                Remember me
            </label>

            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Login</button>
        </form>
    </div>
</body>
</html>
