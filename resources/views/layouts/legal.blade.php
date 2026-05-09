<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite(['resources/js/app.js'])
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background: #f4f7f6;
            color: #16302b;
        }

        .legal-shell {
            max-width: 860px;
            margin: 0 auto;
            padding: 48px 20px 80px;
        }

        .legal-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 18px 60px rgba(11, 54, 46, 0.08);
            padding: 36px;
        }

        .legal-header {
            margin-bottom: 28px;
        }

        .legal-kicker {
            display: inline-block;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #def7ec;
            color: #166534;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1, h2 {
            color: #102a25;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 2rem;
        }

        h2 {
            margin-top: 28px;
            font-size: 1.15rem;
        }

        p, li {
            line-height: 1.7;
            color: #27443e;
        }

        ul {
            padding-left: 20px;
        }

        a {
            color: #0f766e;
        }

        .legal-meta {
            color: #48655f;
            font-size: 0.95rem;
        }

        @media (max-width: 640px) {
            .legal-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <main class="legal-shell">
        <article class="legal-card">
            @yield('content')
        </article>
    </main>
</body>
</html>
