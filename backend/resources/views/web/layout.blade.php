<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @hasSection('noindex')
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Zarf')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Tasarım tokenları (spec §15) */
        :root {
            --bg: #F2F3F5; --surface: #FFFFFF; --ink: #17182A; --ink-2: #5F6273; --border: #DDDFE6;
            --accent: #8A1F3D; --accent-soft: #F6E7EC; --success: #23603F; --success-soft: #E3F0E8;
            --stage: #17182A;
            --font-body: 'Plus Jakarta Sans', system-ui, sans-serif; --font-display: 'Fraunces', Georgia, serif;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 400 16px/1.5 var(--font-body); }
        a { color: var(--accent); }
        .page { max-width: 480px; margin: 0 auto; padding: 24px 16px 48px; }
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 20px; margin-top: 16px; }
        .panel h2 { font: 600 20px/1.3 var(--font-display); margin: 0 0 4px; }
        .muted { color: var(--ink-2); }
        .small { font-size: 14px; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; width: 100%; padding: 12px 20px; border: 0; border-radius: 16px; background: var(--accent); color: #fff; font: 600 16px/1 var(--font-body); text-decoration: none; cursor: pointer; }
        .btn--ghost { background: var(--surface); color: var(--ink); border: 1px solid var(--border); }
        .btn + .btn { margin-top: 10px; }
        .brand { font: 600 22px/1 var(--font-display); color: var(--ink); text-decoration: none; }
        .prose h1 { font: 600 28px/1.25 var(--font-display); margin: 24px 0 8px; }
        .prose h2 { font: 600 18px/1.3 var(--font-display); margin: 24px 0 4px; }
        .notice { background: var(--accent-soft); color: var(--accent); border-radius: 14px; padding: 12px 14px; font-weight: 500; }
        .notice--ok { background: var(--success-soft); color: var(--success); }

        /* Davetiye kartı (spec §11) */
        .icard { position: relative; background: var(--paper); color: var(--ink); border-radius: 22px; padding: 14px; box-shadow: 0 12px 40px rgba(23, 24, 42, .12); overflow: hidden; }
        .icard__inner { position: relative; padding: 40px 22px 36px; text-align: center; font-family: var(--body); border-radius: 12px; }
        .icard--frame-single .icard__inner { border: 1px solid var(--frame); }
        .icard--frame-double .icard__inner { border: 1px solid var(--frame); }
        .icard--frame-double .icard__inner::after { content: ""; position: absolute; inset: 5px; border: 1px solid var(--frame); border-radius: 8px; pointer-events: none; }
        .icard__band { position: absolute; top: 0; left: 50%; width: 56px; height: 6px; margin-left: -28px; background: var(--accent); border-radius: 0 0 4px 4px; }
        .icard p, .icard h1 { margin: 0; }
        .icard__label { color: var(--accent); font-size: 12px; font-weight: 600; letter-spacing: .22em; }
        .icard__intro { color: var(--muted); font-size: 15px; margin-top: 18px !important; }
        .icard__names { font: 500 38px/1.15 var(--display); margin-top: 14px !important; overflow-wrap: anywhere; }
        .icard__message { color: var(--muted); font-size: 15px; margin-top: 14px !important; }
        .icard__dot-rule { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 24px auto; }
        .icard__dot-rule span { width: 48px; height: 1px; background: var(--accent); opacity: .6; }
        .icard__dot-rule i { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }
        .icard__spacer { height: 24px; }
        .icard__date { font: 500 24px/1.2 var(--display); }
        .icard__time { color: var(--muted); font-size: 15px; margin-top: 4px !important; }
        .icard__venue { font-weight: 600; font-size: 16px; margin-top: 18px !important; }
        .icard__address { color: var(--muted); font-size: 14px; margin-top: 2px !important; }

        /* Zarf (spec §15) */
        .env { position: relative; width: min(84vw, 340px); aspect-ratio: 3 / 2; perspective: 1200px; }
        .env__back { position: absolute; inset: 0; background: var(--env-inner); border-radius: 10px; }
        .env__letter { position: absolute; left: 6%; right: 6%; top: 7%; bottom: 6%; z-index: 1; display: flex; align-items: center; justify-content: center; padding: 12px; background: #fff; border-radius: 6px; color: var(--ink); font: 500 18px/1.2 var(--font-display); text-align: center; transition: transform .9s cubic-bezier(.2, .7, .2, 1) .45s; }
        .env__pocket { position: absolute; inset: 0; z-index: 2; background: var(--env); border-radius: 10px; clip-path: polygon(0 0, 50% 54%, 100% 0, 100% 100%, 0 100%); }
        .env__flap { position: absolute; top: 0; left: 0; right: 0; height: 58%; z-index: 3; background: var(--env-flap); border-radius: 10px 10px 0 0; clip-path: polygon(0 0, 100% 0, 50% 100%); transform-origin: top; transition: transform .6s ease, z-index 0s linear .3s; }
        .env__seal { position: absolute; left: 50%; top: 58%; z-index: 4; width: 68px; height: 68px; margin: -34px 0 0 -34px; padding: 0; border: 0; border-radius: 50%; background: var(--seal); color: #fff; font: 600 20px/1 var(--font-display); cursor: pointer; box-shadow: 0 6px 16px rgba(0, 0, 0, .3); transition: opacity .3s ease, transform .3s ease; }
        .env__seal::before { content: ""; position: absolute; inset: 6px; border: 1.5px solid rgba(255, 255, 255, .4); border-radius: 50%; }
        .env.is-open .env__seal { opacity: 0; transform: scale(.6); pointer-events: none; }
        .env.is-open .env__flap { transform: rotateX(180deg); z-index: 0; }
        .env.is-open .env__letter { transform: translateY(-58%); }
        @media (prefers-reduced-motion: reduce) { .env * { transition: none !important; } }

        .stage { min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 28px; padding: 24px 16px; background: var(--stage); color: #fff; text-align: center; }
        .stage__from { font: 500 22px/1.3 var(--font-display); margin: 0; }
        .stage__hint { color: rgba(255, 255, 255, .6); font-size: 14px; margin: 0; }
        .js .reveal { display: none; }
        .js.is-revealed .stage { display: none; }
        .js.is-revealed .reveal { display: block; animation: rise .6s ease both; }
        @keyframes rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
    </style>
    @stack('head')
</head>
<body>
    @yield('body')
    @stack('scripts')
</body>
</html>
