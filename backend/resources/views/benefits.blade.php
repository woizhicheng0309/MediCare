<h1>My healthcare benefits</h1>
@if (session('success'))
    <p>{{ session('success') }}</p>
@endif
<p>Annual limit: TWD {{ $annualLimit }}</p>
<p>Used amount: TWD {{ $usedAmount }}</p>
<p>Remaining balance: TWD {{ $remainingBalance }}</p>

@if ($remainingBalance > 0)
    <p>You still have benefits available.</p>
@else
    <p>Your annual benefits is exhausted.</p>
@endif

<h2>My clinic visits</h2>
<ul> 
    @foreach($visits as $visit)
    <li>
        {{ $visit->visited_on }} - 
        {{ $visit->clinic }} - 
        TWD {{ $visit->covered_amount_cents / 100 }} -
        {{ $visit->status }}
    </li>
    @endforeach
</ul>
<a href="{{ route('visits.create') }}">Record a clinic visit</a>