<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Дровосек</title>
    <link rel="stylesheet" href="{{ asset('css/document.css') }}">
    <style>
        .pub-header {
            max-width: 210mm;
            margin: 0 auto 16px;
            padding: 16px 12mm 0;
            border-bottom: 3px solid #f26522;
        }
        .pub-header__brand {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #f26522;
        }
        .pub-header__title {
            margin: 8px 0 0;
            font-size: 22px;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
    <header class="pub-header">
        <div class="pub-header__brand">ДРОВОСЕК · Техническая инструкция</div>
        <h1 class="pub-header__title">{{ $title }}</h1>
    </header>
    <article class="document-root">
        {!! $html !!}
    </article>
</body>
</html>
