<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Consent Waiver</title>
    <style>
        body {
            font-family: "Times New Roman", serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            color: #000;
        }
        h1 {
            text-align: center;
            text-transform: uppercase;
            font-size: 24px;
            margin-bottom: 40px;
            text-decoration: underline;
        }
        p {
            line-height: 1.8;
            margin-bottom: 20px;
            font-size: 16px;
            text-align: justify;
        }
        .signature-section {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .line {
            border-bottom: 1px solid #000;
            margin-bottom: 10px;
        }
        .print-btn {
            display: block;
            margin: 0 auto 30px;
            padding: 10px 20px;
            background: #19692c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        @media print {
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-btn">🖨️ Print Waiver Form</button>

    <h1>Parent/Guardian Consent and Waiver Form</h1>

    <p>I, the undersigned parent/guardian of ______________________________________________________ (Scout's Name), hereby give my full consent for my child to participate in the Boy Scout event/activity.</p>

    <p>I acknowledge that participation in Scouting activities involves inherent risks, and I voluntarily assume all such risks. I hereby release, waive, discharge, and covenant not to sue the Boy Scouts organization, its leaders, officers, and volunteers from any and all liability, claims, demands, actions, and causes of action whatsoever arising out of or related to any loss, damage, or injury, including death, that may be sustained by my child, or to any property belonging to me, while participating in such activity.</p>

    <p>I also authorize the adult leaders to secure medical treatment for my child in case of emergency if I cannot be reached immediately.</p>

    <div class="signature-section">
        <div class="signature-block">
            <div class="line"></div>
            <strong>Parent/Guardian Signature</strong>
        </div>
        <div class="signature-block">
            <div class="line"></div>
            <strong>Date</strong>
        </div>
    </div>
</body>
</html>