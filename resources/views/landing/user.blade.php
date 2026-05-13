<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $profile->headline ?: $user->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Cormorant+Garamond:wght@600;700&display=swap');

        :root {
            --bg: #4f0f0c;
            --surface: #5e130f;
            --surface-strong: color-mix(in srgb, var(--surface) 78%, black 22%);
            --surface-soft: color-mix(in srgb, var(--surface) 78%, white 22%);
            --text: #f8f3ea;
            --muted: #ece1cf;
            --line: rgba(250, 239, 222, 0.55);
            --button-bg: #d8d0c8;
            --button-text: #2a2520;
            --glow: rgba(255, 232, 196, 0.08);
            --shadow: 0 18px 44px rgba(8, 5, 4, 0.16);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255, 235, 208, 0.08), transparent 30%),
                linear-gradient(180deg, color-mix(in srgb, var(--bg) 88%, black 12%) 0%, var(--bg) 48%, color-mix(in srgb, var(--bg) 82%, black 18%) 100%);
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), transparent 42%, rgba(255, 255, 255, 0.015));
            opacity: .45;
        }
        .wrap { width: min(1380px, calc(100vw - 48px)); margin: 0 auto; }
        .page-shell { padding: 22px 0 72px; position: relative; z-index: 1; }

        .top-nav {
            position: sticky;
            top: 14px;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 16px 20px;
            margin-bottom: 26px;
            border: 1px solid var(--line);
            background: rgba(26, 17, 15, 0.28);
            backdrop-filter: blur(14px);
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.08);
            border-radius: 20px;
        }

        .brand-block {
            display: grid;
            gap: 6px;
        }

        .brand-kicker {
            margin: 0;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .18em;
            font-size: 11px;
        }

        .brand {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(34px, 3vw, 48px);
            margin: 0;
            line-height: .9;
            letter-spacing: .01em;
        }

        .menu {
            display: flex;
            gap: 22px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
        }

        .menu a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
            position: relative;
        }

        .menu a::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -6px;
            width: 100%;
            height: 1px;
            background: var(--text);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .25s ease;
        }

        .menu a:hover::after,
        .menu a:focus-visible::after {
            transform: scaleX(1);
        }

        .nav-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: var(--button-bg);
            color: var(--button-text);
            text-decoration: none;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-size: 12px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, .72fr);
            gap: 28px;
            align-items: stretch;
            padding: 22px 0 28px;
        }

        .hero-copy,
        .hero-side,
        .hero-cover,
        .section-panel,
        .contact-panel,
        .post-modal-dialog {
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.045) 0%, rgba(255, 255, 255, 0.018) 100%);
            box-shadow: var(--shadow);
        }

        .hero-copy {
            border-radius: 30px;
            padding: clamp(26px, 3.5vw, 42px);
            min-height: 100%;
            display: grid;
            align-content: center;
            gap: 18px;
            position: relative;
            overflow: hidden;
        }

        .hero-copy::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, var(--glow), transparent 42%);
            pointer-events: none;
        }

        .hero-side {
            border-radius: 28px;
            padding: 22px;
            display: grid;
            gap: 14px;
            align-content: start;
        }

        .hero-eyebrow {
            margin: 0;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .2em;
            font-size: 12px;
        }

        .hero h1 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(58px, 7vw, 110px);
            line-height: .88;
            max-width: 10ch;
            letter-spacing: -.03em;
        }
        .hero p {
            margin: 0;
            color: var(--muted);
            max-width: 62ch;
            font-size: clamp(15px, 1.6vw, 18px);
            line-height: 1.78;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-action-primary,
        .hero-action-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 0 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .14em;
        }

        .hero-action-primary {
            background: var(--button-bg);
            color: var(--button-text);
        }

        .hero-action-secondary {
            border: 1px solid var(--line);
            color: var(--text);
            background: rgba(255, 255, 255, 0.04);
        }

        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .hero-metric {
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        .hero-metric strong {
            display: block;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(28px, 3.2vw, 42px);
            line-height: .95;
        }

        .hero-metric span,
        .hero-side p,
        .hero-side li {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .hero-side h2,
        .side-card h3,
        .service h3,
        .post-card h3,
        .contact-card h3 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            line-height: .94;
            letter-spacing: -.02em;
        }

        .hero-side h2 {
            font-size: clamp(34px, 3vw, 46px);
        }

        .side-card {
            display: grid;
            gap: 8px;
            padding: 14px 0 0;
            border-radius: 0;
            border: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            background: transparent;
        }

        .side-card span {
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .16em;
            font-size: 11px;
        }

        .hero-cover {
            width: 100%;
            border-radius: 36px;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .hero-cover img,
        .hero-cover video,
        .hero-cover iframe {
            width: 100%;
            height: min(62vh, 720px);
            object-fit: cover;
            display: block;
            border: 0;
        }

        .section { padding: 30px 0 0; }

        .section-panel {
            border-radius: 28px;
            padding: clamp(22px, 2.8vw, 32px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 18px;
            margin-bottom: 28px;
        }

        .section-copy {
            display: grid;
            gap: 10px;
            max-width: 720px;
        }

        .section-kicker {
            margin: 0;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .18em;
            font-size: 11px;
        }

        .section h2 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(42px, 4.6vw, 76px);
            line-height: .94;
            letter-spacing: -.03em;
        }

        .section-intro {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .services {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .service {
            padding: 22px;
            text-align: left;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            display: grid;
            gap: 14px;
        }

        .service h3 {
            font-size: clamp(34px, 2.8vw, 46px);
        }
        .service p { margin: 0; color: var(--muted); line-height: 1.8; }
        .service a,
        .post-card a,
        .post-card button {
            color: var(--text);
            text-decoration: none;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-size: 12px;
        }

        .service .meta {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.09);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--muted);
            margin: 0;
        }

        .split {
            display: grid;
            grid-template-columns: minmax(280px, 340px) 1fr;
            gap: 40px;
            align-items: start;
        }

        .split img, .split video, .gallery img, .gallery video, iframe {
            width: 100%;
            border-radius: 0;
            border: 0;
            display: block;
            object-fit: cover;
        }

        .stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            align-items: start;
        }

        .stack article h4 { margin: 0 0 10px; font-family: 'Cormorant Garamond', serif; font-size: 42px; line-height: .96; }
        .stack article p { margin: 0 0 10px; color: var(--muted); }
        .stack article a { color: var(--text); text-decoration: underline; font-weight: 700; }

        .fitness-videos {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 6px;
        }

        .fitness-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 34px;
        }

        .fitness-header h2 {
            margin: 0;
            text-align: left;
        }

        .fitness-controls {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .fitness-counter {
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            min-width: 52px;
            text-align: center;
        }

        .fitness-nav {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            width: 42px;
            height: 42px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            border-radius: 999px;
        }

        .fitness-nav:disabled {
            opacity: .35;
            cursor: default;
        }

        .fitness-videos::-webkit-scrollbar {
            display: none;
        }

        .fitness-page {
            min-width: 100%;
            flex: 0 0 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            scroll-snap-align: start;
        }

        .fitness-videos .video-item {
            padding: 18px;
            display: grid;
            gap: 10px;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
        }

        .fitness-videos .video-frame {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            aspect-ratio: 16 / 10;
            overflow: hidden;
            border-radius: 20px;
        }

        .fitness-videos h4 {
            margin: 14px 0 8px;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(30px, 2.4vw, 36px);
            line-height: .96;
            text-align: left;
            letter-spacing: -.01em;
        }

        .fitness-videos p {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
            text-align: left;
            max-width: 360px;
            line-height: 1.65;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .gallery .item {
            border-top: 1px solid var(--line);
            padding-top: 12px;
        }

        .gallery .media-frame {
            aspect-ratio: 4 / 3;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--line);
        }

        .gallery .item h5 {
            margin: 10px 0 0;
            font-size: 15px;
            color: var(--muted);
            font-weight: 700;
        }

        .gallery .item p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .post-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 34px;
        }

        .post-header h2 {
            margin: 0;
            text-align: left;
        }

        .post-controls {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .post-counter {
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            min-width: 52px;
            text-align: center;
        }

        .post-nav {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            width: 42px;
            height: 42px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            border-radius: 999px;
        }

        .post-nav:disabled {
            opacity: .35;
            cursor: default;
        }

        .post-list {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 6px;
        }

        .post-list::-webkit-scrollbar {
            display: none;
        }

        .post-page {
            min-width: 100%;
            flex: 0 0 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            scroll-snap-align: start;
        }

        .post-card {
            padding: 22px;
            text-align: left;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
        }

        .post-card h3 {
            margin: 0 0 14px;
            font-size: clamp(36px, 2.8vw, 46px);
            line-height: .94;
            letter-spacing: -.01em;
        }

        .post-card p {
            margin: 0 0 18px;
            color: var(--muted);
            line-height: 1.75;
            max-width: 48ch;
        }

        .post-card button {
            appearance: none;
            border: 0;
            background: transparent;
            padding: 0;
            cursor: pointer;
            font: inherit;
        }

        .post-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 7, 6, 0.78);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 40;
        }

        .post-modal.is-open {
            display: flex;
        }

        .post-modal-dialog {
            width: min(880px, 100%);
            max-height: calc(100vh - 48px);
            overflow: auto;
            background: linear-gradient(180deg, color-mix(in srgb, var(--surface) 88%, white 12%) 0%, var(--surface) 100%);
            padding: 24px;
            border-radius: 30px;
        }

        .post-modal-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .post-modal-topbar h3 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(38px, 4vw, 58px);
            line-height: .98;
        }

        .post-modal-close {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            width: 44px;
            height: 44px;
            cursor: pointer;
            font-size: 28px;
            line-height: 1;
            border-radius: 999px;
        }

        .post-modal-excerpt {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .post-modal-content {
            color: var(--text);
            line-height: 1.85;
            white-space: pre-wrap;
        }

        .post-modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .post-modal-actions a,
        .post-modal-actions button {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            text-decoration: none;
            padding: 12px 14px;
            cursor: pointer;
            font: inherit;
            border-radius: 999px;
        }

        .post-modal-feedback {
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
            gap: 22px;
        }

        .contact-panel {
            border-radius: 28px;
            padding: clamp(22px, 2.8vw, 30px);
            display: grid;
            gap: 20px;
        }

        .contact-grid .services {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .contact-card {
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            padding: 22px;
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .contact-card h3 {
            font-size: clamp(38px, 3vw, 52px);
        }

        .contact-card p,
        .contact-card li,
        .contact-panel p {
            margin: 0;
            color: var(--muted);
            line-height: 1.8;
        }

        .contact-card a {
            display: inline-flex;
            width: fit-content;
            min-height: 46px;
            align-items: center;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text);
            text-decoration: none;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 12px;
        }

        .contact-notes {
            display: grid;
            gap: 16px;
            align-content: start;
        }

        .contact-note {
            border-top: 1px solid var(--line);
            padding-top: 14px;
        }

        .contact-note:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .empty { color: var(--muted); }

        @media (max-width: 1100px) {
            .hero,
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .services,
            .post-page {
                grid-template-columns: 1fr;
            }

            .contact-grid .services {
                grid-template-columns: 1fr;
            }

            .top-nav {
                position: static;
            }
        }

        @media (max-width: 980px) {
            .split { grid-template-columns: 1fr; }
            .stack { grid-template-columns: 1fr; }
            .fitness-page { grid-template-columns: 1fr; }
            .gallery { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hero-copy,
            .hero-side,
            .section-panel,
            .contact-panel,
            .post-modal-dialog { border-radius: 24px; }
            .hero h1 { max-width: 100%; }
            .top-nav { align-items: flex-start; flex-direction: column; }
            .fitness-header,
            .post-header,
            .section-header { gap: 14px; }
            .hero-metrics { grid-template-columns: 1fr; }
        }

        @media (max-width: 620px) {
            .wrap { width: min(100vw - 24px, 100%); }
            .gallery { grid-template-columns: 1fr; }
            .menu { gap: 14px; }
            .nav-cta,
            .hero-action-primary,
            .hero-action-secondary,
            .contact-card a { width: 100%; }
        }
    </style>
</head>
<body style="
    @php
        $preset = (string) ($profile->theme_preset ?: 'myhra_bordeaux');
        $themes = [
            'myhra_bordeaux' => ['bg' => '#4f0f0c', 'surface' => '#5e130f', 'text' => '#f8f3ea', 'muted' => '#ece1cf', 'line' => 'rgba(250, 239, 222, 0.55)', 'button_bg' => '#d8d0c8', 'button_text' => '#2a2520'],
            'sage_serene' => ['bg' => '#49594f', 'surface' => '#5d7266', 'text' => '#f2f1e9', 'muted' => '#dfdccf', 'line' => 'rgba(245, 244, 234, 0.45)', 'button_bg' => '#e2dbcd', 'button_text' => '#253028'],
            'graphite_noir' => ['bg' => '#151617', 'surface' => '#1f2123', 'text' => '#f5f4ef', 'muted' => '#cfccc4', 'line' => 'rgba(245, 244, 239, 0.35)', 'button_bg' => '#d2cec7', 'button_text' => '#1a1b1d'],
            'ocean_mist' => ['bg' => '#20354a', 'surface' => '#2f4e67', 'text' => '#f0f5f7', 'muted' => '#d6e0e7', 'line' => 'rgba(240, 245, 247, 0.4)', 'button_bg' => '#d8e2e7', 'button_text' => '#1f2f3d'],
        ];
        $theme = $themes[$preset] ?? $themes['myhra_bordeaux'];
    @endphp
    --bg: {{ $theme['bg'] }};
    --surface: {{ $theme['surface'] }};
    --text: {{ $theme['text'] }};
    --muted: {{ $theme['muted'] }};
    --line: {{ $theme['line'] }};
    --button-bg: {{ $theme['button_bg'] }};
    --button-text: {{ $theme['button_text'] }};
">
    @php
        $resolveMediaUrl = static function (?string $url, array $preferredDirectories = []): string {
            if (! is_string($url)) {
                return '';
            }

            $normalized = trim($url);

            if ($normalized === '') {
                return '';
            }

            if (
                str_starts_with($normalized, 'http://')
                || str_starts_with($normalized, 'https://')
                || str_starts_with($normalized, '//')
                || str_starts_with($normalized, 'data:')
            ) {
                return $normalized;
            }

            if (str_starts_with($normalized, '/')) {
                return $normalized;
            }

            if (str_starts_with($normalized, 'storage/')) {
                return asset($normalized);
            }

            $paths = [$normalized];

            foreach ($preferredDirectories as $directory) {
                $paths[] = trim((string) $directory, '/') . '/' . ltrim($normalized, '/');
            }

            foreach ($paths as $path) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                }
            }

            return asset('storage/' . ltrim($normalized, '/'));
        };

        $mediaCollection = collect($mediaAssets ?? []);
        $postsCollection = collect($posts?->items() ?? []);
        $coverImageUrl = $resolveMediaUrl((string) ($profile->hero_image_url ?: ''), ['landings/users/heroes', 'landings/tenants/heroes']);
        $coverMediaItem = $mediaCollection->firstWhere('media_type', 'image') ?: $mediaCollection->first();
        $coverMediaUrl = $coverImageUrl !== ''
            ? $coverImageUrl
            : $resolveMediaUrl((string) data_get($coverMediaItem, 'media_url', ''), ['landings/users/media', 'landings/tenants/professionals', 'landings/users/heroes']);
        $coverMediaType = $coverImageUrl !== '' ? 'image' : (string) data_get($coverMediaItem, 'media_type', 'image');
        $coverIsEmbeddedVideo = $coverMediaType !== 'image'
            && (
                str_contains($coverMediaUrl, 'youtube.com')
                || str_contains($coverMediaUrl, 'youtu.be')
                || str_contains($coverMediaUrl, 'vimeo.com')
            );
        $fitnessMediaItems = $mediaCollection->values();
        $fitnessVideoPages = $fitnessMediaItems->chunk(4);
        $postItems = $postsCollection
            ->map(fn ($post) => [
                'slug' => (string) $post->slug,
                'title' => (string) $post->title,
                'excerpt' => (string) ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 240)),
                'content' => trim(strip_tags((string) $post->content)),
                'share_url' => request()->url() . '?post=' . urlencode((string) $post->slug) . '#conteudos',
            ])
            ->values();
        $highlightedPostPayload = $highlightedPost ? [
            'slug' => (string) $highlightedPost->slug,
            'title' => (string) $highlightedPost->title,
            'excerpt' => (string) ($highlightedPost->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $highlightedPost->content), 240)),
            'content' => trim(strip_tags((string) $highlightedPost->content)),
            'share_url' => request()->url() . '?post=' . urlencode((string) $highlightedPost->slug) . '#conteudos',
        ] : null;
        $postPages = $postsCollection->chunk(4);
        $rawWhatsapp = trim((string) ($profile->contact_whatsapp ?? ''));
        $normalizedWhatsapp = preg_replace('/\D+/', '', $rawWhatsapp) ?? '';
        $whatsappUrl = '';
        if ($rawWhatsapp !== '') {
            $whatsappUrl = str_starts_with($rawWhatsapp, 'http://') || str_starts_with($rawWhatsapp, 'https://')
                ? $rawWhatsapp
                : ($normalizedWhatsapp !== '' ? 'https://wa.me/' . $normalizedWhatsapp : '');
        }

        $rawInstagram = trim((string) ($profile->contact_instagram ?? ''));
        $instagramHandle = ltrim($rawInstagram, '@');
        $instagramUrl = '';
        if ($rawInstagram !== '') {
            $instagramUrl = str_starts_with($rawInstagram, 'http://') || str_starts_with($rawInstagram, 'https://')
                ? $rawInstagram
                : 'https://www.instagram.com/' . trim($instagramHandle, '/') . '/';
        }
        $serviceCards = [
            [
                'label' => $profile->service_one_label ?: 'Atendimento',
                'title' => $profile->service_one_title ?: 'Mentoria Individual',
                'description' => $profile->service_one_description ?: 'Acompanhamento direto para acelerar sua evolucao com um plano claro, pratico e adaptado a sua rotina.',
                'link_label' => $profile->service_one_link_label ?: 'Saber mais',
                'link_url' => $profile->service_one_link_url ?: '#contato',
            ],
            [
                'label' => $profile->service_two_label ?: 'Conteudo',
                'title' => $profile->service_two_title ?: 'Posts Semanais',
                'description' => $profile->service_two_description ?: 'Publicacoes praticas com orientacoes, estudos de caso e direcionamentos para aplicar no dia a dia.',
                'link_label' => $profile->service_two_link_label ?: 'Saber mais',
                'link_url' => $profile->service_two_link_url ?: '#conteudos',
            ],
            [
                'label' => $profile->service_three_label ?: 'Comunicacao',
                'title' => $profile->service_three_title ?: 'Conteudos em Video',
                'description' => $profile->service_three_description ?: 'Videos objetivos com explicacoes diretas para transformar teoria em acao com consistencia.',
                'link_label' => $profile->service_three_link_label ?: 'Saber mais',
                'link_url' => $profile->service_three_link_url ?: '#contato',
            ],
        ];
    @endphp
    <div class="wrap page-shell">
        <header class="top-nav">
            <div class="brand-block">
                <p class="brand-kicker">Landing profissional</p>
                <h1 class="brand">{{ $user->name }}</h1>
            </div>
            <nav class="menu">
                <a href="#sobre">Sobre</a>
                <a href="#servicos">Servicos</a>
                <a href="#coaching">Orientacoes Fitness</a>
                <a href="#conteudos">Posts e Conteudos</a>
                <a href="#contato">Contato</a>
            </nav>
            <a class="nav-cta" href="#contato">Falar agora</a>
        </header>

        <section class="hero" id="sobre">
            <div class="hero-copy">
                <p class="hero-eyebrow">Seu guia certificado para evolucao com clareza</p>
                <h1>{{ $profile->headline ?: 'Nutricao consciente como estilo de vida' }}</h1>
                <p>{{ $profile->bio ?: 'Crie uma rotina possivel, com consistencia, autonomia e estrategias praticas para evoluir com equilibrio.' }}</p>
                <div class="hero-actions">
                    <a class="hero-action-primary" href="#contato">Agendar atendimento</a>
                    <a class="hero-action-secondary" href="#conteudos">Ver conteudos</a>
                </div>
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <strong>1:1</strong>
                        <span>Atendimento focado na sua rotina real.</span>
                    </div>
                    <div class="hero-metric">
                        <strong>360</strong>
                        <span>Conteudo, acompanhamento e repertorio pratico.</span>
                    </div>
                    <div class="hero-metric">
                        <strong>Consistencia</strong>
                        <span>Plano claro para transformar orientacao em habito.</span>
                    </div>
                </div>
            </div>
            <aside class="hero-side" aria-label="Destaques do perfil">
                <h2>Estrutura pensada para valor percebido alto.</h2>
                <p>{{ $profile->skills ?: 'Nutricao consciente, organizacao alimentar e evolucao progressiva com acompanhamento humano.' }}</p>
                <div class="side-card">
                    <span>Posicionamento</span>
                    <h3>Imagem premium com conteudo util</h3>
                    <p>Uma presenca digital mais forte, com narrativa clara, provas visuais e chamadas objetivas.</p>
                </div>
                <div class="side-card">
                    <span>Contato direto</span>
                    <p>WhatsApp e Instagram integrados para conversao sem friccao.</p>
                </div>
            </aside>
        </section>

        <section class="hero-cover" aria-label="Imagem principal">
            @if($coverMediaUrl !== '')
                @if($coverMediaType === 'image')
                    <img src="{{ $coverMediaUrl }}" alt="Imagem em destaque">
                @elseif($coverIsEmbeddedVideo)
                    <iframe src="{{ $coverMediaUrl }}" allowfullscreen></iframe>
                @else
                    <video src="{{ $coverMediaUrl }}" controls preload="metadata"></video>
                @endif
            @else
                <div class="wrap" style="padding: 18px 0;">
                    <p class="empty">Adicione uma imagem principal para destacar esta area.</p>
                </div>
            @endif
        </section>

        <section class="section section-line" id="servicos">
            <div class="section-panel">
                <div class="section-header">
                    <div class="section-copy">
                        <p class="section-kicker">Oferta principal</p>
                        <h2>{{ $profile->service_section_title ?: 'Atendimento, conteudo e video' }}</h2>
                        <p class="section-intro">Cada bloco ajuda a explicar seu metodo com mais clareza, reforcando percepcao de autoridade e facilitando a decisao do visitante.</p>
                    </div>
                </div>
                <div class="services">
                    @foreach($serviceCards as $serviceCard)
                        <article class="service">
                            <p class="meta">{{ $serviceCard['label'] }}</p>
                            <h3>{{ $serviceCard['title'] }}</h3>
                            <p>{{ $serviceCard['description'] }}</p>
                            <a href="{{ $serviceCard['link_url'] }}">{{ $serviceCard['link_label'] }}</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section section-line" id="coaching">
            <div class="section-panel">
                <div class="fitness-header">
                    <div class="section-copy">
                        <p class="section-kicker">Biblioteca visual</p>
                        <h2>Orientacoes Fitness</h2>
                        <p class="section-intro">Use este espaco para transformar seu repertorio em autoridade visivel, com materiais que explicam seu metodo e sustentam a conversao.</p>
                    </div>
                    @if($fitnessVideoPages->count() > 1)
                        <div class="fitness-controls">
                            <button type="button" class="fitness-nav" id="fitness-nav-prev" aria-label="Pagina anterior">‹</button>
                            <span class="fitness-counter" id="fitness-page-indicator">1/{{ $fitnessVideoPages->count() }}</span>
                            <button type="button" class="fitness-nav" id="fitness-nav-next" aria-label="Proxima pagina">›</button>
                        </div>
                    @endif
                </div>

                <div class="fitness-videos" id="fitness-videos-track">
                    @forelse($fitnessVideoPages as $fitnessPage)
                        <div class="fitness-page">
                            @foreach($fitnessPage as $video)
                                <article class="video-item">
                                    <div class="video-frame">
                                        @php
                                            $fitnessVideoUrl = $resolveMediaUrl((string) $video->media_url, ['landings/users/media', 'landings/tenants/professionals']);
                                            $isEmbeddedFitnessVideo = str_contains($fitnessVideoUrl, 'youtube.com')
                                                || str_contains($fitnessVideoUrl, 'youtu.be')
                                                || str_contains($fitnessVideoUrl, 'vimeo.com');
                                        @endphp
                                        @if($video->media_type === 'image')
                                            <img src="{{ $fitnessVideoUrl }}" alt="{{ $video->title ?: 'Imagem em destaque' }}">
                                        @elseif($isEmbeddedFitnessVideo)
                                            <iframe src="{{ $fitnessVideoUrl }}" allowfullscreen></iframe>
                                        @else
                                            <video src="{{ $fitnessVideoUrl }}" controls preload="metadata"></video>
                                        @endif
                                    </div>
                                    <h4>{{ $video->title ?: 'Video em destaque' }}</h4>
                                    <p>Conteudo publicado para orientar rotina, performance e tomada de decisao com mais clareza.</p>
                                </article>
                            @endforeach
                        </div>
                    @empty
                        <p class="empty">Nenhuma midia cadastrada para orientacoes fitness.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section section-line" id="conteudos">
            <div class="section-panel">
                <div class="post-header">
                    <div class="section-copy">
                        <p class="section-kicker">Autoridade editorial</p>
                        <h2>Posts e conteudos</h2>
                        <p class="section-intro">Uma camada de conteudo bem apresentada faz a landing parecer mais madura, aumenta permanencia e sustenta melhor sua proposta.</p>
                    </div>
                    @if($postPages->count() > 1)
                        <div class="post-controls">
                            <button type="button" class="post-nav" id="post-nav-prev" aria-label="Pagina anterior de posts">‹</button>
                            <span class="post-counter" id="post-page-indicator">1/{{ $postPages->count() }}</span>
                            <button type="button" class="post-nav" id="post-nav-next" aria-label="Proxima pagina de posts">›</button>
                        </div>
                    @endif
                </div>

                <div class="post-list" id="post-list-track">
                    @forelse($postPages as $postPage)
                        <div class="post-page">
                            @foreach($postPage as $post)
                                <article class="post-card">
                                    <h3>{{ $post->title }}</h3>
                                    <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 240) }}</p>
                                    <button
                                        type="button"
                                        data-post-trigger
                                        data-post-slug="{{ $post->slug }}"
                                    >Continuar leitura</button>
                                </article>
                            @endforeach
                        </div>
                    @empty
                        <p class="empty">Sem posts publicados.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section section-line" id="contato">
            <div class="contact-panel">
                <div class="section-copy">
                    <p class="section-kicker">Proximo passo</p>
                    <h2>Contato</h2>
                    <p class="section-intro">Um fechamento mais forte ajuda a transformar interesse em conversa. O bloco abaixo reforca disponibilidade, especialidade e caminho de contato.</p>
                </div>
                <div class="contact-grid">
                    <div class="services">
                        <article class="contact-card">
                            <h3>WhatsApp</h3>
                            <p>{{ $rawWhatsapp !== '' ? 'Converse direto pelo WhatsApp para agendar atendimento e tirar duvidas.' : 'Adicione um WhatsApp na configuracao da landing para exibir contato direto.' }}</p>
                            @if($whatsappUrl !== '')
                                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Abrir WhatsApp</a>
                            @endif
                        </article>
                        <article class="contact-card">
                            <h3>Instagram</h3>
                            <p>{{ $rawInstagram !== '' ? 'Acompanhe o perfil no Instagram para conteudos, novidades e proximos passos.' : 'Adicione um Instagram na configuracao da landing para exibir o perfil social.' }}</p>
                            @if($instagramUrl !== '')
                                <a href="{{ $instagramUrl }}" target="_blank" rel="noopener">Abrir Instagram</a>
                            @endif
                        </article>
                    </div>
                    <aside class="contact-notes">
                        <div class="contact-note">
                            <p>Uma landing profissional precisa encerrar com uma promessa clara e um caminho facil para contato. Este bloco entrega esse fechamento sem perder sobriedade.</p>
                        </div>
                        <div class="contact-note">
                            <p>Se quiser mais resultado comercial, priorize um WhatsApp ativo, headline objetiva e uma imagem principal forte.</p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>

    <div class="post-modal" id="post-modal" aria-hidden="true">
        <div class="post-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="post-modal-title">
            <div class="post-modal-topbar">
                <div>
                    <h3 id="post-modal-title"></h3>
                    <p class="post-modal-excerpt" id="post-modal-excerpt"></p>
                </div>
                <button type="button" class="post-modal-close" id="post-modal-close" aria-label="Fechar post">×</button>
            </div>

            <div class="post-modal-content" id="post-modal-content"></div>

            <div class="post-modal-actions">
                <a id="post-modal-whatsapp" href="#" target="_blank" rel="noopener">Compartilhar no WhatsApp</a>
                <button type="button" id="post-modal-instagram">Compartilhar no Instagram</button>
                <button type="button" id="post-modal-copy-link">Copiar link do post</button>
            </div>

            <div class="post-modal-feedback" id="post-modal-feedback"></div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('post-modal');
            const modalTitle = document.getElementById('post-modal-title');
            const modalExcerpt = document.getElementById('post-modal-excerpt');
            const modalContent = document.getElementById('post-modal-content');
            const modalWhatsapp = document.getElementById('post-modal-whatsapp');
            const modalInstagram = document.getElementById('post-modal-instagram');
            const modalCopyLink = document.getElementById('post-modal-copy-link');
            const modalClose = document.getElementById('post-modal-close');
            const modalFeedback = document.getElementById('post-modal-feedback');
            const fitnessTrack = document.getElementById('fitness-videos-track');
            const fitnessPages = Array.from(document.querySelectorAll('.fitness-page'));
            const fitnessPrev = document.getElementById('fitness-nav-prev');
            const fitnessNext = document.getElementById('fitness-nav-next');
            const fitnessIndicator = document.getElementById('fitness-page-indicator');
            const postTrack = document.getElementById('post-list-track');
            const postPages = Array.from(document.querySelectorAll('.post-page'));
            const postPrev = document.getElementById('post-nav-prev');
            const postNext = document.getElementById('post-nav-next');
            const postIndicator = document.getElementById('post-page-indicator');
            const postTriggers = Array.from(document.querySelectorAll('[data-post-trigger]'));
            const posts = @json($postItems);
            const highlightedPost = @json($highlightedPostPayload);

            const postsBySlug = new Map(posts.map((post) => [post.slug, post]));
            let activePost = null;
            let activeFitnessPage = 0;
            let activePostPage = 0;

            const setFeedback = (message) => {
                if (modalFeedback) {
                    modalFeedback.textContent = message;
                }
            };

            const buildShareText = (post) => `${post.title}\n\n${post.excerpt}\n\n${post.share_url}`;

            const openModal = (post) => {
                if (!modal || !modalTitle || !modalExcerpt || !modalContent || !modalWhatsapp) {
                    return;
                }

                activePost = post;
                modalTitle.textContent = post.title;
                modalExcerpt.textContent = post.excerpt;
                modalContent.textContent = post.content;
                modalWhatsapp.href = `https://wa.me/?text=${encodeURIComponent(buildShareText(post))}`;
                setFeedback('');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');

                const url = new URL(window.location.href);
                url.searchParams.set('post', post.slug);
                url.hash = 'conteudos';
                window.history.replaceState({}, '', url.toString());
            };

            const closeModal = () => {
                if (!modal) {
                    return;
                }

                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                activePost = null;
                setFeedback('');

                const url = new URL(window.location.href);
                url.searchParams.delete('post');
                url.hash = 'conteudos';
                window.history.replaceState({}, '', url.toString());
            };

            const copyText = async (text) => {
                await navigator.clipboard.writeText(text);
            };

            const updateFitnessControls = () => {
                if (!fitnessTrack || fitnessPages.length <= 1) {
                    return;
                }

                const trackWidth = fitnessTrack.clientWidth || 1;
                activeFitnessPage = Math.round(fitnessTrack.scrollLeft / trackWidth);

                if (fitnessIndicator) {
                    fitnessIndicator.textContent = `${activeFitnessPage + 1}/${fitnessPages.length}`;
                }

                if (fitnessPrev) {
                    fitnessPrev.disabled = activeFitnessPage <= 0;
                }

                if (fitnessNext) {
                    fitnessNext.disabled = activeFitnessPage >= fitnessPages.length - 1;
                }
            };

            const scrollFitnessToPage = (pageIndex) => {
                if (!fitnessTrack) {
                    return;
                }

                const normalizedPage = Math.max(0, Math.min(pageIndex, fitnessPages.length - 1));
                fitnessTrack.scrollTo({
                    left: normalizedPage * fitnessTrack.clientWidth,
                    behavior: 'smooth',
                });
            };

            const updatePostControls = () => {
                if (!postTrack || postPages.length <= 1) {
                    return;
                }

                const trackWidth = postTrack.clientWidth || 1;
                activePostPage = Math.round(postTrack.scrollLeft / trackWidth);

                if (postIndicator) {
                    postIndicator.textContent = `${activePostPage + 1}/${postPages.length}`;
                }

                if (postPrev) {
                    postPrev.disabled = activePostPage <= 0;
                }

                if (postNext) {
                    postNext.disabled = activePostPage >= postPages.length - 1;
                }
            };

            const scrollPostToPage = (pageIndex) => {
                if (!postTrack) {
                    return;
                }

                const normalizedPage = Math.max(0, Math.min(pageIndex, postPages.length - 1));
                postTrack.scrollTo({
                    left: normalizedPage * postTrack.clientWidth,
                    behavior: 'smooth',
                });
            };

            postTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const slug = trigger.getAttribute('data-post-slug');
                    const post = slug ? postsBySlug.get(slug) : null;
                    if (post) {
                        openModal(post);
                    }
                });
            });

            modalClose?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
                    closeModal();
                }
            });

            modalCopyLink?.addEventListener('click', async () => {
                if (!activePost) {
                    return;
                }

                try {
                    await copyText(activePost.share_url);
                    setFeedback('Link do post copiado.');
                } catch (error) {
                    setFeedback('Nao foi possivel copiar o link automaticamente.');
                }
            });

            modalInstagram?.addEventListener('click', async () => {
                if (!activePost) {
                    return;
                }

                try {
                    await copyText(buildShareText(activePost));
                    setFeedback('Texto e link copiados para compartilhar no Instagram.');
                } catch (error) {
                    setFeedback('Nao foi possivel copiar o texto para Instagram automaticamente.');
                }

                window.open('https://www.instagram.com/', '_blank', 'noopener');
            });

            fitnessPrev?.addEventListener('click', () => {
                scrollFitnessToPage(activeFitnessPage - 1);
            });

            fitnessNext?.addEventListener('click', () => {
                scrollFitnessToPage(activeFitnessPage + 1);
            });

            postPrev?.addEventListener('click', () => {
                scrollPostToPage(activePostPage - 1);
            });

            postNext?.addEventListener('click', () => {
                scrollPostToPage(activePostPage + 1);
            });

            fitnessTrack?.addEventListener('scroll', () => {
                window.requestAnimationFrame(updateFitnessControls);
            });

            postTrack?.addEventListener('scroll', () => {
                window.requestAnimationFrame(updatePostControls);
            });

            window.addEventListener('resize', updateFitnessControls);
            window.addEventListener('resize', updatePostControls);

            if (highlightedPost) {
                postsBySlug.set(highlightedPost.slug, highlightedPost);
                openModal(highlightedPost);
            }

            updateFitnessControls();
            updatePostControls();
        })();
    </script>
</body>
</html>
