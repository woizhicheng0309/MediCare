<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Benefits | MediCare Health Group</title>
</head>
<body>
    <h1>My healthcare benefits</h1>
    <p id="message" role="status">Loading...</p>

    <section id="benefit-details" hidden>
        <p>Plan: <span id="plan-name"></span></p>
        <p>Annual limit: TWD <span id="annual-limit"></span></p>
        <p>Used amount: TWD <span id="used-amount"></span></p>
        <p>Remaining balance: TWD <span id="remaining-balance"></span></p>
    </section>

    <script>
        async function loadBenefits() {
            const token = sessionStorage.getItem('access_token');
            const message = document.getElementById('message');

            if (!token) {
                window.location.replace("{{ route('login') }}");
                return;
            }

            try {
                const response = await fetch("{{ route('api.benefits.summary') }}", {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                });

                if (response.status === 401) {
                    sessionStorage.removeItem('access_token');
                    window.location.replace("{{ route('login') }}");
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    message.textContent = data.message ?? 'Unable to load benefits.';
                    return;
                }

                document.getElementById('plan-name').textContent = data.plan_name;
                document.getElementById('annual-limit').textContent = data.annual_limit;
                document.getElementById('used-amount').textContent = data.used_amount;
                document.getElementById('remaining-balance').textContent = data.remaining_balance;

                document.getElementById('benefit-details').hidden = false;
                message.textContent = '';
            } catch (error) {
                message.textContent = 'Unable to connect. Please try again.';
            }
        }

        loadBenefits();
    </script>
</body>
</html>