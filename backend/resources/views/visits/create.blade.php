<h1>Record a clinic visit</h1>

<p>Enter the details of your clinic visit.</p>
@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
<form method="POST" action="{{ route('visits.store') }}">
    @csrf

    <p>
        <label for="clinic">Clinic name</label>
        <input id="clinic" name="clinic" type="text" value="{{ old("clinic") }}" required>
    </p>

    <p>
        <label for="visited_on">Visit date</label>
        <input id="visited_on" name="visited_on" type="date" value="{{ old("visited_on") }}" required>
    </p>

    <p>
        <label for="covered_amount">Covered amount (TWD)</label>
        <input
            id="covered_amount"
            name="covered_amount"
            type="number"
            min="0"
            step="1"
            value="{{ old("covered_amount") }}" 
            required
        >
    </p>

    <button type="submit">Save visit</button>
</form>
<a href="{{ route('benefits.index') }}">Back to benefits</a>