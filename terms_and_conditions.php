<?php
// This is a placeholder for your terms and conditions.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - Scout Master</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');

        body {
            background-color: #000;
            color: white;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/wall3.jpg') no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .terms-card {
            width: 100%;
            max-width: 800px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            color: white;
            animation: fadeInUp 1s ease-out;
        }

        h1 {
            color: #28a745;
            font-weight: 700;
            margin-bottom: 10px;
        }

        h2 {
            color: #28a745;
            font-weight: 600;
            font-size: 1.5rem;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }

        .btn-custom {
            background-color: #28a745;
            color: white;
            padding: 10px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            transition: 0.3s;
            border: none;
        }

        .btn-custom:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Scrollbar for the card if content is long */
        .terms-content {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .terms-content::-webkit-scrollbar {
            width: 8px;
        }
        .terms-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        .terms-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }
        .terms-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <div class="terms-card">
        <div class="terms-content">
            <div class="text-center mb-4">
                <h1>Terms and Conditions</h1>
                <p class="text-white-50">Last updated: <?php echo date('F j, Y'); ?></p>
            </div>
            
            <p>Please read these terms and conditions carefully before using our service.</p>

            <h2>Acknowledgment</h2>
            <p>By creating an account, you agree to be bound by these Terms and Conditions. If You disagree with any part of these Terms and Conditions then You may not access the Service.</p>
            
            <h2>Accounts</h2>
            <p>When You create an account with Us, You must provide Us information that is accurate, complete, and current at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of Your account on Our Service.</p>
            
            <h2>Content</h2>
            <p>Our Service allows You to post, link, store, share and otherwise make available certain information, text, graphics, videos, or other material. You are responsible for the Content that You post to the Service, including its legality, reliability, and appropriateness.</p>

            <h2>Termination</h2>
            <p>We may terminate or suspend Your Account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if You breach these Terms and Conditions.</p>
        </div>
        
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <button class="btn btn-custom" onclick="window.close()">Close Window</button>
        </div>
    </div>
</body>
</html>
