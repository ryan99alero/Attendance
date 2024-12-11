<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punches</title>

    <!-- Filament's CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <!-- Optional custom styles -->
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #1a1a1a;
            color: white;
        }

        h1 {
            color: white;
            margin-bottom: 1rem;
        }

        body {
            background-color: #121212;
            color: white;
            font-family: Arial, sans-serif;
            margin: 2rem;
        }

        .table-container {
            padding: 1rem;
            background-color: #1e1e1e;
            border-radius: 8px;
        }
    </style>
</head>

<body>
<div class="table-container">
    <h1>Punches</h1>
    <table>
        <thead>
        <tr>
            <th>Employee</th>
            <th>Date</th>
            <th>Clock In</th>
            <th>Lunch Start</th>
            <th>Lunch Stop</th>
            <th>Clock Out</th>
        </tr>
        </thead>
        <tbody>
        @forelse($groupedPunches as $punch)
            <tr>
                <td>{{ $punch->employee_id }}</td>
                <td>{{ $punch->punch_date }}</td>
                <td>{{ $punch->ClockIn ?? 'N/A' }}</td>
                <td>{{ $punch->LunchStart ?? 'N/A' }}</td>
                <td>{{ $punch->LunchStop ?? 'N/A' }}</td>
                <td>{{ $punch->ClockOut ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No punches available for this pay period.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<!-- Filament's JS -->
<script src="https://cdn.jsdelivr.net/npm/@filamentphp/scripts/dist/filament.js"></script>
</body>

</html>
