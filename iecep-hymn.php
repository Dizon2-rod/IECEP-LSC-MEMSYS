<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IECEP Hymn - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Times+New+Roman:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0B1D4A;
            --accent: #D4AF37;
            --neutral-100: #F8FAFC;
            --neutral-900: #0F172A;
            --white: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--neutral-100);
            color: var(--neutral-900);
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: var(--primary);
            color: var(--white);
            padding: 3rem 0;
            text-align: center;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        header p {
            font-size: 1.25rem;
            opacity: 0.9;
            font-style: italic;
        }

        main {
            padding: 4rem 0;
        }

        .hymn-card {
            background: var(--white);
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--accent);
        }

        .hymn-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--neutral-100);
        }

        .hymn-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .hymn-subtitle {
            color: var(--accent);
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }

        .hymn-lyrics {
            font-family: 'Times New Roman', serif;
            font-size: 1.1rem;
            line-height: 2;
            color: var(--neutral-900);
        }

        .stanza {
            margin-bottom: 2rem;
        }

        .stanza-number {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .pre-chorus {
            color: var(--neutral-700);
            font-style: italic;
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--neutral-100);
            border-radius: 8px;
            border-left: 3px solid var(--accent);
        }

        .chorus {
            color: var(--primary);
            font-weight: 700;
            margin: 2.5rem 0;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--neutral-100) 0%, var(--white) 100%);
            border-radius: 8px;
            border: 1px solid var(--accent);
            position: relative;
        }

        .chorus::before {
            content: 'CHORUS';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
        }

        .bridge {
            color: var(--white);
            font-weight: 600;
            margin: 2.5rem 0;
            padding: 1.5rem;
            background: var(--primary);
            border-radius: 8px;
            text-align: center;
        }

        .finale {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
            text-align: center;
            margin-top: 2.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%);
            border-radius: 8px;
            letter-spacing: 0.2em;
        }

        footer {
            text-align: center;
            padding: 2rem 0;
            color: var(--neutral-700);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 2rem;
            }
            
            .hymn-card {
                padding: 2rem;
            }
            
            .hymn-lyrics {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>IECEP Hymn</h1>
            <p>ECEs, Let's Build The Nation</p>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="hymn-card">
                <div class="hymn-header">
                    <h2 class="hymn-title">IECEP HYMN</h2>
                    <p class="hymn-subtitle">ECEs, Let's Build The Nation</p>
                </div>

                <div class="hymn-lyrics">
                    <div class="stanza">
                        <div class="stanza-number">Verse I</div>
                        It was about creating value for society…<br>
                        Recognizing electronics for our economy…<br>
                        Purpose-driven, that's who we are; we work with ingenuity,<br>
                        For nation-building and the ever-evolving industry!
                    </div>

                    <div class="stanza">
                        <div class="stanza-number">Verse II</div>
                        Dynamic, globally – competitive is our Institute.<br>
                        Excellence is what we aim. Yes, humanity our root!<br>
                        Professionalism and prestige as our route…<br>
                        For our country and the world all of us are resolute!
                    </div>

                    <div class="pre-chorus">
                        <strong>Pre-Chorus:</strong><br>
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>

                    <div class="chorus">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>

                    <div class="stanza">
                        <div class="stanza-number">Verse III</div>
                        Leadership and unity and progress and development…<br>
                        Camaraderie and ethics and meaningful engagements…<br>
                        Members have the chance for growth and involvement.<br>
                        New opportunities – inspiring life's moments.
                    </div>

                    <div class="pre-chorus">
                        <strong>Pre-Chorus:</strong><br>
                        Engineering solutions that have led to many changes…<br>
                        Creativity, intelligence have been our tool for ages.<br>
                        Marching forward for the country – leading it to progress.<br>
                        Making sure that you and me are part of all success.
                    </div>

                    <div class="chorus">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>

                    <div class="bridge">
                        <strong>Bridge:</strong><br>
                        Marching to the future for the next generation…<br>
                        Always open to innovation! (Innovation!)
                    </div>

                    <div class="chorus">
                        One organization, our many chapters, our one profession.<br>
                        Walking hand–in–hand in the same direction…<br>
                        With the help above we'll do our mission!<br>
                        One organization, our many chapters, our one profession.<br>
                        We are family, guided by our vision!<br>
                        I….E....C....E....P!<br>
                        ECEs, let's build the nation.
                    </div>

                    <div class="finale">
                        I . . . E . . . C . . . E . . . P ! ! !
                    </div>
                    <div class="container">
                        <p>
                            <a href="https://www.youtube.com/watch?v=WTHROMnxE04" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                <i class="fab fa-youtube"></i> Watch Official IECEP Hymn Video
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
           <!-- Footer -->
    <?php include __DIR__ . '/includes/footer-new.php'; ?>
        </div>
    </footer>
</body>
</html>
