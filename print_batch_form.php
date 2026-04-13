<?php
session_start();
include('config.php');

// Security: Ensure user is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: dashboard.php');
    exit();
}

$ids = $_POST['ids'] ?? [];
if (empty($ids)) {
    die("No scouts selected for printing.");
}

// Sanitize IDs
$ids = array_map('intval', $ids);
$ids_str = implode(',', $ids);

// Fetch scouts from archive
$query = "
    SELECT 
        asa.*, 
        u.name as leader_name
    FROM admin_scout_archive asa 
    LEFT JOIN users u ON asa.registered_by_leader_id = u.id
    WHERE asa.id IN ($ids_str) 
    ORDER BY asa.name ASC
";
$result = mysqli_query($conn, $query);
$scouts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $scouts[] = $row;
}

// Fetch leader and institution info
$leader_name = '';
$sponsoring_institution = '';
$local_council = '';
$unit_no = '';

if (!empty($scouts)) {
    $first_scout = $scouts[0];
    $leader_id = $first_scout['registered_by_leader_id'];
    $leader_name = htmlspecialchars(strtoupper($first_scout['leader_name'] ?? ''));
    $sponsoring_institution = "VICTORIAS CITY LOCAL UNIT";

    if ($leader_id) {
        // Fetch leader's info including troop and their school for local council
        $leader_info_query = "
            SELECT u.school, t.troop_name 
            FROM users u
            LEFT JOIN troops t ON u.id = t.scout_leader_id
            WHERE u.id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $leader_info_query);
        mysqli_stmt_bind_param($stmt, "i", $leader_id);
        mysqli_stmt_execute($stmt);
        $leader_result = mysqli_stmt_get_result($stmt);
        if ($leader_info = mysqli_fetch_assoc($leader_result)) {
            $local_council = htmlspecialchars($leader_info['school'] ?? '');
            $unit_no = htmlspecialchars($leader_info['troop_name'] ?? '');
        }
        mysqli_stmt_close($stmt);
    }
}

// Calculate stats
$total_scouts = count($scouts);
$male_count = 0;
$female_count = 0;
foreach ($scouts as $s) {
    if (strtolower($s['sex']) == 'male') $male_count++;
    else $female_count++;
}
$total_fees = $total_scouts * 50;

// Group into chunks of 12 for pagination (mimicking form rows)
$chunks = array_chunk($scouts, 12);
if (empty($chunks)) {
    $chunks[] = []; // Ensure at least one page if empty
}

