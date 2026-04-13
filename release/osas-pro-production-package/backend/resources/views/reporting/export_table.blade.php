<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        td { border: 1px solid #bbb; padding: 2px; vertical-align: top; word-wrap: break-word; }
    </style>
</head>
<body>
<table>
@foreach ($rows as $r)
    <tr>
        @foreach ($r as $c)
            <td>{{ $c }}</td>
        @endforeach
    </tr>
@endforeach
</table>
</body>
</html>
