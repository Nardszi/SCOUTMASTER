<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Registration Form</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }
        .back-button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            border-radius: 5px;
        }
        .back-button:hover {
            background: #0056b3;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
            table-layout: fixed;
        }
        th, td { 
            border: 1px solid black; 
            padding: 5px; 
            text-align: left; 
            box-sizing: border-box;
        }
        th { background-color: #f2f2f2; }
        td:nth-child(1), th:nth-child(1) {
            width: 50px;
        }
        input, select { 
            width: 100%; 
            padding: 5px; 
            box-sizing: border-box;
        }
        .form-section { margin-top: 20px; }
        button { padding: 10px; background: green; color: white; border: none; cursor: pointer; }
        button:hover { background: darkgreen; }
    </style>
</head>
<body>

<div class="header">
    <h2>Application for Additional Scout Registration</h2>
    <a href="manage_scoutsTL.php" class="back-button">Back to Manage Scouts</a>
</div>

<form action="submit_registration.php" method="POST" enctype="multipart/form-data">
    <label>Date Applied: <input type="date" name="date_applied" required></label>
    <div class="form-section">
        <label>Sponsoring Institution: <input type="text" name="sponsoring_institution" required></label>
        <label>Unit No.: <input type="text" name="unit_no" required></label>
        <label>Local Council: <input type="text" name="local_council" required></label>
    </div>
    
    <table>
        <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Reg. Status</th>
            <th>Age</th>
            <th>Sex</th>
            <th>Membership Card No.</th>
            <th>Highest Rank Earned</th>
            <th>Years in Scouting</th>
        </tr>
    </table>
    
    <div class="form-section">
        <label>Number of Scouts: <input type="number" name="total_scouts" required></label>
        <label>Male: <input type="number" name="male_count"></label>
        <label>Female: <input type="number" name="female_count"></label>
    </div>
    
    <div class="form-section">
        <h3>Registration Fees</h3>
        <label>Total Fees Remitted: <input type="number" name="fees_remitted" required></label>
        <label>OR No.: <input type="text" name="or_no"></label>
        <label>Expiration Date: <input type="date" name="expiration_date"></label>
        <label>Upload Payment Proof: <input type="file" name="payment_proof" accept="image/*, .pdf" required></label>
    </div>
    
    <div class="form-section">
        <h3>Local Council Office Action</h3>
        <label>Processed by: <input type="text" name="processed_by"></label>
        <label>Date: <input type="date" name="processed_date"></label>
        <label>Approved by: <input type="text" name="approved_by"></label>
        <label>Date: <input type="date" name="approved_date"></label>
    </div>
    
    <div class="form-section">
        <h3>Regional Office Action</h3>
        <label>Checked by: <input type="text" name="checked_by"></label>
        <label>Date: <input type="date" name="checked_date"></label>
    </div>
    
    <button type="submit">Submit Registration</button>
</form>

<script>
    const table = document.querySelector("table");
    for (let i = 1; i <= 12; i++) {
        const row = table.insertRow();
        row.innerHTML = `
            <td>${i}</td>
            <td><input type='text' name='name_${i}'></td>
            <td><select name='status_${i}'><option value='N'>N</option><option value='RR'>RR</option></select></td>
            <td><input type='number' name='age_${i}'></td>
            <td><select name='sex_${i}'><option value='Male'>Male</option><option value='Female'>Female</option></select></td>
            <td><input type='text' name='card_no_${i}'></td>
            <td><input type='text' name='rank_${i}'></td>
            <td><input type='number' name='years_${i}'></td>
        `;
    }
</script>

</body>
</html>
