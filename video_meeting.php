    <?php
    session_start();
    include('config.php');

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $meeting_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $meetingTopic = isset($_GET['topic']) ? urldecode($_GET['topic']) : "Scout Meeting";
    $is_private = false; // Default to public

    // Log the activity and fetch meeting settings
    if ($meeting_id > 0) {
        // Fetch meeting privacy setting
        $stmt = mysqli_prepare($conn, "SELECT is_private, allowed_role, created_by FROM meetings WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $meeting_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($meeting = mysqli_fetch_assoc($result)) {
            $is_private = (bool)$meeting['is_private'];
            $allowed_role = $meeting['allowed_role'] ?? 'all';
            
            // Check permissions (Admins and Creator are always allowed)
            if ($allowed_role !== 'all' && $_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $meeting['created_by']) {
                if ($allowed_role === 'specific') {
                    // Check if user is in the allowed list
                    $check_specific = mysqli_query($conn, "SELECT 1 FROM meeting_allowed_users WHERE meeting_id = $meeting_id AND user_id = " . $_SESSION['user_id']);
                    if (mysqli_num_rows($check_specific) == 0) {
                        $_SESSION['error'] = "You are not in the list of allowed participants for this meeting.";
                        header("Location: view_meetings.php");
                        exit();
                    }
                } elseif ($_SESSION['role'] !== $allowed_role) {
                    $_SESSION['error'] = "This meeting is restricted to " . ucwords(str_replace('_', ' ', $allowed_role)) . "s only.";
                    header("Location: view_meetings.php");
                    exit();
                }
            }
        }
        
        logActivity($conn, $user_id, 'Join Meeting', "Joined meeting: '$meetingTopic' (ID: $meeting_id)");
    }

    if (isset($_GET['id'])) {
        $meetingRoom = "ScoutMeeting_" . intval($_GET['id']);
    } else {
        $meetingRoom = "ScoutMeeting_" . md5(uniqid(rand(), true));
    }
    $fullMeetingLink = "https://meet.jit.si/" . $meetingRoom;
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars($meetingTopic); ?></title>
    <script src="https://meet.jit.si/external_api.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f5f6fa;
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: fixed;
            -webkit-overflow-scrolling: touch;
        }

        /* Container for the meeting page */
        .meeting-container {
            max-width: 1500px;
            width: 95%;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 40px;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }

        /* Header controls wrapper */
        .meeting-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }

        /* Page title */
        .meeting-title {
            font-weight: 700;
            font-size: 1.7rem;
            margin-bottom: 25px;
            text-align: center;
            color: #198754;
            line-height: 1.3;
        }

        /* Professional button styling */
        .btn-back {
            color: #fff;
            background: linear-gradient(135deg, #198754 0%, #145c33 100%);
            border: none;
            transition: all 0.3s ease;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(25, 135, 84, 0.2);
            text-decoration: none;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, #145c33 0%, #0d3d22 100%);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }

        .btn-back:active {
            transform: translateY(0);
        }

        /* Copy link button */
        .btn-copy {
            color: #198754;
            background-color: #fff;
            border: 2px solid #198754;
            transition: all 0.3s ease;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-copy:hover {
            background-color: #198754;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.2);
        }

        .btn-copy:active {
            transform: translateY(0);
        }

        .btn-copy.copied {
            background-color: #198754;
            color: #fff;
            border-color: #198754;
        }

        /* Jitsi container */
        #meet {
            width: 100%;
            height: 700px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        /* Tablet responsive */
        @media (max-width: 1200px) {
            .meeting-container {
                width: 95%;
                padding: 30px;
            }
            
            #meet {
                height: 550px;
            }
            
            .meeting-title {
                font-size: 1.5rem;
            }
        }

        /* Mobile responsive - Tablet */
        @media (max-width: 768px) {
            body, html {
                background-color: #000;
                overflow: auto;
                position: relative;
                width: 100vw;
                min-height: 100vh;
            }
            
            .meeting-container {
                width: 100%;
                min-height: 100vh;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
                display: flex;
                flex-direction: column;
                background-color: #000;
            }
            
            .meeting-header {
                flex-wrap: nowrap;
                gap: 8px;
                margin: 0;
                padding: 8px;
                background-color: rgba(0, 0, 0, 0.8);
                position: sticky;
                top: 0;
                z-index: 10;
            }
            
            .meeting-title {
                font-size: 0.9rem;
                margin: 0;
                padding: 8px;
                word-break: break-word;
                color: #fff;
                background-color: rgba(0, 0, 0, 0.8);
                text-align: center;
            }
            
            /* Compact professional buttons */
            .btn-back,
            .btn-copy {
                padding: 8px 12px;
                font-size: 12px;
                border-radius: 6px;
                min-height: 36px;
                touch-action: manipulation;
                font-weight: 500;
                gap: 5px;
                flex: 1;
                justify-content: center;
                white-space: nowrap;
            }
            
            .btn-back {
                box-shadow: 0 1px 4px rgba(25, 135, 84, 0.2);
            }
            
            .btn-back i,
            .btn-copy i {
                font-size: 11px;
            }
            
            /* Make video container fit properly with space for controls */
            #meet {
                width: 100% !important;
                height: 70vh !important;
                min-height: 400px !important;
                max-height: 600px !important;
                border-radius: 0 !important;
                flex: 0 0 auto;
                position: relative;
                overflow: visible !important;
                margin-bottom: 20px;
            }
            
            /* Fix for Chrome mobile - ensure iframe is visible */
            #meet iframe {
                width: 100% !important;
                height: 100% !important;
                border: none !important;
                position: relative !important;
            }
        }

        /* Mobile responsive - Phone */
        @media (max-width: 576px) {
            .meeting-container {
                padding: 0;
                height: 100vh;
            }
            
            .meeting-header {
                gap: 6px;
                padding: 6px;
            }
            
            .meeting-title {
                font-size: 0.85rem;
                padding: 6px;
                font-weight: 600;
            }
            
            /* Even more compact buttons for small phones */
            .btn-back,
            .btn-copy {
                padding: 6px 10px;
                font-size: 11px;
                border-radius: 5px;
                min-height: 32px;
                gap: 4px;
            }
            
            .btn-back i,
            .btn-copy i {
                font-size: 10px;
            }
            
            /* Maximize video space on mobile */
            #meet {
                width: 100vw !important;
                height: calc(100vh - 80px) !important;
                border-radius: 0 !important;
            }
        }

        /* Extra small devices - optimize for portrait mode */
        @media (max-width: 375px) {
            .meeting-container {
                padding: 8px;
            }
            
            .meeting-header {
                gap: 5px;
            }
            
            .meeting-title {
                font-size: 0.95rem;
                margin-bottom: 8px;
            }
            
            .btn-back,
            .btn-copy {
                padding: 6px 10px;
                font-size: 11px;
                min-height: 34px;
                gap: 4px;
            }
            
            .btn-back i,
            .btn-copy i {
                font-size: 10px;
            }
            
            #meet {
                height: calc(100vh - 120px);
                min-height: 300px;
            }
        }

        /* Landscape mode on mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .meeting-container {
                padding: 6px;
            }
            
            .meeting-header {
                margin-bottom: 6px;
            }
            
            .meeting-title {
                font-size: 0.9rem;
                margin-bottom: 6px;
            }
            
            .btn-back,
            .btn-copy {
                padding: 6px 10px;
                font-size: 11px;
                min-height: 32px;
            }
            
            .btn-back i,
            .btn-copy i {
                font-size: 10px;
            }
            
            /* Maximize video in landscape */
            #meet {
                height: calc(100vh - 80px);
                min-height: 250px;
            }
        }

        /* Ensure touch-friendly elements */
        button, a {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
            touch-action: manipulation;
        }

        /* Prevent zoom on double tap */
        * {
            touch-action: manipulation;
        }

        /* Loading state */
        .btn-copy.loading {
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
    </head>
    <body>

    <div class="container meeting-container">
        <div class="meeting-header">
            <a href="view_meetings.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                <span class="btn-text">Back</span>
            </a>
            <button class="btn-copy" onclick="copyLink(this)">
                <i class="fas fa-copy"></i>
                <span class="btn-text">Copy Link</span>
            </button>
        </div>
        <div class="meeting-title"><?php echo htmlspecialchars($meetingTopic); ?></div>
        <div id="meet"></div>
    </div>

    <script>
        const domain = "meet.jit.si";
        const isMobile = window.innerWidth <= 768;
        
        const options = {
            roomName: "<?php echo $meetingRoom; ?>",
            width: "100%",
            height: "100%",
            parentNode: document.querySelector('#meet'),
            lang: 'en',
            userInfo: {
                displayName: <?php echo json_encode($_SESSION['name']); ?>
            },
            configOverwrite: {
                startWithAudioMuted: true,
                disableDeepLinking: true,
                prejoinPageEnabled: true,
                // Mobile optimizations
                resolution: isMobile ? 360 : 720,
                constraints: {
                    video: {
                        height: {
                            ideal: isMobile ? 360 : 720,
                            max: isMobile ? 480 : 1080,
                            min: isMobile ? 180 : 360
                        }
                    }
                },
                // Disable features that don't work well on mobile
                disableAudioLevels: isMobile,
                enableNoAudioDetection: !isMobile,
                enableNoisyMicDetection: !isMobile,
                // Better mobile performance
                channelLastN: isMobile ? 4 : 20,
                startVideoMuted: isMobile ? 10 : 20,
                // Keep toolbar visible
                toolbarConfig: {
                    alwaysVisible: isMobile,
                    timeout: isMobile ? 20000 : 4000
                },
                <?php if ($is_private): ?>
                lobby: {
                    enable: true,
                    autoEnable: true
                },
                <?php endif; ?>
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                MOBILE_APP_PROMO: false,
                HIDE_INVITE_MORE_HEADER: true,
                // Ensure toolbar is always visible on mobile
                TOOLBAR_ALWAYS_VISIBLE: isMobile,
                TOOLBAR_TIMEOUT: isMobile ? 10000 : 4000,
                // Simplified toolbar for mobile
                TOOLBAR_BUTTONS: isMobile ? [
                    'microphone', 'camera', 'hangup', 'chat', 
                    'raisehand', 'tileview', 'settings', 'fullscreen'
                ] : [
                    'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                    'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                    'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                    'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
                    'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone',
                    'security'
                ],
                DISABLE_VIDEO_BACKGROUND: isMobile,
                FILM_STRIP_MAX_HEIGHT: isMobile ? 90 : 120,
                VERTICAL_FILMSTRIP: false,
                // Mobile-specific settings
                INITIAL_TOOLBAR_TIMEOUT: isMobile ? 10000 : 20000,
                TOOLBAR_BUTTONS_TIMEOUT: isMobile ? 10000 : 4000,
            }
        };
        const api = new JitsiMeetExternalAPI(domain, options);

        <?php if ($is_private): ?>
        api.addEventListener('videoConferenceJoined', () => {
            // Ensure lobby is enabled when the moderator joins
            api.executeCommand('toggleLobby', true);
        });
        <?php endif; ?>

        // Mobile-specific: Handle orientation changes
        if (isMobile) {
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    // Adjust video container on orientation change
                    const meetContainer = document.getElementById('meet');
                    if (window.orientation === 90 || window.orientation === -90) {
                        // Landscape
                        meetContainer.style.height = 'calc(100vh - 100px)';
                    } else {
                        // Portrait
                        meetContainer.style.height = 'calc(100vh - 160px)';
                    }
                }, 100);
            });
        }

        function copyLink(btn) {
            const link = "<?php echo $fullMeetingLink; ?>";
            
            // Add loading state
            btn.classList.add('loading');
            
            navigator.clipboard.writeText(link).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i><span class="btn-text">Copied!</span>';
                btn.classList.add('copied');
                btn.classList.remove('loading');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = link;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i><span class="btn-text">Copied!</span>';
                    btn.classList.add('copied');
                    btn.classList.remove('loading');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    btn.classList.remove('loading');
                    alert('Failed to copy link. Please copy manually: ' + link);
                }
                
                document.body.removeChild(textArea);
            });
        }
    </script>

    </body>
    </html>
