<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official IECEP Hymn — IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-full: 9999px;
            --shadow-card: 0 12px 35px -5px rgba(11, 29, 74, 0.09), 0 4px 12px -2px rgba(11, 29, 74, 0.04);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-tagline {
            font-family: 'Times New Roman', Arial, serif;
            font-style: italic;
            font-size: 1.25rem;
            color: #F8E7A2;
            margin-bottom: 0.5rem;
        }

        /* ── Main Container & Parchment Document ──────────────── */
        .hymn-wrapper {
            max-width: 840px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        .hymn-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 3.5rem 2.5rem 3rem;
            position: relative;
            overflow: hidden;
        }
        .hymn-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 50%, var(--primary) 100%);
        }

        /* Header in Document */
        .doc-header {
            text-align: center;
            margin-bottom: 2.75rem;
            position: relative;
        }
        .doc-subtitle {
            font-size: 0.78rem;
            font-weight: 700;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-bottom: 0.35rem;
        }
        .doc-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }
        .doc-divider {
            width: 60px;
            height: 2px;
            background: #D4AF37;
            margin: 1rem auto 0;
        }

        /* Lyrics Styling */
        .lyrics-body {
            max-width: 660px;
            margin: 0 auto;
            text-align: center;
        }

        .stanza-box {
            margin-bottom: 2.25rem;
        }
        .stanza-tag {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #D4AF37;
            margin-bottom: 0.65rem;
        }
        .lyric-lines {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.05rem;
            font-style: italic;
            color: #1E293B;
            line-height: 1.85;
        }

        /* Pre-Chorus Highlight */
        .pre-chorus-card {
            background: #F8FAFC;
            border-left: 3px solid #D4AF37;
            border-right: 3px solid #D4AF37;
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            margin: 2.25rem 0;
        }
        .pre-chorus-card .lyric-lines {
            font-size: 1rem;
            color: #475569;
        }

        /* Chorus Highlight */
        .chorus-card {
            background: linear-gradient(135deg, rgba(212,175,55,0.12) 0%, rgba(212,175,55,0.04) 100%);
            border: 1.5px solid rgba(212, 175, 55, 0.35);
            border-radius: var(--radius-md);
            padding: 2rem 1.5rem;
            margin: 2.5rem 0;
            position: relative;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.08);
        }
        .chorus-card .lyric-lines {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.85;
        }

        /* Bridge */
        .bridge-card {
            background: #0B1D4A;
            color: #FFFFFF;
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin: 2.25rem 0;
            border: 1px solid rgba(212, 175, 55, 0.3);
        }
        .bridge-card .stanza-tag {
            color: #F8E7A2;
        }
        .bridge-card .lyric-lines {
            color: #FFFFFF;
            font-style: normal;
            font-weight: 500;
            font-size: 1.05rem;
        }

        /* Finale */
        .finale-row {
            margin: 2.5rem 0 2rem;
            padding-top: 1.5rem;
            border-top: 1.5px solid var(--slate-200);
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.25em;
            text-transform: uppercase;
        }

        /* Action Buttons */
        .hymn-actions-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .btn-hymn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .btn-hymn-action.primary {
            background: #CC0000;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(204, 0, 0, 0.25);
        }
        .btn-hymn-action.primary:hover {
            background: #E60000;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(204, 0, 0, 0.35);
        }
        .btn-hymn-action.secondary {
            background: #F1F5F9;
            color: var(--primary);
            border: 1px solid var(--slate-200);
        }
        .btn-hymn-action.secondary:hover {
            background: #FFFFFF;
            border-color: #D4AF37;
            color: #D4AF37;
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .hymn-card { padding: 2rem 1.25rem; }
            .doc-title { font-size: 1.45rem; }
            .lyric-lines { font-size: 0.95rem; }
            .chorus-card .lyric-lines { font-size: 1.02rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                The Official <span>IECEP Hymn</span>
            </h1>
            <p class="hero-tagline">"ECEs, Let's Build The Nation"</p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Document Sheet -->
    <main class="hymn-wrapper">
        <article class="hymn-card" id="hymnDocument">
            <div class="doc-header">
                <div class="doc-subtitle">Institute of Electronics Engineers of the Philippines</div>
                <h2 class="doc-title">ECEs, Let's Build The Nation</h2>
                <div class="doc-divider"></div>
            </div>

            <div class="lyrics-body">
                <!-- Verse I -->
                <div class="stanza-box">
                    <span class="stanza-tag">Verse I</span>
                    <div class="lyric-lines">
                        It was about creating value for society…<br>
                        Recognizing electronics for our economy…<br>
                        Purpose-driven, that's who we are; we work with ingenuity,<br>
                        For nation-building and the ever-evolving industry!
                    </div>
                </div>

                <!-- Verse II -->
                <div class="stanza-box">
                    <span class="stanza-tag">Verse II</span>
                    <div class="lyric-lines">
                        Dynamic, globally – competitive is our Institute.<br>
                        Excellence is what we aim. Yes, humanity our root!<br>
                        Professionalism and prestige as our route…<br>
                        For our country and the world all of us are resolute!
                    </div>
                </div>

                <!-- Pre-Chorus -->
                <div class="pre-chorus-card">
                    <span class="stanza-tag">Pre-Chorus</span>
                    <div class="lyric-lines">
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>
                </div>

                <!-- Chorus -->
                <div class="chorus-card">
                    <div class="lyric-lines">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I . . . E . . . C . . . E . . . P !<br>
                        ECEs, let's build the nation.
                    </div>
                </div>

                <!-- Verse III -->
                <div class="stanza-box">
                    <span class="stanza-tag">Verse III</span>
                    <div class="lyric-lines">
                        Leadership and unity and progress and development…<br>
                        Camaraderie and ethics and meaningful engagements…<br>
                        Members have the chance for growth and involvement.<br>
                        New opportunities – inspiring life's moments.
                    </div>
                </div>

                <!-- Pre-Chorus Repeat -->
                <div class="pre-chorus-card">
                    <span class="stanza-tag">Pre-Chorus</span>
                    <div class="lyric-lines">
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>
                </div>

                <!-- Chorus Repeat -->
                <div class="chorus-card">
                    <div class="lyric-lines">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I . . . E . . . C . . . E . . . P !<br>
                        ECEs, let's build the nation.
                    </div>
                </div>

                <!-- Bridge -->
                <div class="bridge-card">
                    <span class="stanza-tag">Bridge</span>
                    <div class="lyric-lines">
                        Marching to the future for the next generation…<br>
                        Always open to innovation! (Innovation!)
                    </div>
                </div>

                <!-- Finale -->
                <div class="finale-row">
                    I . . . E . . . C . . . E . . . P ! ! !
                </div>

                <!-- Action Controls -->
                <div class="hymn-actions-row">
                    <a href="https://www.youtube.com/watch?v=WTHROMnxE04" target="_blank" rel="noopener noreferrer" class="btn-hymn-action primary">
                        Watch Official Video
                    </a>
                    <button type="button" class="btn-hymn-action secondary" onclick="copyHymnLyrics()">
                        <span id="copyBtnLabel">Copy Lyrics</span>
                    </button>
                </div>
            </div>
        </article>
    </main>

    <script>
        function copyHymnLyrics() {
            const lyrics = `IECEP HYMN - "ECEs, Let's Build The Nation"

Verse I
It was about creating value for society…
Recognizing electronics for our economy…
Purpose-driven, that's who we are; we work with ingenuity,
For nation-building and the ever-evolving industry!

Verse II
Dynamic, globally – competitive is our Institute.
Excellence is what we aim. Yes, humanity our root!
Professionalism and prestige as our route…
For our country and the world all of us are resolute!

Pre-Chorus
Engineering solutions that have led to many changes…
Creativity, intelligence have been our tool for ages.
Marching forward for the country – leading it to progress.
Making sure that you and me are part of all success.

Chorus
One organization, our many chapters, our one profession.
Walking hand–in–hand in the same direction…
With the help above we'll do our mission!
One organization, our many chapters, our one profession.
We are family, guided by our vision!
I....E....C....E....P!
ECEs, let's build the nation.

Bridge
Marching to the future for the next generation…
Always open to innovation! (Innovation!)

Finale
I... E... C... E... P!`;

            navigator.clipboard.writeText(lyrics).then(() => {
                const btn = document.getElementById('copyBtnLabel');
                btn.textContent = 'Lyrics Copied!';
                setTimeout(() => { btn.textContent = 'Copy Lyrics'; }, 3000);
            });
        }
    </script>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>