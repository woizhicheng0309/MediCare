<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | MediCare Health Group</title>
</head>
<body>
    <h1>MediCare Health Group</h1>
    <h2>Log in</h2>

    <p id="message" role="status"></p>

    <form id="login-form" method="POST" action="{{ route('api.login') }}">
        <p>
            <label for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                autocomplete="username"
                required
            >
        </p>

        <p>
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
        </p>

        <button type="submit">Log in</button>
    </form>
<script>
    const form = document.getElementById('login-form');
    const message = document.getElementById('message');
    const button = form.querySelector('button');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        message.textContent = 'Logging in...';
        button.disabled = true;
        sessionStorage.removeItem('access_token');

        try {
            const response = await fetch("{{ route('api.login') }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                message.textContent = data.message ?? 'Login failed.';
                return;
            }

            sessionStorage.setItem('access_token', data.access_token);
            document.getElementById('password').value = '';
            message.textContent = 'Login successful.';
        } catch (error) {
            message.textContent = 'Unable to connect. Please try again.';
        } finally {
            button.disabled = false;
        }
    });
</script>
</body>
</html>