// Generate ASR No.
$asr_no = 'ASR-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application for Additional Scout Registration</title>
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 11px; margin: 0; padding: 20px; background: #fff; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .page { width: 100%; max-width: 850px; margin: 0 auto; page-break-after: always; position: relative; }
        .page:last-child { page-break-after: auto; }
        
        .header { text-align: center; margin-bottom: 15px; position: relative; }
        .header img { position: absolute; left: 20px; top: 0; width: 60px; height: auto; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; font-weight: bold; line-height: 1.2; }
        .header h3 { margin: 5px 0 0; font-size: 14px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .header p { margin: 0; font-size: 12px; }
        
        .top-info { width: 100%; margin-bottom: 10px; border-collapse: collapse; font-size: 12px; }
        .top-info td { padding: 2px 5px; }
        .border-bottom { border-bottom: 1px solid black; }
        
        table.scout-list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.scout-list th, table.scout-list td { border: 1px solid black; padding: 3px; text-align: center; font-size: 10px; vertical-align: middle; }
        table.scout-list th { background-color: #fff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .text-left { text-align: left !important; }
        
        .bottom-container { border: 1px solid black; margin-top: 10px; display: flex; }
        .summary-col { width: 50%; border-right: 1px solid black; padding: 5px; }
        .fees-col { width: 50%; padding: 5px; }
        
        .signatures { margin-top: 20px; width: 100%; border-collapse: collapse; }
        .signatures td { vertical-align: top; padding: 10px 5px; text-align: center; }
        .signature-line { border-bottom: 1px solid black; margin-top: 35px; width: 90%; margin-left: auto; margin-right: auto; padding-bottom: 2px; font-weight: bold; text-align: center; min-height: 1.2em; }
        
        .action-boxes { width: 100%; margin-top: 10px; }
        .action-box { padding: 5px; border: 1px solid black; }
        .action-box + .action-box { margin-top: 10px; }
        .action-header { text-align: center; font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-size: 10px; }
        
        .page::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('images/bsp-logo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 500px;
            opacity: 0.1;
            z-index: -1;
        }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .page { margin: 0; width: 100%; max-width: none; height: auto; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px; padding: 10px; background: #eee; border-bottom: 1px solid #ccc;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 5px;">🖨️ Print Form</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-left: 10px;">Close</button>
        <div style="margin-top: 15px; text-align: center;">
            <strong>Select Unit:</strong>
            <label style="margin-right: 8px;"><input type="radio" name="unit_select" value="LAKAY" onclick="updateSelection()"> LAKAY</label>
            <label style="margin-right: 10px;"><input type="radio" name="unit_select" value="KAWAN" onclick="updateSelection()"> KAWAN</label>
            <label style="margin-right: 10px;"><input type="radio" name="unit_select" value="TROOP" onclick="updateSelection()"> TROOP</label>
            <label style="margin-right: 10px;"><input type="radio" name="unit_select" value="OUTFIT" onclick="updateSelection()"> OUTFIT</label>
            <label><input type="radio" name="unit_select" value="CIRCLE" onclick="updateSelection()"> CIRCLE</label>
            <br><br>
            <strong>Select Nature:</strong>
            <label style="margin-right: 10px;"><input type="radio" name="nature_select" value="SCHOOL-BASED" onclick="updateSelection()"> SCHOOL-BASED</label>
            <label><input type="radio" name="nature_select" value="COMMUNITY-BASED" onclick="updateSelection()"> COMMUNITY-BASED</label>
        </div>
    </div>

    <?php foreach ($chunks as $page_index => $chunk): ?>
    <div class="page">
        <div class="header">
            <div style="position: absolute; right: 0; top: 0; font-weight: bold; font-size: 12px; color: red;">ASR No. <?= $asr_no ?></div>
            <img src="images/bsp-logo.png" alt="BSP Logo">
            <h2>Boy Scouts of the Philippines</h2>
            <p>National Office Manila</p>
            <h3>Application for Additional Scout Registration</h3>
        </div>

        <table class="top-info">
            <tr>
                <td width="10%">Region:</td>
                <td width="40%" class="border-bottom">NIR</td>
                <td width="15%" style="text-align: right;">Date Applied:</td>
                <td width="35%" class="border-bottom"><?= date('F d, Y') ?></td>
            </tr>
            <tr>
                <td>Local Council:</td>
                <td class="border-bottom"><?= $local_council ?>&nbsp;</td>
                <td style="text-align: right;">Unit No.:</td>
                <td class="border-bottom"><?= $unit_no ?>&nbsp;</td>
            </tr>
            <tr>
                <td>Sponsoring Institution:</td>
                <td colspan="3" class="border-bottom"><?= $sponsoring_institution ?>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top: 5px;">
                   
                    Unit: &nbsp;&nbsp; <span class="chk-unit" data-val="LAKAY">☐</span> LAKAY &nbsp;&nbsp; <span class="chk-unit" data-val="KAWAN">☐</span> KAWAN &nbsp;&nbsp; <span class="chk-unit" data-val="TROOP">☐</span> TROOP &nbsp;&nbsp; <span class="chk-unit" data-val="OUTFIT">☐</span> OUTFIT &nbsp;&nbsp; <span class="chk-unit" data-val="CIRCLE">☐</span> CIRCLE
                </td>
            </tr>
            <tr>
                <td colspan="4">
                  
                    Nature: &nbsp;&nbsp; <span class="chk-nature" data-val="SCHOOL-BASED">☐</span> SCHOOL-BASED &nbsp;&nbsp; <span class="chk-nature" data-val="COMMUNITY-BASED">☐</span> COMMUNITY-BASED
                </td>
            </tr>
        </table>

        <table class="scout-list">
            <thead>
                <tr>
                    <th width="4%">No.</th>
                    <th width="42%">Surname, Given Name, M.I.</th>
                    <th width="8%">Reg. Status<br>(N/RR)</th>
                    <th width="4%">Age</th>
                    <th width="6%">Sex</th>
                    <th width="15%">Membership Card No.<br>(If RR)</th>
                    <th width="13%">Highest Rank Earned</th>
                    <th width="8%">Years in Scouting</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Fill 12 rows per page
                for ($i = 0; $i < 12; $i++): 
                    $s = isset($chunk[$i]) ? $chunk[$i] : null;
                ?>
                <tr>
                    <td><?= ($page_index * 12) + $i + 1 ?></td>
                    <td class="text-left"><?= $s ? htmlspecialchars(strtoupper($s['name'])) : '&nbsp;' ?></td>
                    <td><?= $s ? ($s['registration_status'] == 'N' ? 'New' : 'RR') : '&nbsp;' ?></td>
                    <td><?= $s ? htmlspecialchars($s['age']) : '&nbsp;' ?></td>
                    <td><?= $s ? htmlspecialchars(substr($s['sex'], 0, 1)) : '&nbsp;' ?></td>
                    <td><?= $s ? htmlspecialchars($s['membership_card']) : '&nbsp;' ?></td>
                    <td><?= $s ? htmlspecialchars($s['highest_rank']) : '&nbsp;' ?></td>
                    <td><?= $s ? htmlspecialchars($s['years_in_scouting']) : '&nbsp;' ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <?php if ($page_index == count($chunks) - 1): // Show summary only on last page ?>
        <div class="bottom-container">
            <div class="summary-col">
                <strong>SUMMARY OF REGISTRATION:</strong><br>
                <table width="100%" style="margin-top: 5px;">
                    <tr><td width="70%">Scouts (Male):</td><td style="border-bottom: 1px solid black; text-align: center;"><?= $male_count ?></td></tr>
                    <tr><td>Scouts (Female):</td><td style="border-bottom: 1px solid black; text-align: center;"><?= $female_count ?></td></tr>
                    <tr><td>Unit Leaders (Male):</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
                    <tr><td>Unit Leaders (Female):</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
                    <tr><td>Inst'l. Head/Rep.:</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
                    <tr><td><strong>TOTAL:</strong></td><td style="border-bottom: 1px solid black; text-align: center;"><strong><?= $total_scouts ?></strong></td></tr>
                </table>
            </div>
            <div class="fees-col">
                <strong>REGISTRATION FEES:</strong><br>
                <table width="100%" style="margin-top: 5px;">
                    <tr><td width="50%">Total Fees Remitted:</td><td style="border-bottom: 1px solid black;">P <?= number_format($total_fees, 2) ?></td></tr>
                    <tr><td>O.R. No.:</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
                    <tr><td>Date:</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
                </table>
            </div>
        </div>

        <table class="signatures">
            <tr>
                <td width="33%">
                    <div class="signature-line"><?= $leader_name ?></div>
                    <strong>Unit Leader / Coordinator</strong><br>(Signature over Printed Name)
                </td>
                <td width="33%">
                    <div class="signature-line"></div>
                    <strong>Head of Institution / Rep.</strong><br>(Signature over Printed Name)
                </td>
                <td width="33%">
                    <div class="signature-line"></div>
                    <strong>Council Scout Executive</strong><br>(Signature over Printed Name)
                </td>
            </tr>
        </table>
        
        <div class="action-boxes">
            <div class="action-box">
                <div class="action-header">LOCAL COUNCIL OFFICE ACTION</div>
                <table width="100%" style="font-size: 10px;">
                    <tr>
                        <td width="15%">Processed by:</td>
                        <td width="35%" class="border-bottom">&nbsp;</td>
                        <td width="15%" style="padding-left: 10px;">Approved by:</td>
                        <td width="35%" class="border-bottom">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td class="border-bottom">&nbsp;</td>
                        <td style="padding-left: 10px;">Date:</td>
                        <td class="border-bottom">&nbsp;</td>
                    </tr>
                </table>
            </div>
            <div class="action-box">
                <div class="action-header">REGIONAL OFFICE ACTION</div>
                <table width="100%" style="font-size: 10px;">
                    <tr>
                        <td width="15%">Checked by:</td>
                        <td width="35%" class="border-bottom">&nbsp;</td>
                        <td width="15%" style="padding-left: 10px;">Date:</td>
                        <td width="35%" class="border-bottom">&nbsp;</td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="position: absolute; bottom: 10px; right: 10px; font-size: 10px;">
            Page <?= $page_index + 1 ?> of <?= count($chunks) ?>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        function updateSelection() {
            const selectedUnit = document.querySelector('input[name="unit_select"]:checked')?.value;
            const selectedNature = document.querySelector('input[name="nature_select"]:checked')?.value;

            document.querySelectorAll('.chk-unit').forEach(el => {
                el.innerHTML = (el.getAttribute('data-val') === selectedUnit) ? '☑' : '☐';
            });
            document.querySelectorAll('.chk-nature').forEach(el => {
                el.innerHTML = (el.getAttribute('data-val') === selectedNature) ? '☑' : '☐';
            });
        }
    </script>
</body>
</html>
