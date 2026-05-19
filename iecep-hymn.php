<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IECEP Hymn - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <!-- High-End Typography Pairing -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0B1D4A;
            --primary-main: #1A3A8A;
            --accent-gold: #C5A059;
            --accent-light: #F8F6F0;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --white: #FFFFFF;
            --shadow-sm: 0 5px 20px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 30px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #F7F9FC;
            color: var(--text-main);
            line-height: 1.5;
        }

        /* --- HERO SECTION (compact) --- */
        .page-hero {
            position: relative;
            background: var(--primary-dark);
            color: var(--white);
            padding: 100px 0 60px 0;
            text-align: center;
            overflow: hidden;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.1;
            mix-blend-mode: overlay;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 700px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .hero-content h1 {
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }
        .hero-content p {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* --- HYMN DOCUMENT (compact, card-like) --- */
        main {
            padding: 50px 0 80px 0;
            display: flex;
            justify-content: center;
        }

        .hymn-document {
            background: var(--white);
            max-width: 800px;
            width: 90%;
            padding: 2.5rem 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            position: relative;
            border-top: 5px solid var(--accent-gold);
        }

        .document-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .document-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 0.3rem;
        }
        .document-divider {
            width: 50px;
            height: 2px;
            background: var(--accent-gold);
            margin: 1rem auto;
        }

        .lyrics-container {
            max-width: 650px;
            margin: 0 auto;
        }

        /* Verse styling */
        .stanza {
            margin-bottom: 2rem;
        }
        .stanza-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.75rem;
            display: block;
        }
        .lyric-text {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            color: var(--text-main);
            line-height: 1.7;
            font-style: italic;
        }

        /* Pre-chorus */
        .pre-chorus-block {
            margin: 2rem 0;
            padding: 0.8rem 1rem;
            border-left: 2px solid var(--accent-gold);
            background: var(--accent-light);
            border-radius: 8px;
        }
        .pre-chorus-text {
            font-family: 'Playfair Display', serif;
            font-size: 0.95rem;
            color: var(--text-muted);
            font-style: italic;
            line-height: 1.6;
        }

        /* Chorus block – more refined */
        .chorus-block {
            margin: 2.5rem 0;
            padding: 1.8rem 1.5rem;
            background-color: var(--accent-light);
            border-radius: 12px;
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        .chorus-label {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent-gold);
            color: var(--primary-dark);
            padding: 2px 14px;
            font-size: 0.65rem;
            font-weight: 700;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chorus-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
            line-height: 1.6;
            text-align: center;
        }

        /* Bridge – modern card */
        .bridge-block {
            margin: 2.5rem 0;
            padding: 1.5rem;
            background: var(--primary-dark);
            color: var(--white);
            border-radius: 16px;
            text-align: center;
        }
        .bridge-block .stanza-label {
            color: var(--accent-gold);
            margin-bottom: 0.5rem;
        }
        .bridge-text {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Finale */
        .finale-block {
            margin-top: 2.5rem;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-dark);
            text-align: center;
            letter-spacing: 4px;
            text-transform: uppercase;
            padding: 1rem 0;
            border-top: 1px solid #E5E7EB;
        }

        /* YouTube Link – professional button */
        .video-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 2rem;
            background: var(--primary-main);
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 8px 18px;
            border-radius: 40px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .video-link:hover {
            background: var(--accent-gold);
            color: var(--primary-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .hymn-document { padding: 1.5rem; }
            .document-title { font-size: 1.3rem; }
            .chorus-text { font-size: 0.95rem; }
            .lyric-text { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <header class="page-hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>IECEP Hymn</h1>
            <p>"ECEs, Let's Build The Nation"</p>
        </div>
    </header>

    <main>
        <div class="hymn-document">
            <div class="document-header">
                <h2 class="document-title">The Official Hymn</h2>
                <div class="document-divider"></div>
            </div>

            <div class="lyrics-container">
                <!-- Verse I -->
                <div class="stanza">
                    <span class="stanza-label">Verse I</span>
                    <div class="lyric-text">
                        It was about creating value for society…<br>
                        Recognizing electronics for our economy…<br>
                        Purpose-driven, that's who we are; we work with ingenuity,<br>
                        For nation-building and the ever-evolving industry!
                    </div>
                </div>

                <!-- Verse II -->
                <div class="stanza">
                    <span class="stanza-label">Verse II</span>
                    <div class="lyric-text">
                        Dynamic, globally – competitive is our Institute.<br>
                        Excellence is what we aim. Yes, humanity our root!<br>
                        Professionalism and prestige as our route…<br>
                        For our country and the world all of us are resolute!
                    </div>
                </div>

                <!-- Pre-Chorus -->
                <div class="pre-chorus-block">
                    <span class="stanza-label">Pre-Chorus</span>
                    <div class="pre-chorus-text">
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>
                </div>

                <!-- Chorus -->
                <div class="chorus-block">
                    <span class="chorus-label">Chorus</span>
                    <div class="chorus-text">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>
                </div>

                <!-- Verse III -->
                <div class="stanza">
                    <span class="stanza-label">Verse III</span>
                    <div class="lyric-text">
                        Leadership and unity and progress and development…<br>
                        Camaraderie and ethics and meaningful engagements…<br>
                        Members have the chance for growth and involvement.<br>
                        New opportunities – inspiring life's moments.
                    </div>
                </div>

                <!-- Pre-Chorus repeat -->
                <div class="pre-chorus-block">
                    <span class="stanza-label">Pre-Chorus</span>
                    <div class="pre-chorus-text">
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>
                </div>

                <!-- Chorus repeat -->
                <div class="chorus-block">
                    <span class="chorus-label">Chorus</span>
                    <div class="chorus-text">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>
                </div>

                <!-- Bridge -->
                <div class="bridge-block">
                    <span class="stanza-label">Bridge</span>
                    <div class="bridge-text">
                        Marching to the future for the next generation…<br>
                        Always open to innovation! (Innovation!)
                    </div>
                </div>

                <!-- Final Chorus -->
                <div class="chorus-block">
                    <span class="chorus-label">Chorus</span>
                    <div class="chorus-text">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>
                </div>

                <!-- Finale -->
                <div class="finale-block">
                    I . . . E . . . C . . . E . . . P ! ! !
                </div>

                <!-- Footer Action -->
                <div style="text-align: center;">
                    <a href="https://www.youtube.com/watch?v=WTHROMnxE04" target="_blank" class="video-link">
                        <i class="fab fa-youtube"></i> Watch Official Video
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>